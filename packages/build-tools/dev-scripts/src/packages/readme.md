# NPM Package Publishing Guide

This document explains how the @sitchco packages are published to NPM and how to use them in your projects.

## Published Packages

The following packages are published to NPM:

1. `@sitchco/project-scanner` - Scans project structure for Sitchco modules and assets
2. `@sitchco/module-builder` - Dynamic Vite build tool for Sitchco modules
3. `@sitchco/dev-scripts` - Provides script interfaces for linting and formatting
4. `@sitchco/eslint-config` - Shared ESLint configuration
5. `@sitchco/prettier-config` - Shared Prettier configuration

## Publishing Process

### Automated Publishing

The packages are automatically published to NPM using GitHub Actions when changes are merged to the main branch. The workflow is defined in `.github/workflows/publish.yml`.

The publishing process uses [Changesets](https://github.com/changesets/changesets) to manage versioning and changelogs.

#### How it works:

1. When you make changes to packages, you create a changeset using `pnpm changeset`.
2. When changes are pushed to the main branch, the GitHub Actions workflow runs.
3. If there are changesets, the Changesets GitHub Action creates a PR to version the packages.
4. When that PR is merged, the packages are published to NPM.

### Manual Publishing

If needed, you can also publish packages manually:

1. Version packages:
   ```bash
   pnpm version
   ```

2. Publish packages:
   ```bash
   pnpm publish
   ```

## NPM Authentication

To publish packages to NPM, you need to:

1. Create an NPM organization for @sitchco (if not already done)
2. Generate an NPM token with publish permissions
3. Add the token as a GitHub secret named `NPM_TOKEN`

## Using Published Packages

### Installation

You can install the published packages using npm, yarn, or pnpm:

```bash
# Using npm
npm install @sitchco/project-scanner @sitchco/module-builder

# Using yarn
yarn add @sitchco/project-scanner @sitchco/module-builder

# Using pnpm
pnpm add @sitchco/project-scanner @sitchco/module-builder
```

### Usage in WordPress Projects

#### Via Composer

You can include the packages in your WordPress project using Composer:

```json
{
  "require": {
    "sitchco/sitchco-core": "^1.0.0"
  },
  "repositories": [
    {
      "type": "composer",
      "url": "https://your-composer-repository.com"
    }
  ]
}
```

#### Via npm/pnpm

You can also use the packages directly in your WordPress theme or plugin:

```json
{
  "dependencies": {
    "@sitchco/project-scanner": "^1.0.0",
    "@sitchco/module-builder": "^1.0.0"
  }
}
```

## Troubleshooting

### Common Issues

1. **Authentication Errors**: Make sure your NPM token is correctly set up as a GitHub secret.
2. **Version Conflicts**: If you have version conflicts, you may need to manually resolve them using `pnpm version`.
3. **Build Failures**: Ensure all tests pass before trying to publish.

### Getting Help

If you encounter issues with the publishing process, please:

1. Check the GitHub Actions logs for error messages
2. Review the Changesets documentation
3. Contact the repository maintainers

## TODO

- [ ] Create NPM organization for @sitchco
- [ ] Generate NPM token and add it as a GitHub secret
- [ ] Update repository URLs if needed
