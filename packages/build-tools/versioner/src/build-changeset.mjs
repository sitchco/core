import { writeFile } from 'fs/promises';
import { execSync } from 'child_process';

let commits;

try {
    const raw = execSync(`git log origin/master..HEAD --pretty=format:"- %s"`, {
        encoding: 'utf-8',
    }).trim();
    commits = raw.length ? raw : '- No commits found';
    console.log('Commits:', commits);
} catch (err) {
    console.error(`Failed to get commit log:`, err);
    process.exit(1);
}

// Write changeset content to temp-changeset.md
const content = `---
release: patch
---

${commits}
`;

await writeFile('temp-changeset.md', content);
console.log(`Generated changeset summary`);
