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


class TheEventCalendarExt1 {
    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private $wpdb;
    private $event_data;

    private function __construct($event_data) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->event_data = $event_data;

    }

    public function sync_tribe_events() {

        foreach ($this->event_data as $event) {
            // Check if event exists by title
            
            $post_id = $this->sync_tribe_event_post($event);
            $event_id = $this->sync_trive_events_event($event, $post_id);
            $occurrence_id = $this->sync_trive_events_occurrence($event, $post_id, $event_id);
    
        }
    }

    private function sync_tribe_event_post($event) {
        $post_author = 1; //For Admin
        $existing_event = get_page_by_title($event['post_title'], OBJECT, 'tribe_events');
    
        $event_data = [
            'post_title'     => $event['post_title'],
            'post_content'   => $event['post_content'] ?? '',
            'post_status'    => 'publish',
            'post_type'      => 'tribe_events',
            'post_excerpt'   => $event['post_excerpt'] ?? '',
            'post_author'    => $event['post_author'] ?? $post_author,
            'post_date'      => current_time('mysql'),
            'post_date_gmt'  => get_gmt_from_date(current_time('mysql')),
        ];
    
        if ($existing_event) {
            // Update existing event
            $post_id = $existing_event->ID;
            $event_data['ID'] = $post_id;
            wp_update_post($event_data);
        } else {
            // Insert new event
            $post_id = wp_insert_post($event_data);
        }
    
        return $post_id;
    }

    private function sync_trive_events_event($event, $post_id) {

        $table_name = $this->wpdb->prefix . "tec_events"; // Ensures compatibility with table prefix
    
        $existing_event = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE post_id = %d",
            $post_id
        ));
    
        $duration = $this->get_event_duration($event['event_start_date'],$event['event_end_date']);
        $data = [
            'start_date'      => $event['event_start_date'],
            'end_date'        => $event['event_end_date'],
            'timezone'        => 'UTC+0',
            'start_date_utc'  => get_gmt_from_date($event['event_start_date']),
            'end_date_utc'    => get_gmt_from_date($event['event_end_date']),
            'duration'        => $duration,
            'updated_at'      => current_time('mysql', 1), // UTC timestamp
        ];
    
        $existing_entry = $this->wpdb->get_var($this->wpdb->prepare("SELECT event_id FROM $table_name WHERE post_id = %d", $post_id));
    
        if ($existing_entry) {
            $event_id = $existing_entry->event_id;
            $this->wpdb->update($table_name, $data, ['event_id' => $event_id]); // Update existing entry
        } else {
            $event_data['post_ID'] = $post_id;
            $event_id = $this->wpdb->insert($table_name, $data); // Insert new event data
        }
        return $event_id;
    }

    private function sync_trive_events_occurrence($event, $post_id, $event_id) {

        $table_name = $this->wpdb->prefix . "wp_tec_occurrences"; // Ensures compatibility with table prefix
    
        // Step 3: Check if occurrence exists in wp_tec_occurrences
        $existing_occurrence = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}tec_occurrences WHERE post_id = %d AND event_id = %d",
            $post_id, $event_id
        ));
    
        $duration = $this->get_event_duration($event['event_start_date'],$event['event_end_date']);
        $data = [
            'start_date'    => $event['event_start_date'],
            'start_date_utc'=> get_gmt_from_date($event['event_start_date']), // Assuming UTC is the same
            'end_date'      => $event['event_end_date'],
            'end_date_utc'  => get_gmt_from_date($event['event_end_date']),
            'duration'      => $duration,
            'updated_at'    => current_time('mysql')
        ];
    
        $existing_entry = $this->wpdb->get_var($this->wpdb->prepare("SELECT event_id FROM $table_name WHERE post_id = %d", $post_id));
    
        if ($existing_entry) {
            $occurence_id = $existing_entry->occurence_id;
            $this->wpdb->update($table_name, $data, ['occurrence_id' => $occurence_id]); // Update existing entry
        } else {
            $event_data['post_ID'] = $post_id;
            $event_data['EVENT_ID'] = $event_id;
            $occurence_id = $this->wpdb->insert($table_name, $data); // Insert new event data
        }
        return $occurence_id;
    }
    
    private function get_event_duration($start_date, $end_date) {
        $start_time = strtotime($start_date);
        $end_time = strtotime($end_date);
    
        if (!$start_time || !$end_time) {
            return 'Invalid date format';
        }
    
        $duration_seconds = $end_time - $start_time;
    
        return $duration_seconds;
    }
}