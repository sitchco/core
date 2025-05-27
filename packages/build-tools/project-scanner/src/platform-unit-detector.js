import fs from 'node:fs/promises';
import { existsSync } from 'node:fs';
import path from 'node:path';
import { glob } from 'glob';
import chalk from 'chalk';

/**
 * Platform unit types
 */
export const UNIT_TYPES = {
    THEME: 'theme',
    PLUGIN: 'plugin',
    MU_PLUGIN: 'mu-plugin',
};

/**
 * Required files/patterns for each platform unit type
 */
export const UNIT_REQUIREMENTS = {
    [UNIT_TYPES.THEME]: {
        requiredFiles: ['style.css'],
        optionalFiles: ['functions.php', 'theme.json'],
        moduleMarker: '.sitchco-module',
    },
    [UNIT_TYPES.PLUGIN]: {
        requiredFiles: [], // Main PHP file with Plugin Name header (detected dynamically)
        optionalFiles: ['readme.txt'],
        moduleMarker: '.sitchco-module',
    },
    [UNIT_TYPES.MU_PLUGIN]: {
        requiredFiles: [], // PHP files or directories with PHP files
        optionalFiles: [],
        moduleMarker: '.sitchco-module',
    },
};

/**
 * Detects WordPress platform units (themes, plugins, mu-plugins) in a WordPress installation.
 */
export default class PlatformUnitDetector {
    /**
     * Creates a new PlatformUnitDetector instance.
     * @param {object} options - Configuration options
     * @param {string} options.webRoot - The WordPress web root directory
     * @param {boolean} options.verbose - Whether to log verbose information
     */
    constructor(options = {}) {
        this.webRoot = options.webRoot;
        this.verbose = options.verbose || false;
    }

    /**
     * Logs a message if verbose mode is enabled.
     * @param {string} message - The message to log
     * @private
     */
    _log(message) {
        if (this.verbose) {
            console.log(chalk.blue('[PlatformUnitDetector]'), message);
        }
    }

    /**
     * Checks if a file exists.
     * @param {string} filePath - The path to check
     * @returns {Promise<boolean>} True if the file exists
     * @private
     */
    async _fileExists(filePath) {
        try {
            await fs.access(filePath);
            return true;
        } catch (error) {
            return false;
        }
    }

    /**
     * Reads a file and extracts WordPress headers.
     * @param {string} filePath - The path to the file
     * @param {string[]} headerNames - The header names to extract
     * @returns {Promise<object>} An object with the extracted headers
     * @private
     */
    async _extractFileHeaders(filePath, headerNames) {
        try {
            const content = await fs.readFile(filePath, 'utf8');
            const headers = {};

            for (const headerName of headerNames) {
                const regex = new RegExp(`${headerName}:\\s*(.+)`, 'i');
                const match = content.match(regex);
                if (match && match[1]) {
                    headers[headerName.toLowerCase().replace(/\s+/g, '_')] = match[1].trim();
                }
            }

            return headers;
        } catch (error) {
            return {};
        }
    }

    /**
     * Detects themes in the WordPress installation.
     * @returns {Promise<Array<object>>} An array of detected themes
     */
    async detectThemes() {
        const themesDir = path.join(this.webRoot, 'wp-content/themes');
        this._log(`Scanning for themes in ${themesDir}`);

        try {
            const themeDirs = await fs.readdir(themesDir);
            const themes = [];

            for (const themeDir of themeDirs) {
                const themePath = path.join(themesDir, themeDir);
                const stats = await fs.stat(themePath);

                if (!stats.isDirectory() || themeDir === 'index.php') {
                    continue;
                }

                const styleFile = path.join(themePath, 'style.css');
                if (await this._fileExists(styleFile)) {
                    const headers = await this._extractFileHeaders(styleFile, [
                        'Theme Name',
                        'Version',
                        'Description',
                        'Author',
                        'Template',
                    ]);

                    if (headers.theme_name) {
                        const hasModuleMarker = await this._fileExists(
                            path.join(themePath, UNIT_REQUIREMENTS[UNIT_TYPES.THEME].moduleMarker)
                        );

                        themes.push({
                            type: UNIT_TYPES.THEME,
                            path: themePath,
                            name: headers.theme_name,
                            version: headers.version || '',
                            description: headers.description || '',
                            author: headers.author || '',
                            parent: headers.template || '',
                            isChildTheme: !!headers.template,
                            hasModuleMarker,
                        });

                        this._log(`Detected theme: ${headers.theme_name} at ${themePath}`);
                    }
                }
            }

            return themes;
        } catch (error) {
            this._log(`Error detecting themes: ${error.message}`);
            return [];
        }
    }

