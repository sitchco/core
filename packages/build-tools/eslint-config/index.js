import js from '@eslint/js';
import { defineConfig } from 'eslint/config';
import globals from 'globals';
import importPlugin from 'eslint-plugin-import';
import stylistic from '@stylistic/eslint-plugin';
import eslintConfigPrettier from 'eslint-config-prettier';
import babelParser from '@babel/eslint-parser';

export default defineConfig([
    {
        files: ['**/*.{js,mjs,cjs}'],
        languageOptions: {
            globals: {
                ...globals.browser,
                Sitchco: true,
            },
        },
    },
    {
        files: ['**/*.{js,mjs,cjs}'],
        plugins: { js },
        extends: ['js/recommended'],
    },
    eslintConfigPrettier,
    {
        files: ['**/*.{js,mjs,cjs}'],
        languageOptions: {
            parser: babelParser,
            parserOptions: {
                requireConfigFile: false,
                babelOptions: {
                    babelrc: false,
                    configFile: false,
                    presets: ['@babel/preset-env'],
                },
            },
        },
        plugins: {
            import: importPlugin,
            '@stylistic': stylistic,
        },
        ignores: ['node_modules/', 'dist/', '**/dist/', '.git/', 'vendor/', 'coverage/', 'logs/', '*.log'],
        rules: {
            'import/no-extraneous-dependencies': 'off',
            'no-prototype-builtins': 'off',
            '@stylistic/indent': ['error', 4, { SwitchCase: 1 }],
            '@stylistic/lines-between-class-members': ['error', 'always', { exceptAfterSingleLine: true }],
            '@stylistic/object-curly-newline': ['error', { minProperties: 2 }],
            '@stylistic/array-bracket-newline': ['error', { minItems: 2 }],
            curly: ['error', 'all'],
            '@stylistic/padding-line-between-statements': [
                'error',
                {
                    blankLine: 'always',
                    prev: '*',
                    next: ['multiline-block-like', 'block-like', 'if', 'export', 'function'],
                },
                {
                    blankLine: 'always',
                    prev: [
                        'multiline-block-like',
                        'block-like',
                        'multiline-expression',
                        'if',
                        'export',
                        'function',
                        'import',
                    ],
                    next: '*',
                },
                {
                    blankLine: 'never',
                    prev: ['const', 'let', 'var', 'if'],
                    next: ['if'],
                },
                {
                    blankLine: 'never',
                    prev: '*',
                    next: 'return',
                },
                {
                    blankLine: 'never',
                    prev: ['import'],
                    next: ['import'],
                },
            ],
        },
    },
]);
