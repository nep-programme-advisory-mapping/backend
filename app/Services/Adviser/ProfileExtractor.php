<?php

namespace App\Services\Adviser;

use App\Models\ActivityItem;
use App\Models\Province;
use App\Services\AI\ClaudeService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

class ProfileExtractor
{
    public function extract(string $documentText): array
    {
        $provinces = Cache::remember('provinces:all', now()->addHours(24),
            fn() => Province::select('id', 'province_name')->get()
        );

        $items = Cache::remember('taxonomy:items:all', now()->addHours(24),
            fn() => ActivityItem::with('subcategory.category')
                ->where('is_active', true)
                ->where('is_other', false)
                ->get()
        );

        $provinceLines = $provinces->map(fn($p) => "{$p->id}: {$p->province_name}")->implode("\n");
        $itemLines     = $items->map(fn($i) =>
            "{$i->id}: {$i->label} (subcategory_id:{$i->subcategory_id}, category_id:{$i->subcategory?->category_id})"
        )->implode("\n");

        $truncated = mb_substr($documentText, 0, 12000);

        $prompt = <<<PROMPT
You are an expert in the National Education Policy (NEP) for Cambodia.
Read the programme document and extract a structured profile for overlap matching.

--- CAMBODIA PROVINCES (ID: Name) ---
{$provinceLines}

--- TAXONOMY ITEMS (ID: Label, subcategory_id, category_id) ---
{$itemLines}

Return ONLY valid JSON, no explanation:
{"activities":{"item_ids":[],"subcategory_ids":[],"category_ids":[]},"geography":{"province_ids":[]}}

Rules:
- item_ids: IDs of taxonomy items clearly mentioned or strongly implied (max 10)
- subcategory_ids and category_ids: derive from the matched item_ids
- province_ids: only IDs from the province list where the programme clearly operates
- If nothing matches, return empty arrays

Document:
{$truncated}
PROMPT;

        try {
            $ai       = App::make(ClaudeService::class);
            $response = $ai->generateContent($prompt, [
                'temperature'     => 0.1,
                'response_format' => ['type' => 'json_object'],
            ]);

            $validItemIds     = $items->pluck('id')->flip();
            $validProvinceIds = $provinces->pluck('id')->flip();

            $itemIds = array_values(array_filter(
                array_map('intval', $response['activities']['item_ids'] ?? []),
                fn($id) => isset($validItemIds[$id])
            ));

            $matchedItems   = $items->whereIn('id', $itemIds);
            $subcategoryIds = $matchedItems->pluck('subcategory_id')->filter()->unique()->values()->all();
            $categoryIds    = $matchedItems->map(fn($i) => $i->subcategory?->category_id)->filter()->unique()->values()->all();

            $provinceIds = array_values(array_filter(
                array_map('intval', $response['geography']['province_ids'] ?? []),
                fn($id) => isset($validProvinceIds[$id])
            ));

            return [
                'activities' => [
                    'item_ids'        => $itemIds,
                    'subcategory_ids' => $subcategoryIds,
                    'category_ids'    => $categoryIds,
                ],
                'geography' => ['province_ids' => $provinceIds],
            ];
        } catch (\Throwable $e) {
            // Re-throw rate-limit errors so callers can surface them
            $code = $e->getCode();
            if (in_array($code, [429, 503])) throw $e;

            return [
                'activities' => ['item_ids' => [], 'subcategory_ids' => [], 'category_ids' => []],
                'geography'  => ['province_ids' => []],
            ];
        }
    }
}
