import { glob } from 'glob';
import path from 'node:path';
import fs from 'node:fs/promises';
import chalk from 'chalk';
import PlatformUnitDetector from './platform-unit-detector.js';

/**
 * Configuration constants for project structure scanning.
 */
export const MODULE_MARKER_FILE = '.sitchco-module';

export const ASSETS_FOLDER = 'assets';

export const BLOCKS_FOLDER = 'blocks';

export const SCRIPTS_SUBFOLDER = 'scripts';

export const STYLES_SUBFOLDER = 'styles';

export const PLATFORM_UNIT_TYPES = {
    THEME: 'theme',
    PLUGIN: 'plugin',
    MU_PLUGIN: 'mu-plugin',
};

/**
 * Scans a project structure to discover WordPress platform units (themes, plugins, mu-plugins),
 * Sitchco modules, their asset directories, entry points (JS/SCSS), and source files by extension.
 * Caches discovered paths for efficiency.
 */
export default class ProjectScanner {
    /** @type {string} The absolute path to the project root. */
    projectRoot;
    /** @type {string[]} Glob patterns to ignore during scanning. */
    ignorePatterns;
    /** @type {string[] | null} Cached array of module directory paths. */
    _moduleDirs = null;
    /** @type {string[] | null} Cached array of entry point file paths. */
    _entrypoints = null;
    /** @type {string | null} Cached absolute path to the WordPress web root. */
    _webRoot = null;
    /** @type {object[] | null} Cached array of platform units. */
    _platformUnits = null;
    /** @type {boolean} Whether to log verbose information. */
    verbose;
    static DEFAULT_IGNORE = ['**/node_modules/**', '**/.git/**', '**/vendor/**', '**/dist/**', '**/build/**'];

    /**
     * Creates an instance of ProjectScanner.
     * @param {object} [options={}] - Configuration options.
     * @param {string} [options.projectRoot=process.cwd()] - The root directory of the project to scan.
     * @param {string[]} [options.ignorePatterns=ProjectScanner.DEFAULT_IGNORE] - Glob patterns to ignore.
     * @param {boolean} [options.verbose=false] - Whether to log verbose information.
     */
    constructor(options = {}) {
        this.projectRoot = path.resolve(options.projectRoot || process.cwd());
        this.ignorePatterns = options.ignorePatterns || ProjectScanner.DEFAULT_IGNORE;
        this.verbose = options.verbose || false;
    }

    /**
     * Checks if a directory exists.
     * @param {string} path - The path to check.
     * @returns {Promise<boolean>} True if the path is an existing directory.
     * @private
     * @static
     */
    static async _pathExists(path) {
        try {
            await fs.access(path);
            return true;
        } catch (error) {
            if (error.code === 'ENOENT') {
                return false;
            }

            throw error;
        }
    }

    /**
     * Scans the project for module directories (containing `.sitchco-module`).
     * @returns {Promise<string[]>} A promise resolving to an array of absolute module directory paths.
     * @private
     */
    async _scanForModuleDirs() {
        const markerPattern = `**/${MODULE_MARKER_FILE}`;
        const markerFiles = await glob(markerPattern, {
            cwd: this.projectRoot,
            absolute: true,
            dot: true,
            ignore: this.ignorePatterns,
        });
        return markerFiles.map((markerPath) => path.dirname(markerPath));
    }

    /**
     * Gets the list of module directories, using cache if available.
     * @returns {Promise<string[]>} A promise resolving to an array of absolute module directory paths.
     */
    async getModuleDirs() {
        if (this._moduleDirs === null) {
            this._moduleDirs = await this._scanForModuleDirs();
        }
        return this._moduleDirs;
    }

    /**
     * Scans module directories for entry points (JS/MJS/SCSS files).
     * It looks in the module root, module assets/scripts|styles folders,
     * block roots (within the blocks folder), and block assets/scripts|styles folders.
     * @returns {Promise<string[]>} A promise resolving to a flat array of absolute entry point file paths.
     * @private
     */
    async _scanForEntrypoints() {
        const moduleDirs = await this.getModuleDirs();
        if (!moduleDirs.length) {
            return [];
        }

        const entrypointPatterns = [
            '*.{js,mjs,scss}',
            `${ASSETS_FOLDER}/{${SCRIPTS_SUBFOLDER},${STYLES_SUBFOLDER}}/*.{js,mjs,scss}`,
            `${BLOCKS_FOLDER}/*/*.{js,mjs,scss}`,
            `${BLOCKS_FOLDER}/*/${ASSETS_FOLDER}/{${SCRIPTS_SUBFOLDER},${STYLES_SUBFOLDER}}/*.{js,mjs,scss}`,
        ];
        const promises = moduleDirs.map(
            async (moduleDir) =>
                await glob(entrypointPatterns, {
                    cwd: moduleDir,
                    absolute: true,
                    nodir: true,
                    ignore: this.ignorePatterns,
                })
        );
        const results = await Promise.all(promises);
        const allEntrypoints = results.flat();
        return [...new Set(allEntrypoints)];
    }

