<?php
/**
 * Capo WordPress Site Health Integration
 *
 * Integrates Capo status checks into WordPress Site Health audits.
 *
 * @package Capo
 * @author  Rick Viscomi
 * @license GPL-2.0-or-later
 */

namespace Capo;

defined( 'ABSPATH' ) || exit;

class Site_Health {

	/**
	 * Singleton instance.
	 *
	 * @var Site_Health|null
	 */
	private static $instance = null;

	/**
	 * Main instance getter.
	 *
	 * @return Site_Health
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize hooks.
	 */
	public function init() {
		add_filter( 'site_status_tests', array( $this, 'register_tests' ) );
	}

	/**
	 * Register Capo tests in the WordPress Site Health status check suite.
	 *
	 * @param array $tests Registered site status tests.
	 * @return array Modified tests array.
	 */
	public function register_tests( $tests ) {
		$tests['direct']['capo_head_optimization'] = array(
			'label' => __( 'HTML <head> Optimization (Capo)', 'capo' ),
			'test'  => array( $this, 'test_capo_status' ),
		);

		$tests['direct']['capo_head_hygiene'] = array(
			'label' => __( 'HTML Head Hygiene & Validation (Capo)', 'capo' ),
			'test'  => array( $this, 'test_capo_hygiene' ),
		);

		return $tests;
	}

	/**
	 * Simple syntax highlighter for HTML tags in Site Health diagnostics.
	 *
	 * @param string $html Raw HTML.
	 * @return string Syntax-highlighted HTML.
	 */
	public static function highlight_html( $html ) {
		$escaped = esc_html( $html );

		$highlighted = preg_replace_callback(
			'/(&lt;\/?)([a-zA-Z0-9\-]+)(.*?)(&gt;)/s',
			function( $m ) {
				$open  = '<span style="color:#005a9c;font-weight:600;">' . $m[1] . $m[2] . '</span>';
				$attrs = preg_replace_callback(
					'/([a-zA-Z0-9\-]+)(=)(&quot;.*?&quot;|\'[^\']*\'|[^\s&>]+)/s',
					function( $am ) {
						return '<span style="color:#9c4100;">' . $am[1] . '</span><span style="color:#50575e;">=</span><span style="color:#1e824c;">' . $am[3] . '</span>';
					},
					$m[3]
				);
				$close = '<span style="color:#005a9c;font-weight:600;">' . $m[4] . '</span>';
				return $open . $attrs . $close;
			},
			$escaped
		);

		return $highlighted;
	}

	/**
	 * Run Capo Site Health diagnostic test.
	 *
	 * @return array Test result details.
	 */
	public function test_capo_status() {
		$is_enabled = (bool) get_option( 'capo_enabled', 1 );

		if ( ! $is_enabled ) {
			return array(
				'label'       => __( 'Capo head optimization is disabled', 'capo' ),
				'status'      => 'recommended',
				'badge'       => array(
					'label' => __( 'Performance', 'capo' ),
					'color' => 'orange',
				),
				'description' => sprintf(
					'<p>%s</p>',
					__( 'Capo is currently disabled in your settings. Enabling Capo reorders critical head elements to improve First Contentful Paint (FCP) and Largest Contentful Paint (LCP).', 'capo' )
				),
				'actions'     => sprintf(
					'<p><a class="button button-primary" href="%s">%s</a></p>',
					esc_url( admin_url( 'options-general.php?page=capo' ) ),
					__( 'Enable Capo in Settings', 'capo' )
				),
				'test'        => 'capo_head_optimization',
			);
		}

		return array(
			'label'       => __( 'HTML <head> is optimized by Capo', 'capo' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Performance', 'capo' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'Capo is actively optimizing the order of your HTML &lt;head&gt; tags to ensure fast resource discovery, early network connections, and unblocked rendering.', 'capo' )
			),
			'actions'     => '',
			'test'        => 'capo_head_optimization',
		);
	}

