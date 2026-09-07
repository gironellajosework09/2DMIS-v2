import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { copyFileSync, mkdirSync, readdirSync, statSync } from 'node:fs';
import { dirname, join } from 'node:path';

/*
 * Shared no-build IIFE components (DetailsPanel.js, FilterChips.js) are
 * authored under resources/js/components/ and served from the public
 * assets convention public/js/components/*.js via asset(). Vite does not
 * bundle them (they are plain ES5 IIFEs referenced directly by the views),
 * so we copy them verbatim into public/js/components/ on every build to
 * make delivery deterministic and resolve the historical public/js 404.
 */
function copySharedComponents() {
    const src = 'resources/js/components';
    const dest = 'public/js/components';

    function copyDir(from, to) {
        mkdirSync(to, { recursive: true });
        for (const entry of readdirSync(from)) {
            const s = join(from, entry);
            const d = join(to, entry);
            if (statSync(s).isDirectory()) copyDir(s, d);
            else copyFileSync(s, d);
        }
    }

    return {
        name: 'copy-shared-components',
        closeBundle() {
            copyDir(src, dest);
        },
    };
}

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
        copySharedComponents(),
    ],
});
