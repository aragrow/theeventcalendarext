<?php
/**
 * Plugin Name: The Events Calendar Extender
 * Description: Extends The Events Calendar. Controls the custom styles, and manage the api endpoint to retrieve Events from DaySmart.
 * Version: 1.1.3
 * Requires Calendar Version: 6.10.2
 * Requires WP at least: 6.5
 * Requires PHP: 7.4
 * Author: David Arago, ARAGROW, LLC
 * Author URI: https://aragrow.me
 * Text Domain: the-events-calendar
 * License: GPLv2 or later
 * Version 1.1.3.  
 * # Added functionality to support multiple event types by creating multiple schedule jobs for each type.
 * @package the-events-calendar
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define a constant for the plugin's base directory. This makes the code more readable and easier to maintain.
defined( 'THE_EVENT_CALENDAR_EXT_BASE_DIR' ) or define( 'ARAGROW_TICKETHE_EVENT_CALENDAR_EXT_BASE_DIRTGROW_BASE_DIR', plugin_dir_path( __FILE__ ) );
defined( 'THE_EVENT_CALENDAR_EXT_BASE_URI' ) or define( 'THE_EVENT_CALENDAR_EXT_BASE_URI', plugin_dir_url( __FILE__ ) );
defined( 'THE_EVENT_CALENDAR_EXT_CLASSES' ) or define( 'THE_EVENT_CALENDAR_EXT_CLASSES', ARAGROW_TICKETHE_EVENT_CALENDAR_EXT_BASE_DIRTGROW_BASE_DIR.'classes/' );
defined( 'THE_EVENT_CALENDAR_EXT_ADMIN_CAP' ) or define( 'THE_EVENT_CALENDAR_EXT_ADMIN_CAP', 'the_events_calendar_ext_admin' );
defined( 'THE_EVENT_CALENDAR_EXT_OWNER_CAP' ) or define( 'THE_EVENT_CALENDAR_EXT_OWNER_CAP', 'the_events_calendar_ext_owner' );
defined( 'THE_EVENT_CALENDAR_EXT_EDITOR_CAP' ) or define( 'THE_EVENT_CALENDAR_EXT_EDITOR_CAP', 'the_events_calendar_ext_editor' );
defined( 'THE_EVENT_CALENDAR_EXT_PARENT_MENU' ) or define( 'THE_EVENT_CALENDAR_EXT_PARENT_MENU', 'the-event-calendar-ext' );

require_once THE_EVENT_CALENDAR_EXT_CLASSES . 'the-event-calendar-installer.php';
require_once THE_EVENT_CALENDAR_EXT_CLASSES . 'the-event-calendar.php';
require_once THE_EVENT_CALENDAR_EXT_CLASSES . 'api-manager.php';
require_once THE_EVENT_CALENDAR_EXT_CLASSES . 'sync-the-event-calendar.php';

// Register activation hook using the static method
register_activation_hook( __FILE__, ['TheEventCalendarExt_Installer', 'activate'] );

// Register uninstall hook using the static method
// RECOMMENDED: Use an uninstall.php file instead of this hook for cleaner uninstallation.
// If you use uninstall.php, remove the TheEventCalendarExt_Installer::uninstall method
// and the register_uninstall_hook line.
register_uninstall_hook( __FILE__, ['TheEventCalendarExt_Installer', 'uninstall'] );
