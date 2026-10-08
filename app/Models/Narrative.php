<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Narrative extends Model
{
    use HasFactory;

    protected $fillable = ['indicator_id', 'user_id', 'content', 'data_hash'];

    // Sidik data indikator, disimpan bersama narasi untuk mengetahui apakah datanya berubah sesudah itu.
    public static function sidikData($data): string
    {
        return sha1(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    // Data indikator ($data = $indicator->data saat ini) sudah berubah sejak narasi ini disimpan. Narasi lama
    // tanpa sidik tidak ditandai.
    public function dataBerubah($data): bool
    {
        return $this->data_hash !== null && $this->data_hash !== self::sidikData($data);
    }

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
