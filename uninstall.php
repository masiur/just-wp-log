<?php
/**
 * Uninstall Just Log
 *
 * @package Just_Log
 */

// If uninstall is not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Define table name
global $wpdb;
$table_name = $wpdb->prefix . 'just_log_entries';

// Drop the table
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

// Clean up options (if any were added in the future)
delete_option('just_log_version');
delete_option('just_log_settings');