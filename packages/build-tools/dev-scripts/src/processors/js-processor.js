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
    }

    async processFile(filePath) {
        const originalContent = await fs.readFile(filePath, 'utf8');
        let content = originalContent;
        let changed = false;

        try {
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
