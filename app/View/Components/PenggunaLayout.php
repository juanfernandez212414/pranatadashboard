<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use App\Models\Category;

// Nama class HARUS PenggunaLayout (sesuai nama file dan tag <x-penggunalayout>)
class PenggunaLayout extends Component
{
    public $categories;
    public $title;

    public function __construct($title = 'PRANATA')
    {
        $this->title = $title;

        // Logika ini akan mengisi variabel $categories secara otomatis
        // sehingga Anda tidak perlu lagi mengambilnya di Controller
        $this->categories = Category::orderBy('name', 'asc')->get();
    }

    public function render(): View|Closure|string
    {
        // Pastikan mengarah ke resources/views/components/penggunalayout.blade.php
        return view('components.penggunalayout');
    }
}
