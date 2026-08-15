<?php
/**
 * Capo Parser & Sorter Unit Tests
 *
 * Validates tokenization, attribute extraction, stable sorting,
 * and full document head reordering.
 *
 * @package Capo
 */

defined( 'ABSPATH' ) || define( 'ABSPATH', __DIR__ );

require_once __DIR__ . '/../includes/class-capo-rules.php';
require_once __DIR__ . '/../includes/class-capo-validator.php';
require_once __DIR__ . '/../includes/class-capo-parser.php';

use Capo\Parser;

class Capo_Parser_Test {

	private static $passed = 0;
	private static $failed = 0;

	public static function run() {
		echo "=== Running Capo Parser & Sorter Tests ===\n\n";

		self::test_attribute_parsing();
		self::test_stable_sorting();
		self::test_comment_association();
		self::test_full_head_reordering();

		echo "\n" . sprintf( "Results: %d passed, %d failed\n", self::$passed, self::$failed );
		if ( self::$failed > 0 ) {
			exit( 1 );
		}
	}

	private static function test_attribute_parsing() {
		echo "Testing attribute parsing...\n";

		$attrs = Parser::parse_attributes( 'name="viewport" content="width=device-width, initial-scale=1" id="vp"' );
		if ( isset( $attrs['name'], $attrs['content'], $attrs['id'] ) && 'viewport' === $attrs['name'] ) {
			self::$passed++;
			echo "✅ PASS: Double-quoted attributes parsed correctly\n";
		} else {
			self::$failed++;
			echo "❌ FAIL: Double-quoted attributes failed\n";
		}

		$boolean_attrs = Parser::parse_attributes( 'src="app.js" async defer' );
		if ( isset( $boolean_attrs['src'], $boolean_attrs['async'], $boolean_attrs['defer'] ) && '' === $boolean_attrs['async'] ) {
			self::$passed++;
			echo "✅ PASS: Boolean attributes parsed correctly\n";
		} else {
			self::$failed++;
			echo "❌ FAIL: Boolean attributes failed\n";
		}
	}

	private static function test_stable_sorting() {
		echo "\nTesting stable sorting of equal-weight elements...\n";

		$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
	<link rel="stylesheet" href="theme.css" id="theme-css">
	<link rel="stylesheet" href="custom.css" id="custom-css">
	<link rel="stylesheet" href="fonts.css" id="fonts-css">
</head>
<body></body>
</html>
HTML;

		$reordered = Parser::reorder_head( $html, array( 'debug_comment' => false ) );

		$theme_pos  = strpos( $reordered, 'theme-css' );
		$custom_pos = strpos( $reordered, 'custom-css' );
		$fonts_pos  = strpos( $reordered, 'fonts-css' );

		if ( false !== $theme_pos && false !== $custom_pos && false !== $fonts_pos &&
			$theme_pos < $custom_pos && $custom_pos < $fonts_pos ) {
			self::$passed++;
			echo "✅ PASS: Same-weight stylesheets maintain exact cascade order\n";
		} else {
			self::$failed++;
			echo "❌ FAIL: Stable sort altered relative stylesheet order\n";
		}
	}

	private static function test_comment_association() {
		echo "\nTesting comment association with following element...\n";

		$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
	<meta name="description" content="test">
	<!-- Google Tag Manager -->
	<script src="https://www.googletagmanager.com/gtag/js?id=UA-123" async></script>
</head>
<body></body>
</html>
HTML;

		$reordered = Parser::reorder_head( $html, array( 'debug_comment' => false ) );

		// The async script (weight 7) should move before meta description (weight 0), and its comment should move with it.
		$gtm_comment_pos = strpos( $reordered, '<!-- Google Tag Manager -->' );
		$gtm_script_pos  = strpos( $reordered, 'googletagmanager.com' );
		$meta_desc_pos   = strpos( $reordered, 'name="description"' );

		if ( false !== $gtm_comment_pos && false !== $gtm_script_pos && false !== $meta_desc_pos &&
			$gtm_comment_pos < $gtm_script_pos && $gtm_script_pos < $meta_desc_pos ) {
			self::$passed++;
			echo "✅ PASS: Descriptive comment moved with its associated script\n";
		} else {
			self::$failed++;
			echo "❌ FAIL: Comment association failed\n";
		}
	}

	private static function test_full_head_reordering() {
		echo "\nTesting full real-world disorganized head reordering...\n";

		$html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
	<meta name="robots" content="index, follow">
	<link rel="canonical" href="https://rviscomi.dev/">
	<script type="application/ld+json">{"@context":"https://schema.org"}</script>
	<title>Rick Viscomi</title>
	<link rel="dns-prefetch" href="//fonts.googleapis.com">
	<style id="theme-styles">body { margin: 0; }</style>
	<link rel="stylesheet" href="main.css">
	<script src="gtag.js" async></script>
	<script src="jquery.min.js"></script>
	<script src="ytprefs.min.js" defer></script>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="modulepreload" href="interactivity.min.js">
</head>
<body>
	<h1>Hello World</h1>
</body>
</html>
HTML;

		$reordered = Parser::reorder_head( $html, array( 'debug_comment' => true ) );

		// Extract inner <head> content from $reordered
		preg_match( '/<head[^>]*>(.*?)<\/head>/is', $reordered, $head_matches );
		$head_inner = isset( $head_matches[1] ) ? $head_matches[1] : '';

		// Extract ordered tags to verify sequence.
		$tokens = Parser::tokenize_head( $head_inner );
		$weights = array();
		foreach ( $tokens as $t ) {
			if ( ! empty( $t['tag_name'] ) ) {
				$weights[] = $t['weight'];
			}
		}

		// Verify weights are monotonically non-increasing (e.g. 10, 10, 9, 7, 5, 4, 4, 3, 2, 1, 0, 0, 0).
		$is_sorted = true;
		for ( $i = 0; $i < count( $weights ) - 1; $i++ ) {
			if ( $weights[ $i ] < $weights[ $i + 1 ] ) {
				$is_sorted = false;
				break;
			}
		}

		if ( $is_sorted && count( $weights ) === 13 ) {
			self::$passed++;
			echo "✅ PASS: Real-world disorganized head successfully sorted into Capo spectrum: [" . implode( ', ', $weights ) . "]\n";
		} else {
			self::$failed++;
			echo "❌ FAIL: Full head reordering produced unsorted spectrum: [" . implode( ', ', $weights ) . "]\n";
		}

		// Verify debug comment presence
		if ( false !== strpos( $reordered, '<!-- Reordered by Capo' ) ) {
			self::$passed++;
			echo "✅ PASS: Debug performance comment injected\n";
		} else {
			self::$failed++;
			echo "❌ FAIL: Debug comment missing\n";
		}
	}
}

Capo_Parser_Test::run();
