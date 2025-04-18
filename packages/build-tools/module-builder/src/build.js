import { build as viteBuild } from 'vite';
import path from 'node:path';
import chalk from 'chalk';
import laravel from 'laravel-vite-plugin';
import { BASE_VITE_CONFIG } from './config.js';

async function generateViteConfig(target, isWatchMode) {
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
        server: {},
    };
}

export async function runBuild(target, isWatchMode) {
    if (!target) {
        console.log(chalk.yellow(`No targets found. Nothing to build.`));
        return;
    }

    console.log(
        chalk.cyan(
            `\n🚀 Starting ${isWatchMode ? 'watch' : 'build'} with ${Object.keys(target.viteInput).length} entry points...`
        )
    );

    try {
        const viteConfig = await generateViteConfig(target, isWatchMode);
        await viteBuild(viteConfig);

        if (isWatchMode) {
            console.log(chalk.cyan(`\n👀 Watching...`));
            const hotFileAbsolute = path.join(target.root, target.viteHotFile);
            console.log(chalk.grey(`   Hot file: ${hotFileAbsolute}`));
            console.log(
                chalk.yellow(`   Ensure PHP enqueuer checks for existence of the hot file above to load dev assets.`)
            );
        }
    } catch (error) {
        console.error(error);

        if (!isWatchMode) {
            process.exit(1);
        }
    }
}
