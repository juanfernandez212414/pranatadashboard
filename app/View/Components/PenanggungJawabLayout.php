<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use App\Models\Category;

class PenanggungJawabLayout extends Component
{
    public $categories;
    public $title;

    public function __construct($title = 'PRANATA - Penanggung Jawab')
    {
        $this->title = $title;
        // Ambil kategori untuk sidebar
        $this->categories = Category::orderBy('name', 'asc')->get();
    }

    public function render(): View|Closure|string
    {
        // Pastikan mengarah ke file blade penanggungjawablayout
        return view('components.penanggungjawablayout');
    }
}
