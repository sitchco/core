#!/usr/bin/env node
import { Command } from 'commander';
import chalk from 'chalk';
import { createRequire } from 'module';
import { findAssetTargets, runBuild, cleanBuildArtifacts } from '../src/index.js';

const require = createRequire(import.meta.url);
const pkg = require('../package.json');

function createCommand(name, description, isWatchMode) {
    return new Command(name)
        .description(description)
        .option('-v, --verbose', 'Enable verbose logging')
        .action(async (options) => {
            console.log(chalk.cyan(`Running ${pkg.name} v${pkg.version}${isWatchMode ? ' in dev mode' : ''}`));
            
            if (options.verbose) {
                console.log(chalk.blue('[ModuleBuilder]'), 'Verbose mode enabled');
            }
            
            await cleanBuildArtifacts();

            if (name === 'clean') {
                return;
            }

            try {
                const targets = await findAssetTargets({ verbose: options.verbose });
                
                if (!targets || (Array.isArray(targets) && targets.length === 0)) {
                    console.log(chalk.yellow('No build targets found. Nothing to build.'));
                    return;
                }
                
                await runBuild(targets, isWatchMode, { verbose: options.verbose });
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
