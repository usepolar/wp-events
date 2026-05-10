<?php
/**
 * Plugin Name: Polar Events
 * Plugin URI: https://github.com/vguerrerobosch/polar-events
 * Description: A WordPress plugin to manage events
 * Version: 1.0.0
 * Author: Victor Guerrero
 * Author URI: https://polar.cat
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: polar-events
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.4
 *
 * @package Polar\Events
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('POLAR_EVENTS_VERSION', '1.0.0');
define('POLAR_EVENTS_PLUGIN_FILE', __FILE__);
define('POLAR_EVENTS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('POLAR_EVENTS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('POLAR_EVENTS_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Load Composer autoloader if available
if (file_exists(POLAR_EVENTS_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once POLAR_EVENTS_PLUGIN_DIR . 'vendor/autoload.php';
}

// Initialize the plugin
add_action('plugins_loaded', 'polar_events_init');

/**
 * Initialize the plugin
 */
function polar_events_init() {
    // Initialize the main Events class if it exists
    if (class_exists('Polar\Events\Events')) {
        Polar\Events\Events::getInstance();
    }
}

/**
 * Plugin activation hook
 */
register_activation_hook(__FILE__, 'polar_events_activate');

function polar_events_activate() {
    // Activation logic here
    if (!current_user_can('activate_plugins')) {
        return;
    }

    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Plugin deactivation hook
 */
register_deactivation_hook(__FILE__, 'polar_events_deactivate');

function polar_events_deactivate() {
    // Deactivation logic here
    if (!current_user_can('activate_plugins')) {
        return;
    }

    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Plugin uninstall hook
 */
register_uninstall_hook(__FILE__, 'polar_events_uninstall');

function polar_events_uninstall() {
    // Uninstall logic here
    if (!current_user_can('activate_plugins')) {
        return;
    }

    // Clean up plugin data if needed
}
