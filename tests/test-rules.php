<?php
/**
 * Capo Rules Unit Tests
 *
 * Validates 1:1 parity with capo.js ElementWeights and detector rules.
 *
 * @package Capo
 */

define( 'CAPO_TEST_SUITE', true );

require_once __DIR__ . '/../includes/class-capo-rules.php';
require_once __DIR__ . '/../includes/class-capo-parser.php';

use Capo\Rules;
use Capo\Parser;

class Capo_Rules_Test {

	private static $passed = 0;
	private static $failed = 0;

	public static function run() {
		echo "=== Running Capo Rules Engine Tests ===\n\n";

		self::test_weight_hierarchy();
		self::test_meta_detection();
		self::test_title_detection();
		self::test_preconnect_detection();
		self::test_async_script_detection();
		self::test_import_styles_detection();
		self::test_sync_script_detection();
		self::test_sync_styles_detection();
		self::test_preload_detection();
		self::test_defer_script_detection();
		self::test_prefetch_prerender_detection();
		self::test_other_detection();

		echo "\n" . sprintf( "Results: %d passed, %d failed\n", self::$passed, self::$failed );
		if ( self::$failed > 0 ) {
			exit( 1 );
		}
	}

	private static function assert_weight( $html, $expected_weight, $description ) {
		$tokens = Parser::tokenize_head( $html );
		if ( empty( $tokens ) ) {
			self::$failed++;
			echo "❌ FAIL: [{$description}] - Could not tokenize: {$html}\n";
			return;
		}

		$actual_weight = $tokens[0]['weight'];
		if ( $actual_weight === $expected_weight ) {
			self::$passed++;
			echo "✅ PASS: [{$description}] => Weight {$actual_weight}\n";
		} else {
			self::$failed++;
			echo "❌ FAIL: [{$description}] Expected {$expected_weight}, got {$actual_weight} for: {$html}\n";
		}
	}

	private static function test_weight_hierarchy() {
		echo "Testing weight constants hierarchy...\n";
		$weights = array(
			Rules::WEIGHT_META,
			Rules::WEIGHT_TITLE,
			Rules::WEIGHT_PRECONNECT,
			Rules::WEIGHT_ASYNC_SCRIPT,
			Rules::WEIGHT_IMPORT_STYLES,
			Rules::WEIGHT_SYNC_SCRIPT,
			Rules::WEIGHT_SYNC_STYLES,
			Rules::WEIGHT_PRELOAD,
			Rules::WEIGHT_DEFER_SCRIPT,
			Rules::WEIGHT_PREFETCH_PRERENDER,
			Rules::WEIGHT_OTHER,
		);

		$expected = array( 10, 9, 8, 7, 6, 5, 4, 3, 2, 1, 0 );
		if ( $weights === $expected ) {
			self::$passed++;
			echo "✅ PASS: Weight constants match 10 -> 0\n";
		} else {
			self::$failed++;
			echo "❌ FAIL: Weight hierarchy mismatch\n";
		}
	}

	private static function test_meta_detection() {
		echo "\nTesting Weight 10 (Meta / Viewport / CSP / Base)...\n";
		self::assert_weight( '<base href="/">', 10, 'base element' );
		self::assert_weight( '<meta charset="utf-8">', 10, 'meta charset' );
		self::assert_weight( '<meta name="viewport" content="width=device-width, initial-scale=1">', 10, 'meta viewport' );
		self::assert_weight( '<meta name="Viewport" content="width=device-width">', 10, 'meta Viewport case insensitive' );
		self::assert_weight( '<meta http-equiv="Content-Security-Policy" content="default-src \'self\'">', 10, 'meta CSP' );
		self::assert_weight( '<meta http-equiv="origin-trial" content="token">', 10, 'meta origin-trial' );
		self::assert_weight( '<meta http-equiv="accept-ch" content="DPR">', 10, 'meta accept-ch' );
		self::assert_weight( '<meta http-equiv="x-dns-prefetch-control" content="on">', 10, 'meta x-dns-prefetch-control' );
	}

	private static function test_title_detection() {
		echo "\nTesting Weight 9 (Title)...\n";
		self::assert_weight( '<title>Rick Viscomi</title>', 9, 'title element' );
	}

