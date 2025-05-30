import { writeFile } from 'fs/promises';
import { execSync } from 'child_process';

const [pkgName] = process.argv.slice(2);

if (!pkgName) {
    console.error('No package name provided.');
    process.exit(1);
}

const scope = `@sitchco/${pkgName}`;
const dir = `packages/build-tools/${pkgName}`;

// Get commit messages that touched this directory between origin/master and HEAD
let commits;
try {
    const raw = execSync(`git log origin/master..HEAD --pretty=format:"- %s" -- "${dir}"`, {
        encoding: 'utf-8'
    }).trim();
    commits = raw.length ? raw : '- No commits found for this package';
} catch (err) {
    console.error(`Failed to get commit log for ${scope}:`, err);
    process.exit(1);
}

// Write changeset content to temp-changeset.md
const content = `---
"${scope}": patch
---

${commits}
`;

await writeFile('temp-changeset.md', content);
console.log(`✅ Generated changeset summary for ${scope}`);
