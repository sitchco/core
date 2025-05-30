# @sitchco/project-scanner

A utility for scanning WordPress project structures to discover Sitchco modules, assets, and platform units.

## Installation

```bash
npm install @sitchco/project-scanner
# or
yarn add @sitchco/project-scanner
# or
pnpm add @sitchco/project-scanner
```

## Overview

The platform unit detection system is designed to:

1. Automatically detect WordPress platform units (themes, plugins, MU plugins) based on standardized conventions
2. Respect `.sitchco-module` markers for identifying modules within these units
3. Provide consistent behavior regardless of the working directory
4. Flag invalid or incomplete module structures

## Platform Unit Types

The system recognizes three types of platform units:

1. **Themes**: Located in `wp-content/themes/`, identified by the presence of a `style.css` file with a Theme Name header
2. **Plugins**: Located in `wp-content/plugins/`, identified by a main PHP file with a Plugin Name header
3. **MU Plugins**: Located in `wp-content/mu-plugins/`, can be either single PHP files or directories containing PHP files

## Detection Criteria

### Themes

A directory is identified as a theme if:

- It is located in `wp-content/themes/`
- It contains a `style.css` file with a valid Theme Name header
- It is not named `index.php`

Additional theme metadata extracted from `style.css`:
- Version
- Description
- Author
- Template (for child themes)

### Plugins

A directory is identified as a plugin if:

- It is located in `wp-content/plugins/`
- It contains at least one PHP file with a Plugin Name header
- It is not named `index.php`

The main plugin file is determined by:
1. First checking for a PHP file with the same name as the directory
2. If not found or no Plugin Name header, scanning all PHP files for a Plugin Name header

Additional plugin metadata extracted from the main PHP file:
- Version
- Description
- Author

### MU Plugins

MU plugins can be either:

1. **Single PHP files** directly in `wp-content/mu-plugins/`
2. **Directories** in `wp-content/mu-plugins/` containing PHP files

For single PHP files:
- Must have a `.php` extension
- Not named `index.php`
- Plugin Name header is extracted if available, otherwise the filename is used

For directories:
- The main plugin file is determined using the same logic as regular plugins
- Plugin Name header is extracted if available, otherwise the directory name is used

## Module Detection

Within each platform unit, the system looks for:

- `.sitchco-module` marker files that indicate a module
- Entry points (JS, MJS, SCSS files) in standard locations:
  - Module root
  - `assets/scripts/` and `assets/styles/` directories
  - Block roots within the `blocks/` directory
  - Block asset directories

## Usage

### Command Line Interface

The module-builder CLI has been enhanced with a verbose mode that displays detailed information about detected platform units:

```bash
# Clean all build artifacts
npx module-builder clean -v

# Build all module/block assets for production with verbose output
npx module-builder build -v

# Watch module/block assets and rebuild on changes with verbose output
npx module-builder dev -v
```

### Programmatic Usage

```javascript
import ProjectScanner, { PLATFORM_UNIT_TYPES } from '@sitchco/project-scanner';

// Create a scanner instance
const scanner = new ProjectScanner({ verbose: true });

// Get all platform units
const allUnits = await scanner.getPlatformUnits();

// Get units by type
const themes = await scanner.getPlatformUnitsByType(PLATFORM_UNIT_TYPES.THEME);
const plugins = await scanner.getPlatformUnitsByType(PLATFORM_UNIT_TYPES.PLUGIN);
const muPlugins = await scanner.getPlatformUnitsByType(PLATFORM_UNIT_TYPES.MU_PLUGIN);

// Get a specific unit by name
const specificTheme = await scanner.getPlatformUnitByName('My Theme');

// Validate platform units
const validationResults = await scanner.validatePlatformUnits();

// Get module directories
const moduleDirs = await scanner.getModuleDirs();

// Get entry points
const entrypoints = await scanner.getEntrypoints();

// Find source files with specific extensions
const phpFiles = await scanner.findModuleSourceFiles(['.php']);

// Clean build artifacts
await scanner.cleanBuildArtifacts();
```

## API

### Constructor

```javascript
const scanner = new ProjectScanner(options);
```

Options:
- `projectRoot` (string): The root directory of the project to scan. Defaults to `process.cwd()`.
- `ignorePatterns` (string[]): Glob patterns to ignore. Defaults to `['**/node_modules/**', '**/.git/**', '**/vendor/**', '**/dist/**', '**/build/**']`.
- `verbose` (boolean): Whether to log verbose information. Defaults to `false`.

### Methods

- `getModuleDirs()`: Gets the list of module directories.
- `getEntrypoints()`: Gets the list of all entry point files (JS/MJS/SCSS).
- `getWebRoot()`: Gets the WordPress web root directory path.
- `findModuleSourceFiles(extensions)`: Finds all source files within module directories matching the specified extensions.
- `findAllSourceFiles(extensions)`: Finds all source files within the entire project matching the specified extensions.
- `getBuildArtifacts()`: Finds all build artifact directories (dist, .vite) within the project root.
- `cleanBuildArtifacts()`: Removes all found build artifact directories.
- `clearCache()`: Clears the internal cache.
- `getPlatformUnits()`: Gets the list of platform units.
- `getPlatformUnitsByType(type)`: Gets platform units of a specific type.
- `getPlatformUnitByName(name)`: Gets a platform unit by name.
- `validatePlatformUnits()`: Validates all platform units against their requirements.

## Adding New Platform Units

To add a new platform unit to the build system:

1. **For themes**:
   - Create a directory in `wp-content/themes/`
   - Add a `style.css` file with a valid Theme Name header
   - Optionally add a `.sitchco-module` file to mark it as a module

2. **For plugins**:
   - Create a directory in `wp-content/plugins/`
   - Add a main PHP file with a Plugin Name header
   - Optionally add a `.sitchco-module` file to mark it as a module

3. **For MU plugins**:
   - Add a PHP file directly to `wp-content/mu-plugins/`, or
   - Create a directory in `wp-content/mu-plugins/` with a main PHP file
   - Optionally add a `.sitchco-module` file to mark it as a module (for directories)

## Module Structure

A valid module should have:

1. A `.sitchco-module` marker file
2. At least one of the following:
   - JS/SCSS files in the module root
   - JS/SCSS files in `assets/scripts/` or `assets/styles/`
   - Blocks in a `blocks/` directory with their own assets

## Validation

The system validates platform units against their requirements and provides clear error messages for invalid or incomplete structures. Validation checks include:

- Presence of required files
- Valid headers
- Proper structure

## Error Messages

The system provides clear error messages for invalid or incomplete module structures, such as:

- Missing required files
- Missing Theme Name in style.css
- Missing Plugin Name header
- Missing main PHP file in a plugin or MU plugin directory

## Dependencies

This package uses:

- `glob`: For file pattern matching
- `chalk`: For colorized console output

## License

ISC
