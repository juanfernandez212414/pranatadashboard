<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Indicator extends Model
{
    use HasFactory;

    protected $fillable = ['subject_id', 'user_id', 'name', 'unit', 'data'];

    protected $casts = [
        'data' => 'array',
    ];

    // Relasi: Indikator ini milik satu subjek tertentu.
    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    // Relasi: Indikator ini dibuat atau dikelola oleh satu pengguna.
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relasi: Indikator ini memiliki satu narasi penjelasan.
    public function narrative()
    {
        return $this->hasOne(Narrative::class);
    }
}
