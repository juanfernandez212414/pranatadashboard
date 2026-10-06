<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Models\Category;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Menggunakan array untuk mengirim data ke 3 layout sekaligus
        View::composer(
            [
                'components.adminlayout',
                'components.penanggungjawablayout',
                'components.penggunalayout'
            ],
            function ($view) {
                $view->with('categories', Category::all());
            }
        );
    }
}
