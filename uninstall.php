<?php
/**
 * Uninstall Just Log
 *
 * @package Just_Log
 */

// If uninstall is not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

// Define table name
global $wpdb;
$table_name = $wpdb->prefix . 'just_log_entries';

// Cache the result before dropping
$cache_key = 'just_log_uninstall_' . $table_name;
wp_cache_set($cache_key, true, '', 300);

// Drop the table
$wpdb->query(
    $wpdb->prepare("DROP TABLE IF EXISTS %i", $table_name)
);

// Clean up options (if any were added in the future)
delete_option('just_log_version');
delete_option('just_log_settings');