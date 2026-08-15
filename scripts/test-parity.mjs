#!/usr/bin/env node

/**
 * Capo Cross-Language Parity Test Harness
 *
 * Validates that the PHP implementation in includes/class-capo-rules.php
 * produces 100% identical weight calculations to @rviscomi/capo.js across
 * all HTML element configurations and edge cases.
 */

import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { JSDOM } from 'jsdom';
import { BrowserAdapter } from '@rviscomi/capo.js/adapters/browser';
import { getWeight } from '@rviscomi/capo.js/rules';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname, '..');
const rulesPhpPath = path.join(rootDir, 'includes', 'class-capo-rules.php');

// Locate PHP binary
function getPhpBinary() {
  const candidates = ['php', '/opt/homebrew/bin/php', '/usr/local/bin/php', '/usr/bin/php'];
  for (const bin of candidates) {
    try {
      execFileSync(bin, ['-v'], { stdio: 'ignore' });
      return bin;
    } catch (e) {
      // Continue
    }
  }
  throw new Error('PHP binary not found in PATH or standard directories.');
}

const testSnippets = [
  // Tier 10: Meta / Base
  '<base href="/">',
  '<meta charset="utf-8">',
  '<meta name="viewport" content="width=device-width, initial-scale=1">',
  '<meta name="VIEWPORT" content="width=device-width">',
  '<meta http-equiv="Content-Security-Policy" content="default-src \'self\'">',
  '<meta http-equiv="content-security-policy" content="default-src \'self\'">',
  '<meta http-equiv="origin-trial" content="abc123token">',
  '<meta http-equiv="accept-ch" content="Sec-CH-UA-Model">',
  '<meta http-equiv="delegate-ch" content="Sec-CH-UA-Model">',
  '<meta http-equiv="default-style" content="standard">',
  '<meta http-equiv="content-type" content="text/html; charset=UTF-8">',
  '<meta http-equiv="x-dns-prefetch-control" content="on">',

  // Tier 9: Title
  '<title>My Awesome WordPress Site</title>',

  // Tier 8: Preconnect
  '<link rel="preconnect" href="https://fonts.googleapis.com">',
  '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>',
  '<link rel="PRECONNECT" href="https://cdn.example.com">',

  // Tier 7: Async Script
  '<script src="/app.js" async></script>',
  '<script async src="/analytics.js"></script>',

  // Tier 6: Import Styles
  '<style>@import url("/critical.css");</style>',
  '<style>/* Comments */\n@import "fonts.css";\nbody { color: red; }</style>',

  // Tier 5: Sync / Inline Scripts
  '<script src="/bundle.js"></script>',
  '<script>console.log("inline init");</script>',
  '<script type="text/javascript" src="/lib.js"></script>',

  // Tier 4: Sync Stylesheets & Style Blocks
  '<link rel="stylesheet" href="/style.css">',
  '<link rel="STYLESHEET" href="/theme.css">',
  '<style>body { margin: 0; background: #fff; }</style>',

  // Tier 3: Preload / Modulepreload
  '<link rel="preload" href="/hero.webp" as="image">',
  '<link rel="modulepreload" href="/module.js">',
  '<link rel="PRELOAD" href="/font.woff2" as="font" crossorigin>',

  // Tier 2: Defer Script
  '<script src="/main.js" defer></script>',
  '<script src="/main.js" type="module"></script>',

  // Tier 1: Prefetch / DNS-Prefetch / Prerender
  '<link rel="dns-prefetch" href="//cdn.example.com">',
  '<link rel="prefetch" href="/next-page.html">',
  '<link rel="prerender" href="/future.html">',

  // Tier 0: Other Metadata / Non-blocking
  '<meta name="description" content="Meta description test">',
  '<meta name="robots" content="index, follow">',
  '<meta property="og:title" content="OpenGraph Title">',
  '<link rel="canonical" href="https://example.com/page">',
  '<link rel="icon" href="/favicon.ico">',
  '<link rel="alternate" type="application/rss+xml" title="Feed" href="/feed/">',
  '<style media="print">body { color: black; }</style>',
  '<link rel="stylesheet" href="/print.css" media="print">',
  '<script type="application/ld+json">{"@context": "https://schema.org"}</script>',
  '<script type="importmap">{"imports": {}}</script>',
  '<script type="speculationrules">{}</script>'
];

console.log('=== Running Capo Cross-Language Parity Test ===\n');

const phpBin = getPhpBinary();
const adapter = new BrowserAdapter();

// Evaluate each snippet in JS
const evaluations = [];

for (const snippet of testSnippets) {
  const dom = new JSDOM(`<!DOCTYPE html><html><head>${snippet}</head><body></body></html>`, {
    url: 'https://example.com',
  });
  const element = dom.window.document.head.firstElementChild;
  if (!element) {
    throw new Error(`Failed to parse snippet into DOM element: ${snippet}`);
  }

  const tagName = adapter.getTagName(element);
  const attrs = {};
  for (const attrName of adapter.getAttributeNames(element)) {
    attrs[attrName.toLowerCase()] = adapter.getAttribute(element, attrName);
  }
  const content = adapter.getTextContent(element);
  const jsWeight = getWeight(element, adapter);

  evaluations.push({
    snippet,
    tagName,
    attrs,
    content,
    jsWeight
  });
}

// Evaluate in PHP via single child process
const phpPayload = JSON.stringify(evaluations);
const phpCode = `
define('CAPO_TEST_SUITE', true);
require_once '${rulesPhpPath.replace(/\\/g, '/')}';

$input = json_decode(stream_get_contents(STDIN), true);
$results = array();

foreach ($input as $index => $item) {
    $phpWeight = \\Capo\\Rules::get_weight(
        $item['tagName'],
        $item['attrs'],
        $item['content']
    );
    $results[] = $phpWeight;
}

echo json_encode($results);
`;

const phpOutput = execFileSync(phpBin, ['-r', phpCode], {
  input: phpPayload,
  encoding: 'utf8'
});

const phpWeights = JSON.parse(phpOutput);

let passedCount = 0;
let failedCount = 0;

evaluations.forEach((item, idx) => {
  const phpWeight = phpWeights[idx];
  const isMatch = item.jsWeight === phpWeight;

  if (isMatch) {
    passedCount++;
    console.log(`✅ MATCH: [Weight ${item.jsWeight.toString().padStart(2)}] ${item.snippet}`);
  } else {
    failedCount++;
    console.error(`❌ MISMATCH: ${item.snippet}`);
    console.error(`   JS Capo weight:  ${item.jsWeight}`);
    console.error(`   PHP Capo weight: ${phpWeight}`);
  }
});

console.log(`\nParity Results: ${passedCount} matched, ${failedCount} mismatched out of ${evaluations.length} test cases.`);

if (failedCount > 0) {
  process.exit(1);
} else {
  console.log('🎉 100% Cross-Language Parity Verified between @rviscomi/capo.js and capo-wp!\n');
}
