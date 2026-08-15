<?php
/**
 * Plugin Name:       Capo
 * Plugin URI:        https://github.com/rviscomi/capo-wp
 * Description:       Automatically reorders the &lt;head&gt; of your WordPress pages for optimal browser rendering and web performance using the Capo.js methodology.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Rick Viscomi
 * Author URI:        https://rviscomi.dev/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       capo
 *
 * @package Capo
 */

namespace Capo;

defined( 'ABSPATH' ) || exit;

define( 'CAPO_VERSION', '0.1.0' );
define( 'CAPO_PLUGIN_FILE', __FILE__ );
define( 'CAPO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CAPO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Load plugin classes.
require_once CAPO_PLUGIN_DIR . 'includes/class-capo-rules.php';
require_once CAPO_PLUGIN_DIR . 'includes/class-capo-validator.php';
require_once CAPO_PLUGIN_DIR . 'includes/class-capo-parser.php';
require_once CAPO_PLUGIN_DIR . 'includes/class-capo-admin.php';
require_once CAPO_PLUGIN_DIR . 'includes/class-capo-site-health.php';
require_once CAPO_PLUGIN_DIR . 'includes/class-capo.php';

/**
 * Initialize the Capo plugin instance.
 */
function run_capo() {
	Plugin::instance()->init();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\run_capo' );