    /**
     * Gets the list of all entry point files (JS/MJS/SCSS) found in standard locations
     * across all module directories, using cache if available.
     * (Locations include module root, module assets, block roots, block assets).
     * @returns {Promise<string[]>} A promise resolving to a flat array of absolute entry point file paths.
     */
    async getEntrypoints() {
        if (this._entrypoints === null) {
            this._entrypoints = await this._scanForEntrypoints();
        }
        return this._entrypoints;
    }

    /**
     * Scans upwards from the project root to find the WordPress web root
     * (the directory containing 'wp-content').
     * @returns {Promise<string>} A promise resolving to the absolute path of the web root.
     * @throws {Error} If the web root cannot be found.
     * @private
     */
    async _scanForWebRoot() {
        let currentDir = path.normalize(this.projectRoot);
        const root = path.parse(currentDir).root;

        while (currentDir !== root) {
            const wpContentPath = path.join(currentDir, 'wp-content');
            if (await ProjectScanner._pathExists(wpContentPath)) {
                return currentDir;
            }

            currentDir = path.dirname(currentDir);
        }
        return path.resolve(this.projectRoot, '../../..');
    }

    /**
     * Gets the WordPress web root directory path, using cache if available.
     * @returns {Promise<string>} A promise resolving to the absolute web root path.
     */
    async getWebRoot() {
        if (this._webRoot === null) {
            this._webRoot = await this._scanForWebRoot();
        }
        return this._webRoot;
    }

    /**
     * Finds all source files within the identified module directories (not limited to asset directories)
     * matching the specified extensions. This method does NOT use caching as extensions can vary per call.
     *
     * @param {string[]} extensions - An array of file extensions to find (e.g., ['.php', '.json', '.svg']).
     * @returns {Promise<string[]>} A promise resolving to a flat array of absolute source file paths matching the extensions.
     */
    async findModuleSourceFiles(extensions = []) {
        if (!extensions || extensions.length === 0) {
            return [];
        }

        const moduleDirs = await this.getModuleDirs();
        if (!moduleDirs.length) {
            return [];
        }

        const extGroupPattern = `{${extensions.map((ext) => (ext.startsWith('.') ? ext.substring(1) : ext)).join(',')}}`;
        const findPattern = `**/*.${extGroupPattern}`;
        const promises = moduleDirs.map(async (moduleDir) => {
            const files = await glob(findPattern, {
                cwd: moduleDir,
                absolute: true,
                nodir: true,
                dot: true,
                ignore: this.ignorePatterns,
            });
            return files;
        });
        const results = await Promise.all(promises);
        const allSourceFiles = results.flat();
        return [...new Set(allSourceFiles)];
    }

    /**
     * Finds all source files within the entire project (starting from project root)
     * matching the specified extensions. This method does NOT use caching as extensions can vary per call.
     *
     * @param {string[]} extensions - An array of file extensions to find (e.g., ['.php', '.json', '.svg']).
     * @returns {Promise<string[]>} A promise resolving to a flat array of absolute source file paths matching the extensions.
     */
    async findAllSourceFiles(extensions = []) {
        if (!extensions || extensions.length === 0) {
            return [];
        }

        const extGroupPattern = `{${extensions.map((ext) => (ext.startsWith('.') ? ext.substring(1) : ext)).join(',')}}`;
        const findPattern = `**/*.${extGroupPattern}`;
        const files = await glob(findPattern, {
            cwd: this.projectRoot,
            absolute: true,
            nodir: true,
            dot: true,
            ignore: this.ignorePatterns,
        });
        return [...new Set(files)];
    }

