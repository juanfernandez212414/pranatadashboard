import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/adminstyle.css', 'resources/css/pjstyle.css', 'resources/css/penggunastyle.css', 'resources/js/app.js', 'resources/js/admin.js', 'resources/js/pj.js', 'resources/js/pengguna.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],

    // TAMBAHKAN KODE INI DI SINI 👇
    build: {
        chunkSizeWarningLimit: 1600,
    },
});
