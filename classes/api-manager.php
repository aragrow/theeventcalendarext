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
        add_action('daysmart_api_manager_refresh_content', [$this, 'fetch_content_content'],10,1);

        add_action('update_option_daysmart_event_ids', [$this, 'update_option_daysmart_event_ids_callback'], 10, 3);
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
                wp_nonce_field('daysmart_api_settings_update', 'daysmart_api_nonce'); // Add nonce field
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
            'daysmart_event_ids' => 'DaySmart Events',
            'daysmart_events_last_url' => 'Last URL',
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

        if($field != 'daysmart_event_ids') {

            $type = ($field === 'daysmart_api_client_secret' || $field === 'daysmart_api_jwt_token') ? 'password' : 'text';

            $readonly_fields = ['daysmart_api_jwt_token', 'daysmart_events_last_run', 'daysmart_events_last_url'];
            $readonly = in_array($field, $readonly_fields) ? 'readonly' : '';
            echo '<div style="width: 100%;">';
            echo "<input type='" . esc_attr($type) . "' name='" . esc_attr($field) . "' value='" . esc_attr($value) . "' class='regular-text' " . esc_attr($readonly) . ">";
            echo '</div>';
        } else {
            
            $selected_categories = get_option($field, []); // Retrieve saved values (default to empty array)
            error_log(print_r($selected_categories,true));
            $categories = get_terms([
            'taxonomy' => 'tribe_events_cat',
            'hide_empty' => false,
            ]);

            foreach ($categories as $category):
                $is_checked = in_array($category->term_id, $selected_categories) ? 'checked' : ''; // Check if category is saved
                echo "<input type='checkbox' name='".esc_attr($field)."[]' value='" . esc_attr($category->term_id). "' class='regular-text' ".$is_checked.">".esc_html($category->name); echo ' | ';
            endforeach;
        }
    }

    public function update_option_daysmart_event_ids_callback($old_value, $new_value, $option) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);  // $param will be 1

        // Verify nonce
        if ( ! isset( $_POST['daysmart_api_nonce'] ) || ! wp_verify_nonce( $_POST['daysmart_api_nonce'], 'daysmart_api_settings_update' ) ) {
            // Nonce is invalid, do not process the update
            if(WP_DEBUG) error_log('DaySmart API settings update nonce verification failed.');
            return $old_value; // Return the old value to prevent unauthorized changes
        }

        if (isset($_POST['daysmart_event_ids']) && is_array($_POST['daysmart_event_ids'])) {
                // Sanitize input to ensure only integers are stored
                $selected_categories = array_map('intval', $_POST['daysmart_event_ids']);
                
                // Update the option in the database
                update_option('daysmart_event_ids', $selected_categories);

                if (WP_DEBUG) {
                    error_log('Selected event categories updated: ' . implode(', ', $selected_categories));
                }
        } else {
            // Save an empty array if no categories are selected
            update_option('daysmart_event_ids', []);

            if (WP_DEBUG) {
                error_log('No event categories selected. Saved an empty array.');
            }
        }
    
    }

    // Fetch JWT Token from API
    public function fetch_jwt_token() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        if(WP_DEBUG) error_log('Token expired, fetching a new one...');
        $daysmart_api_url     = get_option('daysmart_api_base_url').'auth/token';
        $client_id   = get_option('daysmart_api_client_id');
        $client_secret = get_option('daysmart_api_client_secret');
        $grant_type  = get_option('daysmart_api_grant_type');

        if (!$daysmart_api_url || !$client_id || !$client_secret || !$grant_type) {
            error_log('TheEventCalendarExt_APIManager - Information missing on request.  Unable to execute');
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
            error_log('TheEventCalendarExt_APIManager - API Manager Error: ' . $response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($body['access_token'])) {
            if(WP_DEBUG) error_log('JWT Token fetched.');
            $sanitized_jwt = sanitize_text_field($body['access_token']);
            update_option('daysmart_api_jwt_token', $sanitized_jwt);
            return $body['access_token'];
        } 
        error_log('TheEventCalendarExt_APIManager - JWT Token Not fetched.');
        error_log(print_r($body,true));
        return false;
    }

    // Fetch Content from API
    public function fetch_content_content($category_id = null) {
        error_log('##### Start: DaySmart API - Fetching content...');
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        if(WP_DEBUG) error_log( 'Received parameter Category: ' . $category_id ); // For debugging

        $today = date('Y-m-d');
        $six_months_later = date('Y-m-d', strtotime('+6 months'));
        $page_size = 100; // Increase page size to get more events at once
        $daysmart_content_type     = get_option('daysmart_api_content_type');
        $daysmart_jwt_token   = get_option('daysmart_api_jwt_token');
        if (empty($category_id)) {     
            $selected_categories = get_option('daysmart_event_ids', []); // Retrieve saved values (default to empty array)
        
            $events_to_retrieve = [];
            foreach ($selected_categories as $category_id) {
                // Get the category object
                $category = get_term($category_id, 'tribe_events_cat');
        
                if (!$category || is_wp_error($category)) {
                    continue; // Skip invalid categories
                }
        
                // Get custom field value for the category
                $events_to_retrieve[] = get_term_meta($category->term_id, 'daysmart_event_ids', true);
                
            }
            $events = implode(',', $events_to_retrieve[0]);
        } else {
            $term = get_term($category_id, 'tribe_events_cat');
            $events = get_term_meta($term->term_id, 'daysmart_event_ids', true)[0];
        }
        if(WP_DEBUG) error_log( 'Day Smart Events: ' . print_r($events, true)  ); // For debugging
        // Convert the string into an array using explode()
        //error_log(print_r($events_to_retrieve,true));

        //error_log(print_r($events,true));
        $daysmart_api_filters = "?filter[start_date__gte]={$today}&filter[start_date__lte]={$six_months_later}&filter[event_type_id]={$events}";
        $daysmart_api_url = get_option('daysmart_api_base_url').'events'.$daysmart_api_filters;
        update_option('daysmart_events_last_url', $daysmart_api_url);
    
        if (!$daysmart_jwt_token) {
            $this->fetch_jwt_token();
            $daysmart_jwt_token   = get_option('daysmart_api_jwt_token');
            if (!$daysmart_jwt_token) {
                error_log('TheEventCalendarExt_APIManager - API Manager Error: Unabled to find JWT Token');
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
            error_log('TheEventCalendarExt_APIManager API Manager Error fetching content: ' . $response->get_error_message());
            return new WP_Error('wp_remote_error', __('Error fetching data from DaySmart API.', 'the-event-calendar-ext'), ['details' => $response->get_error_message()]); // Return WP_Error
        }

        $http_status = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

         // Check for non-successful HTTP status codes first
        if ($http_status < 200 || $http_status >= 300) {
            // Log the full response for debugging non-success status codes
            error_log('#####DAYSMART API Non-Success HTTP Status: ' . $http_status . ' - Response Body: ' . print_r($body, true));
            if ($http_status === 401) {
                // It's a 401, definitely try to refresh the token
                if(WP_DEBUG) error_log('HTTP 401 received. Attempting token refresh...');
                $new_token_result = $this->fetch_jwt_token();
                if (is_wp_error($new_token_result)) {
                    error_log('####ERROR: TheEventCalendarExt_APIManager API Manager Error during token refresh after 401: ' . $new_token_result->get_error_message());
                    return $new_token_result; // Return the error if token refresh fails
                }
                // Token refreshed, now try fetching content again (optional: add a retry mechanism)
                // For now, the scheduled task will just run again later with the new token.
                return new WP_Error('token_refreshed_retry_needed', __('DaySmart API token refreshed. The content sync will retry on the next scheduled interval.', 'the-event-calendar-ext'));
            } else {
                // Handle other non-401 errors
                error_log('#####ERROR - TheEventCalendarExt_APIManager - API returned non-401 HTTP status: ' . $http_status);
                return new WP_Error('api_http_error', __('DaySmart API returned an HTTP error.', 'the-event-calendar-ext'), ['status' => $http_status, 'body' => $body]);
            }
        }
    
        // Check for token expiration
        $is_expired = $this->is_token_expired($body);
        // If the token is expired, refresh and retry
        if ($is_expired) {
            if(WP_DEBUG) error_log('DaySmart API: Body indicates token is expired. Attempting token refresh...');
            $new_token_result = $this->fetch_jwt_token();
            if (is_wp_error($new_token_result)) {
                error_log('####ERROR: TheEventCalendarExt_APIManager API Manager Error during token refresh after body expiration indicator: ' . $new_token_result->get_error_message());
                return $new_token_result; // Return the error if token refresh fails
            }
            // Token refreshed, sync will retry later
            return new WP_Error('token_refreshed_retry_needed', __('DaySmart API token refreshed. The content sync will retry on the next scheduled interval.', 'the-event-calendar-ext'));
        } 

        // If no errors and no token expiration indicated...
        if (!isset($body['data']) || !is_array($body['data'])) {
            error_log('#####DAYSMART API ERROR: Unexpected response format. No "data" array found. Response: ' . print_r($body, true));
            return new WP_Error('unexpected_response_format', __('DaySmart API returned an unexpected response format.', 'the-event-calendar-ext'), ['body' => $body]);
        }

        // Get the singleton instance
        $sync_instance = TheEventCalendarExt_Sync::get_instance();
                
        // Set event data separately
        $sync_instance->set_event_data($body['data']);

        // Now sync events
        $return = $sync_instance->sync_events();

        error_log('##### End: DaySmart API - Ended');
        if ($return) 
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

        $day_smart_event_ids = get_option('daysmart_event_ids', []); // Retrieve saved values (default to empty array)
        $next_schedule_time_ts = ceil(time() / 3600) * 3600; # Round to nearest next hour
        $hook = "daysmart_api_manager_refresh_content";

        # Loop through events, to make sure that they are scheduled five minutes apart
        foreach($day_smart_event_ids as $event_id) { # Loop through events
            $existing_ts = wp_next_scheduled( $hook, array( $event_id ) ); # Check if event is already scheduled
            wp_unschedule_event( $existing_ts, $hook, array( $event_id ) ); # Unschedule the event
            wp_schedule_event($next_schedule_time_ts, 'hourly', $hook, array( $event_id )); # Schedule the event
            $next_schedule_time_ts += 300; # Increment the schedule time by 5 minutes
        }

    }

    // Check if Token is Expired
    private function is_token_expired($response_body) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        // Check for a common API error structure indicating issues
        if (isset($response_body['errors'])) {
            // Log the full error details securely
            error_log('#####DAYSMART API ERROR: ' . print_r($response_body['errors'], true));

            if(isset($response_body['errors'][0]['status']) && $response_body['errors'][0]['status'] == 401) {
                if(WP_DEBUG) error_log('DaySmart API: Token expired (401 status).');
                return true; // Token expired
            } else {
                // Handle other types of API errors
                error_log('#####ERROR - TheEventCalendarExt_APIManager - API returned an error other than 401.');
                // Depending on the error, you might want to stop processing or try again
                // For now, we'll just log and assume it's not a token expiration requiring refresh.
                // If these errors should stop the sync, you'd return true here or handle it in fetch_content_content.
                return false; // It's an error, but not necessarily a token expiration
            }
        } elseif (isset($response_body['error']) && !empty($response_body['error'])) {
            // Check for another common API error structure
            error_log('#####DAYSMART API ERROR (Alternative Structure): ' . print_r($response_body, true));
            // If it's a recognized "invalid_token" or similar error, return true
            if (strpos(strtolower($response_body['error']), 'invalid_token') !== false) {
                if(WP_DEBUG) error_log('DaySmart API: Invalid token error.');
                return true; // Token is invalid
            }
            // Handle other errors as needed
            return false; // It's an error, but not necessarily a token expiration
        }

        // If no explicit error structure indicating token expiration is found
        return false;

    }
}

// Initialize Plugin
TheEventCalendarExt_APIManager::get_instance();
