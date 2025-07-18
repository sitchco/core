import { writeFile } from 'fs/promises';
import { execSync } from 'child_process';
import { getChangedPackages } from './list-packages.mjs';

const changedPackages = await getChangedPackages();

if (!changedPackages.length) {
    console.log('No changed packages found. Skipping changeset.');
    process.exit(0);
}

let commits;

try {
    const raw = execSync(`git log origin/master..HEAD --pretty=format:"- %s"`, {
        encoding: 'utf-8',
    }).trim();
    commits = raw.length ? raw : '- No commits found';
} catch (err) {
    console.error(`Failed to get commit log:`, err);
    process.exit(1);
}

const frontMatter = changedPackages
    .map(pkg => `"${pkg.name}": patch`)
    .join('\n');

const content = `---
${frontMatter}
---

${commits}
`;

await writeFile('temp-changeset.md', content);
console.log(`Generated changeset summary`);
