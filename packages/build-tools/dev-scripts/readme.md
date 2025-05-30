# @sitchco/dev-scripts

Provides stable script interfaces for linting and formatting for Sitchco projects.

## Installation

```bash
npm install @sitchco/dev-scripts
# or
yarn add @sitchco/dev-scripts
# or
pnpm add @sitchco/dev-scripts
```

## Usage

### CLI Commands

This package provides two main CLI commands:

#### Linting

```bash
sitchco-lint [options]
```

Runs ESLint on your project files using the shared Sitchco ESLint configuration.

#### Formatting

```bash
sitchco-format [options]
```

Runs Prettier on your project files using the shared Sitchco Prettier configuration.

### Package.json Integration

Add these scripts to your package.json:

```json
{
  "scripts": {
    "lint": "sitchco-lint",
    "format": "sitchco-format"
  }
}
```

Then run:

```bash
npm run lint
npm run format
```

## Dependencies

This package depends on:

- `@sitchco/eslint-config`: Shared ESLint configuration
- `@sitchco/prettier-config`: Shared Prettier configuration
- `@sitchco/project-scanner`: Project structure scanner

## License

ISC
