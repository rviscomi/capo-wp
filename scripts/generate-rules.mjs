#!/usr/bin/env node

/**
 * Capo Rules PHP Generator
 *
 * Generates includes/class-capo-rules.php directly from @rviscomi/capo.js/rules.
 * Run via: npm run sync-rules
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  ElementWeights,
  META_HTTP_EQUIV_KEYWORDS,
} from '@rviscomi/capo.js/rules';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname, '..');
const targetFile = path.join(rootDir, 'includes', 'class-capo-rules.php');

// Read package version of @rviscomi/capo.js
const capoPackageJsonPath = path.join(rootDir, 'node_modules', '@rviscomi', 'capo.js', 'package.json');
let capoVersion = 'unknown';
try {
  const capoPkg = JSON.parse(fs.readFileSync(capoPackageJsonPath, 'utf8'));
  capoVersion = capoPkg.version || 'unknown';
} catch (e) {
  // Ignore fallback
}

// Generate weight constants
const weightEntries = Object.entries(ElementWeights);
const maxWeightKeyLen = Math.max(...weightEntries.map(([k]) => k.length));
const weightConstants = weightEntries
  .map(([key, val]) => `\tconst WEIGHT_${key.padEnd(maxWeightKeyLen)} = ${val};`)
  .join('\n');

// Generate keywords array
const keywordsList = META_HTTP_EQUIV_KEYWORDS
  .map((kw) => `\t\t'${kw}',`)
  .join('\n');

// Generate category labels switch cases
const categoryLabels = {
  WEIGHT_META: 'Critical Meta / Viewport',
  WEIGHT_TITLE: 'Title',
  WEIGHT_PRECONNECT: 'Preconnect',
  WEIGHT_ASYNC_SCRIPT: 'Async Script',
  WEIGHT_IMPORT_STYLES: 'CSS @import Styles',
  WEIGHT_SYNC_SCRIPT: 'Sync / Inline Script',
  WEIGHT_SYNC_STYLES: 'Stylesheet / Style Block',
  WEIGHT_PRELOAD: 'Preload / Modulepreload',
  WEIGHT_DEFER_SCRIPT: 'Defer Script',
  WEIGHT_PREFETCH_PRERENDER: 'Prefetch / Prerender',
  WEIGHT_OTHER: 'Other Metadata',
};

const categoryCases = Object.entries(categoryLabels)
  .filter(([c]) => c !== 'WEIGHT_OTHER')
  .map(([constName, label]) => `\t\t\tcase self::${constName}:\n\t\t\t\treturn '${label}';`)
  .join('\n');

const phpCode = `<?php
/**
 * Capo Rules Engine
 *
 * Implements the 1:1 classification rules and weight matrix from capo.js.
 * AUTO-GENERATED from @rviscomi/capo.js (v${capoVersion}).
 * DO NOT EDIT DIRECTLY. Run \`npm run sync-rules\` to regenerate.
 *
 * @package Capo
 * @author  Rick Viscomi
 * @license GPL-2.0-or-later
 */

namespace Capo;

defined( 'ABSPATH' ) || defined( 'CAPO_TEST_SUITE' ) || exit;

class Rules {

	/**
	 * Element weight constants (matching capo.js ElementWeights).
	 */
${weightConstants}

	/**
	 * Critical http-equiv header keywords (matching capo.js META_HTTP_EQUIV_KEYWORDS).
	 *
	 * @var string[]
	 */
	const META_HTTP_EQUIV_KEYWORDS = array(
${keywordsList}
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

		// JSON or non-executable script data (e.g. application/ld+json, importmap, speculationrules).
		if ( isset( $attrs['type'] ) && false !== stripos( $attrs['type'], 'json' ) ) {
			return false;
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
	 * Check if element is prefetch, dns-prefetch, or prerender.
	 *
	 * @param string $tag_name Tag name.
	 * @param array  $attrs    Element attributes.
	 * @return bool
	 */
	public static function is_prefetch_prerender( $tag_name, array $attrs ) {
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
${categoryCases}
			case self::WEIGHT_OTHER:
			default:
				return 'Other Metadata';
		}
	}
}
`;

fs.writeFileSync(targetFile, phpCode, 'utf8');
console.log(`✅ Successfully generated ${path.relative(rootDir, targetFile)} from @rviscomi/capo.js (v${capoVersion})`);
