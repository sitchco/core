# @sitchco/module-builder

Dynamic Vite build tool for Sitchco modules.

## Installation

```bash
npm install @sitchco/module-builder
# or
yarn add @sitchco/module-builder
# or
pnpm add @sitchco/module-builder
```

## Overview

The `@sitchco/module-builder` is a specialized build tool for Sitchco WordPress projects. It uses Vite to compile assets for modules marked with `.sitchco-module` files. It's designed to work with the Sitchco WordPress Platform architecture, which separates functionality into modular components.

## Features

- Automatic discovery of modules via `.sitchco-module` marker files
- Dynamic Vite configuration generation based on discovered modules
- Development mode with Hot Module Replacement (HMR)
- Production builds with optimized assets
- Support for JavaScript, TypeScript, SCSS, CSS, and other assets
- Integration with WordPress via manifest generation

## Usage

### Command Line Interface

```bash
# Build all module/block assets for production
npx module-builder build

# Watch module/block assets and rebuild on changes (with HMR)
npx module-builder dev

# Clean all build artifacts
npx module-builder clean

# Add verbose output to any command
npx module-builder build -v
```

### Package.json Integration

Add these scripts to your package.json:

```json
{
  "scripts": {
    "build": "module-builder build",
    "dev": "module-builder dev",
    "clean": "module-builder clean"
  }
}
```

Then run:

```bash
npm run build
npm run dev
npm run clean
```

## How It Works

1. The module-builder uses `@sitchco/project-scanner` to find all modules marked with `.sitchco-module` files
2. It identifies entry points in standard locations:
   - Module root (*.js, *.mjs, *.scss)
   - `assets/scripts/` and `assets/styles/` directories
   - Block roots within the `blocks/` directory
   - Block asset directories
3. It generates a Vite configuration based on the discovered entry points
4. In development mode, it starts a Vite dev server with HMR
5. In production mode, it builds optimized assets and generates a manifest.json

## WordPress Integration

The module-builder creates:

- A `dist/` directory with compiled assets
- A `manifest.json` file mapping source files to compiled assets
- A `hot` file in development mode to indicate HMR is active

These files are used by the Sitchco WordPress platform to properly enqueue assets in both development and production environments.

## Dependencies

This package depends on:

- `@sitchco/project-scanner`: For discovering modules and entry points
- `vite`: For building and serving assets
- `laravel-vite-plugin`: For manifest generation and HMR integration
- `sass-embedded`: For compiling Sass/SCSS stylesheets

## License

ISC
