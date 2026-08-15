<?php
/**
 * Capo Head Validation Engine
 *
 * Implements HTML <head> hygiene and validation rules matching capo.js/validation.
 *
 * @package Capo
 * @author  Rick Viscomi
 * @license GPL-2.0-or-later
 */

namespace Capo;

defined( 'ABSPATH' ) || defined( 'CAPO_TEST_SUITE' ) || exit;

class Validator {

	/**
	 * Valid HTML <head> elements (matching capo.js VALID_HEAD_ELEMENTS).
	 *
	 * @var string[]
	 */
	const VALID_HEAD_ELEMENTS = array(
		'base',
		'link',
		'meta',
		'noscript',
		'script',
		'style',
		'template',
		'title',
	);

	/**
	 * Validate a raw <head> HTML string.
	 *
	 * @param string $head_content Inner content of <head>.
	 * @return array<int, array<string, mixed>> List of validation warnings.
	 */
	public static function validate_head( $head_content ) {
		$tokens = Parser::tokenize_head( $head_content );
		return self::validate_tokens( $tokens );
	}

	/**
	 * Validate an array of tokenized head elements.
	 *
	 * @param array<int, array<string, mixed>> $tokens Tokenized head elements from Parser.
	 * @return array<int, array<string, mixed>> List of validation warnings.
	 */
	public static function validate_tokens( array $tokens ) {
		$warnings = array();

		// Filter tokens to actual elements (ignoring pure comments/whitespace).
		$elements = array_values(
			array_filter(
				$tokens,
				function( $token ) {
					return ! empty( $token['tag_name'] );
				}
			)
		);

		// 1. Document-Level: Check <title>
		$title_elements = array_filter(
			$elements,
			function( $el ) {
				return 'title' === $el['tag_name'];
			}
		);
		$title_count = count( $title_elements );

		if ( 0 === $title_count ) {
			$warnings[] = array(
				'rule_id'      => 'require-title',
				'severity'     => 'error',
				'warning'      => 'Expected exactly 1 <title> element, found 0',
				'element_html' => '',
			);
		} elseif ( $title_count > 1 ) {
			$warnings[] = array(
				'rule_id'      => 'no-duplicate-title',
				'severity'     => 'warning',
				'warning'      => sprintf( 'Expected exactly 1 <title> element, found %d', $title_count ),
				'element_html' => '<title>',
			);
		}

		// 2. Document-Level: Check <base>
		$base_elements = array_filter(
			$elements,
			function( $el ) {
				return 'base' === $el['tag_name'];
			}
		);
		$base_count = count( $base_elements );

		if ( $base_count > 1 ) {
			$warnings[] = array(
				'rule_id'      => 'no-duplicate-base',
				'severity'     => 'error',
				'warning'      => sprintf( 'Expected at most 1 <base> element, found %d', $base_count ),
				'element_html' => '<base>',
			);
		}

		// 3. Document-Level: Check <meta name="viewport">
		$viewport_elements = array_values(
			array_filter(
				$elements,
				function( $el ) {
					if ( 'meta' !== $el['tag_name'] ) {
						return false;
					}
					$name = isset( $el['attrs']['name'] ) ? strtolower( trim( $el['attrs']['name'] ) ) : '';
					return 'viewport' === $name;
				}
			)
		);
		$viewport_count = count( $viewport_elements );

		if ( 0 === $viewport_count ) {
			$warnings[] = array(
				'rule_id'      => 'require-meta-viewport',
				'severity'     => 'warning',
				'warning'      => 'Expected exactly 1 <meta name=viewport> element, found 0',
				'element_html' => '',
			);
		} elseif ( $viewport_count > 1 ) {
			$warnings[] = array(
				'rule_id'      => 'valid-meta-viewport',
				'severity'     => 'warning',
				'warning'      => 'Another meta viewport element has already been declared. Having multiple viewport settings can lead to unexpected behavior.',
				'element_html' => isset( $viewport_elements[1]['raw_html'] ) ? $viewport_elements[1]['raw_html'] : '',
			);
		} else {
			// Validate viewport directives on single viewport tag.
			$viewport_warnings = self::validate_viewport_element( $viewport_elements[0] );
			foreach ( $viewport_warnings as $vw ) {
				$warnings[] = $vw;
			}
		}

		// 4. Element-Level Checks (Invalid tags, Preloads, etc.)
		foreach ( $elements as $element ) {
			$tag_name = $element['tag_name'];
			$attrs    = $element['attrs'];

			// Check for invalid head elements.
			if ( ! in_array( $tag_name, self::VALID_HEAD_ELEMENTS, true ) ) {
				$warnings[] = array(
					'rule_id'      => 'no-invalid-head-elements',
					'severity'     => 'error',
					'warning'      => sprintf( '%s elements are not allowed in the <head>', strtoupper( $tag_name ) ),
					'element_html' => $element['raw_html'],
				);
				continue;
			}

			// Check for font preloads missing crossorigin.
			if ( 'link' === $tag_name ) {
				$rel = isset( $attrs['rel'] ) ? strtolower( trim( $attrs['rel'] ) ) : '';
				$as  = isset( $attrs['as'] ) ? strtolower( trim( $attrs['as'] ) ) : '';

				if ( 'preload' === $rel && 'font' === $as && ! isset( $attrs['crossorigin'] ) ) {
					$warnings[] = array(
						'rule_id'      => 'valid-font-preload',
						'severity'     => 'warning',
						'warning'      => 'Font preloads must have the crossorigin attribute set, even for same-origin fonts.',
						'element_html' => $element['raw_html'],
					);
				}

				// Check for unnecessary preloads already referenced in the head.
				if ( in_array( $rel, array( 'preload', 'modulepreload' ), true ) && isset( $attrs['href'] ) ) {
					$href = $attrs['href'];
					$duplicate = self::find_matching_resource( $elements, $element, $href );
					if ( $duplicate ) {
						$warnings[] = array(
							'rule_id'      => 'no-unnecessary-preload',
							'severity'     => 'info',
							'warning'      => sprintf(
								'This preload has little to no effect. %s is already discoverable by another %s element.',
								esc_html( $href ),
								$duplicate['tag_name']
							),
							'element_html' => $element['raw_html'],
						);
					}
				}
			}
		}

		return $warnings;
	}

