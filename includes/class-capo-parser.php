<?php
/**
 * Capo Head Parser & Sorter
 *
 * Tokenizes HTML <head> elements, evaluates Capo priority weights,
 * and stably reorders them for optimal browser rendering performance.
 *
 * @package Capo
 * @author  Rick Viscomi
 * @license GPL-2.0-or-later
 */

namespace Capo;

defined( 'ABSPATH' ) || defined( 'CAPO_TEST_SUITE' ) || exit;

class Parser {

	/**
	 * Reorder the <head> elements in a full HTML document.
	 *
	 * @param string $html Full HTML document.
	 * @param array  $options Optional configuration flags.
	 * @return string Modified HTML document with reordered <head>.
	 */
	public static function reorder_head( $html, array $options = array() ) {
		if ( empty( $html ) || ! is_string( $html ) ) {
			return $html;
		}

		// Locate the <head>...</head> section.
		$head_pattern = '/<head(\s[^>]*)?>(.*?)<\/head>/is';
		if ( ! preg_match( $head_pattern, $html, $matches, PREG_OFFSET_CAPTURE ) ) {
			return $html;
		}

		$start_time     = microtime( true );
		$full_match_str = $matches[0][0];
		$match_offset   = $matches[0][1];
		$head_attrs_str = isset( $matches[1][0] ) ? $matches[1][0] : '';
		$head_content   = $matches[2][0];

		// Tokenize elements within <head>.
		$tokens = self::tokenize_head( $head_content );
		if ( empty( $tokens ) ) {
			return $html;
		}

		// Perform deterministic stable sort descending by weight.
		usort(
			$tokens,
			function( $a, $b ) {
				if ( $a['weight'] !== $b['weight'] ) {
					return ( $b['weight'] <=> $a['weight'] );
				}
				return ( $a['index'] <=> $b['index'] );
			}
		);

		// Reconstruct <head> content.
		$reordered_chunks = array();
		$element_count    = 0;

		foreach ( $tokens as $token ) {
			$chunk = '';
			if ( ! empty( $token['leading_comment'] ) ) {
				$chunk .= trim( $token['leading_comment'] ) . "\n\t";
			}
			$chunk .= trim( $token['raw_html'] );
			$reordered_chunks[] = "\t" . $chunk;

			if ( ! empty( $token['tag_name'] ) ) {
				$element_count++;
			}
		}

		$elapsed_ms = round( ( microtime( true ) - $start_time ) * 1000, 2 );

		// Build debug/comment header if requested.
		$debug_comment = '';
		$include_debug = isset( $options['debug_comment'] ) ? (bool) $options['debug_comment'] : true;
		if ( $include_debug ) {
			$debug_comment = "\t<!-- Reordered by Capo (https://rviscomi.github.io/capo.js/) [" .
				$element_count . ' elements optimized in ' . $elapsed_ms . "ms] -->\n";
		}

		$new_head_content = "\n" . $debug_comment . implode( "\n", $reordered_chunks ) . "\n";
		$new_head_tag     = '<head' . $head_attrs_str . '>' . $new_head_content . '</head>';

		return substr_replace( $html, $new_head_tag, $match_offset, strlen( $full_match_str ) );
	}

