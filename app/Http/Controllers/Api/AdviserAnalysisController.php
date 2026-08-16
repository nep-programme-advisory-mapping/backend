<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdvisoryNote;
use App\Models\Province;
use App\Services\Adviser\MapOverlapMatcher;
use App\Services\Adviser\ProfileExtractor;
use App\Services\AI\ClaudeService;
use App\Services\AI\PromptBuilder;
use App\Support\AdvisoryNoteStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser as PdfParser;

class AdviserAnalysisController extends Controller
{
    public function generateAdvisoryNote(
        int $id,
        Request $request,
        MapOverlapMatcher $matcher,
        PromptBuilder $promptBuilder
    ): JsonResponse {
        $submission = AdvisoryNote::findOrFail($id);

        // Authorization is enforced by the route's permission:advisory.update
        // middleware — this used to also hardcode role checks here, which
        // silently overrode any grant of that permission to a different role.
        $documentText  = $this->cleanExtractedText($submission->document_text ?? '');
        $documentText  = $documentText ?: null;
        $analysisScope = $submission->analysis_scope ?? 'full map';

        $profile = $this->resolveProfile($request, $submission, $documentText);

        $overlappingEntries = $matcher
            ->match($profile, $analysisScope)
            ->with([
                'organisation',
                'locations.province', 'locations.district',
                'activities.activityItem.subcategory.category',
                'activities.activityItem',
            ])
            ->get();

        if ($submission->programme_entry_id) {
            $overlappingEntries = $overlappingEntries
                ->where('id', '!=', $submission->programme_entry_id)
                ->values();
        }

        $mapOverlapEntries = $overlappingEntries->map(fn($e) => [
            'id'             => $e->id,
            'programme_name' => $e->programme_name,
            'organisation'   => $e->organisation?->name,
            'locations'      => $e->locations->map(fn($l) => [
                'province_id'   => $l->province_id,
                'province_name' => $l->province?->province_name,
                'district_name' => $l->district?->name,
            ])->toArray(),
            'activities'     => $e->activities->map(fn($a) => [
                'activity_item_id' => $a->activity_item_id,
                'name'             => $a->activityItem?->label,
                'subcategory_name' => $a->activityItem?->subcategory?->label,
                'category_name'    => $a->activityItem?->subcategory?->category?->label,
            ])->toArray(),
        ])->values()->toArray();

        $prompt = $promptBuilder->build(
            $profile,
            $mapOverlapEntries,
            $analysisScope,
            $submission->analysis_scope_detail,
            $documentText,
            $submission->document_name,
            $submission->submitting_party
        );

        try {
            $claude     = App::make(ClaudeService::class);
            $aiResponse = $claude->generateContent($prompt);

            $updateData = ['status' => AdvisoryNoteStatus::ANALYSED];
            if (!empty($aiResponse['section_a'])) $updateData['section_profile']            = $aiResponse['section_a'];
            if (!empty($aiResponse['section_c'])) $updateData['section_gaps']               = $aiResponse['section_c'];
            if (!empty($aiResponse['section_d'])) $updateData['section_coordinators_notes'] = $aiResponse['section_d'];
            $submission->update($updateData);

            $submission->recommendations()->delete();
            if (is_array($aiResponse['section_b'] ?? null)) {
                foreach ($aiResponse['section_b'] as $rec) {
                    $type = $rec['overlap_type'] ?? 'Geographic overlap';
                    $submission->recommendations()->create([
                        'organisation_name' => $rec['organisation'] ?? null,
                        'programme_name'    => $rec['programme_name'] ?? null,
                        'type'              => $type,
                        'relational'        => $rec['rationale'] ?? $type,
                        'rationale'         => $rec['rationale'] ?? null,
                    ]);
                }
            }

            $aiResponse['map_overlap_entries'] = $mapOverlapEntries;
            $aiResponse['recommendations']     = $submission->fresh()->recommendations()->with('programmeEntry.organisation:id,name')->get();

            return response()->json([
                'message' => 'Advisory note generated successfully.',
                'data'    => $aiResponse,
            ]);
        } catch (\Throwable $e) {
            $code = $e->getCode();
            $http = in_array($code, [400, 401, 403, 404, 429, 500, 502, 503]) ? $code : 503;
            if ($http < 100 || $http > 599) $http = 503;

            Log::warning('Advisory note generation failed', [
                'submission_id' => $submission->id,
                'error'         => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Map overlaps found but AI generation failed — please retry.',
                'data'    => ['map_overlap_entries' => $mapOverlapEntries, 'ai_failed' => true],
            ]);
        }
    }

