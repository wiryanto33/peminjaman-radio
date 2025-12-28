<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BuktiPenyerahanController;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::middleware(['web'])->group(function () {
    Route::get('/peminjaman/{peminjaman}/bukti-penyerahan', [BuktiPenyerahanController::class, 'download'])
        ->name('peminjaman.downloadBukti');
});
