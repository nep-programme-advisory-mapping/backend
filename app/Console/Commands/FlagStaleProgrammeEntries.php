<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;


class FlagStaleProgrammeEntries extends Command
{
    protected $signature = 'programme-entries:flag-stale';
    protected $description = 'Marks programme entries not updated in 18+ months as unverified.';
    public function handle(): int
    {
        $cutoff = now()->subMonths(18);
        $affected = DB::table('programme_entries')
            ->where('last_updated_at', '<', $cutoff)
            ->where('is_unverified', false)
            ->update(['is_unverified' => true]);

        $this->info("Flagged {$affected} entr" . ($affected === 1 ? 'y' : 'ies') . " as unverified.");
        return self::SUCCESS;
    }
}