	private static function test_preconnect_detection() {
		echo "\nTesting Weight 8 (Preconnect)...\n";
		self::assert_weight( '<link rel="preconnect" href="https://fonts.googleapis.com">', 8, 'preconnect link' );
		self::assert_weight( '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>', 8, 'preconnect with crossorigin' );
	}

	private static function test_async_script_detection() {
		echo "\nTesting Weight 7 (Async Script)...\n";
		self::assert_weight( '<script src="https://www.googletagmanager.com/gtag/js?id=UA-123" async></script>', 7, 'async script with src' );
	}

	private static function test_import_styles_detection() {
		echo "\nTesting Weight 6 (CSS @import Styles)...\n";
		self::assert_weight( '<style>@import url("https://fonts.googleapis.com/css2?family=Inter");</style>', 6, 'style with @import' );
		self::assert_weight( "<style>\nbody { margin: 0; }\n@import url('fonts.css');\n</style>", 6, 'style with embedded @import' );
	}

	private static function test_sync_script_detection() {
		echo "\nTesting Weight 5 (Sync / Inline Scripts)...\n";
		self::assert_weight( '<script src="https://rviscomi.dev/wp-includes/js/jquery/jquery.min.js"></script>', 5, 'sync external script' );
		self::assert_weight( '<script>window.dataLayer = window.dataLayer || [];</script>', 5, 'inline sync script' );
		self::assert_weight( '<script type="importmap">{"imports":{}}</script>', 5, 'importmap script' );
	}

	private static function test_sync_styles_detection() {
		echo "\nTesting Weight 4 (Sync Stylesheets & Style Blocks)...\n";
		self::assert_weight( '<link rel="stylesheet" href="style.css">', 4, 'stylesheet link' );
		self::assert_weight( '<style id="wp-block-library-inline-css">body { margin: 0; }</style>', 4, 'inline style block' );
	}

	private static function test_preload_detection() {
		echo "\nTesting Weight 3 (Preload / Modulepreload)...\n";
		self::assert_weight( '<link rel="preload" href="font.woff2" as="font" type="font/woff2" crossorigin>', 3, 'preload link' );
		self::assert_weight( '<link rel="modulepreload" href="app.js">', 3, 'modulepreload link' );
	}

	private static function test_defer_script_detection() {
		echo "\nTesting Weight 2 (Defer Script)...\n";
		self::assert_weight( '<script src="app.js" defer></script>', 2, 'script with defer' );
		self::assert_weight( '<script src="module.js" type="module"></script>', 2, 'script type=module without async' );
	}

	private static function test_prefetch_prerender_detection() {
		echo "\nTesting Weight 1 (Prefetch / DNS-Prefetch / Prerender)...\n";
		self::assert_weight( '<link rel="dns-prefetch" href="//fonts.googleapis.com">', 1, 'dns-prefetch link' );
		self::assert_weight( '<link rel="prefetch" href="/next-page.html">', 1, 'prefetch link' );
		self::assert_weight( '<link rel="prerender" href="/next-page.html">', 1, 'prerender link' );
	}

	private static function test_other_detection() {
		echo "\nTesting Weight 0 (Other Metadata / Icons / Robots / Schema)...\n";
		self::assert_weight( '<meta name="robots" content="index, follow">', 0, 'meta robots' );
		self::assert_weight( '<meta name="description" content="Personal blog">', 0, 'meta description' );
		self::assert_weight( '<link rel="canonical" href="https://rviscomi.dev/">', 0, 'link canonical' );
		self::assert_weight( '<link rel="icon" href="/favicon.ico">', 0, 'link icon' );
		self::assert_weight( '<link rel="alternate" type="application/rss+xml" href="/feed/">', 0, 'link RSS feed' );
		self::assert_weight( '<script type="application/ld+json">{"@context":"https://schema.org"}</script>', 0, 'schema JSON-LD script' );
		self::assert_weight( '<link rel="stylesheet" href="print.css" media="print">', 0, 'print stylesheet' );
		self::assert_weight( '<style media="print">body { color: #000; }</style>', 0, 'print style block' );
	}
}

Capo_Rules_Test::run();
