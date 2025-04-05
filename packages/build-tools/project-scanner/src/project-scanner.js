import { glob } from 'glob';
import path from 'node:path';
import fs from 'node:fs/promises';

/**
 * Configuration constants for project structure scanning.
 */
export const MODULE_MARKER_FILE = '.sitchco-module';

export const ASSETS_FOLDER = 'assets';

export const BLOCKS_FOLDER = 'blocks';

export const SCRIPTS_SUBFOLDER = 'scripts';

export const STYLES_SUBFOLDER = 'styles';

/**
 * Scans a project structure to discover Sitchco modules, their asset directories,
 * entry points (JS/SCSS), and source files by extension. Caches discovered paths
 * for efficiency.
 */
export default class ProjectScanner {
    /** @type {string} The absolute path to the project root. */
    projectRoot;
    /** @type {string[]} Glob patterns to ignore during scanning. */
    ignorePatterns;
    /** @type {string[] | null} Cached array of module directory paths. */
    _moduleDirs = null;
    /** @type {string[] | null} Cached array of asset directory paths. */
    _assetDirs = null;
    /** @type {string[] | null} Cached array of entry point file paths. */
    _entrypoints = null;
    static DEFAULT_IGNORE = ['**/node_modules/**', '**/.git/**', '**/vendor/**', '**/dist/**', '**/build/**'];

    /**
     * Creates an instance of ProjectScanner.
     * @param {object} [options={}] - Configuration options.
     * @param {string} [options.projectRoot=process.cwd()] - The root directory of the project to scan.
     * @param {string[]} [options.ignorePatterns=ProjectScanner.DEFAULT_IGNORE] - Glob patterns to ignore.
     */
    constructor(options = {}) {
        this.projectRoot = path.resolve(options.projectRoot || process.cwd());
        this.ignorePatterns = options.ignorePatterns || ProjectScanner.DEFAULT_IGNORE;
    }

    /**
     * Checks if a directory exists.
     * @param {string} dirPath - The path to check.
     * @returns {Promise<boolean>} True if the path is an existing directory.
     * @private
     * @static
     */
    static async _directoryExists(dirPath) {
        try {
            const stats = await fs.stat(dirPath);
            return stats.isDirectory();
        } catch (error) {
            if (error.code === 'ENOENT') {
                return false;
            }

            // Rethrow unexpected errors
            console.error(`Error checking directory ${dirPath}:`, error);
            throw error;
        }
    }

