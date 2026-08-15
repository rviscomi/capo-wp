#!/usr/bin/env node

/**
 * Capo Upstream Version Checker & Auto-Updater
 *
 * Queries the npm registry for the latest release of @rviscomi/capo.js.
 * If a newer version is available, it automatically updates the dependency,
 * regenerates includes/class-capo-rules.php, and runs the parity test suite.
 */

import { execSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname, '..');

console.log('=== Checking Upstream @rviscomi/capo.js Updates ===\n');

// 1. Get current installed version
let currentVersion = 'unknown';
try {
  const pkg = JSON.parse(
    fs.readFileSync(path.join(rootDir, 'node_modules', '@rviscomi', 'capo.js', 'package.json'), 'utf8')
  );
  currentVersion = pkg.version;
} catch (e) {
  // Not installed yet
}

console.log(`  Current installed version: v${currentVersion}`);

// 2. Fetch latest version from npm registry
console.log('  Checking npm registry for latest version...');
let latestVersion = '';
try {
  latestVersion = execSync('npm view @rviscomi/capo.js version', { encoding: 'utf8' }).trim();
} catch (e) {
  console.warn('  ! Failed to query npm registry (offline or network issue).');
  process.exit(0);
}

console.log(`  Latest upstream version:   v${latestVersion}`);

if (currentVersion === latestVersion) {
  console.log('\n✨ Already up to date with upstream @rviscomi/capo.js!\n');
  process.exit(0);
}

console.log(`\n⬆️ Upgrading @rviscomi/capo.js from v${currentVersion} to v${latestVersion}...`);
execSync(`npm install @rviscomi/capo.js@${latestVersion}`, { cwd: rootDir, stdio: 'inherit' });

console.log('\n🔄 Regenerating PHP rules from upstream...');
execSync('npm run sync-rules', { cwd: rootDir, stdio: 'inherit' });

console.log('\n🧪 Running parity and unit tests...');
execSync('npm test', { cwd: rootDir, stdio: 'inherit' });

console.log(`\n🎉 Successfully upgraded to @rviscomi/capo.js v${latestVersion} and verified rules parity!\n`);
