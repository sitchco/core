import path from 'node:path';
import laravel from 'laravel-vite-plugin';

export const DIST_FOLDER = 'dist';

export const BASE_VITE_CONFIG = {
    build: {
        manifest: true,
        sourcemap: true,
        emptyOutDir: false,
    },
    plugins: []
};

export async function generateViteConfig(target, isWatchMode) {
    process.env.APP_URL = process.env.DDEV_PRIMARY_URL;
    return {
        root: target.root,
        base: isWatchMode ? '/' : './',
        plugins: [
            laravel({
                input: target.viteInput,
                publicDirectory: target.vitePublicDir,
                buildDirectory: target.viteBuildDir,
                hotFile: target.viteHotFile,
                refresh: target.viteRefreshPaths,
            }),
            ...(BASE_VITE_CONFIG.plugins || []),
        ],
        build: {
            ...BASE_VITE_CONFIG.build,
            outDir: path.relative(target.root, target.outDir),
            manifest: true,
            watch: isWatchMode ? {} : null,
        },
        clearScreen: false,
    //     server: {
    //         host: '0.0.0.0',
    //         port: 5173,
    //         strictPort: true,
    //         origin: `${process.env.DDEV_PRIMARY_URL.replace(/:\d+$/, "")}:5173`,
    //         cors: {
    //             origin: new RegExp(
    //     `https?:\/\/(${process.env.DDEV_HOSTNAME.split(",")
    //         .map((h) => h.replace("*", "[^.]+"))
    //         .join("|")})(?::\\d+)?$`
    // ),
    //         },
    //     }
    };
}
