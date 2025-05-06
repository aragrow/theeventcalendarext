<?php
/**
 * Handles plugin installation and uninstallation tasks.
 *
 * @package the-events-calendar
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class TheEventCalendarExt_Installer {

    /**
     * Creates the custom database table for DaySmart event mapping on plugin activation.
     *
     * @static
     */
    public static function activate() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        global $wpdb;
        $table_name = $wpdb->prefix . 'cst_daysmart_map'; // Choose a unique and descriptive table name
        $charset_collate = $wpdb->get_charset_collate();

        // SQL to create the table
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            daysmart_event_id varchar(255) NOT NULL UNIQUE,     
            wp_post_id bigint(20) unsigned NOT NULL,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY daysmart_event_id_map (daysmart_event_id),
            KEY wp_post_id (wp_post_id)
        ) $charset_collate;";

        // Include upgrade.php to use dbDelta
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        // Use dbDelta to create the table (handles creating or updating safely)
        dbDelta( $sql );

        if(WP_DEBUG && empty($wpdb->last_error)) {
            error_log('Database table ' . $table_name . ' created or updated successfully.');
        } elseif (WP_DEBUG) {
            error_log('Error creating or updating database table ' . $table_name . ': ' . $wpdb->last_error);
        }
    }

    /**
     * Drops the custom database table on plugin uninstall.
     * This function should ideally be in an uninstall.php file for cleaner uninstall.
     *
     * @static
     */
    public static function uninstall() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        global $wpdb;
        $table_name = $wpdb->prefix . 'cst_daysmart_map';
        $sql = "DROP TABLE IF EXISTS $table_name;";

        $drop_result = $wpdb->query($sql);

        if (false === $drop_result && ! empty($wpdb->last_error)) {
            error_log('TheEventCalendarExt_Installer - Database error dropping mapping table: ' . $wpdb->last_error);
        } elseif (WP_DEBUG) {
            error_log('Database table ' . $table_name . ' dropped successfully.');
        }

        // You would add other cleanup tasks here if needed (e.g., deleting options)
    }
}