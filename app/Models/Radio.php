<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Radio extends Model
{
    protected $table = 'radios';

    public const STATUS_TERSEDIA   = 'tersedia';
    public const STATUS_DIPINJAM    = 'dipinjam';
    public const STATUS_PERBAIKAN  = 'perbaikan';
    public const STATUS_STOK_HABIS = 'stok_habis';

    public const KONDISI_BAIK          = 'baik';
    public const KONDISI_RUSAK_RINGAN   = 'rusak_ringan';
    public const KONDISI_RUSAK_BERAT    = 'rusak_berat';

    protected $fillable = [
        'kategori_id',
        'merk',
        'model',
        'image',
        'serial_no',
        'status',
        'kondisi',
        'stok',
        'stok_total',
        'deskripsi',
    ];

    protected $casts = [
        'tanggal_perolehan' => 'date',
    ];

    // add guaded
    protected $guarded = ['id'];
    // add hidden
    protected $hidden = ['created_at', 'updated_at'];

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(KategoriRadio::class, 'kategori_id');
    }

    public function peminjamans(): HasMany
    {
        return $this->hasMany(Peminjaman::class, 'radio_id');
    }

    public function pengembalians(): HasMany
    {
        return $this->hasMany(Pengembalian::class, 'radio_id');
    }

    /** Scope radio yang siap dipinjam */
    public function scopeTersedia($q)
    {
        return $q->where('status', self::STATUS_TERSEDIA);
    }

    /** Scope radio yang sedang dipinjam */
    public function scopeDipinjam($q)
    {
        return $q->where('status', self::STATUS_DIPINJAM);
    }

    /** Scope radio dengan stok tersedia (>0) */
    public function scopeInStock($q)
    {
        return $q->where('stok', '>', 0);
    }

    public function isTersedia(): bool
    {
        return $this->status === self::STATUS_TERSEDIA;
    }

    public function hasStock(): bool
    {
        return (int) $this->stok > 0;
    }

    public function borrowedCount(): int
    {
        return max(0, (int) $this->stok_total - (int) $this->stok);
    }

}
