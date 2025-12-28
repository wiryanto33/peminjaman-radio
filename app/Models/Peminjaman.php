<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Peminjaman extends Model
{
    // Perlu set nama tabel karena bukan bentuk jamak standar Eloquent
    protected $table = 'peminjaman';

    public const STATUS_PENDING   = 'pending';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_DIPINJAM  = 'dipinjam';
    public const STATUS_DIKEMBALIKAN  = 'dikembalikan';
    public const STATUS_DIBATALKAN  = 'dibatalkan';
    public const STATUS_TERLAMBAT   = 'terlambat';

    protected $fillable = [
        'kode_peminjaman',
        'radio_id',
        'jumlah',
        'peminjam_id',
        'petugas_id',
        'tgl_pinjam',
        'tgl_jatuh_tempo',
        'status',
        'keperluan',
        'lokasi_penggunaan',
        'catatan',
    ];

    protected $casts = [
        'tgl_pinjam'       => 'datetime',
        'tgl_jatuh_tempo'  => 'datetime',
        'jumlah'           => 'integer',
    ];

    // add guaded
    protected $guarded = ['id'];
    // add hidden
    protected $hidden = ['created_at', 'updated_at'];


    public function radio(): BelongsTo
    {
        return $this->belongsTo(Radio::class, 'radio_id');
    }

    public function peminjam(): BelongsTo
    {
        return $this->belongsTo(User::class, 'peminjam_id');
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'petugas_id');
    }

    /** Aktif: belum returned/canceled */
    public function scopeActive($q)
    {
        return $q->whereNotIn('status', [self::STATUS_DIKEMBALIKAN, self::STATUS_DIBATALKAN]);
    }

    /** Overdue (terlambat) */
    public function scopeOverdue($q)
    {
        return $q->where('status', self::STATUS_TERLAMBAT);
    }

    public function isActive(): bool
    {
        return ! in_array($this->status, [self::STATUS_DIKEMBALIKAN, self::STATUS_DIBATALKAN], true);
    }

    /**
     * Generate kode peminjaman unik yang ramah dibaca.
     * Contoh: PMJ-20251024-AB12C
     */
    public static function generateKode(): string
    {
        $prefix = 'PMJ-' . now()->format('Ymd') . '-';

        do {
            $suffix = Str::upper(Str::random(5));
            $kode = $prefix . $suffix;
        } while (self::where('kode_peminjaman', $kode)->exists());

        return $kode;
    }

    public function buktiPenyerahanPath(): string
    {
        return 'peminjaman/' . $this->id . '/bukti-penyerahan.pdf';
    }

    public function hasBuktiPenyerahan(): bool
    {
        return Storage::disk('public')->exists($this->buktiPenyerahanPath());
    }

    public function buktiPenyerahanUrl(): ?string
    {
        return $this->hasBuktiPenyerahan()
            ? Storage::disk('public')->url($this->buktiPenyerahanPath())
            : null;
    }
}
