import { build as viteBuild } from 'vite';
import path from 'node:path';
import chalk from 'chalk';
import laravel from 'laravel-vite-plugin';
import { BASE_VITE_CONFIG } from './config.js';
import { PLATFORM_UNIT_TYPES } from '@sitchco/project-scanner';

/**
 * Generates a Vite configuration for the build.
 * @param {object} target - The build target
 * @param {boolean} isWatchMode - Whether to run in watch mode
 * @returns {object} The Vite configuration
 */
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

/**
 * Logs information about the platform units and modules.
 * @param {object} target - The build target
 */
function logPlatformInfo(target) {
    if (!target.platformUnits || !target.moduleRoots) {
        return;
    }

    const unitsByType = {
        [PLATFORM_UNIT_TYPES.THEME]: target.platformUnits.filter(unit => unit.type === PLATFORM_UNIT_TYPES.THEME),
        [PLATFORM_UNIT_TYPES.PLUGIN]: target.platformUnits.filter(unit => unit.type === PLATFORM_UNIT_TYPES.PLUGIN),
        [PLATFORM_UNIT_TYPES.MU_PLUGIN]: target.platformUnits.filter(unit => unit.type === PLATFORM_UNIT_TYPES.MU_PLUGIN),
    };

    console.log(chalk.cyan('\n📦 Platform Units:'));
    console.log(chalk.cyan(`   Themes (${unitsByType[PLATFORM_UNIT_TYPES.THEME].length}):`));
    unitsByType[PLATFORM_UNIT_TYPES.THEME].forEach(theme => {
        console.log(chalk.grey(`   - ${theme.name}${theme.hasModuleMarker ? ' (has module marker)' : ''}`));
    });

    console.log(chalk.cyan(`   Plugins (${unitsByType[PLATFORM_UNIT_TYPES.PLUGIN].length}):`));
    unitsByType[PLATFORM_UNIT_TYPES.PLUGIN].forEach(plugin => {
        console.log(chalk.grey(`   - ${plugin.name}${plugin.hasModuleMarker ? ' (has module marker)' : ''}`));
    });

    console.log(chalk.cyan(`   MU Plugins (${unitsByType[PLATFORM_UNIT_TYPES.MU_PLUGIN].length}):`));
    unitsByType[PLATFORM_UNIT_TYPES.MU_PLUGIN].forEach(muPlugin => {
        console.log(chalk.grey(`   - ${muPlugin.name}${muPlugin.hasModuleMarker ? ' (has module marker)' : ''}`));
    });

    console.log(chalk.cyan(`\n📂 Module Directories (${target.moduleRoots.length}):`));
    target.moduleRoots.forEach(moduleDir => {
        console.log(chalk.grey(`   - ${path.relative(target.root, moduleDir)}`));
    });
}

/**
 * Runs the build process.
 * @param {object} target - The build target
 * @param {boolean} isWatchMode - Whether to run in watch mode
 * @param {object} options - Additional options
 * @param {boolean} [options.verbose=false] - Whether to log verbose information
 * @returns {Promise<void>} A promise that resolves when the build is complete
 */
export async function runBuild(target, isWatchMode, options = {}) {
    const verbose = options.verbose || false;
    
    if (!target) {
        console.log(chalk.yellow(`No targets found. Nothing to build.`));
        return;
    }

    console.log(
        chalk.cyan(
            `\n🚀 Starting ${isWatchMode ? 'watch' : 'build'} with ${target.viteInput.length} entry points...`
        )
    );

    // Log platform units and modules if verbose
    if (verbose) {
        logPlatformInfo(target);
    }

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
