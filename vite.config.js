import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [vue(), tailwindcss()],
    publicDir: false,
    build: {
        outDir: 'public/assets',
        emptyOutDir: true,
        sourcemap: false,
        rollupOptions: {
            input: 'frontend/main.js',
            output: {
                entryFileNames: 'app.js',
                chunkFileNames: 'chunks/[name].js',
                assetFileNames: (assetInfo) => {
                    if (assetInfo.name && assetInfo.name.endsWith('.css')) {
                        return 'app.css';
                    }

                    return 'assets/[name][extname]';
                },
            },
        },
    },
});
