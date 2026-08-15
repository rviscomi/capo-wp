<?php
/**
 * Capo Admin Settings
 *
 * Provides the settings page under Settings -> Capo in WordPress admin.
 *
 * @package Capo
 * @author  Rick Viscomi
 * @license GPL-2.0-or-later
 */

namespace Capo;

defined( 'ABSPATH' ) || exit;

class Admin {

	/**
	 * Singleton instance.
	 *
	 * @var Admin|null
	 */
	private static $instance = null;

	/**
	 * Main instance getter.
	 *
	 * @return Admin
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize admin hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_options_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'plugin_action_links_' . CAPO_PLUGIN_BASENAME, array( $this, 'add_action_links' ) );
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_node' ), 100 );
	}

	/**
	 * Add Capo page to Settings menu.
	 */
	public function add_options_page() {
		add_options_page(
			__( 'Capo Head Optimization', 'capo' ),
			__( 'Capo', 'capo' ),
			'manage_options',
			'capo',
			array( $this, 'render_options_page' )
		);
	}

	/**
	 * Register settings fields with Settings API.
	 */
	public function register_settings() {
		register_setting(
			'capo_settings_group',
			'capo_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => 1,
			)
		);

		register_setting(
			'capo_settings_group',
			'capo_debug_comment',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => 1,
			)
		);

		add_settings_section(
			'capo_general_section',
			__( 'General Settings', 'capo' ),
			array( $this, 'render_section_description' ),
			'capo'
		);

		add_settings_field(
			'capo_enabled',
			__( 'Enable Capo', 'capo' ),
			array( $this, 'render_field_enabled' ),
			'capo',
			'capo_general_section'
		);

		add_settings_field(
			'capo_debug_comment',
			__( 'HTML Stats Comment', 'capo' ),
			array( $this, 'render_field_debug_comment' ),
			'capo',
			'capo_general_section'
		);
	}

	/**
	 * Render settings section description.
	 */
	public function render_section_description() {
		echo '<p>' . esc_html__( 'Capo automatically reorganizes your HTML <head> elements so the browser can discover and fetch render-critical resources as early as possible.', 'capo' ) . '</p>';
	}

	/**
	 * Render master enabled toggle.
	 */
	public function render_field_enabled() {
		$enabled = (bool) get_option( 'capo_enabled', 1 );
		?>
		<label for="capo_enabled">
			<input type="checkbox" id="capo_enabled" name="capo_enabled" value="1" <?php checked( $enabled, true ); ?> />
			<?php esc_html_e( 'Enable automatic <head> ordering on frontend page requests.', 'capo' ); ?>
		</label>
		<?php
	}

	/**
	 * Render debug comment toggle.
	 */
	public function render_field_debug_comment() {
		$debug = (bool) get_option( 'capo_debug_comment', 1 );
		?>
		<label for="capo_debug_comment">
			<input type="checkbox" id="capo_debug_comment" name="capo_debug_comment" value="1" <?php checked( $debug, true ); ?> />
			<?php esc_html_e( 'Output a brief HTML comment in <head> with optimization metrics (e.g. element count, parse time).', 'capo' ); ?>
		</label>
		<?php
	}

	/**
	 * Add Settings link to Plugins page table row.
	 *
	 * @param string[] $links Array of action links.
	 * @return string[] Updated action links.
	 */
	public function add_action_links( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=capo' ) ),
			esc_html__( 'Settings', 'capo' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Render options page HTML.
	 */
	public function render_options_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'capo_settings_group' );
				do_settings_sections( 'capo' );
				submit_button( __( 'Save Changes', 'capo' ) );
				?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Testing & Verification', 'capo' ); ?></h2>
			<p>
				<?php esc_html_e( 'You can compare your site before and after Capo by testing your URL on the live demo:', 'capo' ); ?>
				<br />
				<a href="<?php echo esc_url( 'https://rviscomi.github.io/capo.js/user/demo/?url=' . rawurlencode( home_url( '/' ) ) ); ?>" target="_blank" rel="noopener noreferrer" class="button">
					<?php esc_html_e( 'Analyze Site with Capo.js Demo ↗', 'capo' ); ?>
				</a>
			</p>
			<p class="description">
				<?php
				printf(
					/* translators: %s: query parameter example */
					esc_html__( 'To view the un-optimized raw HTML output for debugging, append %s to any URL.', 'capo' ),
					'<code>?capo=off</code>'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Register the Capo diagnostic node with the WordPress Toolbar.
	 *
	 * Registers the initial Toolbar node during `admin_bar_menu`. Its contents
	 * and dropdown items are dynamically populated with real-time execution
	 * analysis by `inject_admin_bar_html()` when the output buffer completes.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 */
	public function add_admin_bar_node( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$wp_admin_bar->add_node(
			array(
				'id'    => 'capo-diagnostics',
				'title' => '<span class="ab-icon dashicons-dashboard" style="top:2px;"></span><span class="ab-label">Capo</span>',
				'href'  => admin_url( 'options-general.php?page=capo' ),
				'meta'  => array(
					'title' => esc_attr__( 'Capo Head Diagnostics & Optimization', 'capo' ),
					'class' => 'menupop',
				),
			)
		);
	}

	/**
	 * Inject real-time analysis, metrics, and warnings into the Admin Toolbar HTML.
	 *
	 * @param string               $html     Full HTML page buffer.
	 * @param array<string, mixed> $analysis Last Capo execution analysis.
	 * @return string Modified HTML with populated Admin Bar node.
	 */
	public static function inject_admin_bar_html( $html, array $analysis ) {
		if ( empty( $html ) || false === strpos( $html, 'wp-admin-bar-capo-diagnostics' ) ) {
			return $html;
		}

		$warning_count = count( $analysis['warnings'] );
		$has_warnings  = $warning_count > 0;

		if ( $has_warnings ) {
			$title_text = sprintf( '⚠️ Capo (%d)', $warning_count );
			$main_url   = admin_url( 'site-health.php' );
		} else {
			$title_text = '⚡ Capo';
			$main_url   = admin_url( 'options-general.php?page=capo' );
		}

		$submenu_items = '';
		$submenu_items .= sprintf(
			'<li role="group" id="wp-admin-bar-capo-metric"><a class="ab-item" role="menuitem" href="%s">⚡ Reordered %d elements in %sms</a></li>',
			esc_url( admin_url( 'options-general.php?page=capo' ) ),
			intval( $analysis['element_count'] ),
			esc_html( $analysis['elapsed_ms'] )
		);

		if ( $has_warnings ) {
			foreach ( $analysis['warnings'] as $idx => $w ) {
				$submenu_items .= sprintf(
					'<li role="group" id="wp-admin-bar-capo-warn-%d"><a class="ab-item" role="menuitem" href="%s" style="white-space:normal;height:auto;line-height:1.4;padding:10px 14px;border-bottom:1px solid rgba(255,255,255,0.07);"><span style="color:#dba617;font-weight:600;font-size:12px;">⚠️ %s</span><br><span style="color:#f0f0f1;font-size:12px;display:block;margin-top:2px;">%s</span></a></li>',
					$idx,
					esc_url( admin_url( 'site-health.php' ) ),
					esc_html( $w['rule_id'] ),
					esc_html( $w['warning'] )
				);
			}
		} else {
			$submenu_items .= sprintf(
				'<li role="group" id="wp-admin-bar-capo-good"><a class="ab-item" role="menuitem" href="%s" style="padding:10px 14px;">✅ No &lt;head&gt; hygiene issues detected</a></li>',
				esc_url( admin_url( 'options-general.php?page=capo' ) )
			);
		}

		$node_html = sprintf(
			'<li role="group" id="wp-admin-bar-capo-diagnostics" class="menupop"><a class="ab-item" role="menuitem" aria-haspopup="true" href="%s">%s</a><div class="ab-sub-wrapper" style="max-height:calc(100vh - 48px);overflow-y:auto;overscroll-behavior:contain;"><ul role="menu" id="wp-admin-bar-capo-diagnostics-default" class="ab-submenu" style="max-height:calc(100vh - 48px);overflow-y:auto;overscroll-behavior:contain;">%s</ul></div></li>',
			esc_url( $main_url ),
			esc_html( $title_text ),
			$submenu_items
		);

		$pattern = '/<li\s+[^>]*id=[\'"]wp-admin-bar-capo-diagnostics[\'"][^>]*>.*?<\/li>/s';
		return preg_replace( $pattern, $node_html, $html, 1 );
	}
}

