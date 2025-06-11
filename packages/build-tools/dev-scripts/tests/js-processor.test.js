import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { JsProcessor } from '../src/processors/js-processor.js';
import { minify } from 'terser';
import { ESLint } from 'eslint';
import prettier from 'prettier';
import fs from 'node:fs/promises';

// Mock dependencies
vi.mock('terser', () => ({
    minify: vi.fn(),
}));

vi.mock('eslint', () => ({
    ESLint: vi.fn(),
}));

vi.mock('prettier', () => ({
    default: {
        format: vi.fn(),
    },
}));

vi.mock('node:fs/promises', () => ({
    default: {
        readFile: vi.fn(),
        writeFile: vi.fn(),
    },
}));

vi.mock('@sitchco/eslint-config', () => ({
    default: { rules: {} },
}));

describe('JsProcessor', () => {
    let processor;
    const mockPrettierConfig = { tabWidth: 4, singleQuote: true };
    const mockFilePath = 'test.js';
    const mockContent = 'const test = "test";';
    const mockFormattedContent = 'const test = \'test\';';

    beforeEach(() => {
        // Reset mocks
        vi.clearAllMocks();

        // Setup mock implementations
        fs.readFile.mockResolvedValue(mockContent);
        fs.writeFile.mockResolvedValue();

        minify.mockResolvedValue({ code: mockContent });

        ESLint.mockImplementation(() => ({
            lintText: vi.fn().mockResolvedValue([{ output: mockContent }]),
        }));

        prettier.format.mockResolvedValue(mockFormattedContent);

        // Create processor instance
        processor = new JsProcessor(mockPrettierConfig);
    });

    afterEach(() => {
        vi.resetAllMocks();
    });

    describe('constructor', () => {
        it('should initialize with correct extensions', () => {
            expect(processor.extensions).toEqual(['.js', '.mjs']);
        });

        it('should initialize with correct name', () => {
            expect(processor.name).toBe('javascript');
        });

        it('should initialize ESLint with correct config', () => {
            expect(ESLint).toHaveBeenCalledWith({
                baseConfig: expect.any(Object),
                fix: true,
            });
        });

        describe('processFile', () => {
            it('should read the file content', async () => {
                await processor.processFile(mockFilePath);
                expect(fs.readFile).toHaveBeenCalledWith(mockFilePath, 'utf8');
            });

            it('should format the content with prettier', async () => {
                await processor.processFile(mockFilePath);
                expect(prettier.format).toHaveBeenCalledTimes(2);
                expect(prettier.format).toHaveBeenCalledWith(expect.any(String), {
                    ...mockPrettierConfig,
                    filepath: mockFilePath,
                });
            });

            it('should lint the content with ESLint', async () => {
                await processor.processFile(mockFilePath);
                expect(processor.eslint.lintText).toHaveBeenCalledWith(expect.any(String), {
                    filePath: mockFilePath,
                });
            });

            it('should write the file if content changed', async () => {
                await processor.processFile(mockFilePath);
                expect(fs.writeFile).toHaveBeenCalledWith(mockFilePath, mockFormattedContent, 'utf8');
            });

            it('should return changed: true if content changed', async () => {
                const result = await processor.processFile(mockFilePath);
                expect(result).toEqual({changed: true});
            });

            it('should not write the file if content did not change', async () => {
                // Setup to return the same content
                fs.readFile.mockResolvedValue(mockFormattedContent);
                prettier.format.mockResolvedValue(mockFormattedContent);

                const result = await processor.processFile(mockFilePath);
                expect(fs.writeFile).not.toHaveBeenCalled();
                expect(result).toEqual({changed: false});
            });
        });

        describe('test method (inherited from BaseProcessor)', () => {
            it('should return true for .js files', () => {
                expect(processor.test('file.js')).toBe(true);
            });

            it('should return true for .mjs files', () => {
                expect(processor.test('file.mjs')).toBe(true);
            });

            it('should return false for other file types', () => {
                expect(processor.test('file.css')).toBe(false);
                expect(processor.test('file.php')).toBe(false);
                expect(processor.test('file.html')).toBe(false);
            });
        });
    });
});
