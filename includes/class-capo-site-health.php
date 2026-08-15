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
		add_filter( 'site_status_tests', array( $this, 'register_site_health_test' ) );
	}

	/**
	 * Register Capo test in Site Health direct tests.
	 *
	 * @param array $tests Array of registered Site Health tests.
	 * @return array Updated tests.
	 */
	/**
	 * Register Capo test in Site Health direct tests.
	 *
	 * @param array $tests Array of registered Site Health tests.
	 * @return array Updated tests.
	 */
	public function register_site_health_test( $tests ) {
		$tests['direct']['capo_head_optimization'] = array(
			'label' => __( 'HTML Head Optimization (Capo)', 'capo' ),
			'test'  => array( $this, 'test_capo_status' ),
		);

		$tests['direct']['capo_head_hygiene'] = array(
			'label' => __( 'HTML Head Hygiene & Validation (Capo)', 'capo' ),
			'test'  => array( $this, 'test_capo_hygiene' ),
		);

		return $tests;
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
		$response = wp_remote_get(
			home_url( '/' ),
			array(
				'timeout'   => 5,
				'sslverify' => false,
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
				$warning_items .= sprintf( '<li><strong>%s:</strong> %s</li>', esc_html( $w['rule_id'] ), esc_html( $w['warning'] ) );
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
					'<p>%s</p><ul>%s</ul><p>%s</p>',
					__( 'The following issues were detected in your &lt;head&gt; markup, which may impact browser rendering speed, SEO, or layout stability:', 'capo' ),
					$warning_items,
					__( 'Check your active theme templates or header plugins to resolve these warnings.', 'capo' )
				),
				'actions'     => sprintf(
					'<p><a class="button" href="%s">%s</a></p>',
					esc_url( admin_url( 'options-general.php?page=capo' ) ),
					__( 'View Capo Settings', 'capo' )
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
