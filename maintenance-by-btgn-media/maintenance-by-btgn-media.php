<?php
/**
 * Plugin Name: Maintenance by #btgn.media
 * Description: Maintenance or coming-soon page with a per-project session bypass via URL parameter & cookie. Show custom HTML/CSS or any existing page. Works with any builder or theme.
 * Version: 2.2.0
 * Author: #btgn.media
 * Author URI: https://btgn.media
 * Plugin URI: https://github.com/btgn-media/maintenance-by-btgn-media
 * License: GPL-2.0-or-later
 * Text Domain: maintenance-by-btgn-media
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

// Schutz: falls eine aeltere Version des Plugins (anderer Ordnername) noch aktiv ist,
// nicht erneut laden — sonst fatale "Cannot redeclare class"-Fehler.
if (defined('MBTGN_VERSION') || class_exists('MBTGN_Settings')) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p><strong>Maintenance by #btgn.media:</strong> ';
        echo esc_html__('Another version of this plugin is already active. Please deactivate and delete the old version first.', 'maintenance-by-btgn-media');
        echo '</p></div>';
    });
    return;
}

define('MBTGN_VERSION', '2.2.0');
define('MBTGN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MBTGN_OPTION_KEY', 'mbtgn_settings');

require_once MBTGN_PLUGIN_DIR . 'includes/class-mbtgn-settings.php';
require_once MBTGN_PLUGIN_DIR . 'includes/class-mbtgn-frontend.php';
require_once MBTGN_PLUGIN_DIR . 'includes/class-mbtgn-updater.php';

/**
 * One-time migration from the legacy "wmwp_" prefix (pre-2.1.0) to "mbtgn_".
 * Copies the old settings over, then removes the legacy option and transient so
 * nothing stale is left behind in the database.
 */
function mbtgn_maybe_migrate_legacy() {
    $legacy = get_option('wmwp_settings');
    if ($legacy === false) {
        return;
    }
    if (get_option(MBTGN_OPTION_KEY) === false) {
        update_option(MBTGN_OPTION_KEY, $legacy);
    }
    delete_option('wmwp_settings');
    delete_transient('wmwp_updater_release');
}

add_action('plugins_loaded', function () {
    load_plugin_textdomain('maintenance-by-btgn-media', false, dirname(plugin_basename(__FILE__)) . '/languages');
    mbtgn_maybe_migrate_legacy();
    MBTGN_Settings::init();
    MBTGN_Frontend::init();
    MBTGN_Updater::init(__FILE__, MBTGN_VERSION);
});

// Uninstall is handled by uninstall.php (removes all options and transients,
// including legacy keys, across every site on multisite).
