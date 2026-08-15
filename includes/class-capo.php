<?php
/**
 * Capo Core Controller
 *
 * Manages request lifecycle, output buffering, bypass conditions,
 * and delegates head reordering to the Parser.
 *
 * @package Capo
 * @author  Rick Viscomi
 * @license GPL-2.0-or-later
 */

namespace Capo;

defined( 'ABSPATH' ) || exit;

class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Main instance getter.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize plugin hooks.
	 */
	public function init() {
		// Register buffer handler on frontend template_redirect.
		add_action( 'template_redirect', array( $this, 'maybe_start_buffer' ), 1 );

		// Admin settings & Site Health.
		if ( is_admin() ) {
			Admin::instance()->init();
		}
		Site_Health::instance()->init();
	}

	/**
	 * Determine if buffer should start and attach output callback.
	 */
	public function maybe_start_buffer() {
		if ( $this->should_bypass() ) {
			return;
		}

		ob_start( array( $this, 'filter_output' ) );
	}

	/**
	 * Filter and reorder <head> elements in final HTML response.
	 *
	 * @param string $html Full HTML page buffer.
	 * @return string Modified HTML.
	 */
	public function filter_output( $html ) {
		if ( empty( $html ) || ! is_string( $html ) ) {
			return $html;
		}

		// Check if response contains an HTML head.
		if ( false === stripos( $html, '<head' ) || false === stripos( $html, '</head>' ) ) {
			return $html;
		}

		$options = array(
			'debug_comment' => (bool) get_option( 'capo_debug_comment', 1 ),
		);

		return Parser::reorder_head( $html, $options );
	}

	/**
	 * Check if current request should bypass Capo head optimization.
	 *
	 * @return bool True if request should bypass Capo.
	 */
	public function should_bypass() {
		// Master plugin toggle.
		if ( ! (bool) get_option( 'capo_enabled', 1 ) ) {
			return true;
		}

		// Standard WordPress bypass checks.
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return true;
		}

		if ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) {
			return true;
		}

		if ( is_feed() || is_robots() || is_trackback() ) {
			return true;
		}

		if ( is_preview() || is_customize_preview() ) {
			return true;
		}

		// REST API request.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		// Query parameter bypass for testing/debugging (e.g. ?capo=off).
		if ( isset( $_GET['capo'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$capo_param = strtolower( sanitize_text_field( wp_unslash( $_GET['capo'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( in_array( $capo_param, array( '0', 'off', 'false', 'no', 'disabled' ), true ) ) {
				return true;
			}
		}

		// Allow themes/plugins to bypass via filter.
		return (bool) apply_filters( 'capo_disable', false );
	}
}
