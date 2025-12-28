<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Peminjaman;
use App\Services\PeminjamanService;

class MarkOverduePeminjaman extends Command
{
    protected $signature = 'peminjaman:mark-overdue';
    protected $description = 'Tandai peminjaman yang melewati jatuh tempo sebagai OVERDUE';

    public function handle(PeminjamanService $service): int
    {
        $count = 0;
        Peminjaman::query()
            ->whereIn('status', [Peminjaman::STATUS_APPROVED, Peminjaman::STATUS_DIPINJAM])
            ->where('tgl_jatuh_tempo', '<', now())
            ->chunkById(200, function ($rows) use ($service, &$count) {
                foreach ($rows as $p) {
                    if ($service->markOverdueIfNeeded($p)) {
                        $count++;
                    }
                }
            });

        $this->info("Overdue updated: {$count}");
        return self::SUCCESS;
    }
}
