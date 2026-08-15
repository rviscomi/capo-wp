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
	public function register_site_health_test( $tests ) {
		$tests['direct']['capo_head_optimization'] = array(
			'label' => __( 'HTML Head Optimization (Capo)', 'capo' ),
			'test'  => array( $this, 'test_capo_status' ),
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
}
