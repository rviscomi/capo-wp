<?php
/**
 * Capo Uninstall Handler
 *
 * Cleans up options when the plugin is deleted via the WordPress Admin.
 *
 * @package Capo
 * @author  Rick Viscomi
 * @license GPL-2.0-or-later
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'capo_enabled' );
delete_option( 'capo_debug_comment' );
