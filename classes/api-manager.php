<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class TheEventCalendarExt_APIManager {
    private static $instance = null;
    private $option_group = 'tr_daysmart_api_manager_options_group';
   
    public static function get_instance() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp', [$this, 'schedule_token_refresh']);
        add_action('wp', [$this, 'schedule_content_refresh']);
        add_action('daysmart_api_manager_refresh_jwt_token', [$this, 'fetch_jwt_token']);
        add_action('daysmart_api_manager_refresh_content', [$this, 'fetch_content_content']);
    }

    // Add Admin Menu Page
    public function add_admin_menu() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        add_menu_page(
            'DaySmart API',
            'DaySmart API',
            'manage_options',
            THE_EVENT_CALENDAR_EXT_PARENT_MENU,
            [$this, 'settings_page'],
            'dashicons-admin-generic',
            100
        );
    }

    // Create Settings Page
    public function settings_page() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        ?>
        <div class="wrap">
            <h1>API Manager Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields($this->option_group);
                do_settings_sections(THE_EVENT_CALENDAR_EXT_PARENT_MENU);
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    // Register Settings
    public function register_settings() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        $fields = [
            'daysmart_api_base_url'     => 'API Base URL',
            'daysmart_api_client_id'    => 'Client ID',
            'daysmart_api_client_secret'=> 'Client Secret',
            'daysmart_api_grant_type'   => 'Grant Type',
            'daysmart_api_content_type' => 'Content Type',
            'daysmart_api_jwt_token'    => 'JWT Token',
            'daysmart_events_last_run' => 'Last Run GTM',
        ];

        foreach ($fields as $field => $label) {
            register_setting($this->option_group, $field);
            add_settings_field($field, $label, [$this, 'settings_field_callback'], THE_EVENT_CALENDAR_EXT_PARENT_MENU, 'daysmart_api_manager_section', ['field' => $field]);
        }

        add_settings_section('daysmart_api_manager_section', 'API Configuration', null, THE_EVENT_CALENDAR_EXT_PARENT_MENU);
    }

    // Callback for Settings Fields
    public function settings_field_callback($args) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        $field = $args['field'];
        $value = esc_attr(get_option($field, ''));
        $type = ($field === 'daysmart_api_client_secret' || $field === 'daysmart_api_jwt_token') ? 'password' : 'text';

        $readonly_fields = ['daysmart_api_jwt_token', 'daysmart_events_last_run'];
        $readonly = in_array($field, $readonly_fields) ? 'readonly' : '';
        echo "<input type='{$type}' name='{$field}' value='{$value}' class='regular-text' {$readonly}>";
    }

    // Fetch JWT Token from API
    public function fetch_jwt_token() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        $daysmart_api_url     = get_option('daysmart_api_base_url').'auth/token';
        $client_id   = get_option('daysmart_api_client_id');
        $client_secret = get_option('daysmart_api_client_secret');
        $grant_type  = get_option('daysmart_api_grant_type');

        if (!$daysmart_api_url || !$client_id || !$client_secret || !$grant_type) {
            return false;
        }

        $args = [
            'body'    => [
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'grant_type'    => $grant_type,
            ],
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
            'method'  => 'POST'
        ];

        $response = wp_remote_post($daysmart_api_url, $args);

        if (is_wp_error($response)) {
            error_log('API Manager Error: ' . $response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($body['access_token'])) {
            update_option('daysmart_api_jwt_token', $body['access_token']);
            return $body['access_token'];
        }

        return false;
    }

// Fetch Content from API
public function fetch_content_content() {
    if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
    $today = date('Y-m-d');
    $six_months_later = date('Y-m-d', strtotime('+6 months'));
    $page_size = 100; // Increase page size to get more events at once
    $daysmart_content_type     = get_option('daysmart_api_content_type');
    $daysmart_jwt_token   = get_option('daysmart_api_jwt_token');
    $daysmart_api_filters = "?filter[start_date__gte]={$today}&filter[start_date__lte]={$six_months_later}&page%5Bsize%5D={$page_size}&filter[event_type_id]=b";
    $daysmart_api_url     = get_option('daysmart_api_base_url').'events'.$daysmart_api_filters;


    if (!$daysmart_jwt_token) {
        $this->fetch_jwt_token();
        $daysmart_jwt_token   = get_option('daysmart_api_jwt_token');
        if (!$daysmart_jwt_token) {
            error_log('API Manager Error: Unabled to find JWT Token');
            return new WP_Error('jwt_token_missing', __('Unable to find JWT Token', 'the-event-calendar-ext'));
        }
    }
    $args = [
        'headers' => [
            'Authorization' => 'Bearer ' . $daysmart_jwt_token,
            'Content-Type'  => $daysmart_content_type,
        ],
        'method'  => 'GET'
    ];

    $response = wp_remote_post($daysmart_api_url, $args);

    if (is_wp_error($response)) {
        error_log('API Manager Error: ' . $response->get_error_message());
        return false;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    // If the token is expired, refresh and retry
    if ($this->is_token_expired($body)) {
        error_log('Token expired, fetching a new one...');
        $new_token = $this->fetch_jwt_token();
        if (is_wp_error($new_token)) {
            return $new_token; // Return the error if token refresh fails
        }
        return $this->fetch_content_content(); // Retry after getting a new token
    }

    // Get the singleton instance
    $sync_instance = TheEventCalendarExt_Sync::get_instance();
            
    // Set event data separately
    $sync_instance->set_event_data($body['data']);

    // Now sync events
    $return = $sync_instance->sync_events();

    if ($return) 
        update_option('daysmart_events_next_link', $body['links']['last']);
        update_option('daysmart_events_last_run', gmdate('Y-m-d H:i:s', current_time('timestamp', true)));
    }

    // Schedule Token Refresh
    public function schedule_token_refresh() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        // Get the next occurrence of 12:01 AM
        $timestamp = strtotime('tomorrow 00:01');
        if (!wp_next_scheduled('daysmart_api_manager_refresh_jwt_token')) {
            wp_schedule_event($timestamp, 'daily', 'daysmart_api_manager_refresh_jwt_token');
        }
    }

   // Schedule Token Refresh
   public function schedule_content_refresh() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        $timestamp = time(); // Current timestamp

        if ($timestamp < 1000000000) { 
            // If timestamp is suspiciously low, get an adjusted time
            $timestamp = strtotime('+1 minute');
        }
        if (!wp_next_scheduled('daysmart_api_manager_refresh_content')) {
            wp_schedule_event($timestamp, 'hourly', 'daysmart_api_manager_refresh_content');
        }
    }

    // Check if Token is Expired
    private function is_token_expired($response_body) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        return isset($response_body['error']) && $response_body['error'] === 'token_expired';
    }


}

// Initialize Plugin
TheEventCalendarExt_APIManager::get_instance();
