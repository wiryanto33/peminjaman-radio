<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pengembalian extends Model
{


    // add fillable
    protected $table = 'pengembalian';

    public const KONDISI_BAIK         = 'baik';
    public const KONDISI_RUSAK_RINGAN  = 'rusak_ringan';
    public const KONDISI_RUSAK_BERAT   = 'rusak_berat';
    public const KONDISI_HILANG        = 'hilang';

    protected $fillable = [
        'peminjaman_id',
        'radio_id',
        'penerima_id',
        'tgl_kembali',
        'kondisi_kembali',
        'catatan',
    ];

    protected $casts = [
        'tgl_kembali' => 'datetime',
        'denda'       => 'decimal:2',
    ];

    // add guaded
    protected $guarded = ['id'];
    // add hidden
    protected $hidden = ['created_at', 'updated_at'];

    public function peminjaman(): BelongsTo
    {
        return $this->belongsTo(Peminjaman::class, 'peminjaman_id');
    }

    public function radio(): BelongsTo
    {
        return $this->belongsTo(Radio::class, 'radio_id');
    }

    public function penerima(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penerima_id');
    }

}
