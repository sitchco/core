import path from 'node:path';
import chalk from 'chalk';
import ProjectScanner, { PLATFORM_UNIT_TYPES } from '@sitchco/project-scanner';
import { DIST_FOLDER } from './config.js';

/**
 * Finds asset targets for the build process.
 * @param {object} options - Options for finding asset targets
 * @param {boolean} [options.verbose=false] - Whether to log verbose information
 * @returns {Promise<object>} A promise resolving to the asset targets
 */
export async function findAssetTargets(options = {}) {
    const verbose = options.verbose || false;
    const service = new ProjectScanner({ verbose });
    const { projectRoot } = service;
    const webRoot = await service.getWebRoot();
    
    // Get platform units and modules
    const platformUnits = await service.getPlatformUnits();
    const moduleRoots = await service.getModuleDirs();
    const entryPoints = await service.getEntrypoints();
    
    if (verbose) {
        console.log(chalk.blue('[ModuleBuilder]'), `Found ${platformUnits.length} platform units`);
        console.log(chalk.blue('[ModuleBuilder]'), `Found ${moduleRoots.length} module directories`);
        console.log(chalk.blue('[ModuleBuilder]'), `Found ${entryPoints.length} entry points`);
    }
    
    if (moduleRoots.length === 0 || entryPoints.length === 0) {
        if (verbose) {
            console.log(chalk.yellow('[ModuleBuilder] No modules or entry points found. Nothing to build.'));
        }
        return [];
    }

    // Group platform units by type for easier reference
    const unitsByType = {
        [PLATFORM_UNIT_TYPES.THEME]: platformUnits.filter(unit => unit.type === PLATFORM_UNIT_TYPES.THEME),
        [PLATFORM_UNIT_TYPES.PLUGIN]: platformUnits.filter(unit => unit.type === PLATFORM_UNIT_TYPES.PLUGIN),
        [PLATFORM_UNIT_TYPES.MU_PLUGIN]: platformUnits.filter(unit => unit.type === PLATFORM_UNIT_TYPES.MU_PLUGIN),
    };
    
    if (verbose) {
        console.log(chalk.blue('[ModuleBuilder]'), `Platform units by type:`);
        console.log(chalk.blue('[ModuleBuilder]'), `- Themes: ${unitsByType[PLATFORM_UNIT_TYPES.THEME].length}`);
        console.log(chalk.blue('[ModuleBuilder]'), `- Plugins: ${unitsByType[PLATFORM_UNIT_TYPES.PLUGIN].length}`);
        console.log(chalk.blue('[ModuleBuilder]'), `- MU Plugins: ${unitsByType[PLATFORM_UNIT_TYPES.MU_PLUGIN].length}`);
    }

    // Validate platform units
    const validationResults = await service.validatePlatformUnits();
    const invalidUnits = validationResults.filter(result => !result.isValid);
    
    if (invalidUnits.length > 0) {
        console.log(chalk.yellow('[ModuleBuilder] Warning: Some platform units have validation issues:'));
        for (const result of invalidUnits) {
            console.log(chalk.yellow(`- ${result.unit.name} (${result.unit.type}):`));
            for (const message of result.messages) {
                console.log(chalk.yellow(`  - ${message}`));
            }
        }
    }

    // Configure build targets
    const publicDirRelative = path.relative(projectRoot, webRoot).replace(/\\/g, '/');
    const outDirAbsolute = path.join(projectRoot, DIST_FOLDER);
    const buildDirRelative = path.relative(webRoot, outDirAbsolute).replace(/\\/g, '/');
    const inputPaths = entryPoints.map((fullPath) => path.relative(projectRoot, fullPath));
    const hotFileAbsolute = path.join(outDirAbsolute, 'hot');
    const hotFileRelative = path.relative(projectRoot, hotFileAbsolute).replace(/\\/g, '/');
    const refreshPaths = [`${projectRoot}/**/*.php`];
    
    return {
        root: projectRoot,
        outDir: outDirAbsolute,
        viteInput: inputPaths,
        vitePublicDir: publicDirRelative,
        viteBuildDir: buildDirRelative,
        viteHotFile: hotFileRelative,
        viteRefreshPaths: refreshPaths,
        platformUnits,
        moduleRoots,
    };
}