    /**
     * Scans the project for module directories (containing `.sitchco-module`).
     * @returns {Promise<string[]>} A promise resolving to an array of absolute module directory paths.
     * @private
     */
    async _scanForModuleDirs() {
        console.log(`[ProjectScanner] Scanning for module roots in: ${this.projectRoot}`);
        const markerPattern = `**/${MODULE_MARKER_FILE}`;
        const markerFiles = await glob(markerPattern, {
            cwd: this.projectRoot,
            absolute: true,
            dot: true,
            ignore: this.ignorePatterns,
        });
        const moduleDirs = markerFiles.map((markerPath) => path.dirname(markerPath));
        console.log(`[ProjectScanner] Found ${moduleDirs.length} module roots.`);
        return moduleDirs;
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
     * Scans a single module directory for its asset directories.
     * @param {string} moduleDir - The absolute path to the module directory.
     * @returns {Promise<string[]>} A promise resolving to an array of absolute asset directory paths within the module.
     * @private
     */
    async _scanForAssetDirsInModule(moduleDir) {
        const assetDirs = [];
        // 1. Check primary assets directory: <module>/assets
        const mainAssetsPath = path.join(moduleDir, ASSETS_FOLDER);
        if (await ProjectScanner._directoryExists(mainAssetsPath)) {
            assetDirs.push(mainAssetsPath);
        }

        // 2. Check block-level assets directories: <module>/blocks/*/assets
        const blockAssetsPattern = `${BLOCKS_FOLDER}/*/${ASSETS_FOLDER}`;
        const blockAssetDirs = await glob(blockAssetsPattern, {
            cwd: moduleDir,
            absolute: true,
            onlyDirectories: true, // We only want directories
            ignore: this.ignorePatterns,
        });
        return assetDirs.concat(blockAssetDirs);
    }

    /**
     * Scans all modules for their asset directories.
     * @returns {Promise<string[]>} A promise resolving to a flat array of absolute asset directory paths.
     * @private
     */
    async _scanForAssetDirs() {
        const moduleDirs = await this.getModuleDirs(); // Ensures modules are scanned first
        if (!moduleDirs.length) {
            console.log('[ProjectScanner] No module roots found, skipping asset dir scan.');
            return [];
        }

        console.log(`[ProjectScanner] Scanning for asset directories within ${moduleDirs.length} modules.`);
        const promises = moduleDirs.map((moduleDir) => this._scanForAssetDirsInModule(moduleDir));
        const results = await Promise.all(promises);
        const allAssetDirs = results.flat(); // Flatten the array of arrays
        console.log(`[ProjectScanner] Found ${allAssetDirs.length} asset directories.`);
        return [...new Set(allAssetDirs)]; // Ensure uniqueness
    }

    /**
     * Gets the list of all asset directories across all modules, using cache if available.
     * @returns {Promise<string[]>} A promise resolving to a flat array of absolute asset directory paths.
     */
    async getAssetDirs() {
        if (this._assetDirs === null) {
            this._assetDirs = await this._scanForAssetDirs();
        }
        return this._assetDirs;
    }

    /**
     * Scans asset directories for entry points (top-level JS/SCSS files).
     * @returns {Promise<string[]>} A promise resolving to a flat array of absolute entry point file paths.
     * @private
     */
    async _scanForEntrypoints() {
        const assetDirs = await this.getAssetDirs(); // Ensures asset dirs are scanned first
        if (!assetDirs.length) {
            console.log('[ProjectScanner] No asset directories found, skipping entry point scan.');
            return [];
        }

        console.log(`[ProjectScanner] Scanning for entry points within ${assetDirs.length} asset directories.`);
        // Look for *.js, *.mjs, *.scss directly within assets/scripts/ or assets/styles/
        const entrypointPattern = `{${SCRIPTS_SUBFOLDER},${STYLES_SUBFOLDER}}/*.{js,mjs,scss}`;
        const promises = assetDirs.map(async (assetDir) => {
            // console.log(`[ProjectScanner] Globbing entrypoints in CWD: ${assetDir} using pattern: ${entrypointPattern}`);
            const files = await glob(entrypointPattern, {
                cwd: assetDir,
                absolute: true,
                nodir: true, // Only files
                ignore: this.ignorePatterns,
            });
            // console.log(`[ProjectScanner] Found entry points in ${assetDir}: ${files.length > 0 ? files : 'None'}`);
            return files;
        });
        const results = await Promise.all(promises);
        const allEntrypoints = results.flat();
        console.log(`[ProjectScanner] Found ${allEntrypoints.length} entry points.`);
        return [...new Set(allEntrypoints)]; // Ensure uniqueness
    }

    /**
     * Gets the list of all entry point files (top-level JS/MJS/SCSS in assets/scripts|styles)
     * across all asset directories, using cache if available.
     * @returns {Promise<string[]>} A promise resolving to a flat array of absolute entry point file paths.
     */
    async getEntrypoints() {
        if (this._entrypoints === null) {
            this._entrypoints = await this._scanForEntrypoints();
        }
        return this._entrypoints;
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
            console.warn('[ProjectScanner] findModuleSourceFiles called with no extensions.');
            return [];
        }

        const moduleDirs = await this.getModuleDirs(); // Ensures modules are scanned first
        if (!moduleDirs.length) {
            console.log('[ProjectScanner] No module roots found, cannot find source files.');
            return [];
        }

        // Create a glob pattern part like '{php,json,svg}'
        const extGroupPattern = `{${extensions.map((ext) => (ext.startsWith('.') ? ext.substring(1) : ext)).join(',')}}`;
        // Pattern to find files with specified extensions anywhere within the module dir
        const findPattern = `**/*.${extGroupPattern}`;
        console.log(
            `[ProjectScanner] Finding files with extensions [${extensions.join(', ')}] within ${moduleDirs.length} modules.`
        );

        const promises = moduleDirs.map(async (moduleDir) => {
            // console.log(`[ProjectScanner] Globbing source files in CWD: ${moduleDir} using pattern: ${findPattern}`);
            const files = await glob(findPattern, {
                cwd: moduleDir,
                absolute: true,
                nodir: true,
                dot: true, // Include dotfiles if they match extension
                ignore: this.ignorePatterns,
            });
            // console.log(`[ProjectScanner] Found ${files.length} source files in ${moduleDir}.`);
            return files;
        });
        const results = await Promise.all(promises);
        const allSourceFiles = results.flat();
        console.log(
            `[ProjectScanner] Found total ${allSourceFiles.length} source files with extensions [${extensions.join(', ')}].`
        );
        return [...new Set(allSourceFiles)]; // Ensure uniqueness
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
            console.warn('[ProjectScanner] findAllSourceFiles called with no extensions.');
            return [];
        }