	/**
	 * Validate viewport content directives.
	 *
	 * @param array<string, mixed> $element Viewport meta element.
	 * @return array<int, array<string, mixed>>
	 */
	private static function validate_viewport_element( array $element ) {
		$warnings = array();
		$attrs    = $element['attrs'];

		if ( ! isset( $attrs['content'] ) || '' === trim( $attrs['content'] ) ) {
			$warnings[] = array(
				'rule_id'      => 'valid-meta-viewport',
				'severity'     => 'warning',
				'warning'      => 'Invalid viewport. The content attribute must be set.',
				'element_html' => $element['raw_html'],
			);
			return $warnings;
		}

		$content    = strtolower( trim( $attrs['content'] ) );
		$directives = array();
		foreach ( explode( ',', $content ) as $dir ) {
			$parts = explode( '=', $dir );
			if ( count( $parts ) === 2 ) {
				$directives[ trim( $parts[0] ) ] = trim( $parts[1] );
			}
		}

		if ( isset( $directives['width'] ) ) {
			$width = $directives['width'];
			if ( is_numeric( $width ) ) {
				$num = (float) $width;
				if ( $num < 1 || $num > 10000 ) {
					$warnings[] = array(
						'rule_id'      => 'valid-meta-viewport',
						'severity'     => 'warning',
						'warning'      => sprintf( 'Invalid width "%s". Numeric values must be between 1 and 10000.', $width ),
						'element_html' => $element['raw_html'],
					);
				}
			} elseif ( 'device-width' !== $width ) {
				$warnings[] = array(
					'rule_id'      => 'valid-meta-viewport',
					'severity'     => 'warning',
					'warning'      => sprintf( 'Invalid width "%s".', $width ),
					'element_html' => $element['raw_html'],
				);
			}
		}

		if ( isset( $directives['initial-scale'] ) ) {
			$scale = $directives['initial-scale'];
			if ( ! is_numeric( $scale ) ) {
				$warnings[] = array(
					'rule_id'      => 'valid-meta-viewport',
					'severity'     => 'warning',
					'warning'      => sprintf( 'Invalid initial zoom level "%s". Values must be numeric.', $scale ),
					'element_html' => $element['raw_html'],
				);
			} else {
				$num = (float) $scale;
				if ( $num < 0.1 || $num > 10 ) {
					$warnings[] = array(
						'rule_id'      => 'valid-meta-viewport',
						'severity'     => 'warning',
						'warning'      => sprintf( 'Invalid initial zoom level "%s". Values must be between 0.1 and 10.', $scale ),
						'element_html' => $element['raw_html'],
					);
				}
			}
		}

		return $warnings;
	}

	/**
	 * Find if another non-preload element in the head already references the same resource URL.
	 *
	 * @param array<int, array<string, mixed>> $elements All head elements.
	 * @param array<string, mixed>             $exclude  The preload element itself.
	 * @param string                           $href     The URL to match.
	 * @return array<string, mixed>|null Matching element or null.
	 */
	private static function find_matching_resource( array $elements, array $exclude, $href ) {
		foreach ( $elements as $el ) {
			if ( $el['index'] === $exclude['index'] ) {
				continue;
			}

			if ( 'link' === $el['tag_name'] ) {
				$rel = isset( $el['attrs']['rel'] ) ? strtolower( trim( $el['attrs']['rel'] ) ) : '';
				if ( in_array( $rel, array( 'preload', 'modulepreload' ), true ) ) {
					continue;
				}
				if ( isset( $el['attrs']['href'] ) && $el['attrs']['href'] === $href ) {
					return $el;
				}
			}

			if ( 'script' === $el['tag_name'] ) {
				if ( isset( $el['attrs']['src'] ) && $el['attrs']['src'] === $href ) {
					return $el;
				}
			}
		}

		return null;
	}
}
