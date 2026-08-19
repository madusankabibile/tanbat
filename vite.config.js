import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/home.js',
                'resources/js/profile.js',
                'resources/js/search.js',
                'resources/js/article-create.js',
                'resources/js/messenger.js',
                'resources/js/share-module.js',
                'resources/js/sidebars.js',
                'resources/js/people.js',
                'resources/js/blog.js',
                'resources/js/user-deleted.js',
                'resources/js/assistant.js',
                'resources/js/books.js',
                'resources/js/book-show.js',
                'resources/js/tv-player.js',
                'resources/js/runtime-check.js',
            ],
            refresh: true,
        }),
    ],
});
