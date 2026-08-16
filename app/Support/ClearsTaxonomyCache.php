<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

trait ClearsTaxonomyCache
{
    protected function clearTaxonomyCache(): void
    {
        Cache::forget('taxonomy:categories:all');
        Cache::forget('taxonomy:category_counts');
        Cache::forget('taxonomy:active_codes');
    }
}