<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityCategory;
use App\Models\ActivityItem;
use App\Services\AI\GroqService;
use App\Services\ProgrammeDescriptionExtractor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ProgrammeActivityAiController extends Controller
{
    public function __construct(private ProgrammeDescriptionExtractor $extractor) {}

    public function aiAutofill(Request $request)
    {
        $validated = $request->validate([
            'text' => 'nullable|string|max:200000',
            'file' => 'nullable|file|mimes:pdf,txt|max:200240',
        ]);

        $description = trim((string) ($validated['text'] ?? ''));

        if ($request->hasFile('file')) {
            $fileText    = $this->extractor->fromFile($request->file('file'));
            $description = $description !== '' ? $description . "\n\n" . $fileText : $fileText;
        }

        if (mb_strlen($description) < 10) {
            return response()->json(['message' => 'No readable programme description provided.'], 422);
        }

        $taxonomyList = $this->buildTaxonomyList();

        $provinces = Cache::remember('provinces:all', now()->addHours(24),
            fn() => \App\Models\Province::select('id', 'province_name')->get()
        );
        $provinceLines = $provinces->map(fn($p) => "{$p->id}: {$p->province_name}")->implode("\n");

        $inclusionGroups = ['Disability', 'Gender', 'LGBTIQ+', 'Ethnicity/language', 'Displacement', 'Migrant families', 'Statelessness', 'Other'];
        $counterparts    = ['MoEYS national level', 'Provincial Office of Education', 'District Office of Education', 'Teacher Education Institution', 'specific school or cluster', 'other government ministry'];
        $natures         = ['MoU', 'Letter of Understanding', 'official approval letter', 'informal working arrangement'];
        $statuses        = ['active', 'expired', 'under renewal', 'under negotiation'];
        $budgetBands     = ['Under $50,000', '$50,000ΓÇô$200,000', '$200,000ΓÇô$500,000', '$500,000ΓÇô$2,000,000', 'Above $2,000,000'];

        $prompt = <<<PROMPT
You are an expert in the National Education Policy (NEP) for Cambodia. Analyse the programme description and return a single JSON object with 5 keys: "identity", "activities", "geography", "agreements", "keywords".

--- TAXONOMY ITEMS (CODE: Label) ---
{$taxonomyList}

--- EDUCATION LEVELS ---
1=Pre-primary/ECCD, 2=Primary, 3=Lower secondary, 4=Upper secondary, 5=Higher education

--- INCLUSION GROUPS ---
{$this->csv($inclusionGroups)}
Type A=Inclusive design, Type B=Targeted programme

--- CAMBODIA PROVINCES (ID: Name) ---
{$provinceLines}

--- GOVERNMENT COUNTERPART AGENCIES (use exact values) ---
{$this->csv($counterparts)}

--- AGREEMENT NATURES (use exact values) ---
{$this->csv($natures)}

--- AGREEMENT STATUSES (use exact values) ---
{$this->csv($statuses)}

--- BUDGET BANDS (use exact value) ---
{$this->csv($budgetBands)}

Return ONLY this JSON structure, no explanation:
{
  "identity": {
    "name": "Girls Education Scholarship Programme",
    "start_year": 2019,
    "end_year": null,
    "is_ongoing": true,
    "fte_staff": 12,
    "budget_band": "\$200,000ΓÇô\$500,000",
    "direct_beneficiaries": 500,
    "indirect_beneficiaries": 2000
  },
  "activities": {
    "codes": ["B1.1.01"],
    "suggestions": {
      "B1.1.01": {
        "education_levels": [2, 3],
        "inclusion": {"hasInclusion": true, "dimensions": [{"group": "Gender", "type": "B"}]}
      }
    }
  },
  "geography": {
    "province_ids": [15, 21]
  },
  "agreements": [
    {
      "counterpart_agency": "MoEYS national level",
      "nature": "MoU",
      "status": "active",
      "institution_name": "Ministry of Education, Youth and Sports"
    }
  ],
  "keywords": ["scholarship", "girls education", "rural", "mentoring", "lower secondary"]
}

Rules:
- identity: extract programme name, years, staff count, budget band (exact value from list), beneficiary numbers ΓÇö use null for any field not mentioned
- activities.codes: max 8 matching taxonomy codes
- geography.province_ids: only IDs from the provinces list above, only if clearly mentioned
- agreements: extract ALL government partnerships/agreements mentioned; use exact counterpart_agency/nature/status values from the lists above; institution_name is the specific name of the agency/department ΓÇö use empty string if not specified; if no agreements mentioned return []
- keywords: 3-5 short descriptive keywords/phrases from the description
- If a section has no relevant data, return empty array/object for it

Programme description:
{$description}
PROMPT;

        try {
            $groq     = App::make(GroqService::class);
            $response = $groq->generateContent($prompt, [
                'temperature'     => 0.1,
                'response_format' => ['type' => 'json_object'],
            ]);

            $result = $this->parseAutofillResponse($response);

            $validCodeSet = Cache::remember('taxonomy:active_codes', now()->addHours(24),
                fn() => ActivityItem::where('is_active', true)->pluck('code')->flip()
            );

            $codes = collect($result['activities']['codes'] ?? [])
                ->filter(fn($c) => is_scalar($c))
                ->flatMap(fn($c) => $this->codeCandidates((string) $c))
                ->unique()->values();

            $result['activities']['codes']       = $codes->filter(fn($c) => isset($validCodeSet[$c]))->values()->all();
            $result['activities']['suggestions'] = array_filter(
                $result['activities']['suggestions'] ?? [],
                fn($k) => isset($validCodeSet[$k]),
                ARRAY_FILTER_USE_KEY
            );

            $validProvinceIds = $provinces->pluck('id')->flip();
            $result['geography']['province_ids'] = array_values(array_filter(
                $result['geography']['province_ids'] ?? [],
                fn($id) => isset($validProvinceIds[$id])
            ));

            $counterpartsLower = array_combine(array_map('strtolower', $counterparts), $counterparts);
            $naturesLower      = array_combine(array_map('strtolower', $natures), $natures);
            $statusesLower     = array_combine(array_map('strtolower', $statuses), $statuses);

            $result['agreements'] = array_values(array_filter(
                array_map(function ($a) use ($counterpartsLower, $naturesLower, $statusesLower) {
                    if (!is_array($a)) return null;
                    $agency = $counterpartsLower[strtolower(trim((string) ($a['counterpart_agency'] ?? '')))] ?? null;
                    $nature = $naturesLower[strtolower(trim((string) ($a['nature'] ?? '')))]                  ?? null;
                    $status = $statusesLower[strtolower(trim((string) ($a['status'] ?? '')))]                 ?? null;
                    if (!$agency || !$nature || !$status) return null;
                    return [
                        'counterpart_agency' => $agency,
                        'nature'             => $nature,
                        'status'             => $status,
                        'institution_name'   => trim((string) ($a['institution_name'] ?? '')),
                    ];
                }, $result['agreements'] ?? []),
                fn($a) => $a !== null
            ));

            return response()->json($result);
        } catch (\RuntimeException $e) {
            $code   = $e->getCode();
            $status = in_array($code, [429, 500, 503]) ? $code : 503;
            return response()->json(['message' => $e->getMessage()], $status);
        }
    }

    public function suggestActivities(Request $request)
    {
        $validated = $request->validate([
            'text' => 'nullable|string|max:200000',
            'file' => 'nullable|file|mimes:pdf,txt|max:10240',
        ]);

        $description = trim((string) ($validated['text'] ?? ''));

        if ($description === '' && $request->hasFile('file')) {
            $description = $this->extractor->fromFile($request->file('file'));
        }

        if ($this->extractor->looksLikeRawPdf($description)) {
            return response()->json([
                'message' => 'The uploaded PDF was sent as raw PDF data, not readable programme text. Extract the PDF text first, use OCR for scanned PDFs, or paste the programme description.',
                'errors'  => ['text' => ['Raw PDF data cannot be used for AI activity suggestions.']],
            ], 422);
        }

        if (mb_strlen($description) < 10) {
            return response()->json([
                'message' => 'No readable programme description was found. Please paste the programme description text, or upload a text-based PDF. Scanned/image PDFs need OCR before AI suggestions can be generated.',
                'errors'  => ['text' => ['The programme description must contain at least 10 readable characters.']],
            ], 422);
        }

        $taxonomyList    = $this->buildTaxonomyList();
        $inclusionGroups = ['Disability', 'Gender', 'LGBTIQ+', 'Ethnicity/language', 'Displacement', 'Migrant families', 'Statelessness', 'Other'];
        $groupsList      = implode(', ', $inclusionGroups);

        $prompt = <<<PROMPT
You are an expert in the National Education Policy (NEP) taxonomy for Cambodia.

Below is a list of all available activity taxonomy items in the format CODE: Label.

{$taxonomyList}

Education level IDs:
1 = Pre-primary / ECCD
2 = Primary
3 = Lower secondary
4 = Upper secondary
5 = Higher education

Inclusion groups: {$groupsList}
Type A = Inclusive design (the activity is designed to be inclusive for this group)
Type B = Targeted programme (the activity specifically targets this group)

Based on the following programme description, return a JSON object with:
- "codes": array of matching activity codes (max 8)
- "suggestions": object keyed by code, each with:
  - "education_levels": array of education level IDs (integers) that apply
  - "inclusion": object with "hasInclusion" (boolean) and "dimensions" (array of {"group": string, "type": "A" or "B"}) ΓÇö only include if the description clearly mentions a specific target group

Return ONLY valid JSON, no explanation. Example:
{"codes":["B1.1.01","B2.3.02"],"suggestions":{"B1.1.01":{"education_levels":[2,3],"inclusion":{"hasInclusion":true,"dimensions":[{"group":"Gender","type":"B"}]}},"B2.3.02":{"education_levels":[1],"inclusion":{"hasInclusion":false,"dimensions":[]}}}}

Programme description:
{$description}
PROMPT;

        try {
            $groq     = App::make(GroqService::class);
            $response = $groq->generateContent($prompt, [
                'temperature'     => 0.1,
                'response_format' => ['type' => 'json_object'],
            ]);

            [$codes, $suggestions] = $this->extractCodesAndSuggestions($response);

            $validCodeSet = Cache::remember('taxonomy:active_codes', now()->addHours(24),
                fn() => ActivityItem::where('is_active', true)->pluck('code')->flip()
            );

            $validCodes = collect($codes)
                ->filter(fn($c) => is_scalar($c))
                ->flatMap(fn($c) => $this->codeCandidates((string) $c))
                ->unique()->values()
                ->filter(fn($c) => isset($validCodeSet[$c]))
                ->values()->all();

            $validSuggestions = array_filter(
                $suggestions,
                fn($k) => isset($validCodeSet[$k]),
                ARRAY_FILTER_USE_KEY
            );

            return response()->json(['data' => $validCodes, 'suggestions' => $validSuggestions]);
        } catch (\RuntimeException $e) {
            $code   = $e->getCode();
            $status = in_array($code, [429, 500, 503]) ? $code : 503;
            return response()->json(['message' => $e->getMessage()], $status);
        }
    }

    public function fetchUrl(Request $request)
    {
        $request->validate(['url' => 'required|url|max:2048']);

        $url = $request->input('url');

        if (! $this->isPubliclyFetchable($url)) {
            return response()->json([
                'message' => 'This URL cannot be fetched — only public http/https addresses are allowed.',
            ], 422);
        }

        try {
            $response = Http::timeout(15)
                ->withOptions(['allow_redirects' => false]) // a redirect could repoint at an internal address after this check
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; NEP-Bot/1.0)'])
                ->get($url);

            if (!$response->successful()) {
                return response()->json(['message' => 'Could not fetch the URL. The website may be unavailable or blocking requests.'], 422);
            }

            $html = preg_replace('/<(script|style|nav|footer|header|aside|noscript)[^>]*>.*?<\/\1>/si', '', $response->body());
            $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
            $text = mb_substr($text, 0, 15000);

            if (mb_strlen($text) < 50) {
                return response()->json(['message' => 'Could not extract readable text from this URL. Try pasting the content manually.'], 422);
            }

            return response()->json(['text' => $text]);
        } catch (\Illuminate\Http\Client\ConnectionException) {
            return response()->json(['message' => 'Could not connect to the URL. Please check the address and try again.'], 422);
        }
    }

    private function buildTaxonomyList(): string
    {
        $categories = Cache::remember('taxonomy:categories:all', now()->addHours(24), fn() =>
            ActivityCategory::with(['subcategories.items' => fn($q) =>
                $q->where('is_active', true)->where('is_other', false)
            ])->get()
        );

        $lines = [];
        foreach ($categories as $cat) {
            foreach ($cat->subcategories as $sub) {
                foreach ($sub->items as $item) {
                    $lines[] = "{$item->code}: {$item->label} (subcategory: {$sub->label}, category: {$cat->label})";
                }
            }
        }
        return implode("\n", $lines);
    }

    private function parseAutofillResponse(array $response): array
    {
        $defaults = [
            'identity'   => [],
            'activities' => ['codes' => [], 'suggestions' => []],
            'geography'  => ['province_ids' => []],
            'agreements' => [],
            'keywords'   => [],
        ];

        $data = null;
        if (isset($response['activities']) || isset($response['geography']) || isset($response['keywords']) || isset($response['identity'])) {
            $data = $response;
        } elseif (isset($response['executive_summary'])) {
            $data = json_decode($response['executive_summary'], true);
        }

        if (!is_array($data)) {
            return $defaults;
        }

        return array_merge($defaults, [
            'identity'   => is_array($data['identity'] ?? null) ? $data['identity'] : [],
            'activities' => [
                'codes'       => is_array($data['activities']['codes'] ?? null) ? $data['activities']['codes'] : [],
                'suggestions' => is_array($data['activities']['suggestions'] ?? null) ? $data['activities']['suggestions'] : [],
            ],
            'geography'  => [
                'province_ids' => is_array($data['geography']['province_ids'] ?? null)
                    ? array_map('intval', $data['geography']['province_ids']) : [],
            ],
            'agreements' => is_array($data['agreements'] ?? null) ? $data['agreements'] : [],
            'keywords'   => is_array($data['keywords'] ?? null) ? array_slice($data['keywords'], 0, 5) : [],
        ]);
    }

    private function extractCodesAndSuggestions(array $response): array
    {
        if (!empty($response['codes']) && is_array($response['codes'])) {
            return [$response['codes'], is_array($response['suggestions'] ?? null) ? $response['suggestions'] : []];
        }

        $raw = $response['executive_summary'] ?? '';
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                if (!empty($decoded['codes'])) {
                    return [$decoded['codes'], is_array($decoded['suggestions'] ?? null) ? $decoded['suggestions'] : []];
                }
                if (array_is_list($decoded)) {
                    return [$decoded, []];
                }
            }
            if (preg_match('/\[.*?\]/s', $raw, $matches)) {
                $decoded = json_decode($matches[0], true);
                if (is_array($decoded)) {
                    return [$decoded, []];
                }
            }
        }

        if (!empty($response['_raw_array']) && is_array($response['_raw_array'])) {
            return [$response['_raw_array'], []];
        }

        return [[], []];
    }

    private function codeCandidates(string $code): array
    {
        $code = strtoupper(trim($code));
        if ($code === '') return [];

        $candidates = [$code];
        if (preg_match('/^([A-Z]\d+)\.(\d+)\.(\d+)$/', $code, $m)) {
            $candidates[] = sprintf('%s.%s.%02d', $m[1], $m[2], (int) $m[3]);
        }
        return $candidates;
    }

    private function csv(array $arr): string
    {
        return implode(', ', $arr);
    }

    /**
     * SSRF guard for fetchUrl(): only allow http/https URLs whose host
     * resolves exclusively to public IP addresses. Laravel's `url` validation
     * rule only checks syntax — without this, an authenticated user (any
     * role with programmes.create/update, i.e. every member_org account)
     * could make this server issue arbitrary outbound requests, including to
     * cloud metadata endpoints or other internal-only services.
     *
     * This does not fully defend against DNS rebinding (resolving safely
     * here, then the name resolving differently at connect time) — closing
     * that gap would require pinning the connection to the resolved IP
     * while still sending the original Host header. What's implemented here
     * blocks the realistic, direct case: a user simply pointing the field at
     * an internal or link-local address.
     */
    private function isPubliclyFetchable(string $url): bool
    {
        $parts = parse_url($url);
        if (! $parts || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower($parts['host']);
        if ($host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return false;
        }

        // Literal IP in the URL (e.g. http://169.254.169.254/...)
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $this->isPublicIp($host);
        }

        // Hostname — resolve and check every returned address, IPv4 and IPv6.
        $ipv4s = gethostbynamel($host) ?: [];
        $ipv6s = [];
        if (function_exists('dns_get_record')) {
            foreach (dns_get_record($host, DNS_AAAA) ?: [] as $record) {
                if (! empty($record['ipv6'])) {
                    $ipv6s[] = $record['ipv6'];
                }
            }
        }

        $resolved = array_merge($ipv4s, $ipv6s);
        if (empty($resolved)) {
            return false; // couldn't resolve — reject rather than let the HTTP client resolve it again
        }

        foreach ($resolved as $ip) {
            if (! $this->isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}