    /**
     * Detects plugins in the WordPress installation.
     * @returns {Promise<Array<object>>} An array of detected plugins
     */
    async detectPlugins() {
        const pluginsDir = path.join(this.webRoot, 'wp-content/plugins');
        this._log(`Scanning for plugins in ${pluginsDir}`);

        try {
            const pluginDirs = await fs.readdir(pluginsDir);
            const plugins = [];

            for (const pluginDir of pluginDirs) {
                const pluginPath = path.join(pluginsDir, pluginDir);
                const stats = await fs.stat(pluginPath);

                if (!stats.isDirectory() || pluginDir === 'index.php') {
                    continue;
                }

                // Find the main plugin file (same name as directory or has Plugin Name header)
                const phpFiles = await glob('*.php', {
                    cwd: pluginPath,
                    absolute: true,
                });

                let mainPluginFile = null;
                let pluginHeaders = {};

                // First try to find a file with the same name as the directory
                const sameNameFile = phpFiles.find((file) => path.basename(file, '.php') === pluginDir);
                if (sameNameFile) {
                    mainPluginFile = sameNameFile;
                    pluginHeaders = await this._extractFileHeaders(mainPluginFile, [
                        'Plugin Name',
                        'Version',
                        'Description',
                        'Author',
                    ]);
                }

                // If not found or no Plugin Name header, check all PHP files
                if (!mainPluginFile || !pluginHeaders.plugin_name) {
                    for (const phpFile of phpFiles) {
                        const headers = await this._extractFileHeaders(phpFile, [
                            'Plugin Name',
                            'Version',
                            'Description',
                            'Author',
                        ]);

                        if (headers.plugin_name) {
                            mainPluginFile = phpFile;
                            pluginHeaders = headers;
                            break;
                        }
                    }
                }

                if (mainPluginFile && pluginHeaders.plugin_name) {
                    const hasModuleMarker = await this._fileExists(
                        path.join(pluginPath, UNIT_REQUIREMENTS[UNIT_TYPES.PLUGIN].moduleMarker)
                    );

                    plugins.push({
                        type: UNIT_TYPES.PLUGIN,
                        path: pluginPath,
                        mainFile: mainPluginFile,
                        name: pluginHeaders.plugin_name,
                        version: pluginHeaders.version || '',
                        description: pluginHeaders.description || '',
                        author: pluginHeaders.author || '',
                        hasModuleMarker,
                    });

                    this._log(`Detected plugin: ${pluginHeaders.plugin_name} at ${pluginPath}`);
                }
            }

            return plugins;
        } catch (error) {
            this._log(`Error detecting plugins: ${error.message}`);
            return [];
        }
    }

