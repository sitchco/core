import { createServer as viteCreateServer } from 'vite';
import chalk from 'chalk';
import { generateViteConfig } from './config.js';

export async function runDev(target) {
    if (!target) {
        console.log(chalk.yellow(`No targets found. Nothing to build.`));
        return;
    }

    console.log(
        chalk.cyan(
            `\n🚀 Running dev build with ${Object.keys(target.viteInput).length} entry points...`
        )
    );
    const viteConfig = await generateViteConfig(target, true);
    const server = await viteCreateServer(viteConfig);
    await server.listen();
    server.printUrls();
}