        // Create a glob pattern part like '{php,json,svg}'
        const extGroupPattern = `{${extensions.map((ext) => (ext.startsWith('.') ? ext.substring(1) : ext)).join(',')}}`;
        // Pattern to find files with specified extensions anywhere within the project root
        const findPattern = `**/*.${extGroupPattern}`;
        console.log(
            `[ProjectScanner] Finding files with extensions [${extensions.join(', ')}] in project root: ${this.projectRoot}`
        );

        const files = await glob(findPattern, {
            cwd: this.projectRoot,
            absolute: true,
            nodir: true,
            dot: true, // Include dotfiles if they match extension
            ignore: this.ignorePatterns,
        });
        console.log(
            `[ProjectScanner] Found total ${files.length} source files in project root with extensions [${extensions.join(', ')}].`
        );
        return [...new Set(files)]; // Ensure uniqueness
    }

    /**
     * Finds all build artifact directories (dist, .vite) within the project root.
     * This method does NOT use caching.
     * @returns {Promise<string[]>} A promise resolving to a flat array of absolute artifact directory paths.
     */
    async getBuildArtifacts() {
        const artifactPatterns = ['**/dist', '**/.vite'];
        console.log(
            `[ProjectScanner] Scanning for build artifacts (${artifactPatterns.join(', ')}) in: ${this.projectRoot}`
        );

        // Filter out ignore patterns that might exclude the artifacts themselves
        const filteredIgnorePatterns = this.ignorePatterns.filter(
            (pattern) => !pattern.includes('dist') && !pattern.includes('.vite')
        );
        const artifactDirs = await glob(artifactPatterns, {
            cwd: this.projectRoot,
            absolute: true,
            onlyDirectories: true,
            dot: true, // Include .vite
            ignore: filteredIgnorePatterns,
        });
        console.log(`[ProjectScanner] Found ${artifactDirs.length} build artifact directories.`);
        return [...new Set(artifactDirs)]; // Ensure uniqueness
    }

    /**
     * Removes all found build artifact directories (dist, .vite).
     * @returns {Promise<void>} A promise that resolves when deletion is complete.
     */
    async cleanBuildArtifacts() {
        const artifactDirs = await this.getBuildArtifacts();
        if (!artifactDirs.length) {
            console.log('[ProjectScanner] No build artifacts found to clean.');
            return;
        }

        console.log(`[ProjectScanner] Cleaning ${artifactDirs.length} build artifact directories...`);
        const promises = artifactDirs.map(async (dirPath) => {
            try {
                console.log(`[ProjectScanner] Removing: ${path.relative(this.projectRoot, dirPath)}`);
                await fs.rm(dirPath, {
                    recursive: true,
                    force: true,
                });
            } catch (error) {
                console.error(`[ProjectScanner] Error removing directory ${dirPath}:`, error);
                // Decide if you want to throw or just log and continue
                // throw error; // Uncomment to stop on first error
            }
        });
        await Promise.all(promises);
        console.log('[ProjectScanner] Finished cleaning build artifacts.');
    }

    /**
     * Clears the internal cache for module directories, asset directories, and entrypoints.
     * Subsequent calls to getters will trigger a fresh scan.
     */
    clearCache() {
        console.log('[ProjectScanner] Clearing cache.');
        this._moduleDirs = null;
        this._assetDirs = null;
        this._entrypoints = null;
    }
}
// Make the class the default export
// export { ProjectScanner };
