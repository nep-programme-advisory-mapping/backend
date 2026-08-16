<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\BuildsMapQuery;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class MapExportController extends Controller
{
    use BuildsMapQuery;

    private const EXPORT_WITH = [
        'organisation',
        'budgetBand',
        'keywords',
        'locations.province',
        'locations.district',
        'locations.commune',
        'locations.village',
        'activities.activityItem.subcategory.category',
        'activities.activityLevels.educationLevel',
        'governmentAgreements',
    ];

    #[OA\Get(
        path: "/map/entries/export",
        summary: "Export filtered programme entries as CSV",
        description: "Returns a CSV file containing all key fields for entries matching the given filters. Uses the same filters and permissions as the query endpoint.",
        security: [["bearerAuth" => []]],
        tags: ["Map Query & Export"],
        parameters: [
            new OA\Parameter(name: "category_id", in: "query", required: false, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "subcategory_id", in: "query", required: false, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "item_id", in: "query", required: false, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "education_level_id", in: "query", required: false, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "inclusion_group", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "inclusion_type", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "keyword", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "organisation_name", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "budget_band_id", in: "query", required: false, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "min_staff", in: "query", required: false, schema: new OA\Schema(type: "number")),
            new OA\Parameter(name: "max_staff", in: "query", required: false, schema: new OA\Schema(type: "number")),
            new OA\Parameter(name: "min_beneficiaries", in: "query", required: false, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "max_beneficiaries", in: "query", required: false, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "province_id", in: "query", required: false, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "district_id", in: "query", required: false, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "agreement_counterpart_type", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "agreement_status", in: "query", required: false, schema: new OA\Schema(type: "string")),
        ],
        responses: [
            new OA\Response(response: 200, description: "CSV export successful", content: new OA\MediaType(mediaType: "text/csv", schema: new OA\Schema(type: "string", format: "binary"))),
            new OA\Response(response: 401, description: "Unauthenticated"),
        ]
    )]
    public function export(Request $request)
    {
        $entries = $this->buildMapQuery($request, $request->user())
            ->with(self::EXPORT_WITH)
            ->get()
            ->all();

        $filename = 'programme-entries-export-' . now()->format('Y-m-d-H-i-s') . '.csv';

        return response($this->generateCsv($entries), 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    #[OA\Get(
        path: "/map/entries/export/pdf",
        summary: "Export filtered programme entries as PDF summary report",
        description: "Returns a PDF file containing a readable summary report of entries matching the given filters. Large result sets are processed in chunks to prevent timeouts.",
        security: [["bearerAuth" => []]],
        tags: ["Map Query & Export"],
        parameters: [
            new OA\Parameter(name: "category_id", in: "query", required: false, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "subcategory_id", in: "query", required: false, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "item_id", in: "query", required: false, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "education_level_id", in: "query", required: false, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "inclusion_group", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "inclusion_type", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "keyword", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "organisation_name", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "budget_band_id", in: "query", required: false, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "min_staff", in: "query", required: false, schema: new OA\Schema(type: "number")),
            new OA\Parameter(name: "max_staff", in: "query", required: false, schema: new OA\Schema(type: "number")),
            new OA\Parameter(name: "min_beneficiaries", in: "query", required: false, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "max_beneficiaries", in: "query", required: false, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "province_id", in: "query", required: false, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "district_id", in: "query", required: false, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "agreement_counterpart_type", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "agreement_status", in: "query", required: false, schema: new OA\Schema(type: "string")),
        ],
        responses: [
            new OA\Response(response: 200, description: "PDF export successful", content: new OA\MediaType(mediaType: "application/pdf", schema: new OA\Schema(type: "string", format: "binary"))),
            new OA\Response(response: 401, description: "Unauthenticated"),
        ]
    )]
    public function exportPdf(Request $request)
    {
        $entries = collect();
        $this->buildMapQuery($request, $request->user())
            ->with(self::EXPORT_WITH)
            ->chunk(50, function ($chunk) use ($entries) {
                $entries->push(...$chunk);
            });

        $pdf = Pdf::loadView('exports.programme-entries-pdf', [
            'entries'       => $entries,
            'generatedAt'   => now()->format('Y-m-d H:i:s'),
            'totalEntries'  => $entries->count(),
        ]);

        return $pdf->download('programme-entries-report-' . now()->format('Y-m-d-H-i-s') . '.pdf');
    }

    private function generateCsv(array $entries): string
    {
        $handle = fopen('php://temp', 'w+');

        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, [
            'Entry ID', 'Programme Name', 'Organisation Name', 'Budget Band',
            'Start Year', 'End Year', 'Ongoing', 'FTE Staff',
            'Direct Beneficiaries', 'Indirect Beneficiaries', 'Method', 'Verified Date',
            'Last Updated', 'Keywords', 'Locations (Provinces/Districts)',
            'Activities (Taxonomy)', 'Education Levels', 'Inclusion Groups',
            'Inclusion Types', 'Government Agreements',
        ]);

        foreach ($entries as $entry) {
            $keywords = $entry->keywords->pluck('keyword')->implode('; ');

            $locations = $entry->locations->map(function ($loc) {
                return implode('/', array_filter([
                    $loc->province?->province_name,
                    $loc->district?->name,
                    $loc->commune?->name,
                    $loc->village?->name,
                ]));
            })->filter()->unique()->implode('; ');

            $activities = $entry->activities->map(function ($activity) {
                $parts = array_filter([
                    $activity->activityItem?->subcategory?->category?->category_name,
                    $activity->activityItem?->subcategory?->subcategory_name,
                    $activity->activityItem?->item_name,
                ]);
                return implode(' > ', $parts);
            })->filter()->unique()->implode('; ');

            $educationLevels = $entry->activities->flatMap(
                fn($a) => $a->activityLevels->map(fn($al) => $al->educationLevel?->level_name)
            )->filter()->unique()->implode('; ');

            $agreements = $entry->governmentAgreements->map(fn($a) => sprintf(
                '%s (%s) - %s [%s]',
                $a->counterpart_agency, $a->institution_name, $a->status, $a->nature
            ))->implode('; ');

            fputcsv($handle, [
                $entry->id,
                $entry->programme_name,
                $entry->organisation?->name ?? 'N/A',
                $entry->budgetBand?->label ?? 'N/A',
                $entry->start_year,
                $entry->end_year ?? 'N/A',
                $entry->ongoing ? 'Yes' : 'No',
                $entry->fte_staff,
                $entry->direct_beneficiaries,
                $entry->indirect_beneficiaries,
                $entry->method,
                $entry->verified_date?->format('Y-m-d') ?? 'N/A',
                $entry->last_updated_at?->format('Y-m-d H:i:s') ?? 'N/A',
                $keywords ?: 'N/A',
                $locations ?: 'N/A',
                $activities ?: 'N/A',
                $educationLevels ?: 'N/A',
                $entry->activities->pluck('inclusion_group')->filter()->unique()->implode('; ') ?: 'N/A',
                $entry->activities->pluck('inclusion_type')->filter()->unique()->implode('; ') ?: 'N/A',
                $agreements ?: 'N/A',
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content;
    }
}
