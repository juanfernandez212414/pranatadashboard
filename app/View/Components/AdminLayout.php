<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use App\Models\Category;

class AdminLayout extends Component
{
    /**
     * @var \Illuminate\Database\Eloquent\Collection
     */
    public $categories;

    /**
     * @var string
     */
    public $title;

    /**
     * Create a new component instance.
     *
     * @param string $title
     */
    public function __construct($title = 'Admin PRANATA')
    {
        $this->title = $title;

        // Ambil data Kategori untuk sidebar secara otomatis
        $this->categories = Category::orderBy('name', 'asc')->get();
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.adminlayout');
    }
}