	/**
	 * Tokenize head content into discrete HTML tags and comments.
	 *
	 * @param string $head_content Inner content of <head>.
	 * @return array<int, array<string, mixed>> List of tokens with weights and positions.
	 */
	public static function tokenize_head( $head_content ) {
		$tokens           = array();
		$pending_comments = array();
		$token_index      = 0;

		// Regex pattern to extract top-level tokens in <head>.
		$pattern = '/
			(?P<comment><!--[\s\S]*?-->)
			|
			(?P<container_tag><(?P<ctag_name>script|style|title|template|noscript)(?P<ctag_attrs>\s[^>]*)?>[\s\S]*?<\/(?P=ctag_name)\s*>)
			|
			(?P<void_tag><(?P<vtag_name>meta|link|base)(?P<vtag_attrs>\s[^>]*)?\s*\/?>)
			|
			(?P<generic_tag><(?P<gtag_name>[a-zA-Z0-9:-]+)(?P<gtag_attrs>\s[^>]*)?>(?:[\s\S]*?<\/(?P=gtag_name)\s*>|\s*\/?>)?)
		/ix';

		if ( ! preg_match_all( $pattern, $head_content, $matches, PREG_SET_ORDER ) ) {
			return $tokens;
		}

		foreach ( $matches as $match ) {
			if ( ! empty( $match['comment'] ) ) {
				$pending_comments[] = trim( $match['comment'] );
				continue;
			}

			$tag_name       = '';
			$attrs_str      = '';
			$raw_html       = '';
			$inner_content  = '';

			if ( ! empty( $match['container_tag'] ) ) {
				$tag_name  = strtolower( $match['ctag_name'] );
				$attrs_str = isset( $match['ctag_attrs'] ) ? $match['ctag_attrs'] : '';
				$raw_html  = $match['container_tag'];

				// Extract inner content.
				$inner_content = preg_replace( '/^<[a-zA-Z0-9:-]+[^>]*>|<\/[a-zA-Z0-9:-]+\s*>$/is', '', $raw_html );
			} elseif ( ! empty( $match['void_tag'] ) ) {
				$tag_name  = strtolower( $match['vtag_name'] );
				$attrs_str = isset( $match['vtag_attrs'] ) ? $match['vtag_attrs'] : '';
				$raw_html  = $match['void_tag'];
			} elseif ( ! empty( $match['generic_tag'] ) ) {
				$tag_name  = strtolower( $match['gtag_name'] );
				$attrs_str = isset( $match['gtag_attrs'] ) ? $match['gtag_attrs'] : '';
				$raw_html  = $match['generic_tag'];
			}

			if ( empty( $tag_name ) ) {
				continue;
			}

			$attrs  = self::parse_attributes( $attrs_str );
			$weight = Rules::get_weight( $tag_name, $attrs, $inner_content );

			$leading_comment = ! empty( $pending_comments ) ? implode( "\n\t", $pending_comments ) : '';
			$pending_comments = array();

			$tokens[] = array(
				'index'           => $token_index++,
				'tag_name'        => $tag_name,
				'attrs'           => $attrs,
				'raw_html'        => $raw_html,
				'inner_content'   => $inner_content,
				'leading_comment' => $leading_comment,
				'weight'          => $weight,
			);
		}

		// If there are trailing comments with no subsequent tag, preserve them as weight 0 items.
		if ( ! empty( $pending_comments ) ) {
			foreach ( $pending_comments as $comment ) {
				$tokens[] = array(
					'index'           => $token_index++,
					'tag_name'        => '',
					'attrs'           => array(),
					'raw_html'        => $comment,
					'inner_content'   => '',
					'leading_comment' => '',
					'weight'          => Rules::WEIGHT_OTHER,
				);
			}
		}

		return $tokens;
	}

	/**
	 * Parse HTML attribute string into key-value map.
	 *
	 * @param string $attrs_str Raw attribute string from opening tag.
	 * @return array<string, string> Lowercase attribute names and values.
	 */
	public static function parse_attributes( $attrs_str ) {
		$attrs = array();
		if ( empty( trim( $attrs_str ) ) ) {
			return $attrs;
		}

		$pattern = '/([a-zA-Z0-9_:-]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+)))?/i';
		if ( preg_match_all( $pattern, $attrs_str, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$name = strtolower( $match[1] );
				if ( isset( $match[2] ) && '' !== $match[2] ) {
					$val = $match[2];
				} elseif ( isset( $match[3] ) && '' !== $match[3] ) {
					$val = $match[3];
				} elseif ( isset( $match[4] ) && '' !== $match[4] ) {
					$val = $match[4];
				} else {
					$val = '';
				}
				$attrs[ $name ] = $val;
			}
		}

		return $attrs;
	}
}
