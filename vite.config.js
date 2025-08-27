import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/filament/admin/theme.css',
                'resources/css/filament/advisor/theme.css',
                'resources/css/filament/municipality/theme.css',
                'resources/css/filament/organiser/theme.css',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
