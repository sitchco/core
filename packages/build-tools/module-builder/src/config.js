export { MODULE_MARKER_FILE, ASSETS_FOLDER, BLOCKS_FOLDER } from '@sitchco/project-scanner';

export const DIST_FOLDER = 'dist';

export const BASE_VITE_CONFIG = {
    build: {
        manifest: true,
        sourcemap: true,
        emptyOutDir: false,
        rollupOptions: {
            output: {
                entryFileNames: '[name]-[hash].js',
                assetFileNames: '[name]-[hash][extname]',
                chunkFileNames: 'chunks/[name]-[hash].js',
            },
        },
    },
};
