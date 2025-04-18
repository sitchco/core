#!/usr/bin/env node
import { execa } from 'execa';
import chalk from 'chalk';
import path from 'node:path';
import ProjectScanner from '@sitchco/project-scanner';

const eslintBin = 'eslint';
const args = process.argv.slice(2);
const eslintFlags = args.filter((arg) => arg.startsWith('--'));

async function runLint() {
    const scanner = new ProjectScanner();
    const relPath = path.relative('..', scanner.projectRoot);
    console.log(chalk.blue(`Scanning for modules in: ${relPath}/*`));

    try {
        const modules = await scanner.getModuleDirs();
        const jsFilesToLint = await scanner.findAllSourceFiles(['.js', '.mjs']);
        if (modules.length === 0) {
            console.log(chalk.yellow('No module roots found. Nothing to lint.'));
            process.exit(0);
        }
        if (!jsFilesToLint.length) {
            console.log(chalk.green('No JavaScript files found to lint.'));
            process.exit(0);
        }

        console.log(chalk.blue(`Running ESLint on ${jsFilesToLint.length} file(s)...`));
        const { exitCode } = await execa(eslintBin, [...eslintFlags, ...jsFilesToLint], {
            stdio: 'inherit',
            cwd: scanner.projectRoot,
            preferLocal: true,
            reject: false,
        });
        process.exit(exitCode);
    } catch (error) {
        console.error(chalk.red('An error occurred:'), error);
        process.exit(1);
    }
}

runLint();
