# Changesets

This directory contains configuration and temporary files for [Changesets](https://github.com/changesets/changesets), a tool for managing versioning and changelogs.

## How to use Changesets

When you make changes to packages that should be published, you need to create a changeset to document those changes. This will be used to determine the next version number and generate changelogs.

### Creating a changeset

```bash
pnpm changeset
```

This will prompt you to:
1. Select which packages have changed
2. Choose the type of change for each package (major, minor, patch)
3. Write a summary of the changes

A new markdown file will be created in this directory with the information you provided.

### Versioning packages

When it's time to release, run:

```bash
pnpm version
```

This will:
1. Read all changesets
2. Update package versions according to the changes
3. Update changelogs
4. Remove the changeset files

### Publishing packages

After versioning, you can publish the packages:

```bash
pnpm publish
```

This will publish all packages with new versions to npm.

## Automated publishing

In CI, the publishing process is automated using the [Changesets GitHub Action](https://github.com/changesets/action). When changes are pushed to the main branch, it will:

1. Create a PR to version packages if there are changesets
2. Publish packages when that PR is merged

## Learn more

- [Changesets Documentation](https://github.com/changesets/changesets/tree/main/docs)
