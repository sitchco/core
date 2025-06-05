import { readFile, existsSync } from 'fs';
import { join } from 'path';
import { execSync } from 'child_process';

const pkg = process.argv[2];
const pkgPath = join(process.cwd(), 'packages/build-tools', pkg);
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
        execSync('pnpm run build', { cwd: pkgPath, stdio: 'inherit' });
    } else {
        console.log(`No build script for ${pkgJson.name}, skipping build`);
    }
});
