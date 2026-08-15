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
defined( 'CAPO_PLUGIN_BASENAME' ) || define( 'CAPO_PLUGIN_BASENAME', 'capo/capo.php' );

require_once __DIR__ . '/../includes/class-capo-rules.php';
require_once __DIR__ . '/../includes/class-capo-validator.php';
require_once __DIR__ . '/../includes/class-capo-parser.php';
require_once __DIR__ . '/../includes/class-capo-admin.php';

// Polyfills for standalone testing.
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return filter_var( $url, FILTER_SANITIZE_URL );
	}
}
if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return 'https://example.com/wp-admin/' . $path;
	}
}

use Capo\Parser;
use Capo\Admin;

class Capo_Parser_Test {

	private static $passed = 0;
	private static $failed = 0;

	public static function run() {
		echo "=== Running Capo Parser & Sorter Tests ===\n\n";

		self::test_attribute_parsing();
		self::test_quoted_attributes_with_greater_than();
		self::test_conditional_comments_and_cdata();
		self::test_large_payloads_and_malformed_html();
		self::test_stable_sorting();
		self::test_comment_association();
		self::test_full_head_reordering();
		self::test_admin_bar_injection();

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

	private static function test_quoted_attributes_with_greater_than() {
		echo "\nTesting tags with '>' inside quoted attributes...\n";

		$html = '<meta name="description" content="Click > here & explore > now"><script src="/app.js" data-condition="count > 5" async></script>';
		$tokens = Parser::tokenize_head( $html );

		if ( count( $tokens ) === 2 &&
			'Click > here & explore > now' === $tokens[0]['attrs']['content'] &&
			'count > 5' === $tokens[1]['attrs']['data-condition'] &&
			7 === $tokens[1]['weight'] ) {
			self::$passed++;
			echo "✅ PASS: Quoted attributes containing '>' preserve full content and correct tag boundaries\n";
		} else {
			self::$failed++;
			echo "❌ FAIL: Quoted attributes containing '>' failed to tokenize properly\n";
		}
	}

	private static function test_conditional_comments_and_cdata() {
		echo "\nTesting conditional comments and CDATA blocks...\n";

		$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
	<!--[if lt IE 9]>
	<script src="html5shiv.js"></script>
	<![endif]-->
	<![if !IE]>
	<link rel="stylesheet" href="modern.css">
	<![endif]>
	<![CDATA[
	console.log("cdata block");
	]]>
	<meta charset="utf-8">
</head>
<body></body>
</html>
HTML;

		$reordered = Parser::reorder_head( $html, array( 'debug_comment' => false ) );

		if ( false !== strpos( $reordered, 'html5shiv.js' ) &&
			false !== strpos( $reordered, '<![if !IE]>' ) &&
			false !== strpos( $reordered, '<![CDATA[' ) &&
			false !== strpos( $reordered, '<meta charset="utf-8">' ) ) {
			self::$passed++;
			echo "✅ PASS: Conditional comments and CDATA sections preserved in output\n";
		} else {
			self::$failed++;
			echo "❌ FAIL: Conditional comments or CDATA sections lost during reordering\n";
		}
	}

	private static function test_large_payloads_and_malformed_html() {
		echo "\nTesting large payloads and malformed HTML handling...\n";

		// Test 1: Large inline script (100KB)
		$large_script = '<script>var data = "' . str_repeat( 'a', 100000 ) . '";</script>';
		$head = '<meta charset="utf-8">' . $large_script;
		$tokens = Parser::tokenize_head( $head );

		if ( count( $tokens ) === 2 && 5 === $tokens[1]['weight'] ) {
			self::$passed++;
			echo "✅ PASS: 100KB inline script tokenized without regex backtracking error\n";
		} else {
			self::$failed++;
			echo "❌ FAIL: Large script payload failed tokenization\n";
		}

		// Test 2: Unclosed/malformed head tags fallback gracefully
		$malformed_doc = '<html><head>No closing head tag<meta charset="utf-8">';
		$result = Parser::reorder_head( $malformed_doc );
		if ( $result === $malformed_doc ) {
			self::$passed++;
			echo "✅ PASS: Malformed document without closing </head> returned unmodified\n";
		} else {
			self::$failed++;
			echo "❌ FAIL: Malformed document handling failed\n";
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

	private static function test_admin_bar_injection() {
		echo "\nTesting Admin Toolbar HTML injection and backreference safety...\n";

		$html = '<div id="wpadminbar"><ul id="wp-admin-bar-root-default"><li id="wp-admin-bar-capo-diagnostics" class="menupop"><a class="ab-item" href="https://example.com/wp-admin/options-general.php?page=capo">Capo</a></li></ul></div>';

		// Analysis containing special characters like $1, \1, and special symbols that could trigger backreference bugs.
		$analysis = array(
			'element_count' => 12,
			'elapsed_ms'    => 1.45,
			'warnings'      => array(
				array(
					'rule_id' => 'no-invalid-head-elements',
					'warning' => 'Invalid tag with $1 and \1 pattern inside warning text',
				),
			),
		);

		$injected = Admin::inject_admin_bar_html( $html, $analysis );

		if ( false !== strpos( $injected, 'Invalid tag with $1 and \1 pattern' ) &&
			false !== strpos( $injected, '⚡ Reordered 12 elements in 1.45ms' ) &&
			false !== strpos( $injected, '⚠️ Capo (1)' ) ) {
			self::$passed++;
			echo "✅ PASS: Admin Toolbar injected successfully without backreference corruption\n";
		} else {
			self::$failed++;
			echo "❌ FAIL: Admin Toolbar injection failed or corrupted special characters\n";
		}
	}
}

Capo_Parser_Test::run();