    /**
     * Finds all build artifact directories (dist, .vite) within the project root.
     * This method does NOT use caching.
     * @returns {Promise<string[]>} A promise resolving to a flat array of absolute artifact directory paths.
     */
    async getBuildArtifacts() {
        const artifactPatterns = ['**/dist', '**/.vite'];
        const filteredIgnorePatterns = this.ignorePatterns.filter(
            (pattern) => !pattern.includes('dist') && !pattern.includes('.vite')
        );
        const artifactDirs = await glob(artifactPatterns, {
            cwd: this.projectRoot,
            absolute: true,
            onlyDirectories: true,
            dot: true,
            ignore: filteredIgnorePatterns,
        });
        return [...new Set(artifactDirs)];
    }

    /**
     * Removes all found build artifact directories (dist, .vite).
     * @returns {Promise<void>} A promise that resolves when deletion is complete.
     */
    async cleanBuildArtifacts() {
        const artifactDirs = await this.getBuildArtifacts();
        if (!artifactDirs.length) {
            return;
        }

        const promises = artifactDirs.map(async (dirPath) => {
            try {
                await fs.rm(dirPath, {
                    recursive: true,
                    force: true,
                });
            } catch (error) {
                console.error(`[ProjectScanner] Error removing directory ${dirPath}:`, error);
            }
        });
        await Promise.all(promises);
    }

    /**
     * Clears the internal cache for module directories, asset directories, entrypoints, and platform units.
     * Subsequent calls to getters will trigger a fresh scan.
     */
    clearCache() {
        this._moduleDirs = null;
        this._entrypoints = null;
        this._webRoot = null;
        this._platformUnits = null;
    }

    /**
     * Scans the project for platform units (themes, plugins, mu-plugins).
     * @returns {Promise<object[]>} A promise resolving to an array of platform unit objects.
     * @private
     */
    async _scanForPlatformUnits() {
        const webRoot = await this.getWebRoot();
        const detector = new PlatformUnitDetector({
            webRoot,
            verbose: this.verbose,
        });

        console.log('Scanning for platform units...');
        const units = await detector.detectAllUnits();
        
        // Log the detected units
        if (this.verbose) {
            const unitsByType = {
                [PLATFORM_UNIT_TYPES.THEME]: units.filter(unit => unit.type === PLATFORM_UNIT_TYPES.THEME),
                [PLATFORM_UNIT_TYPES.PLUGIN]: units.filter(unit => unit.type === PLATFORM_UNIT_TYPES.PLUGIN),
                [PLATFORM_UNIT_TYPES.MU_PLUGIN]: units.filter(unit => unit.type === PLATFORM_UNIT_TYPES.MU_PLUGIN),
            };
            
            console.log(chalk.blue('[ProjectScanner]'), `Detected ${units.length} platform units:`);
            console.log(chalk.blue('[ProjectScanner]'), `- ${unitsByType[PLATFORM_UNIT_TYPES.THEME].length} themes`);
            console.log(chalk.blue('[ProjectScanner]'), `- ${unitsByType[PLATFORM_UNIT_TYPES.PLUGIN].length} plugins`);
            console.log(chalk.blue('[ProjectScanner]'), `- ${unitsByType[PLATFORM_UNIT_TYPES.MU_PLUGIN].length} mu-plugins`);
        }

        return units;
    }

    /**
     * Gets the list of platform units, using cache if available.
     * @returns {Promise<object[]>} A promise resolving to an array of platform unit objects.
     */
    async getPlatformUnits() {
        if (this._platformUnits === null) {
            this._platformUnits = await this._scanForPlatformUnits();
        }
        return this._platformUnits;
    }

    /**
     * Gets platform units of a specific type.
     * @param {string} type - The type of platform units to get (theme, plugin, mu-plugin).
     * @returns {Promise<object[]>} A promise resolving to an array of platform units of the specified type.
     */
    async getPlatformUnitsByType(type) {
        const units = await this.getPlatformUnits();
        return units.filter(unit => unit.type === type);
    }

    /**
     * Gets a platform unit by name.
     * @param {string} name - The name of the platform unit to get.
     * @returns {Promise<object|null>} A promise resolving to the platform unit object, or null if not found.
     */
    async getPlatformUnitByName(name) {
        const units = await this.getPlatformUnits();
        return units.find(unit => unit.name === name) || null;
    }

    /**
     * Validates all platform units against their requirements.
     * @returns {Promise<object[]>} A promise resolving to an array of validation results.
     */
    async validatePlatformUnits() {
        const units = await this.getPlatformUnits();
        const detector = new PlatformUnitDetector({ webRoot: await this.getWebRoot() });
        
        return units.map(unit => {
            const validation = detector.validateUnit(unit);
            return {
                unit,
                ...validation,
            };
        });
    }
}

export { ProjectScanner };
