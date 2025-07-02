# Internal Publishing Tools

**Note:** This is an internal, private package (`"private": true`). It contains scripts used by our CI/CD pipeline (e.g., GitHub Actions) to automate the versioning and publishing of the public `@sitchco/*` tooling packages to NPM.

## The Publishing Workflow

Our publishing process is automated using [Changesets](https://github.com/changesets/changesets) and GitHub Actions. The scripts in this package are helper utilities designed to be called from within that CI/CD workflow.

The high-level process is as follows:
1.  A developer makes changes to one or more packages. Before committing, they run `pnpm changeset` to document the changes.
2.  When a pull request with new changesets is merged into the `master` branch, the Changesets GitHub Action runs.
3.  The action creates a new "Version Packages" pull request that bumps the versions of the changed packages and updates their changelogs.
4.  Once the "Version Packages" PR is reviewed and merged, the Changesets action automatically publishes the updated packages to NPM.

## Scripts

These are the internal scripts used by the CI pipeline.

#### `list-packages.mjs`

* **Purpose:** To generate a build matrix for the CI pipeline, identifying which packages have changed.
* **How it Works:** It runs a `git diff` against the `master` branch to find changed files. It then determines which packages in `packages/build-tools/` were affected and outputs this list to a `packages-matrix.json` file, which the CI workflow can then use.

#### `build-changeset.mjs`

* **Purpose:** To automate the creation of a changeset file from commit messages.
* **How it Works:** This script takes a package name as an argument. It scans the `git log` for commits that have touched that specific package and generates a `temp-changeset.md` file formatted for the Changesets tool.

#### `prep-package.mjs`

* **Purpose:** A utility to ensure a package is built before the publishing step.
* **How it Works:** It checks for a `build` script in the target package's `package.json` and, if found, executes it using `pnpm`.

## Public Packages

This tooling monorepo publishes the following public packages to NPM:

* `@sitchco/cli` - Unified command-line interface.
* `@sitchco/formatter` - Programmatic multi-file-type formatter.
* `@sitchco/linter` - Programmatic ESLint runner.
* `@sitchco/module-builder` - The core Vite-based asset build engine.
* `@sitchco/project-scanner` - Convention-based project scanning utility.
* `@sitchco/eslint-config` - Shared ESLint configuration.
* `@sitchco/prettier-config` - Shared Prettier configuration.

## Setup for Publishing

To enable automated publishing, the following setup is required.

### NPM Authentication
1.  Create an NPM organization for `@sitchco` (if not already done).
2.  Generate an NPM automation token with "publish" permissions.
3.  Add the token as a secret to the GitHub repository with the name `NPM_TOKEN`. The Changesets action will use this to authenticate.

### TODO Checklist
- [x] Create NPM organization for `@sitchco`.
- [ ] Generate NPM token and add it as a GitHub secret (`NPM_TOKEN`).
- [ ] Ensure the `repository` URL in each public package's `package.json` is correct.
