#!/usr/bin/env node

/**
 * Capo WordPress Plugin Release Packager
 *
 * Creates a production-ready dist/capo.zip archive with all development
 * dependencies, tests, and build scripts excluded.
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { execFileSync } from 'node:child_process';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname, '..');
const distDir = path.join(rootDir, 'dist');
const zipFile = path.join(distDir, 'capo.zip');
const tempStageDir = path.join(distDir, 'stage');
const pluginStageDir = path.join(tempStageDir, 'capo');

console.log('=== Packaging Capo for WordPress Release ===\n');

// Clean dist and stage directories
if (fs.existsSync(distDir)) {
  fs.rmSync(distDir, { recursive: true, force: true });
}
fs.mkdirSync(pluginStageDir, { recursive: true });

// Production files/directories to include
const filesToCopy = [
  'capo.php',
  'LICENSE',
  'readme.txt',
  'README.md',
];

const dirsToCopy = [
  'includes',
];

// Copy files
for (const file of filesToCopy) {
  const src = path.join(rootDir, file);
  const dest = path.join(pluginStageDir, file);
  if (fs.existsSync(src)) {
    fs.copyFileSync(src, dest);
    console.log(`  + Added file: ${file}`);
  } else {
    console.warn(`  ! Warning: File not found: ${file}`);
  }
}

// Copy directories
for (const dir of dirsToCopy) {
  const src = path.join(rootDir, dir);
  const dest = path.join(pluginStageDir, dir);
  if (fs.existsSync(src)) {
    fs.cpSync(src, dest, { recursive: true });
    console.log(`  + Added directory: ${dir}/`);
  } else {
    console.warn(`  ! Warning: Directory not found: ${dir}/`);
  }
}

// Create the zip archive
try {
  execFileSync('zip', ['-r', '-q', zipFile, 'capo'], {
    cwd: tempStageDir,
    stdio: 'inherit',
  });
} catch (error) {
  console.error('Failed to create zip archive via zip command:', error);
  process.exit(1);
}

// Clean up temporary stage directory
fs.rmSync(tempStageDir, { recursive: true, force: true });

const stats = fs.statSync(zipFile);
const sizeKb = (stats.size / 1024).toFixed(2);

console.log(`\n🎉 Successfully built release archive: dist/capo.zip (${sizeKb} KB)\n`);
