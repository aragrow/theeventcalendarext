<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}



class TheEventCalendarExt {
    private static $instance = null;
   
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_custom']);  
    }

    function enqueue_custom() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        wp_enqueue_style(
            'the-event-calendar', // Unique handle
            THE_EVENT_CALENDAR_EXT_BASE_URI . '/assets/css/styles.css', // Path to the file
            array(), // Dependencies (leave empty if none)
            '1.0.0', // Version number
            'all' // Media type (all, screen, print, etc.)
        );
    }
    
}
// Initialize Plugin
TheEventCalendarExt::get_instance();