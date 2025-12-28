<?php

namespace App\Services;

use App\Models\Peminjaman;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class BuktiPenyerahanService
{
    /**
     * Generate and save PDF receipt for a loan marked as borrowed.
     * Returns the storage path (relative to disk) when successful.
     */
    public function generate(Peminjaman $peminjaman): string
    {
        $path = $peminjaman->buktiPenyerahanPath();

        // Ensure directory exists
        $dir = dirname($path);
        if (!Storage::disk('public')->exists($dir)) {
            Storage::disk('public')->makeDirectory($dir);
        }

        $pdf = Pdf::loadView('pdf.bukti_penyerahan', [
            'peminjaman' => $peminjaman->loadMissing(['peminjam', 'petugas', 'radio.kategori']),
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait');

        // Save to storage/app/public/...
        $absolutePath = Storage::disk('public')->path($path);
        $pdf->save($absolutePath);

        return $path;
    }

    /**
     * Stream the PDF inline (no forced download).
     */
    public function streamInline(Peminjaman $peminjaman)
    {
        $pdf = Pdf::loadView('pdf.bukti_penyerahan', [
            'peminjaman' => $peminjaman->loadMissing(['peminjam', 'petugas', 'radio.kategori']),
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait');

        $filename = 'Bukti-Penyerahan-' . ($peminjaman->kode_peminjaman ?: ('PMJ-'.$peminjaman->id)) . '.pdf';

        return $pdf->stream($filename, [
            'Attachment' => false, // inline open in browser
        ]);
    }
}
