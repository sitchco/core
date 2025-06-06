#!/usr/bin/env node
import { Command } from 'commander';
import chalk from 'chalk';
import { createRequire } from 'module';
import { findAssetTargets, runBuild, runDev, cleanBuildArtifacts } from '../src/index.js';

const _require = createRequire(import.meta.url);
const pkg = _require('../package.json');

function createCommand(name, description, callback) {
    return new Command(name).description(description).action(async () => {
        console.log(chalk.cyan(`Running ${pkg.name} v${pkg.version}`));
        try {
            await callback();
        } catch (error) {
            console.error(chalk.red(`${name} process encountered an error:`), error);
            //process.exit(1);
        }
    });
}

async function build() {
    await cleanBuildArtifacts();
    const targets = await findAssetTargets();
    await runBuild(targets);
}

async function dev() {
    await cleanBuildArtifacts();
    const targets = await findAssetTargets();
    await runDev(targets);
}

const program = new Command()
    .name('module-builder')
    .description(pkg.description)
    .version(pkg.version)
    .addCommand(createCommand('clean', 'Clean all build artifacts', cleanBuildArtifacts))
    .addCommand(createCommand('build', 'Build all module/block assets for production', build))
    .addCommand(createCommand('dev', 'Watch module/block assets and rebuild on changes', dev));
program.parse(process.argv);

if (!process.argv.slice(2).length) {
    program.outputHelp();
}
