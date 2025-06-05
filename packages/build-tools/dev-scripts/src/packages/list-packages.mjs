import { readdir, writeFile } from 'fs/promises';
import { existsSync } from 'fs';
import { join } from 'path';
import {execSync} from "child_process";

const packagesDir = join(process.cwd(), 'packages/build-tools');
const allPackages = await readdir(packagesDir);

// Get a list of changed files from master
const changedFiles = execSync('git diff --name-only origin/master...HEAD', { encoding: 'utf-8' })
    .split('\n')
    .filter(Boolean);

// Get package names where at least one file was modified
const changedPackages = allPackages.filter(pkg => {
    const packagePath = `packages/build-tools/${pkg}`;
    return changedFiles.some(file => file.startsWith(packagePath));
});

const validPackages = changedPackages
    .filter(pkg => existsSync(join(packagesDir, pkg, 'package.json')))
    .map(pkg => ({ name: pkg }));

await writeFile('packages-matrix.json', JSON.stringify(validPackages, null, 2));

console.log(`Found ${validPackages.length} packages.`);
console.log(`${JSON.stringify(validPackages)}`);
