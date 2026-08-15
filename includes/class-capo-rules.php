<?php
/**
 * Capo Rules Engine
 *
 * Implements the 1:1 classification rules and weight matrix from capo.js.
 * AUTO-GENERATED from @rviscomi/capo.js (v2.2.1).
 * DO NOT EDIT DIRECTLY. Run `npm run sync-rules` to regenerate.
 *
 * @package Capo
 * @author  Rick Viscomi
 * @license GPL-2.0-or-later
 */

namespace Capo;

defined( 'ABSPATH' ) || exit;

class Rules {

	/**
	 * Element weight constants (matching capo.js ElementWeights).
	 */
	const WEIGHT_META               = 10;
	const WEIGHT_TITLE              = 9;
	const WEIGHT_PRECONNECT         = 8;
	const WEIGHT_ASYNC_SCRIPT       = 7;
	const WEIGHT_IMPORT_STYLES      = 6;
	const WEIGHT_SYNC_SCRIPT        = 5;
	const WEIGHT_SYNC_STYLES        = 4;
	const WEIGHT_PRELOAD            = 3;
	const WEIGHT_DEFER_SCRIPT       = 2;
	const WEIGHT_PREFETCH_PRERENDER = 1;
	const WEIGHT_OTHER              = 0;

	/**
	 * Critical http-equiv header keywords (matching capo.js META_HTTP_EQUIV_KEYWORDS).
	 *
	 * @var string[]
	 */
	const META_HTTP_EQUIV_KEYWORDS = array(
		'accept-ch',
		'content-security-policy',
		'content-type',
		'default-style',
		'delegate-ch',
		'origin-trial',
		'x-dns-prefetch-control',
	);

	/**
	 * Calculate the Capo priority weight for a head element.
	 *
	 * @param string               $tag_name Tag name (lowercase).
	 * @param array<string,string> $attrs    Associative array of lowercase attribute names and values.
	 * @param string               $content  Inner text/HTML content of the element.
	 * @return int Priority weight (0 to 10).
	 */
	public static function get_weight( $tag_name, array $attrs, $content = '' ) {
		$tag_name = strtolower( trim( $tag_name ) );

		if ( self::is_meta( $tag_name, $attrs ) ) {
			return self::WEIGHT_META;
		}

		if ( self::is_title( $tag_name ) ) {
			return self::WEIGHT_TITLE;
		}

		if ( self::is_preconnect( $tag_name, $attrs ) ) {
			return self::WEIGHT_PRECONNECT;
		}

		if ( self::is_async_script( $tag_name, $attrs ) ) {
			return self::WEIGHT_ASYNC_SCRIPT;
		}

		if ( self::is_import_styles( $tag_name, $attrs, $content ) ) {
			return self::WEIGHT_IMPORT_STYLES;
		}

		if ( self::is_sync_script( $tag_name, $attrs ) ) {
			return self::WEIGHT_SYNC_SCRIPT;
		}

		if ( self::is_sync_styles( $tag_name, $attrs ) ) {
			return self::WEIGHT_SYNC_STYLES;
		}

		if ( self::is_preload( $tag_name, $attrs ) ) {
			return self::WEIGHT_PRELOAD;
		}

		if ( self::is_defer_script( $tag_name, $attrs ) ) {
			return self::WEIGHT_DEFER_SCRIPT;
		}

		if ( self::is_prefetch_prerender( $tag_name, $attrs ) ) {
			return self::WEIGHT_PREFETCH_PRERENDER;
		}

		return self::WEIGHT_OTHER;
	}

	/**
	 * Check if element is a critical meta or base tag.
	 *
	 * @param string $tag_name Tag name.
	 * @param array  $attrs    Element attributes.
	 * @return bool
	 */
	public static function is_meta( $tag_name, array $attrs ) {
		if ( 'base' === $tag_name ) {
			return true;
		}

		if ( 'meta' !== $tag_name ) {
			return false;
		}

		if ( isset( $attrs['charset'] ) ) {
			return true;
		}

		if ( isset( $attrs['name'] ) && 'viewport' === strtolower( trim( $attrs['name'] ) ) ) {
			return true;
		}

		if ( isset( $attrs['http-equiv'] ) ) {
			$http_equiv = strtolower( trim( $attrs['http-equiv'] ) );
			return in_array( $http_equiv, self::META_HTTP_EQUIV_KEYWORDS, true );
		}

		return false;
	}

	/**
	 * Check if element is a title tag.
	 *
	 * @param string $tag_name Tag name.
	 * @return bool
	 */
	public static function is_title( $tag_name ) {
		return 'title' === $tag_name;
	}

	/**
	 * Check if element is a preconnect link.
	 *
	 * @param string $tag_name Tag name.
	 * @param array  $attrs    Element attributes.
	 * @return bool
	 */
	public static function is_preconnect( $tag_name, array $attrs ) {
		if ( 'link' !== $tag_name ) {
			return false;
		}

		$rel = isset( $attrs['rel'] ) ? strtolower( trim( $attrs['rel'] ) ) : '';
		return 'preconnect' === $rel;
	}

	/**
	 * Check if element is an async script.
	 *
	 * @param string $tag_name Tag name.
	 * @param array  $attrs    Element attributes.
	 * @return bool
	 */
	public static function is_async_script( $tag_name, array $attrs ) {
		return 'script' === $tag_name
			&& isset( $attrs['src'] )
			&& isset( $attrs['async'] );
	}

