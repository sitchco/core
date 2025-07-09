import { readFile, existsSync, readdirSync, statSync } from 'fs';
import { join } from 'path';
import { execSync } from 'child_process';

const buildToolsDir = join(process.cwd(), 'packages/build-tools');

const pkgs = readdirSync(buildToolsDir).filter(name => {
    const fullPath = join(buildToolsDir, name);
    return statSync(fullPath).isDirectory();
});

pkgs.forEach(pkg => {
    const pkgPath = join(buildToolsDir, pkg);
    const pkgJsonPath = join(pkgPath, 'package.json');

    if (!existsSync(pkgJsonPath)) {
        console.error(`No package.json found for ${pkg}`);
        process.exit(1);
    }

    readFile(pkgJsonPath, 'utf8', (err, data) => {
        if (err) {
            console.error(`Error reading package.json:`, err);
            process.exit(1);
        }

        const pkgJson = JSON.parse(data);
        if (pkgJson.scripts?.build) {
            console.log(`Building ${pkgJson.name}...`);
            execSync('pnpm run build', {
                cwd: pkgPath,
                stdio: 'inherit',
            });
        } else {
            console.log(`No build script for ${pkgJson.name}, skipping build`);
        }
    });
});
