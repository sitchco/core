import { readdir } from 'fs/promises';
import { existsSync, readFileSync } from 'fs';
import { join } from 'path';
import { execSync } from 'child_process';

export async function getChangedPackages() {
    const packagesDir = join(process.cwd(), 'packages/build-tools');
    const allPackages = await readdir(packagesDir);

    const changedFiles = execSync('git diff --name-only origin/master...HEAD', {
        encoding: 'utf-8',
    })
        .split('\n')
        .filter(Boolean);

    const changedPackages = allPackages.filter((pkg) => {
        const packagePath = `packages/build-tools/${pkg}`;
        return changedFiles.some((file) => file.startsWith(packagePath));
    });

    const packagePath = (pkg) => join(packagesDir, pkg, 'package.json');
    const packageFileExists = (pkg) => existsSync(packagePath(pkg));
    const parsedPackage = (pkg) =>
        JSON.parse(readFileSync(packagePath(pkg), 'utf-8'));
    const packageIsPrivate = (pkg) => parsedPackage(pkg).private === true;

    return changedPackages
        .filter((pkg) => packageFileExists(pkg))
        .filter((pkg) => !packageIsPrivate(pkg))
        .map((pkg) => ({ name: pkg }));
}
