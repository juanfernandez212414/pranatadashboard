<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = ['category_id', 'name'];

    // Relasi: Subjek ini termasuk dalam satu kategori tertentu.
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    // Relasi: Satu subjek memiliki banyak indikator.
    public function indicators()
    {
        return $this->hasMany(Indicator::class);
    }
}
