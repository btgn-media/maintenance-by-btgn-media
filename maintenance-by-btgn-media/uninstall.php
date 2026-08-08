<?php
/**
 * Runs when the plugin is deleted from WordPress. Removes every trace the plugin
 * ever wrote to the database — current and legacy options and transients — so
 * nothing is left behind. Handles multisite by cleaning each site.
 */
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

function mbtgn_delete_all_data() {
    // Options (current + legacy prefix).
    delete_option('mbtgn_settings');
    delete_option('wmwp_settings');

    // Transients used by the updater (current + legacy), site and single variants.
    delete_transient('mbtgn_updater_release');
    delete_transient('wmwp_updater_release');
    delete_site_transient('mbtgn_updater_release');
    delete_site_transient('wmwp_updater_release');
}

if (is_multisite()) {
    $site_ids = get_sites(['fields' => 'ids']);
    foreach ($site_ids as $site_id) {
        switch_to_blog($site_id);
        mbtgn_delete_all_data();
        restore_current_blog();
    }
} else {
    mbtgn_delete_all_data();
}