    private function resolveProfile(Request $request, AdvisoryNote $submission, ?string $documentText): array
    {
        $raw = $request->input('programme_profile', []);

        $profile = [
            'activities' => is_array($raw['activities'] ?? null) ? $raw['activities'] : [],
            'geography'  => is_array($raw['geography']  ?? null) ? $raw['geography']  : [],
            'audiences'  => is_array($raw['audiences']  ?? null) ? $raw['audiences']  : [],
        ];

        $hasSignals = !empty($profile['activities']['item_ids'])
            || !empty($profile['activities']['category_ids'])
            || !empty($profile['geography']['province_ids']);

        // Non-member with no signals: use all categories + all provinces so the matcher
        // finds candidates and the AI reasons directly from the document text.
        // Skipping ProfileExtractor avoids a second sequential AI call in the same request.
        if (!$hasSignals && !$submission->programme_entry_id) {
            $profile['activities']['category_ids'] = \App\Models\ActivityCategory::pluck('id')->toArray();
            $profile['geography']['province_ids']  = \App\Models\Province::pluck('id')->toArray();
            return $profile;
        }

        // Member with no signals: pull from linked programme entry
        if (!$hasSignals && $submission->programme_entry_id) {
            $entry = \App\Models\ProgrammeEntry::with([
                'activities.activityItem.subcategory',
                'locations',
            ])->find($submission->programme_entry_id);

            if ($entry) {
                $profile['activities']['item_ids']        = $entry->activities->pluck('activity_item_id')->filter()->unique()->values()->toArray();
                $profile['activities']['subcategory_ids'] = $entry->activities->map(fn($a) => $a->activityItem?->subcategory?->id)->filter()->unique()->values()->toArray();
                $profile['activities']['category_ids']    = $entry->activities->map(fn($a) => $a->activityItem?->subcategory?->category_id)->filter()->unique()->values()->toArray();
                $profile['geography']['province_ids']     = $entry->locations->pluck('province_id')->filter()->unique()->values()->toArray();
                $profile['geography']['district_ids']     = $entry->locations->pluck('district_id')->filter()->unique()->values()->toArray();
                $profile['geography']['commune_ids']      = $entry->locations->pluck('commune_id')->filter()->unique()->values()->toArray();
            }
        }

        return $profile;
    }

    public function extractProfile(int $id, Request $request, ProfileExtractor $extractor): JsonResponse
    {
        $submission = AdvisoryNote::findOrFail($id);
        $request->validate(['text' => 'nullable|string|min:10|max:200000']);

        $text = $request->input('text') ?? $submission->document_text;
        if (empty($text)) {
            return response()->json(['message' => 'No document text available for this submission.'], 422);
        }

        try {
            $result = $extractor->extract($text);
            return response()->json(['programme_profile' => $result]);
        } catch (\RuntimeException $e) {
            $code   = $e->getCode();
            $status = in_array($code, [429, 500, 503]) ? $code : 503;
            return response()->json(['message' => $e->getMessage()], $status);
        }
    }

    public function parseDocument(int $id): JsonResponse
    {
        $submission = AdvisoryNote::findOrFail($id);

        // Return cached text if already parsed
        if (!empty($submission->document_text)) {
            return response()->json(['text' => $submission->document_text]);
        }

        if (!$submission->document_file) {
            return response()->json(['message' => 'No document file stored for this submission.'], 404);
        }

        if (!Storage::disk('public')->exists($submission->document_file)) {
            return response()->json(['message' => 'Document file not found on storage.'], 404);
        }

        $path = Storage::disk('public')->path($submission->document_file);

        try {
            $parser = new PdfParser();
            $text   = trim($parser->parseFile($path)->getText());

            if (empty($text)) {
                return response()->json(['message' => 'Could not extract text from the stored PDF.'], 422);
            }

            // Cache parsed text on the record so we never re-parse
            $submission->update(['document_text' => $text]);

            return response()->json(['text' => $text]);
        } catch (\Exception $e) {
            Log::error('Stored PDF parsing failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to parse stored document.'], 422);
        }
    }

    public function parsePdf(int $id, Request $request): JsonResponse
    {
        $submission = AdvisoryNote::findOrFail($id);
        $request->validate(['file' => 'required|file|mimes:pdf,doc,docx|max:20480']);

        $file     = $request->file('file');
        $realPath = $file->getRealPath();
        $ext      = strtolower($file->getClientOriginalExtension());

        if ($realPath === false || !str_starts_with($realPath, sys_get_temp_dir())) {
            return response()->json(['message' => 'Invalid file path.'], 422);
        }

        try {
            $text = match(true) {
                $ext === 'pdf' => (new PdfParser())->parseFile($realPath)->getText(),
                in_array($ext, ['docx', 'doc']) => $this->extractDocxText($realPath),
                default => '',
            };

            if (empty(trim($text))) {
                return response()->json(['message' => 'Could not extract text from the document.'], 422);
            }

            $submission->update(['document_text' => $this->cleanExtractedText($text)]);
            return response()->json(['text' => $text]);
        } catch (\Exception $e) {
            Log::error('Document parsing failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to parse document file.'], 422);
        }
    }

    private function cleanExtractedText(string $text): string
    {
        // Remove <>-style character separators from badly encoded PDFs (e.g. "N<>E<>P" → "NEP")
        $cleaned = preg_replace('/<>/', '', $text);
        // Collapse excessive whitespace
        return preg_replace('/[ \t]{2,}/', ' ', $cleaned);
    }

    private function extractDocxText(string $path): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) return '';
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xml === false) return '';
        $text = strip_tags(str_replace(['</w:p>', '</w:tr>'], "\n", $xml));
        return html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
