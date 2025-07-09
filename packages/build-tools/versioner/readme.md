# Internal Publishing Tools

**Note:** This is an internal, private package (`"private": true`). It contains scripts used by our CI/CD pipeline (e.g., GitHub Actions) to automate the versioning and publishing of the public `@sitchco/*` tooling packages to NPM.

---

## The Publishing Workflow

Our publishing process is powered by [Changesets](https://github.com/changesets/changesets) and GitHub Actions, with support scripts provided by this internal tooling package.

### Overview

1. A developer opens a pull request from `develop` → `master`.
2. A GitHub Action runs and auto-generates a `.changeset/auto-*.md` file containing a `minor` release, based on commit messages.
3. If the developer intends to ship a **major** version (breaking change), they:
    - Run `pnpm changeset`
    - Select affected package(s)
    - Choose `major` as the release type
    - Commit the generated changeset file
4. When the PR is merged into `master`, another GitHub Action runs:
    - Applies all changesets via `pnpm changeset version`
    - Bumps versions and generates changelogs
    - Commits version changes
    - Publishes updated packages to NPM using `pnpm changeset publish`

---

## Releasing a Major Version

Major releases **must be explicitly declared** — they are never inferred from commit messages.

### To trigger a major release:

1. Run:

   ```bash
   pnpm changeset

2. Select the package(s) you are releasing.

3. Choose major as the change type.

4. Add a clear, human-readable summary explaining the breaking change.

5. Commit the resulting file in .changeset/.

> The presence of a manual major changeset will override any automatically generated changes and delete the auto-*.md file.


## Scripts
These helper scripts are used internally by the CI pipeline.

#### `build-changeset.mjs`

* **Purpose:** To automate the creation of a changeset file from commit messages.
* **How it Works:** Scans the commit history between origin/master and HEAD and generates a temp-changeset.md file with a default patch or minor bump and commit summaries.

Note: This script is used only when no manual changeset exists in the PR.

## Public Packages
Only packages meeting the following criteria are published to NPM as part of the automated workflow:

* The package must be intended for public consumption under the @sitchco namespace.
* Its package.json must not have "private": true.
* It must include a "publishConfig" section specifying "access": "public" to ensure correct NPM access level.
* It should have a valid and up-to-date "repository" field linking to the source repository.
* The package must have at least one semantic version bump triggered by changesets.
* The source code and configuration should comply with organizational quality and release standards.
* Packages that do not meet these criteria (e.g., private utility packages or internal tooling) are excluded from publishing and marked as "private": true in their package.json to prevent accidental publication.
