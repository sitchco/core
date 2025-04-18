#!/usr/bin/env node
import { Command } from 'commander';
import chalk from 'chalk';
import { createRequire } from 'module';
import { findAssetTargets, runBuild, cleanBuildArtifacts } from '../src/index.js';

const require = createRequire(import.meta.url);
const pkg = require('../package.json');

function createCommand(name, description, isWatchMode) {
    return new Command(name).description(description).action(async () => {
        console.log(chalk.cyan(`Running ${pkg.name} v${pkg.version}${isWatchMode ? ' in dev mode' : ''}`));
        await cleanBuildArtifacts();

        if (name === 'clean') {
            return;
        }

        try {
            const targets = await findAssetTargets();
            await runBuild(targets, isWatchMode);
        } catch (error) {
            console.error(chalk.red(`${name} process encountered an error:`), error);
            process.exit(1);
        }
    });
}

const program = new Command()
    .name('module-builder')
    .description(pkg.description)
    .version(pkg.version)
    .addCommand(createCommand('clean', 'Clean all build artifacts', false))
    .addCommand(createCommand('build', 'Build all module/block assets for production', false))
    .addCommand(createCommand('dev', 'Watch module/block assets and rebuild on changes', true));
program.parse(process.argv);

if (!process.argv.slice(2).length) {
    program.outputHelp();
}
