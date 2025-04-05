import path from 'node:path';
import chalk from 'chalk';
import ProjectScanner from '@sitchco/project-scanner';
import { DIST_FOLDER } from './config.js';

export async function findAssetTargets() {
    const service = new ProjectScanner();
    const { projectRoot } = service;
    const moduleRoots = await service.getModuleDirs();
    const entryPoints = await service.getEntrypoints();
    const combinedEntries = {};
    if (moduleRoots.length === 0) {
        console.warn(chalk.yellow('No module roots found'));
        return [];
    }

    // Process all discovered entry points
    for (const entryPoint of entryPoints) {
        const relPath = path.relative(projectRoot, entryPoint);
        const distKey = relPath.replace(/assets(\/|\\)/, 'dist$1').replace(/\.[^/.]+$/, '');
        combinedEntries[distKey] = entryPoint;
    }

    if (Object.keys(combinedEntries).length === 0) {
        console.warn(chalk.yellow('No asset targets with entry points found.'));
        return [];
    }

    console.log(chalk.green(`Discovered ${Object.keys(combinedEntries).length} entry points across all modules.`));
    return [
        {
            name: 'combined',
            root: projectRoot,
            outDir: path.join(projectRoot, DIST_FOLDER),
            entryPoints: combinedEntries,
        },
    ];
}
