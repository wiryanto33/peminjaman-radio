<?php

namespace App\Observers;

use App\Models\Radio;

class RadioObserver
{
    public function saving(Radio $model): void
    {
        // Otomatiskan status berdasarkan stok, kecuali jika perbaikan
        if ($model->status !== Radio::STATUS_PERBAIKAN) {
            $model->status = (int) $model->stok <= 0
                ? Radio::STATUS_STOK_HABIS
                : Radio::STATUS_TERSEDIA;
        }
    }
}

