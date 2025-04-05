import { build as viteBuild } from 'vite';
import path from 'node:path';
import chalk from 'chalk';
import { BASE_VITE_CONFIG } from './config.js';

function generateViteConfig(target, isWatchMode = false) {
    const config = JSON.parse(JSON.stringify(BASE_VITE_CONFIG));
    config.root = target.root;
    config.build = {
        ...config.build,
        outDir: target.outDir,
        base: `${path.relative(target.root, target.outDir)}/`,
        watch: isWatchMode ? {} : null,
        rollupOptions: {
            ...(config.build?.rollupOptions || {}),
            input: target.entryPoints,
        },
    };
    return config;
}

export async function runBuilds(targets, isWatchMode = false) {
    if (targets.length === 0) {
        console.log(chalk.yellow('No targets to build.'));
        return;
    }

    // We only expect one combined target now
    const target = targets[0];
    console.log(
        chalk.cyan(
            `\n🚀 Starting ${isWatchMode ? 'watch' : 'build'} with ${Object.keys(target.entryPoints).length} entry points...`
        )
    );

    try {
        const viteConfig = generateViteConfig(target, isWatchMode);
        await viteBuild(viteConfig);

        if (!isWatchMode) {
            console.log(chalk.cyan('\n✨ Build completed successfully!'));
        } else {
            console.log(chalk.cyan('\n👀 Watching all entry points...'));
        }
    } catch (error) {
        console.error(chalk.red('\n💥 Build failed:'));
        console.error(error);

        if (!isWatchMode) {
            process.exit(1);
        }
    }
}
