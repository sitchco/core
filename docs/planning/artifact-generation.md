# Building & Distributing Composer Packages with Satis and CI

This document outlines the process for configuring a project to be packaged and distributed using Composer, Satis, and a custom CI workflow that includes built artifacts (e.g. `/dist` folder) in release tags while adhering to Composer's prefer-dist and prefer-source modes.

## Overview

This system enables:

* Clean source repositories (no committed `/dist`)
* Tagged releases that contain built distribution artifacts
* Satis to only index tagged releases with versioned composer.json files
* Composer consumers to install from your private Satis server

## Process Outline

The following steps outline the overall configuration and process required for using Composer and Satis to manage dependencies.

### 1. Configure Satis to Grab Release Tags

Satis uses a satis.json file to define which repositories to scan and package. In our case, Satis should already be configured to grab all tags:

```json
{
    "name": "sitchco/composer",
    "homepage": "https://composer.sitch.co",
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:sitchco/core.git"
        },
        {
            "type": "vcs",
            "url": "git@github.com:sitchco/parent-theme.git"
        },

        {
            "type": "vcs",
            "url": "git@github.com:sitchco/wp-composer-automator.git"
        }
    ],
    "require-all": true
}
```

`require-all: true` tells Satis to include every version and tag it finds.

### 2. Build the /dist Folder with Your Project’s Build Command

The source project should have a build step that generates production-ready files into a `/dist` folder. Identify and note the command for use in the next step.

This might be something like:

```
npm run build
# or pnpm build
# or make build
# or composer run build
```

### 3. Create a Release Tag That Includes the Built /dist Folder

Instead of checking `/dist` into the main branch, we want it to be committed only in a release tag. This keeps the dev branches clean but ensures Composer can access the built files. This can be done manually, or via CI.

#### Key Steps:

In the project to be deployed via Satis/Composer:

* Create a temporary release branch
* Determine the desired version of the package
* Update composer.json version to the desired version
* Update package.json (if applicable) version to the desired version
* Run the appropriate build script to generate `/dist`
* Force-add `/dist` (despite it being in `.gitignore`)
* Create the release tag
* Commit the changes to the release branch and push to origin

#### CI Workflow:

This sample CI workflow handles the key steps for us.

```yaml
name: release.yml
on:
  workflow_dispatch:
    inputs:
      tag:
        description: "Release tag (e.g. 1.0.0)"
        required: true

permissions:
  contents: write
  packages: write
  checks: read

jobs:
  release:
    runs-on: ubuntu-latest
    name: Build and Release Project

    env:
      BRANCH_NAME: test-release/${{ github.run_id }}

    steps:
      - name: Checkout repo
        uses: actions/checkout@v4
        with:
          fetch-depth: 0

      - name: Setup Node and pnpm
        uses: pnpm/action-setup@v3
        with:
          version: 8
      - uses: actions/setup-node@v4
        with:
          node-version: 20

      - name: Install dependencies
        run: pnpm install

      - name: Create test branch
        run: git checkout -b $BRANCH_NAME

      - name: Set Composer version
        run: |
          jq --arg version "${{ github.event.inputs.tag }}" '.version = $version' composer.json > composer.tmp.json
          mv composer.tmp.json composer.json

      - name: Run build
        run: pnpm build

      - name: Force dist folder and update tag
        run: |
          git config user.name "github-actions[bot]"
          git config user.email "41898282+github-actions[bot]@users.noreply.github.com"
          git add dist -f
          git commit -m "Add dist for release ${{ github.event.inputs.tag }}"
          git tag -f "${{ github.event.inputs.tag }}"
          git push origin ${{ github.event.inputs.tag }} --force
```

### 4. Configure Consumers to Install Packages from Satis

In the consuming project:

Set Satis as a Composer repository

```json
"repositories": [
  {
    "type": "composer",
    "url": "https://composer.sitch.co"
  }
]
```

If you're running Satis locally, you can use:

```json
"repositories": [
  {
    "type": "composer",
    "url": "http://localhost:8000"
  }
]
```

Then, specify the dependency:

```json
"require": {
  "sitchco/sitchco-core": "^0.0.2"
}
```


## Limiting Files in Composer/Satis Distributions

To ensure only necessary files (like `/dist`) are included in the Composer package, there are two ways to control what goes into the ZIP archive:

### 1. .gitattributes

Use export-ignore to exclude files from Git archives (used by Composer dist):

```
/tests export-ignore
/.github export-ignore
/.gitignore export-ignore
/composer.lock export-ignore
/README.md export-ignore

/dist/ !export-ignore
```

### 2. archive in composer.json

Use the archive section to include or exclude files:

```json
"archive": {
  "exclude": [
    "/tests",
    "/.github",
    "/README.md"
  ],
  "include": [
    "/dist/**"
  ]
}
```

Both methods help keep your Composer distributions clean and lightweight.

## Testing locally

The following section outlines how to test this entire Composer + Satis packaging workflow using local resources, while still creating real tags on GitHub. This allows you to validate changes without impacting production or deploying to live consumers.

### 1. Create and configure a local Satis instance

Clone the Satis repo or create a project with Satis installed:

```
git clone https://github.com/composer/satis.git satis-local
cd satis-local
composer install
```

Add your satis.json config in the root of the satis-local directory:

```json
{
  "name": "sitchco/composer",
  "homepage": "http://localhost:8000",
  "repositories": [
    {
      "type": "vcs",
      "url": "git@github.com:sitchco/core.git"
    }
  ],
  "require-all": true
}
```

Start a local web server from the public/ folder:

```
php -S localhost:8000 -t public/
```

This exposes your local Satis index at http://localhost:8000.

### 2. Create and configure a local composer consumer

Create a new test project:

```
mkdir consumer-test && cd consumer-test
composer init
```

Configure it to use your local Satis instance:

In composer.json, add:

```json
"repositories": [
  {
    "type": "composer",
    "url": "http://localhost:8000"
  }
],
"config": {
  "preferred-install": "dist"
}
```

Add the package as a dependency:

```
composer require sitchco/sitchco-core:^0.0.2
```

This will only work after your tag has been created and Satis has been rebuilt.

### 3. Update a dependency

Optional: Make a code change in the package repository.

Determine an appropriate version number for the change. Update to that version in composer.json inside the source package.

Run your build script to generate dist:

```
npm run build
# or equivalent for your project
```

Commit the change and create a new Git tag, using the version number in the composer.json config:

```
git add dist composer.json
git commit -m "Release v0.0.3"
git tag v0.0.3
git push origin main --tags
```

This can also be automated via a GitHub Actions release workflow.

### 4. Build your Satis index locally

Run the following from the root of your satis-local directory:

```
php bin/satis build satis.json public/ --no-cache
```

After building, a file like sitchco-core.json will appear in:

```
public/p2/sitchco/sitchco-core.json
```

This file lists all available versions (tags) for that package. The contents of the archive (e.g., whether `/dist` is included) will match what’s committed in the tag.

_Note: If you're not seeing new versions appear in your consumer project, try clearing Composer's cache with `composer clear-cache`._

### 5. Install the New Version in the Consumer

Back in your consumer test project:

```
composer update sitchco/sitchco-core
```

Then confirm that:

* The new version (0.0.3, etc.) was installed
* The vendor/sitchco/sitchco-core folder contains your built `/dist` files
