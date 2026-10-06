<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Narrative extends Model
{
    use HasFactory;

    protected $fillable = ['indicator_id', 'user_id', 'content'];

    // Relasi: Narasi ini terkait dengan satu indikator tertentu.
    public function indicator()
    {
        return $this->belongsTo(Indicator::class);
    }

    // Relasi: Narasi ini dibuat atau diedit oleh satu pengguna.
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