	/**
	 * Check if element is a style element containing @import rules.
	 *
	 * @param string $tag_name Tag name.
	 * @param array  $attrs    Element attributes.
	 * @param string $content  Inner CSS content.
	 * @return bool
	 */
	public static function is_import_styles( $tag_name, array $attrs, $content ) {
		if ( 'style' !== $tag_name ) {
			return false;
		}

		if ( isset( $attrs['media'] ) && 'print' === strtolower( trim( $attrs['media'] ) ) ) {
			return false;
		}

		return (bool) preg_match( '/@import/i', $content );
	}

	/**
	 * Check if element is a synchronous script.
	 *
	 * @param string $tag_name Tag name.
	 * @param array  $attrs    Element attributes.
	 * @return bool
	 */
	public static function is_sync_script( $tag_name, array $attrs ) {
		if ( 'script' !== $tag_name ) {
			return false;
		}

		// Deferred script.
		if ( isset( $attrs['src'] ) && isset( $attrs['defer'] ) ) {
			return false;
		}

		// Module script with src.
		if ( isset( $attrs['src'] ) && isset( $attrs['type'] ) && 'module' === strtolower( trim( $attrs['type'] ) ) ) {
			return false;
		}

		// Async script with src.
		if ( isset( $attrs['src'] ) && isset( $attrs['async'] ) ) {
			return false;
		}

		// JSON or non-executable script data (e.g. application/ld+json, speculationrules).
		if ( isset( $attrs['type'] ) ) {
			$type = strtolower( trim( $attrs['type'] ) );
			if ( false !== stripos( $type, 'json' ) || 'speculationrules' === $type ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check if element is synchronous stylesheet or style block.
	 *
	 * @param string $tag_name Tag name.
	 * @param array  $attrs    Element attributes.
	 * @return bool
	 */
	public static function is_sync_styles( $tag_name, array $attrs ) {
		if ( 'style' === $tag_name ) {
			if ( isset( $attrs['media'] ) && 'print' === strtolower( trim( $attrs['media'] ) ) ) {
				return false;
			}
			return true;
		}

		if ( 'link' === $tag_name ) {
			$rel = isset( $attrs['rel'] ) ? strtolower( trim( $attrs['rel'] ) ) : '';
			if ( 'stylesheet' === $rel ) {
				if ( isset( $attrs['media'] ) && 'print' === strtolower( trim( $attrs['media'] ) ) ) {
					return false;
				}
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if element is a preload or modulepreload link.
	 *
	 * @param string $tag_name Tag name.
	 * @param array  $attrs    Element attributes.
	 * @return bool
	 */
	public static function is_preload( $tag_name, array $attrs ) {
		if ( 'link' !== $tag_name ) {
			return false;
		}

		$rel = isset( $attrs['rel'] ) ? strtolower( trim( $attrs['rel'] ) ) : '';
		return in_array( $rel, array( 'preload', 'modulepreload' ), true );
	}

	/**
	 * Check if element is a deferred script.
	 *
	 * @param string $tag_name Tag name.
	 * @param array  $attrs    Element attributes.
	 * @return bool
	 */
	public static function is_defer_script( $tag_name, array $attrs ) {
		if ( 'script' !== $tag_name ) {
			return false;
		}

		if ( ! isset( $attrs['src'] ) ) {
			return false;
		}

		if ( isset( $attrs['defer'] ) ) {
			return true;
		}

		$type = isset( $attrs['type'] ) ? strtolower( trim( $attrs['type'] ) ) : '';
		if ( 'module' === $type ) {
			return ! isset( $attrs['async'] );
		}

		return false;
	}

	/**
	 * Check if element is prefetch, dns-prefetch, prerender, or speculationrules script.
	 *
	 * @param string $tag_name Tag name.
	 * @param array  $attrs    Element attributes.
	 * @return bool
	 */
	public static function is_prefetch_prerender( $tag_name, array $attrs ) {
		if ( 'script' === $tag_name ) {
			$type = isset( $attrs['type'] ) ? strtolower( trim( $attrs['type'] ) ) : '';
			return 'speculationrules' === $type;
		}

		if ( 'link' !== $tag_name ) {
			return false;
		}

		$rel = isset( $attrs['rel'] ) ? strtolower( trim( $attrs['rel'] ) ) : '';
		return in_array( $rel, array( 'prefetch', 'dns-prefetch', 'prerender' ), true );
	}

	/**
	 * Get readable label for a weight category.
	 *
	 * @param int $weight Weight score.
	 * @return string Category label.
	 */
	public static function get_category_name( $weight ) {
		switch ( $weight ) {
			case self::WEIGHT_META:
				return 'Critical Meta / Viewport';
			case self::WEIGHT_TITLE:
				return 'Title';
			case self::WEIGHT_PRECONNECT:
				return 'Preconnect';
			case self::WEIGHT_ASYNC_SCRIPT:
				return 'Async Script';
			case self::WEIGHT_IMPORT_STYLES:
				return 'CSS @import Styles';
			case self::WEIGHT_SYNC_SCRIPT:
				return 'Sync / Inline Script';
			case self::WEIGHT_SYNC_STYLES:
				return 'Stylesheet / Style Block';
			case self::WEIGHT_PRELOAD:
				return 'Preload / Modulepreload';
			case self::WEIGHT_DEFER_SCRIPT:
				return 'Defer Script';
			case self::WEIGHT_PREFETCH_PRERENDER:
				return 'Prefetch / Prerender';
			case self::WEIGHT_OTHER:
			default:
				return 'Other Metadata';
		}
	}
}
