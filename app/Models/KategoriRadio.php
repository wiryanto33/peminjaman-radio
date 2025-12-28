<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KategoriRadio extends Model
{
    //

    // add fillable
    protected $fillable = [
        'nama',
    ];
    // add guaded
    protected $guarded = ['id'];
    // add hidden
    protected $hidden = ['created_at', 'updated_at'];

    public function radios(): HasMany
    {
        return $this->hasMany(Radio::class, 'kategori_id');
    }
}
