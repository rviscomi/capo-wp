<?php
/**
 * Capo Validator Unit Test Suite
 *
 * Tests the Capo\Validator class rules against various valid and invalid <head> structures.
 */

require_once __DIR__ . '/bootstrap.php';

$passed = 0;
$failed = 0;

function assert_test( $condition, $message ) {
	global $passed, $failed;
	if ( $condition ) {
		echo "✅ PASS: $message\n";
		$passed++;
	} else {
		echo "❌ FAIL: $message\n";
		$failed++;
	}
}

echo "=== Running Capo Validator Tests ===\n\n";

// Test 1: Clean valid head
$valid_head = '<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Valid Head</title>
<link rel="stylesheet" href="/style.css">';
$warnings = Capo\Validator::validate_head( $valid_head );
assert_test( count( $warnings ) === 0, 'Clean valid head returns 0 warnings' );

// Test 2: Missing title
$missing_title_head = '<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">';
$warnings = Capo\Validator::validate_head( $missing_title_head );
assert_test( count( $warnings ) === 1 && $warnings[0]['rule_id'] === 'require-title', 'Missing title triggers require-title' );

// Test 3: Duplicate title
$dup_title_head = '<title>First Title</title>
<title>Second Title</title>
<meta name="viewport" content="width=device-width, initial-scale=1">';
$warnings = Capo\Validator::validate_head( $dup_title_head );
assert_test( count( $warnings ) === 1 && $warnings[0]['rule_id'] === 'no-duplicate-title', 'Duplicate title triggers no-duplicate-title' );

// Test 4: Missing viewport
$missing_vp_head = '<title>Test</title>';
$warnings = Capo\Validator::validate_head( $missing_vp_head );
assert_test( count( $warnings ) === 1 && $warnings[0]['rule_id'] === 'require-meta-viewport', 'Missing viewport triggers require-meta-viewport' );

// Test 5: Duplicate viewport
$dup_vp_head = '<title>Test</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="viewport" content="width=600">';
$warnings = Capo\Validator::validate_head( $dup_vp_head );
assert_test( count( $warnings ) === 1 && $warnings[0]['rule_id'] === 'valid-meta-viewport', 'Duplicate viewport triggers valid-meta-viewport' );

// Test 6: Duplicate base
$dup_base_head = '<title>Test</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="/app/">
<base href="/cdn/">';
$warnings = Capo\Validator::validate_head( $dup_base_head );
assert_test( count( $warnings ) === 1 && $warnings[0]['rule_id'] === 'no-duplicate-base', 'Duplicate base triggers no-duplicate-base' );

// Test 7: Invalid elements in head (e.g. <div> or <img>)
$invalid_tag_head = '<title>Test</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<div>Accidental div inside head</div>
<img src="/tracker.gif">';
$warnings = Capo\Validator::validate_head( $invalid_tag_head );
$invalid_rules = array_filter( $warnings, function( $w ) { return $w['rule_id'] === 'no-invalid-head-elements'; } );
assert_test( count( $invalid_rules ) === 2, 'Invalid elements in head trigger no-invalid-head-elements' );

// Test 8: Font preload without crossorigin
$font_preload_head = '<title>Test</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preload" href="/font.woff2" as="font" type="font/woff2">';
$warnings = Capo\Validator::validate_head( $font_preload_head );
$font_rules = array_filter( $warnings, function( $w ) { return $w['rule_id'] === 'valid-font-preload'; } );
assert_test( count( $font_rules ) === 1, 'Font preload without crossorigin triggers valid-font-preload' );

// Test 9: Font preload with crossorigin
$valid_font_head = '<title>Test</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preload" href="/font.woff2" as="font" type="font/woff2" crossorigin>';
$warnings = Capo\Validator::validate_head( $valid_font_head );
assert_test( count( $warnings ) === 0, 'Font preload with crossorigin passes with 0 warnings' );

// Test 10: Unnecessary preload for stylesheet already in head
$unnecessary_preload_head = '<title>Test</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preload" href="/main.css" as="style">
<link rel="stylesheet" href="/main.css">';
$warnings = Capo\Validator::validate_head( $unnecessary_preload_head );
$unnec_rules = array_filter( $warnings, function( $w ) { return $w['rule_id'] === 'no-unnecessary-preload'; } );
assert_test( count( $unnec_rules ) === 1, 'Unnecessary preload triggers no-unnecessary-preload' );

// Test 11: Expired Origin Trial
$expired_ot_head = '<title>Test</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="origin-trial" content="AuNyVoVDAnYrBa2cL89WmgDSi1Os1UAt4SmcY1vXSJKDlIlBNfD4SEpIfg3LNDexEWv6N2kHnJ17MT4cVmRhQgIAAABueyJvcmlnaW4iOiJodHRwczovL3J2aXNjb21pLmdpdGh1Yi5pbzo0NDMiLCJmZWF0dXJlIjoiQmFja0ZvcndhcmRDYWNoZU5vdFJlc3RvcmVkUmVhc29ucyIsImV4cGlyeSI6MTY5MTUzOTE5OX0=">';
$warnings = Capo\Validator::validate_head( $expired_ot_head );
$ot_rules = array_filter( $warnings, function( $w ) { return $w['rule_id'] === 'no-invalid-origin-trial'; } );
assert_test( count( $ot_rules ) >= 1, 'Expired origin trial triggers no-invalid-origin-trial' );

echo "\nValidator Results: $passed passed, $failed failed\n";

if ( $failed > 0 ) {
	exit( 1 );
}
