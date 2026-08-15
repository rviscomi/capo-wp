<?php
/**
 * Capo Unit Test Bootstrap & WordPress Polyfills
 *
 * Sets up the testing environment, defines constants, provides
 * WordPress function polyfills, and requires all plugin class files.
 *
 * @package Capo
 */

defined( 'ABSPATH' ) || define( 'ABSPATH', dirname( __DIR__ ) . '/' );
defined( 'CAPO_VERSION' ) || define( 'CAPO_VERSION', '0.1.3' );
defined( 'CAPO_PLUGIN_FILE' ) || define( 'CAPO_PLUGIN_FILE', dirname( __DIR__ ) . '/capo.php' );
defined( 'CAPO_PLUGIN_DIR' ) || define( 'CAPO_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
defined( 'CAPO_PLUGIN_BASENAME' ) || define( 'CAPO_PLUGIN_BASENAME', 'capo/capo.php' );

// WordPress Polyfills for Standalone Test Execution.
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
		return filter_var( (string) $url, FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return 'https://example.com/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'https://example.com/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $text, $domain = 'default' ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( '_n' ) ) {
	function _n( $single, $plural, $number, $domain = 'default' ) {
		return 1 === (int) $number ? $single : $plural;
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( ...$args ) {
		if ( 2 === count( $args ) && is_array( $args[0] ) ) {
			$url = $args[1];
			$sep = false === strpos( $url, '?' ) ? '?' : '&';
			return $url . $sep . http_build_query( $args[0] );
		}
		if ( 3 === count( $args ) ) {
			$url = $args[2];
			$sep = false === strpos( $url, '?' ) ? '?' : '&';
			return $url . $sep . urlencode( (string) $args[0] ) . '=' . urlencode( (string) $args[1] );
		}
		return $args[ count( $args ) - 1 ];
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value, ...$args ) {
		return $value;
	}
}

// Require Plugin Class Files.
require_once __DIR__ . '/../includes/class-capo-rules.php';
require_once __DIR__ . '/../includes/class-capo-validator.php';
require_once __DIR__ . '/../includes/class-capo-parser.php';
require_once __DIR__ . '/../includes/class-capo-admin.php';
require_once __DIR__ . '/../includes/class-capo-site-health.php';
require_once __DIR__ . '/../includes/class-capo.php';
