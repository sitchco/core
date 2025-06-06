import { minify } from 'terser';
import { ESLint } from 'eslint';
import prettier from 'prettier';
import eslintConfig from '@sitchco/eslint-config';
import fs from 'node:fs/promises';
import { BaseProcessor } from './base-processor.js';

export class JsProcessor extends BaseProcessor {
    extensions = ['.js', '.mjs'];

    name = 'javascript';
    constructor(prettierConfig) {
        super(prettierConfig);
        this.eslint = new ESLint({
            baseConfig: eslintConfig,
            fix: true,
        });

        this.terserOptions = {
            format: {
                comments: 'all',
                keep_numbers: true,
                ecma: 2022,
                quote_style: 3,
            },
            parse: {
                ecma: 2022,
                module: true,
            },
            mangle: false,
            compress: false,
        };
    }

    async processFile(filePath) {
        const originalContent = await fs.readFile(filePath, 'utf8');
        let content = originalContent;
        let changed = false;

        try {
            const terserResult = await minify(content, this.terserOptions);
            if (terserResult.error) {
                throw terserResult.error;
            }

            content = terserResult.code || content;
            content = content.replace(/(\n\/\*[\s\S]*?\*\/)/g, '$1\n').replace(/(\n\n)/g, '\n');
            content = await prettier.format(content, {
                ...this.prettierConfig,
                filepath: filePath,
            });

            const results = await this.eslint.lintText(content, { filePath });
            const output = results[0]?.output;
            content = output || content;
            content = await prettier.format(content, {
                ...this.prettierConfig,
                filepath: filePath,
            });

            if (content !== originalContent) {
                await fs.writeFile(filePath, content, 'utf8');
                changed = true;
            }
            return { changed };
        } catch (error) {
            error.message = `JS processing failed: ${error.message}`;
            throw error;
        }
    }
}
