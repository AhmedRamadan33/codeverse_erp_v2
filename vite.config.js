import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';

export default defineConfig({
    plugins: [
        laravel({
            // One stylesheet per text direction; the layout picks one by locale.
            input: ['resources/css/app-ltr.css', 'resources/css/app-rtl.css', 'resources/js/app.js'],
            refresh: ['resources/views/**', 'Modules/*/resources/views/**'],
            fonts: [
                bunny('Cairo', {
                    weights: [400, 600, 700],
                }),
            ],
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