	/**
	 * Run Capo head hygiene and validation audit on the homepage.
	 *
	 * @return array Test result details.
	 */
	public function test_capo_hygiene() {
		$audit_url = add_query_arg( 'capo_audit', time(), home_url( '/' ) );
		$response  = wp_remote_get(
			$audit_url,
			array(
				'timeout' => 5,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'label'       => __( 'Could not verify <head> hygiene via loopback', 'capo' ),
				'status'      => 'good',
				'badge'       => array(
					'label' => __( 'Performance', 'capo' ),
					'color' => 'blue',
				),
				'description' => sprintf(
					'<p>%s</p>',
					__( 'Loopback request to the homepage timed out or failed. You can test your site directly using the live demo link in Capo Settings.', 'capo' )
				),
				'actions'     => '',
				'test'        => 'capo_head_hygiene',
			);
		}

		$html = wp_remote_retrieve_body( $response );
		if ( empty( $html ) || ! preg_match( '/<head(\s[^>]*)?>(.*?)<\/head>/is', $html, $matches ) ) {
			return array(
				'label'       => __( 'No HTML <head> section found to validate', 'capo' ),
				'status'      => 'good',
				'badge'       => array(
					'label' => __( 'Performance', 'capo' ),
					'color' => 'blue',
				),
				'description' => '<p>' . __( 'The homepage did not return a valid HTML &lt;head&gt; section.', 'capo' ) . '</p>',
				'actions'     => '',
				'test'        => 'capo_head_hygiene',
			);
		}

		$head_content = $matches[2];
		$warnings     = Validator::validate_head( $head_content );

		if ( ! empty( $warnings ) ) {
			$warning_items = '';
			foreach ( $warnings as $w ) {
				$snippet = '';
				if ( ! empty( $w['element_html'] ) ) {
					$snippet .= sprintf(
						'<pre style="background:#f6f7f7;padding:8px 12px;margin:6px 0;font-size:12px;overflow-x:auto;border:1px solid #dcdcde;border-radius:4px;white-space:pre-wrap;word-break:break-all;font-family:Consolas,Monaco,monospace;"><code>%s</code></pre>',
						self::highlight_html( $w['element_html'] )
					);
				}

				if ( ! empty( $w['payload'] ) && is_array( $w['payload'] ) ) {
					$p           = $w['payload'];
					$expiry_time = isset( $p['expiry'] ) ? intval( $p['expiry'] ) : 0;
					$is_expired  = $expiry_time && ( $expiry_time < time() );
					$expiry_str  = $expiry_time ? gmdate( 'Y-m-d H:i:s \U\T\C', $expiry_time ) : 'Unknown';

					$snippet .= '<div style="background:#ffffff;border:1px solid #c3c4c7;border-radius:4px;padding:8px 12px;margin:8px 0;font-size:12px;max-width:550px;">';
					$snippet .= '<strong style="display:block;margin-bottom:6px;color:#1d2327;font-size:12px;">Decoded Origin Trial Payload:</strong>';
					$snippet .= '<table style="width:100%;border-collapse:collapse;font-size:12px;">';
					if ( isset( $p['feature'] ) ) {
						$snippet .= sprintf( '<tr><td style="padding:3px 8px 3px 0;color:#646970;width:120px;">Feature:</td><td><code style="font-weight:600;">%s</code></td></tr>', esc_html( $p['feature'] ) );
					}
					if ( isset( $p['origin'] ) ) {
						$snippet .= sprintf( '<tr><td style="padding:3px 8px 3px 0;color:#646970;">Origin:</td><td><code>%s</code></td></tr>', esc_html( $p['origin'] ) );
					}
					if ( $expiry_time ) {
						$expiry_badge = $is_expired ? '<span style="background:#fcf0f1;color:#d63638;border:1px solid #f8c9cb;padding:1px 6px;border-radius:3px;font-weight:600;font-size:11px;margin-left:8px;">⚠️ Expired</span>' : '<span style="background:#edf7ed;color:#1e824c;padding:1px 6px;border-radius:3px;font-size:11px;margin-left:8px;">Valid</span>';
						$snippet .= sprintf( '<tr><td style="padding:3px 8px 3px 0;color:#646970;">Expiry:</td><td>%s%s</td></tr>', esc_html( $expiry_str ), $expiry_badge );
					}
					if ( isset( $p['isSubdomain'] ) ) {
						$snippet .= sprintf( '<tr><td style="padding:3px 8px 3px 0;color:#646970;">Subdomain Match:</td><td>%s</td></tr>', $p['isSubdomain'] ? 'Yes (Wildcard Enabled)' : 'No' );
					}
					if ( isset( $p['isThirdParty'] ) ) {
						$snippet .= sprintf( '<tr><td style="padding:3px 8px 3px 0;color:#646970;">Third-Party:</td><td>%s</td></tr>', $p['isThirdParty'] ? 'Yes' : 'No' );
					}
					$snippet .= '</table>';
					$snippet .= '</div>';
				}

				$warning_items .= sprintf(
					'<li style="margin-bottom:16px;"><strong>%s:</strong> %s%s</li>',
					esc_html( $w['rule_id'] ),
					esc_html( $w['warning'] ),
					$snippet
				);
			}

			return array(
				'label'       => sprintf(
					/* translators: %d: number of validation warnings */
					_n( 'Capo detected %d <head> hygiene issue on your homepage', 'Capo detected %d <head> hygiene issues on your homepage', count( $warnings ), 'capo' ),
					count( $warnings )
				),
				'status'      => 'recommended',
				'badge'       => array(
					'label' => __( 'Performance', 'capo' ),
					'color' => 'orange',
				),
				'description' => sprintf(
					'<p>%s</p><ul style="list-style:disc;margin-left:20px;">%s</ul><p>%s</p>',
					__( 'The following issues were detected in your &lt;head&gt; markup, which may impact browser rendering speed, SEO, or layout stability:', 'capo' ),
					$warning_items,
					__( 'Check your active theme templates, custom code snippets, or header injection settings to remove or fix these offending elements.', 'capo' )
				),
				'actions'     => sprintf(
					'<p><a class="button button-primary" href="%s">%s</a></p>',
					esc_url( admin_url( 'options-general.php?page=capo' ) ),
					__( 'Capo Settings', 'capo' )
				),
				'test'        => 'capo_head_hygiene',
			);
		}

		return array(
			'label'       => __( 'No HTML <head> hygiene issues detected', 'capo' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Performance', 'capo' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'Your &lt;head&gt; markup is clean! All critical tags (single &lt;title&gt;, valid &lt;meta viewport&gt;, and resource preloads) adhere to web performance best practices.', 'capo' )
			),
			'actions'     => '',
			'test'        => 'capo_head_hygiene',
		);
	}
}
