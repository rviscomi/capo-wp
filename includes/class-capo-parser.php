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

defined( 'ABSPATH' ) || exit;

class Parser {

	/**
	 * Last execution analysis result.
	 *
	 * @var array<string, mixed>|null
	 */
	private static $last_analysis = null;

	/**
	 * Get the last execution analysis result.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function get_last_analysis() {
		return self::$last_analysis;
	}

	/**
	 * Reset the last execution analysis result.
	 */
	public static function reset_analysis() {
		self::$last_analysis = null;
	}

	/**
	 * Reorder the <head> elements in a full HTML document.
	 *
	 * @param string                    $html Full HTML document.
	 * @param array                     $options Optional configuration flags.
	 * @param array<string, mixed>|null $analysis Optional output parameter populated with execution metrics.
	 * @return string Modified HTML document with reordered <head>.
	 */
	public static function reorder_head( $html, array $options = array(), &$analysis = null ) {
		$analysis = null;

		if ( empty( $html ) || ! is_string( $html ) ) {
			return $html;
		}

		// Locate the <head>...</head> section.
		$head_pattern = '/<head(?P<head_attrs>(?:\s+[^"\'\/>=\s]+(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'=<>`]+))?)*\s*)>(?P<head_content>[\s\S]*?)<\/head>/i';
		if ( ! preg_match( $head_pattern, $html, $matches, PREG_OFFSET_CAPTURE ) ) {
			return $html;
		}

		$start_time     = microtime( true );
		$full_match_str = $matches[0][0];
		$match_offset   = $matches[0][1];
		$head_attrs_str = isset( $matches['head_attrs'][0] ) ? $matches['head_attrs'][0] : '';
		$head_content   = isset( $matches['head_content'][0] ) ? $matches['head_content'][0] : '';

		// Tokenize elements within <head>.
		$tokens = self::tokenize_head( $head_content );
		if ( empty( $tokens ) ) {
			return $html;
		}

		// Validate head tokens.
		$warnings = Validator::validate_tokens( $tokens );

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

		// Record analysis.
		$result_analysis = array(
			'element_count' => $element_count,
			'elapsed_ms'    => $elapsed_ms,
			'warnings'      => $warnings,
			'tokens'        => $tokens,
		);

		self::$last_analysis = $result_analysis;
		$analysis            = $result_analysis;

		// Build debug/comment header if requested.
		$debug_comment = '';
		$include_debug = isset( $options['debug_comment'] ) ? (bool) $options['debug_comment'] : false;
		if ( $include_debug ) {
			$warning_suffix = '';
			if ( ! empty( $warnings ) ) {
				$warning_suffix = sprintf( ' | %d warning%s', count( $warnings ), count( $warnings ) === 1 ? '' : 's' );
			}
			$debug_comment = "\t<!-- Reordered by Capo [" .
				$element_count . ' elements optimized in ' . $elapsed_ms . 'ms' . $warning_suffix . "] -->\n";
		}

		$new_head_content = "\n" . $debug_comment . implode( "\n", $reordered_chunks ) . "\n";
		$new_head_tag     = '<head' . $head_attrs_str . '>' . $new_head_content . '</head>';

		return substr_replace( $html, $new_head_tag, $match_offset, strlen( $full_match_str ) );
	}

	/**
	 * Tokenize head content into discrete HTML tags, conditional comments, CDATA, and comments.
	 *
	 * @param string $head_content Inner content of <head>.
	 * @return array<int, array<string, mixed>> List of tokens with weights and positions.
	 */
	public static function tokenize_head( $head_content ) {
		$tokens           = array();
		$pending_comments = array();
		$token_index      = 0;

		// Regex pattern to extract top-level tokens in <head>.
		// Supports quoted attribute values containing '>', CDATA blocks, and IE conditional comments.
		$pattern = '/
			(?P<comment><!--[\s\S]*?-->)
			|
			(?P<conditional_comment><!\[if[\s\S]*?<!\[endif\]>|<!\s*\[if[^\]]*\]>|<!\s*\[endif\]>)
			|
			(?P<cdata><!\[CDATA\[[\s\S]*?\]\]>)
			|
			(?P<container_tag><(?P<ctag_name>script|style|title|template|noscript)(?P<ctag_attrs>(?:\s+[^"\'\/>=\s]+(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'=<>`]+))?)*\s*)>(?P<ctag_content>[\s\S]*?)<\/(?P=ctag_name)\s*>)
			|
			(?P<void_tag><(?P<vtag_name>meta|link|base)(?P<vtag_attrs>(?:\s+[^"\'\/>=\s]+(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'=<>`]+))?)*\s*)\s*\/?>)
			|
			(?P<generic_tag><(?P<gtag_name>[a-zA-Z0-9:-]+)(?P<gtag_attrs>(?:\s+[^"\'\/>=\s]+(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'=<>`]+))?)*\s*)\s*(?:\/>|>(?P<gtag_inner>[\s\S]*?)<\/(?P=gtag_name)\s*>|>))
		/ix';

		$res = preg_match_all( $pattern, $head_content, $matches, PREG_SET_ORDER );
		if ( false === $res || PREG_NO_ERROR !== preg_last_error() ) {
			return $tokens;
		}

		foreach ( $matches as $match ) {
			if ( ! empty( $match['comment'] ) ) {
				$pending_comments[] = trim( $match['comment'] );
				continue;
			}
			if ( ! empty( $match['conditional_comment'] ) ) {
				$pending_comments[] = trim( $match['conditional_comment'] );
				continue;
			}
			if ( ! empty( $match['cdata'] ) ) {
				$pending_comments[] = trim( $match['cdata'] );
				continue;
			}

			$tag_name      = '';
			$attrs_str     = '';
			$raw_html      = '';
			$inner_content = '';

			if ( ! empty( $match['container_tag'] ) ) {
				$tag_name      = strtolower( $match['ctag_name'] );
				$attrs_str     = isset( $match['ctag_attrs'] ) ? $match['ctag_attrs'] : '';
				$raw_html      = $match['container_tag'];
				$inner_content = isset( $match['ctag_content'] ) ? $match['ctag_content'] : '';
			} elseif ( ! empty( $match['void_tag'] ) ) {
				$tag_name      = strtolower( $match['vtag_name'] );
				$attrs_str     = isset( $match['vtag_attrs'] ) ? $match['vtag_attrs'] : '';
				$raw_html      = $match['void_tag'];
				$inner_content = '';
			} elseif ( ! empty( $match['generic_tag'] ) ) {
				$tag_name      = strtolower( $match['gtag_name'] );
				$attrs_str     = isset( $match['gtag_attrs'] ) ? $match['gtag_attrs'] : '';
				$raw_html      = $match['generic_tag'];
				$inner_content = isset( $match['gtag_inner'] ) ? $match['gtag_inner'] : '';
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

		$pattern = '/([^\s"\'=<>`]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+)))?/i';
		if ( preg_match_all( $pattern, $attrs_str, $matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL ) ) {
			foreach ( $matches as $match ) {
				$name = strtolower( $match[1] );
				if ( null !== $match[2] ) {
					$val = $match[2];
				} elseif ( null !== $match[3] ) {
					$val = $match[3];
				} elseif ( null !== $match[4] ) {
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
