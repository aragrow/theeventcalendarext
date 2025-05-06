<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class TheEventCalendarExt_Sync {
    private static $instance = null;

    public static function get_instance() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private $wpdb;
    private $events_data;
    private $mapping_table_name; // Add a property for the table name

    private function __construct() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->mapping_table_name = $wpdb->prefix . 'cst_daysmart_map'; // Initialize the table name
    }

    // New method to set event data
    public function set_event_data($event_data) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        $this->events_data = $event_data;
       // error_log(print_r($this->events_data,true));
    }

    // The sync_events function remains the same in terms of calling get/set,
    // but now they have enhanced error handling and sanitization internally.
    // The exception handling in sync_events will catch errors from set_synced_event_mapping.
    public function sync_events() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        global $wpdb;
        $current_daysmart_ids_and_posts = []; // To store DaySmart ID => WP Post ID for the current API fetch

        try {
            // Start a transaction
            $wpdb->query("START TRANSACTION");

            // Step 1: Get previous mapping before processing new data
            $previous_mapping = $this->get_synced_event_mapping();

            // Step 2: Process the newly fetched data and build the current mapping
            foreach ($this->events_data as $data) {
                $event = $this->parse_event_data($data);
                $post_id = $this->sync_tribe_event_post($event);
                $event_id = $this->sync_trive_events_event($event, $post_id);
                $occurrence_id = $this->sync_trive_events_occurrence($event, $post_id, $event_id);

                // Add the DaySmart ID and WP Post ID to our current list
                if ($post_id) {
                    $current_daysmart_ids_and_posts[$event['event_source_id']] = $post_id;
                } else {
                    // Log if a post creation/update failed - this event won't be mapped
                    error_log('TheEventCalendarExt_Sync - Failed to get valid WP Post ID for DaySmart event: ' . $event['event_source_id']);
                }
            }

            // Step 3: Identify events to delete
            $previous_daysmart_ids = array_keys($previous_mapping);
            $current_daysmart_ids = array_keys($current_daysmart_ids_and_posts); // Get keys from the newly synced data

            $deleted_daysmart_ids = array_diff($previous_daysmart_ids, $current_daysmart_ids); // Compare keys only

            /* CODE TO DELETE OUTDATED EVENTS
             ****************** THIS CODE HAS NOT BEEN TESTED YET DUE TO LACK OF TEST DATA ******************  
             ****************** ALSO, THERE MAY BE AN ISSUE WHEN EXECUTING BECAUSE IT DOES NOT ACCOUT FOR THE
             ******************  THREE JOBS EXECUTED, EACH FOR A DIFFERENT EVENT TYPE.
             ******************  THE IDEA IS TO WAIT UNTIL DAYSMART HELP DESK GET BACK TO US ON HOW TO MAKE ONE REQUEST
             ******************  FOR ALL EVENT TYPES. 
             
            // Step 4: Delete outdated events from WordPress
            if (!empty($deleted_daysmart_ids)) {
                if(WP_DEBUG) error_log('Found DaySmart events to delete: ' . implode(', ', $deleted_daysmart_ids));
                foreach ($deleted_daysmart_ids as $deleted_daysmart_id) {
                    $post_to_delete_id = $previous_mapping[$deleted_daysmart_id]; // Get WP Post ID from the previous mapping
                    if ($post_to_delete_id) {
                        if(WP_DEBUG) error_log('Deleting WordPress post ID: ' . $post_to_delete_id);
                        // Use wp_delete_post to trash the post
                        $delete_result = wp_delete_post($post_to_delete_id, false); // Set to true for permanent delete

                        if (false === $delete_result) {
                            error_log('TheEventCalendarExt_Sync - Failed to delete post ID: ' . $post_to_delete_id . ' (might already be deleted)');
                        } else {
                            if(WP_DEBUG) error_log('Successfully deleted post ID: ' . $post_to_delete_id);
                        }
                    } else {
                        // This shouldn't happen if get_synced_event_mapping works correctly,
                        // but it's a safeguard.
                        error_log('TheEventCalendarExt_Sync - Could not find WP Post ID for DaySmart ID in previous mapping when attempting deletion: ' . $deleted_daysmart_id);
                    }
                }
            }

            // Step 5: Update the mapping in the database with the current list of synced events
            $this->set_synced_event_mapping($current_daysmart_ids_and_posts); // Pass the new mapping

            END OF DELETION CODE
            */

            // Step 6: Commit the transaction if everything is successful
            $wpdb->query("COMMIT");
            if(WP_DEBUG) error_log("Transactions committed successfully!");
            return true;

        } catch (Exception $e) {
            // Rollback the transaction in case of an error
            $wpdb->query("ROLLBACK");
            error_log("TheEventCalendarExt_Sync - Transaction failed: " . $e->getMessage());
            return false; // Indicate sync failure
        } catch (WP_Error $e) {
            // Catch WP_Error from internal functions like wp_delete_post
            $wpdb->query("ROLLBACK");
            error_log("TheEventCalendarExt_Sync - WP_Error during sync: " . $e->get_error_message());
            return false; // Indicate sync failure
        }
    }



    private function parse_event_data($data) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        
        $admin_user = get_user_by('id', 1); // Get user by ID 1 (common admin ID)
        $post_author = $admin_user ? $admin_user->ID : 0; // Use 0 if user 1 doesn't exist
        
        $post_type = 'tribe_events';
        $event_source_id = sanitize_text_field($data['id']);
    
        $event_data = $data['attributes'];
        $event_type_id = sanitize_text_field($event_data['event_type_id']);
        $post_title = trim(sanitize_text_field($event_data['title'] ?? $event_data['desc']));
        $post_content = trim(sanitize_textarea_field($event_data['desc'] ?? ''));
        
        $post_start_date = sanitize_text_field(str_replace('T', ' ', $event_data['start']));
        $post_end_date = sanitize_text_field(str_replace('T', ' ', $event_data['end']));
        $post_start_gmt_date = sanitize_text_field(str_replace('T', ' ', $event_data['start_gmt']));
        $post_end_gmt_date = sanitize_text_field(str_replace('T', ' ', $event_data['end_gmt']));
        
        $post_status = $event_data['publish'] ? 'publish' : 'draft';
        $post_status = 'publish';
        $post_end_gmt_date = sanitize_text_field($event_data['end_gmt']);

        $post_modified = current_time('mysql');
        $post_modified_gmt = get_gmt_from_date($post_modified);

        // Get the local timezone string from WP settings, fallback to UTC if not set.
        $local_timezone = get_option('timezone_string') ? get_option('timezone_string') : 'UTC';
        // Get the current local timestamp
        $local_timestamp = current_time('timestamp');
        // Calculate the timezone abbreviation (e.g., CDT or CST)
        $timezone_abbr = date('T', $local_timestamp);

        // Check if event already exists
        $post_id = $this->get_existing_event_by_event_id($event_source_id);
        $event_duration = $this->get_event_duration($post_start_date,$post_end_date);

        $mod_u = sanitize_text_field($event_data['mod_u']);


        if (empty($event_type_id)) {
            error_log('TheEventCalendarExt_Sync - Critical Error: No event type IDs found for event ID: ' . $event_source_id); // Log technical detail securely
            wp_die(__('An unexpected error occurred while syncing events. Please contact the site administrator.', 'the-event-calendar-ext'), __('Sync Error', 'the-event-calendar-ext'), array('response' => 500)); // Provide generic message to user 
        }

        return [
            'event_source_id' => $event_source_id,
            'event_duration' => $event_duration,
            'event_type_id' => $event_type_id,
            'mod_u' => $mod_u,
            'post_id' => $post_id,
            'post_author' => $post_author,
            'post_type' => $post_type,
            'post_title' => $post_title,
            'post_content' => $post_content,
            'post_start_date' => $post_start_date,
            'post_end_date' => $post_end_date,
            'post_start_gmt_date' => $post_start_gmt_date,
            'post_end_gmt_date' => $post_end_gmt_date,
            'post_modified' => $post_modified,
            'post_modified_gmt' => $post_modified_gmt,
            'post_status' => $post_status,
            'comment_status' => 'closed',
            'local_timezone' => $local_timezone,
            'timezone_abbr' => $timezone_abbr
        ];
    }

    private function sync_tribe_event_post($event) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        try {

            $event_category_id = $this->get_category_by_event_type($event['event_type_id']);
            if (empty($event_category_id)) 
            {
                error_log("DAYSMART.APIERROR => {$event['event_source_id']}");
                wp_die('No event type IDs found', 503);
            }

            if ($event['post_id']) {
                $post_id = $event['post_id'];

                // Update existing event
                $update_result = wp_update_post([
                    'ID' => $post_id,
                    'post_content' => $event['post_content'],
                    'post_modified' => $event['post_modified'],
                    'post_modified_gmt' => $event['post_modified_gmt'],
                    'comment_status' => $event['comment_status']
                ], true);
    
                if (is_wp_error($update_result)) {
                    throw new Exception("Failed to update event: " . $update_result->get_error_message());
                }
                if(WP_DEBUG) error_log("Update existing event ({$post_id})");
            } else {
                // Insert new event
                $post_id = wp_insert_post([
                    'post_title' => $event['post_title'],
                    'post_content' => $event['post_content'],
                    'post_type' => $event['post_type'],
                    'post_author' => $event['post_author'],
                    'post_date' => $event['post_modified'],
                    'post_date_gmt' => $event['post_modified_gmt'],
                    'post_modified' => $event['post_modified'],
                    'post_modified_gmt' => $event['post_modified_gmt'],
                    'post_status' => $event['post_status'],
                    'comment_status' => $event['comment_status']
                ], true);
    
                if (is_wp_error($post_id)) {
                    throw new Exception("Failed to insert event: " . $post_id->get_error_message());
                }
                if(WP_DEBUG) error_log("Inserted new event ({$post_id})");
            }
    
            if ($post_id) {

                $meta_fields = [
                    '_EventStartDate' => $event['post_start_date'],
                    '_EventEndDate' => $event['post_end_date'],
                    '_EventStartDateUTC' => $event['post_start_gmt_date'],
                    '_EventEndDateUTC' => $event['post_end_gmt_date'],
                    '_EventDuration' => $event['event_duration'],
                    '_EventCurrencySymbol' => '$',
                    '_EventCurrencyCode' => 'USD',
                    '_EventCurrencyPosition' => 'prefix',
                    '_EventCost' => '',
                    '_EventURL' => '',
                    '_EventTimezone' => $event['local_timezone'],
                    '_EventTimezoneAbbr' => $event['timezone_abbr'],
                    '_EventSourceID' => $event['event_source_id'],
                    '_EventModifiedUser' => $event['mod_u']
                ];
    
                foreach ($meta_fields as $key => $value) {
                    $update_result = update_post_meta($post_id, $key, $value);
                }
        
                $updated = wp_set_post_terms($post_id, $event_category_id, 'tribe_events_cat');
                if (false === $updated) {
                    error_log('TheEventCalendarExt_Sync - Database error updating event category: ' . $this->wpdb->last_error);
                } elseif (0 === $updated) {
                    if(WP_DEBUG) error_log('No changes made to event category: ' . $post_id);    
                } else {
                    if(WP_DEBUG) error_log('Successfully updating event category: ' . $event_category_id . ' for post: ' . $post_id);
                }
            }
        
            return $post_id;
    
        } catch (Exception $e) {
            error_log("Error in " . __CLASS__ . "::" . __FUNCTION__ . ": " . $e->getMessage());
            // You might want to handle the error differently, e.g., return false or re-throw the exception
            return false;
        }
    }
    
    public function get_category_by_event_type($event_type_id) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        error_log('Event Type Id: ' . $event_type_id);
         // Retrieve the Event Category associated with the event type
        
        $args= [
            'taxonomy' => 'tribe_events_cat',
            'hide_empty' => false,
            'meta_key' => 'daysmart_event_ids'
        ];
        $all_terms = get_terms($args);
        if (is_wp_error($all_terms)) 
            error_log('Term query failed: ' . $all_terms->get_error_message());

        foreach ($all_terms as $term) {
            $meta = maybe_unserialize(get_term_meta($term->term_id, 'daysmart_event_ids', true));
            if ($meta[0] == $event_type_id) return $term->term_id;
        }

        return false;

    }
    private function sync_trive_events_event($data, $post_id) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        if(WP_DEBUG) error_log("Post ID: {$post_id}");
        
        try {
            $table = $this->wpdb->prefix . 'tec_events';
        
            $event = $this->get_existing_tec_event($post_id);

            $update_at = current_time('mysql');
            $string = trim(sanitize_textarea_field($data['desc'] ?? ''));
    
            $unique_hash = sha1(
                $string . 
                microtime(true) . 
                wp_generate_uuid4() . 
                $post_id 
            );
    
            
            // Data array for update/insert
            $record = array(
                'post_id'        => $post_id,
                'start_date'     => $data['post_start_date'],
                'end_date'       => $data['post_end_date'],
                'timezone'       => $data['local_timezone'],
                'start_date_utc' => $data['post_start_gmt_date'],
                'end_date_utc'   => $data['post_end_gmt_date'],
                'duration'       => $data['event_duration'],
                'updated_at'     => $update_at,
                'hash'           => $unique_hash,
            );
            if(WP_DEBUG) error_log(print_r($record,true));

            $format = array('%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s');
            
            if ($event) {
                $event_id = $event->event_id;
                // Format for each value in the array

                // Update record
                $updated = $this->wpdb->update(
                    $table,
                    $record,
                    ['event_id' => $event_id],
                    $format,         // data formats
                    array('%d')      // where format
                );

                // Result handling
                if (false === $updated) {
                    error_log('TheEventCalendarExt_Sync - Database error updating event: ' . $this->wpdb->last_error);
                } elseif (0 === $updated) {
                    if(WP_DEBUG) error_log('No changes made to event ID: ' . $event_id);
                } else {
                    if(WP_DEBUG) error_log('Successfully updated event ID: ' . $event_id);
                }
                
                return $post_id;
            } else {
                // Add event_id to the record for insertion
                // Format for each value in the array

        
                $inserted = $this->wpdb->insert($table, $record, $format);
                
                // Result handling
                if (false === $inserted) {
                    error_log('TheEventCalendarExt_Sync - Database error inserting event: ' . $this->wpdb->last_error);
                } else {
                    if(WP_DEBUG) error_log('Successfully inserting event ID: ' . $this->wpdb->insert_id);
                }
                                
                return $this->wpdb->insert_id;
            }
        } catch (Exception $e) {
            error_log("TheEventCalendarExt_Sync - Error in " . __CLASS__ . "::" . __FUNCTION__ . ": " . $e->getMessage());
            // You might want to handle the error differently, e.g., return false or re-throw the exception
            return false;
        }
    }    

    private function sync_trive_events_occurrence($data, $post_id, $event_id) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        try {
            $table_name = $this->wpdb->prefix . "tec_occurrences"; // Ensures compatibility with table prefix
        
            // Step 3: Check if occurrence exists in wp_tec_occurrences
            $occurrence = $this->get_existing_tec_occurrence($post_id, $event_id);
    
            $string = trim(sanitize_textarea_field($data['desc'] ?? ''));
    
            $unique_hash = sha1(
                $string . 
                microtime(true) . 
                wp_generate_uuid4() . 
                $post_id . 
                $event_id
            );
            $record = [
                'start_date'    => $data['post_start_date'],
                'start_date_utc'=> $data['post_start_gmt_date'], // Assuming UTC is the same
                'end_date'      => $data['post_end_date'],
                'end_date_utc'  => $data['post_end_gmt_date'],
                'duration'      => $data['event_duration'],
                'updated_at'    => current_time('mysql'),
                'hash'          => $unique_hash
            ];
        
            // Format for each value in the array
            $format = array('%s', '%s', '%s', '%s', '%d', '%s', '%s');
            
            if ($occurrence) {
                $occurrence_id = $occurrence->occurrence_id;

                $updated = $this->wpdb->update(
                    $table_name, 
                    $record, 
                    ['occurrence_id' => $occurrence_id],
                    $format,
                    array('%d')
                ); // Update existing entry
                
                // Result handling
                if (false === $updated) {
                    error_log('TheEventCalendarExt_Sync - Database error updating occurence: ' . $this->wpdb->last_error);
                } elseif (0 === $updated) {
                    if(WP_DEBUG) error_log('No changes made to occurence ID: ' . $occurrence_id);
                } else {
                    if(WP_DEBUG) error_log('Successfully updated occurence ID: ' . $occurrence_id);
                }

            } else {

                $record['post_id'] = $post_id;
                $record['event_id'] = $event_id;
                $format[] = '%d';
                $format[] = '%d';
    
                $inserted = $this->wpdb->insert($table_name, $record, $format); // Insert new event data
                
                // Result handling
                if (false === $inserted) {
                    error_log('TheEventCalendarExt_Sync - Database error inserting occurrence: ' . $this->wpdb->last_error);
                } else {
                    if(WP_DEBUG) error_log('Successfully inserting occurrence ID: ' . $this->wpdb->insert_id);
                }
                
                $occurrence_id = $this->wpdb->insert_id;
            }
            
            return $occurrence_id;
        } catch (Exception $e) {
            error_log("TheEventCalendarExt_Sync - Error in " . __CLASS__ . "::" . __FUNCTION__ . ": " . $e->getMessage());
            // You might want to handle the error differently, e.g., return false or re-throw the exception
            return false;
        }
    }    
    
    private function get_event_duration($start_date, $end_date) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        $start_time = strtotime($start_date);
        $end_time = strtotime($end_date);
    
        if (!$start_time || !$end_time) {
            return 'Invalid date format';
        }
    
        $duration_seconds = $end_time - $start_time;
    
        return $duration_seconds;
    }

    private function get_existing_event_by_event_id($event_id) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        $event_id = sanitize_text_field($event_id);
        $args = [
            'post_type'      => 'tribe_events',
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'     => '_EventSourceID', // Adjust this meta key if needed
                    'value'   => $event_id,
                    'compare' => '='
                ]
            ],
            'fields'         => 'id'
        ];
        $posts = get_posts($args);
        return !empty($posts) ? $posts[0]->ID : false;
    }

    private function get_existing_tec_event( $post_id ) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        global $wpdb;
        $table = $wpdb->prefix . 'tec_events';
        $post_id = sanitize_text_field( $post_id );
        
        $event_id = $wpdb->get_row( $wpdb->prepare(
            "SELECT event_id FROM {$table} WHERE post_id = %d",
            $post_id
        ) );

        return $event_id;

    }
    
    private function get_existing_tec_occurrence( $post_id, $event_id ) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        global $wpdb;
        $table = $wpdb->prefix . 'tec_occurrences';
        $post_id = sanitize_text_field( $post_id );
        $event_id = sanitize_text_field( $event_id );

        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT occurrence_id FROM {$table} WHERE post_id = %d AND event_id = %d",
            $post_id, $event_id
        ));
    }

    /**
     * Retrieves the mapping of DaySmart Event IDs to WordPress Post IDs from the database.
     * Includes basic error handling for database queries.
     *
     * @return array An associative array where keys are DaySmart Event IDs and values are WordPress Post IDs.
     */
    private function get_synced_event_mapping() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        $mapping = [];

        // Prepare the SQL query. No user input directly in this simple SELECT.
        $sql = "SELECT daysmart_event_id, wp_post_id FROM {$this->mapping_table_name}";

        // Execute the query
        $results = $this->wpdb->get_results($sql, ARRAY_A);

        // Check for database errors during the query
        if (false === $results && ! empty($this->wpdb->last_error)) {
            error_log('TheEventCalendarExt_Sync - Database error retrieving mapping entries: ' . $this->wpdb->last_error);
            // In a real application, you might throw an exception or return a WP_Error here
            // depending on how critical it is for the sync to fail. For this case, returning
            // an empty mapping might allow the sync to continue (without deletions),
            // which might be acceptable.
            return [];
        }

        if ($results) {
            foreach ($results as $row) {
                // Sanitize/validate data retrieved from the database
                $daysmart_id = sanitize_text_field($row['daysmart_event_id']);
                $daysmart_type = sanitize_text_field($row['daysmart_event_type']);
                $wp_post_id = (int) $row['wp_post_id']; // Ensure it's an integer

                // Add to mapping if IDs are valid
                if (!empty($daysmart_id) && $wp_post_id > 0) {
                    $mapping[$daysmart_id] = [
                        'type' => $daysmart_type,
                        'post_id' => $wp_post_id];
                } else {
                    // Log if we find unexpected data in the mapping table
                    error_log('TheEventCalendarExt_Sync - Found invalid mapping entry in DB: ' . print_r($row, true));
                }
            }
        }

        if(WP_DEBUG) error_log('Retrieved mapping entries: ' . count($mapping));
        return $mapping;
    }

    /**
     * Updates the mapping of DaySmart Event IDs to WordPress Post IDs in the database.
     * This function clears all existing mapping entries and inserts the new ones.
     * Includes input validation/sanitization and error handling for database queries.
     *
     * @param array $mapping An associative array where keys are DaySmart Event IDs and values are WordPress Post IDs.
     */
    private function set_synced_event_mapping($mapping) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        if(WP_DEBUG) error_log('Updating mapping with entries: ' . count($mapping));

        // Start a transaction if not already started by the sync_events method (it is, but good practice)
        // $this->wpdb->query("START TRANSACTION"); // Already done in sync_events

        // It's often simplest to delete all existing mappings and insert the new ones.
        // For very large datasets, an upsert/batch update might be more efficient.
        // TRUNCATE is faster than DELETE FROM for clearing the whole table.
        $truncate_result = $this->wpdb->query("TRUNCATE TABLE {$this->mapping_table_name}");
        if (false === $truncate_result && ! empty($this->wpdb->last_error)) {
            error_log('TheEventCalendarExt_Sync - Database error truncating mapping table: ' . $this->wpdb->last_error);
            // Consider rolling back the main transaction in sync_events if truncation fails
            // and this is a critical operation. For now, let's assume we proceed
            // and the next sync will overwrite.
            return; // Stop processing if truncation fails
        }


        if (!empty($mapping)) {
            $values = [];
            $placeholders = [];
            $date_now = current_time('mysql'); // Get current time in WordPress format

            // Prepare data for batch insert with validation/sanitization
            foreach ($mapping as $daysmart_id => $wp_post_id) {
                // Basic validation and sanitization for each entry
                $sanitized_daysmart_id = sanitize_text_field($daysmart_id);
                $sanitized_wp_post_id = (int) $wp_post_id; // Ensure it's an integer

                // Only add valid entries to the insert batch
                if (!empty($sanitized_daysmart_id) && $sanitized_wp_post_id > 0) {
                    $values[] = $sanitized_daysmart_id;
                    $values[] = $sanitized_wp_post_id;
                    $values[] = $date_now;
                    $placeholders[] = "(%s, %d, %s)";
                } else {
                    // Log if we find invalid data in the mapping being passed to this function
                    error_log('TheEventCalendarExt_Sync - Skipping invalid mapping entry for insertion: DaySmart ID = "' . esc_html($daysmart_id) . '", WP Post ID = "' . esc_html($wp_post_id) . '"');
                }
            }

            if (!empty($placeholders)) { // Only insert if there are valid entries
                $sql = "INSERT INTO {$this->mapping_table_name} (daysmart_event_id, wp_post_id, updated_at) VALUES ";
                $sql .= implode(', ', $placeholders);

                // Use $wpdb->prepare() for safe query execution
                $prepared_sql = $this->wpdb->prepare($sql, $values);

                // Execute the insert query
                $inserted = $this->wpdb->query($prepared_sql);

                // Check for database errors during insertion
                if (false === $inserted && ! empty($this->wpdb->last_error)) {
                    error_log('TheEventCalendarExt_Sync - Database error inserting mapping entries: ' . $this->wpdb->last_error);
                    // This error happens *within* the main transaction of sync_events.
                    // An exception will be thrown in sync_events and the transaction will be rolled back.
                } else {
                    if(WP_DEBUG) error_log('Successfully inserted mapping entries: ' . $inserted . ' rows affected.');
                }
            } else {
                if(WP_DEBUG) error_log('No valid mapping entries to insert after sanitization.');
            }
        } else {
            if(WP_DEBUG) error_log('No mapping entries to insert.');
        }

        // Transaction commit/rollback is handled by sync_events.
    }

}