    /**
     * Detects MU plugins in the WordPress installation.
     * @returns {Promise<Array<object>>} An array of detected MU plugins
     */
    async detectMuPlugins() {
        const muPluginsDir = path.join(this.webRoot, 'wp-content/mu-plugins');
        this._log(`Scanning for MU plugins in ${muPluginsDir}`);

        try {
            const muPluginEntries = await fs.readdir(muPluginsDir);
            const muPlugins = [];

            for (const entry of muPluginEntries) {
                const entryPath = path.join(muPluginsDir, entry);
                const stats = await fs.stat(entryPath);

                // Handle PHP files directly in mu-plugins directory
                if (stats.isFile() && path.extname(entry) === '.php' && entry !== 'index.php') {
                    const headers = await this._extractFileHeaders(entryPath, [
                        'Plugin Name',
                        'Version',
                        'Description',
                        'Author',
                    ]);

                    muPlugins.push({
                        type: UNIT_TYPES.MU_PLUGIN,
                        path: entryPath,
                        mainFile: entryPath,
                        name: headers.plugin_name || path.basename(entryPath, '.php'),
                        version: headers.version || '',
                        description: headers.description || '',
                        author: headers.author || '',
                        isDirectory: false,
                        hasModuleMarker: false, // Single PHP files can't have module markers
                    });

                    this._log(`Detected MU plugin file: ${entry} at ${entryPath}`);
                }
                // Handle directories in mu-plugins
                else if (stats.isDirectory() && entry !== '.' && entry !== '..') {
                    // Find the main plugin file (same name as directory or has Plugin Name header)
                    const phpFiles = await glob('*.php', {
                        cwd: entryPath,
                        absolute: true,
                    });

                    let mainPluginFile = null;
                    let pluginHeaders = {};

                    // First try to find a file with the same name as the directory
                    const sameNameFile = phpFiles.find((file) => path.basename(file, '.php') === entry);
                    if (sameNameFile) {
                        mainPluginFile = sameNameFile;
                        pluginHeaders = await this._extractFileHeaders(mainPluginFile, [
                            'Plugin Name',
                            'Version',
                            'Description',
                            'Author',
                        ]);
                    }

                    // If not found or no Plugin Name header, check all PHP files
                    if (!mainPluginFile || !pluginHeaders.plugin_name) {
                        for (const phpFile of phpFiles) {
                            const headers = await this._extractFileHeaders(phpFile, [
                                'Plugin Name',
                                'Version',
                                'Description',
                                'Author',
                            ]);

                            if (headers.plugin_name) {
                                mainPluginFile = phpFile;
                                pluginHeaders = headers;
                                break;
                            }
                        }
                    }

                    const hasModuleMarker = await this._fileExists(
                        path.join(entryPath, UNIT_REQUIREMENTS[UNIT_TYPES.MU_PLUGIN].moduleMarker)
                    );

                    muPlugins.push({
                        type: UNIT_TYPES.MU_PLUGIN,
                        path: entryPath,
                        mainFile: mainPluginFile || null,
                        name: pluginHeaders.plugin_name || entry,
                        version: pluginHeaders.version || '',
                        description: pluginHeaders.description || '',
                        author: pluginHeaders.author || '',
                        isDirectory: true,
                        hasModuleMarker,
                    });

                    this._log(`Detected MU plugin directory: ${entry} at ${entryPath}`);
                }
            }

            return muPlugins;
        } catch (error) {
            this._log(`Error detecting MU plugins: ${error.message}`);
            return [];
        }
    }

    /**
     * Detects all platform units in the WordPress installation.
     * @returns {Promise<Array<object>>} An array of all detected platform units
     */
    async detectAllUnits() {
        const [themes, plugins, muPlugins] = await Promise.all([
            this.detectThemes(),
            this.detectPlugins(),
            this.detectMuPlugins(),
        ]);

        return [...themes, ...plugins, ...muPlugins];
    }

    /**
     * Validates a platform unit against its requirements.
     * @param {object} unit - The platform unit to validate
     * @returns {object} Validation result with isValid and messages
     */
    validateUnit(unit) {
        const requirements = UNIT_REQUIREMENTS[unit.type];
        const messages = [];
        let isValid = true;

        if (!requirements) {
            return { isValid: false, messages: [`Unknown unit type: ${unit.type}`] };
        }

        // Check required files
        for (const requiredFile of requirements.requiredFiles) {
            const filePath = path.join(unit.path, requiredFile);
            if (!existsSync(filePath)) {
                isValid = false;
                messages.push(`Missing required file: ${requiredFile}`);
            }
        }

        // Additional type-specific validation
        switch (unit.type) {
            case UNIT_TYPES.THEME:
                if (!unit.name) {
                    isValid = false;
                    messages.push('Theme is missing Theme Name in style.css');
                }
                break;
            case UNIT_TYPES.PLUGIN:
                if (!unit.mainFile) {
                    isValid = false;
                    messages.push('Plugin is missing a main PHP file');
                }
                if (!unit.name) {
                    isValid = false;
                    messages.push('Plugin is missing Plugin Name header');
                }
                break;
            case UNIT_TYPES.MU_PLUGIN:
                if (unit.isDirectory && !unit.mainFile) {
                    isValid = false;
                    messages.push('MU Plugin directory is missing a main PHP file');
                }
                break;
        }

        return { isValid, messages };
    }
}
