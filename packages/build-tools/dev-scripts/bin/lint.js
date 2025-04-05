#!/usr/bin/env node
import { execa } from 'execa';
import chalk from 'chalk';
import path from 'node:path';
import ProjectScanner from '@sitchco/project-scanner';

const eslintBin = 'eslint';
// Capture arguments passed to `sitchco-lint` (e.g., "--fix", "--ext", ".js,.mjs")
const args = process.argv.slice(2);
// Separate flags (like --fix) from potential positional arguments (which we will ignore)
const eslintFlags = args.filter((arg) => arg.startsWith('--'));

async function runLint() {
    const projectRoot = process.env.INIT_CWD || process.cwd();
    const relPath = path.relative('..', projectRoot);
    console.log(chalk.blue(`Scanning for modules in: ${relPath}/*`));

    try {
        const scanner = new ProjectScanner({ projectRoot });
        const modules = await scanner.getModuleDirs();
        const jsFilesToLint = await scanner.findModuleSourceFiles(['.js', '.mjs']);
        if (modules.length === 0) {
            console.log(chalk.yellow('No module roots found. Nothing to lint.'));
            process.exit(0);
        }
        if (!jsFilesToLint.length) {
            console.log(chalk.green('No JavaScript files found to lint.'));
            process.exit(0);
        }

        // --- End Scanner Usage ---
        console.log(chalk.blue(`Running ESLint on ${jsFilesToLint.length} file(s)...`));
        // Execute eslint using execa, passing flags and the explicit file list
        const { exitCode } = await execa(eslintBin, [...eslintFlags, ...jsFilesToLint], {
            stdio: 'inherit',
            cwd: projectRoot,
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
