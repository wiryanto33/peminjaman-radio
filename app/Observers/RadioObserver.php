<?php

namespace App\Observers;

use App\Models\Radio;

class RadioObserver
{
    public function saving(Radio $model): void
    {
        // Jangan timpa status jika radio sedang dipinjam atau dalam perbaikan
        if (!in_array($model->status, [Radio::STATUS_PERBAIKAN, Radio::STATUS_DIPINJAM])) {
            $model->status = (int) $model->stok <= 0
                ? Radio::STATUS_STOK_HABIS
                : Radio::STATUS_TERSEDIA;
        }
    }
}

