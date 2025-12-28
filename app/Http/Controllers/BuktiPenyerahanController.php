<?php

namespace App\Http\Controllers;

use App\Models\Peminjaman;
use App\Services\BuktiPenyerahanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BuktiPenyerahanController extends Controller
{
    public function download(Request $request, Peminjaman $peminjaman)
    {
        // Ensure exists if already dipinjam, but stream inline regardless
        if ($peminjaman->status === Peminjaman::STATUS_DIPINJAM && ! $peminjaman->hasBuktiPenyerahan()) {
            app(BuktiPenyerahanService::class)->generate($peminjaman);
        }

        return app(BuktiPenyerahanService::class)->streamInline($peminjaman);
    }
}
