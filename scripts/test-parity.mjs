#!/usr/bin/env node

/**
 * Capo Cross-Language Parity Test Harness
 *
 * Validates that the PHP implementation in includes/class-capo-rules.php
 * and includes/class-capo-validator.php produces 100% identical weight
 * calculations and validation warnings to @rviscomi/capo.js.
 */

import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { JSDOM } from 'jsdom';
import { BrowserAdapter } from '@rviscomi/capo.js/adapters/browser';
import { getWeight } from '@rviscomi/capo.js/rules';
import { getValidationWarnings } from '@rviscomi/capo.js/validation';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname, '..');
const rulesPhpPath = path.join(rootDir, 'includes', 'class-capo-rules.php');
const validatorPhpPath = path.join(rootDir, 'includes', 'class-capo-validator.php');
const parserPhpPath = path.join(rootDir, 'includes', 'class-capo-parser.php');

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

const validationHeads = [
  {
    name: 'Clean valid head',
    head: '<meta charset="utf-8">\n<meta name="viewport" content="width=device-width, initial-scale=1">\n<title>Clean</title>\n<link rel="stylesheet" href="/main.css">',
  },
  {
    name: 'Duplicate Title Head',
    head: '<meta charset="utf-8">\n<meta name="viewport" content="width=device-width, initial-scale=1">\n<title>Title 1</title>\n<title>Title 2</title>',
  },
  {
    name: 'Missing Title Head',
    head: '<meta charset="utf-8">\n<meta name="viewport" content="width=device-width, initial-scale=1">',
  },
  {
    name: 'Duplicate Base Head',
    head: '<title>Base Test</title>\n<meta name="viewport" content="width=device-width, initial-scale=1">\n<base href="/a/">\n<base href="/b/">',
  },
  {
    name: 'Invalid Tag in Head (div, img)',
    head: '<title>Invalid Elements</title>\n<meta name="viewport" content="width=device-width, initial-scale=1">\n<div>Invalid div</div>\n<img src="/track.png">',
    baseHeadForDom: '<title>Invalid Elements</title>\n<meta name="viewport" content="width=device-width, initial-scale=1">',
    extraElements: ['div', 'img'],
  },
  {
    name: 'Missing Viewport Head',
    head: '<title>No Viewport</title>',
  },
];

console.log('=== Running Capo Cross-Language Parity Test ===\n');

const phpBin = getPhpBinary();
const adapter = new BrowserAdapter();

// 1. Evaluate element weights in JS
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

// 2. Evaluate validation warnings in JS
const validationEvaluations = [];
for (const vTest of validationHeads) {
  const htmlToParse = vTest.baseHeadForDom ? vTest.baseHeadForDom : vTest.head;
  const dom = new JSDOM(`<!DOCTYPE html><html><head>${htmlToParse}</head><body></body></html>`, {
    url: 'https://example.com',
  });
  if (vTest.extraElements) {
    for (const tag of vTest.extraElements) {
      dom.window.document.head.appendChild(dom.window.document.createElement(tag));
    }
  }
  const jsWarnings = getValidationWarnings(dom.window.document.head, adapter).map((w) => w.ruleId);
  validationEvaluations.push({
    name: vTest.name,
    head: vTest.head,
    jsRuleIds: jsWarnings,
  });
}

// 3. Evaluate in PHP via single child process
const phpPayload = JSON.stringify({
  weights: evaluations,
  validations: validationEvaluations,
});

const phpCode = `
defined('ABSPATH') || define('ABSPATH', true);
require_once '${rulesPhpPath.replace(/\\/g, '/')}';
require_once '${validatorPhpPath.replace(/\\/g, '/')}';
require_once '${parserPhpPath.replace(/\\/g, '/')}';

$input = json_decode(stream_get_contents(STDIN), true);
$results = array(
    'weights'     => array(),
    'validations' => array(),
);

foreach ($input['weights'] as $item) {
    $results['weights'][] = \\Capo\\Rules::get_weight(
        $item['tagName'],
        $item['attrs'],
        $item['content']
    );
}

foreach ($input['validations'] as $vItem) {
    $warnings = \\Capo\\Validator::validate_head($vItem['head']);
    $ruleIds = array_map(function($w) { return $w['rule_id']; }, $warnings);
    $results['validations'][] = $ruleIds;
}

echo json_encode($results);
`;

const phpOutput = execFileSync(phpBin, ['-r', phpCode], {
  input: phpPayload,
  encoding: 'utf8'
});

const phpResults = JSON.parse(phpOutput);

let passedCount = 0;
let failedCount = 0;

console.log('-- Part 1: Priority Weight Classification Parity --');
evaluations.forEach((item, idx) => {
  const phpWeight = phpResults.weights[idx];
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

console.log('\n-- Part 2: Head Validation Rules Parity --');
validationEvaluations.forEach((vItem, idx) => {
  const phpRuleIds = phpResults.validations[idx];
  // Sort rule IDs to compare rule sets regardless of detection order
  const jsSorted = [...vItem.jsRuleIds].sort();
  const phpSorted = [...phpRuleIds].sort();
  const isMatch = JSON.stringify(jsSorted) === JSON.stringify(phpSorted);

  if (isMatch) {
    passedCount++;
    console.log(`✅ MATCH: [Validation: ${vItem.name}] Rules: ${JSON.stringify(phpSorted)}`);
  } else {
    failedCount++;
    console.error(`❌ MISMATCH: [Validation: ${vItem.name}]`);
    console.error(`   JS Rules:  ${JSON.stringify(jsSorted)}`);
    console.error(`   PHP Rules: ${JSON.stringify(phpSorted)}`);
  }
});

const totalCases = evaluations.length + validationEvaluations.length;
console.log(`\nParity Results: ${passedCount} matched, ${failedCount} mismatched out of ${totalCases} test cases.`);

if (failedCount > 0) {
  process.exit(1);
} else {
  console.log('🎉 100% Cross-Language Parity Verified between @rviscomi/capo.js and capo-wp!\n');
}
