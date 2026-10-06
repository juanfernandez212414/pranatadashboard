<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Arahkan halaman bersama ke area rute milik role pengguna (lihat ArahkanKeAreaRole).
        $middleware->alias([
            'area.role' => \App\Http\Middleware\ArahkanKeAreaRole::class,
        ]);
        
        // Kecualikan rute logout dari pengecekan CSRF Token agar tidak muncul 419 saat sesi habis
        $middleware->validateCsrfTokens(except: [
            'logout',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, \Illuminate\Http\Request $request) {
            return redirect()->route('login')->with('error', 'Sesi Anda telah berakhir, silakan login kembali.');
        });
    })->create();
