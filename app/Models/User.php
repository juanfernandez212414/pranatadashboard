<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Notifications\CustomResetPassword;

class User extends Authenticatable
{
    use HasFactory, Notifiable; // ← Gunakan trait HasRoles

    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'role_id', // <-- TAMBAHKAN INI (ID Peran: 1=Admin, 2=Pimpinan, 3=PJ, 4=User Biasa)
        'photo', // <-- TAMBAHKAN INI (Untuk foto profil)
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Relasi: Pengguna ini memiliki satu role (peran) tertentu.
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    protected function avatarUrl(): Attribute
    {
        return Attribute::make(
            // --- KODE YANG SUDAH DIPERBAIKI (Tanda . ekstra dihapus) ---
            get: fn() => ($this->photo && $this->photo != 'avatar.png')
                ? asset('storage/profil/' . $this->photo)
                : asset('images/avatar.png')
        );
    }

    // Pengecekan: Memastikan apakah pengguna ini adalah Admin.
    public function isAdmin(): bool
    {
        return $this->role_id == 1;
    }

    // Notifikasi: Mengirimkan email berisi link untuk reset password.
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new CustomResetPassword($token));
    }
}
