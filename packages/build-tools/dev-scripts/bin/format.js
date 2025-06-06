#!/usr/bin/env node
import path from 'node:path';
import chalk from 'chalk';
import prettier from 'prettier';
import ProjectScanner from '@sitchco/project-scanner';
import sitchcoPrettierConfig from '@sitchco/prettier-config' with { type: 'json' };
import { JsProcessor } from '../src/processors/js-processor.js';
import { SvgProcessor } from '../src/processors/svg-processor.js';
import { CssProcessor } from '../src/processors/css-processor.js';

async function loadProcessors(prettierConfig) {
    return [new JsProcessor(prettierConfig), new SvgProcessor(prettierConfig), new CssProcessor(prettierConfig)];
}

async function runFormat() {
    let totalFilesProcessed = 0;
    let totalFilesChanged = 0;
    let totalFilesErrored = 0;
    console.log(chalk.blue('[sitchco-format] Starting format process...'));

    try {
        const scanner = new ProjectScanner();
        const prettierConfig = (await prettier.resolveConfig(scanner.projectRoot)) || sitchcoPrettierConfig;
        const processors = await loadProcessors(prettierConfig);
        const supportedExtensions = processors.flatMap((p) => p.extensions);
        const filesToProcess = await scanner.findAllSourceFiles(supportedExtensions);
        if (!filesToProcess.length) {
            console.log(chalk.green('No files to format'));
            process.exit(0);
        }

        totalFilesProcessed = filesToProcess.length;
        console.log(chalk.blue(`Processing ${totalFilesProcessed} file(s)`));
        const results = await Promise.all(
            filesToProcess.map(async (filePath) => {
                try {
                    const processor = processors.find((p) => p.test(filePath));
                    if (!processor) {
                        console.log(chalk.yellow(`Skipping ${filePath} - no processor found`));
                        return {
                            changed: false,
                            error: null,
                        };
                    }

                    const result = await processor.processFile(filePath);
                    return {
                        changed: result.changed,
                        error: null,
                    };
                } catch (error) {
                    console.error(chalk.red(`\nError processing ${path.relative(scanner.projectRoot, filePath)}:`));
                    console.error(error.message);

                    if (error.stdout) {
                        console.error(chalk.grey(error.stdout));
                    }
                    if (error.stderr) {
                        console.error(chalk.grey(error.stderr));
                    }
                    return {
                        changed: false,
                        error,
                    };
                }
            })
        );
        results.forEach((result) => {
            if (result.error) {
                totalFilesErrored++;
            } else if (result.changed) {
                totalFilesChanged++;
            }
        });

        if (totalFilesChanged || totalFilesErrored) {
            console.log(chalk.blue('\n--- Format Summary ---'));
            console.log(chalk.blue(`Processed: ${totalFilesProcessed}`));

            if (totalFilesChanged) {
                console.log(chalk.green(`✅ Changed: ${totalFilesChanged}`));
            }
            if (totalFilesErrored) {
                console.log(chalk.red(`❌ Errors: ${totalFilesErrored}`));
            }
        }

        process.exit(totalFilesErrored > 0 ? 1 : 0);
    } catch (error) {
        console.error(chalk.red('\nFatal error:'), error);
        process.exit(1);
    }
}

runFormat();
