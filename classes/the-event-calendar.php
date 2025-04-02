<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}



class TheEventCalendarExt {
    private static $instance = null;
    private $event_types; 

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {

        $this->event_types = [
            3 => 'Camp',
            7 => 'Public Skate',
            8 => 'Stick & Puck',
            9 => 'Adult Stick & Puck',
            10 => 'Freestyle',
            12 => 'Hockey Private Lessons',
            17 => 'Catch Corner',
        ];
        add_action('wp_enqueue_scripts', [$this, 'enqueue_custom']);  
        add_action('tribe_events_cat_add_form_fields', [$this, 'tribe_events_cat_add_form_fields_callback'], 10,1);
        add_action('tribe_events_cat_edit_form_fields', [$this, 'tribe_events_cat_edit_form_fields_callback'], 10, 1);

        add_action('create_tribe_events_cat', [$this, 'create_tribe_events_cat_callback'], 10, 1);
        add_action('edited_tribe_events_cat', [$this, 'edited_tribe_events_cat_callback'], 10, 1);
       
        add_filter('manage_edit-tribe_events_cat_columns', [$this, 'manage_edit_tribe_events_cat_columns_callback'], 10, 1);
        add_filter('manage_tribe_events_cat_custom_column', [$this, 'manage_tribe_events_cat_custom_column_callback'], 10, 3);
        // 3. Make the column sortable (optional)
        add_filter('manage_edit-tribe_events_cat_sortable_columns', [$this, 'manage_edit_tribe_events_cat_sortable_columns_callback'], 10 ,1);
    }

    public function enqueue_custom() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        wp_enqueue_style(
            'the-event-calendar', // Unique handle
            THE_EVENT_CALENDAR_EXT_BASE_URI . '/assets/css/styles.css', // Path to the file
            array(), // Dependencies (leave empty if none)
            '1.0.0', // Version number
            'all' // Media type (all, screen, print, etc.)
        );
    }
    
    public function tribe_events_cat_add_form_fields_callback($taxonomy) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        if ($taxonomy != 'tribe_events_cat') return;

        ?>
        <div class="category-checklist">
            <label for="daysmart_event_ids">SmartDay Events to Include</label>
            <?php foreach ($this->event_types as $item => $label): ?>
                <label style="display: block; margin-bottom: 5px;">
                    <input type="radio" name="daysmart_event_ids[]" value="<?php echo esc_attr($item); ?>"  /><?php echo esc_html($label); ?>
                </label>
            <?php endforeach; ?>
        </div>
        <?php
    }

    public function tribe_events_cat_edit_form_fields_callback($term) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        if ($term->taxonomy != 'tribe_events_cat') return;
    
        // Get selected categories from term meta
        $selected_events= get_term_meta($term->term_id, 'daysmart_event_ids', true);
        $selected_events = is_array($selected_events) ? $selected_events : []; // Ensure it's an array

        ?>
        <tr class="form-field">
            <th scope="row">
                <label for="daysmart_event_ids">SmartDay Events to Include</label>
            </th>
            <td>
                <div class="category-checklist">
                    <?php foreach ($this->event_types as $item => $label): ?>
                        <label style="display: block; margin-bottom: 5px;">
                            <input type="radio" name="daysmart_event_ids[]" value="<?php echo esc_attr($item); ?>" 
                                <?php checked(in_array($item, $selected_events)); ?> />
                            <?php echo esc_html($label); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </td>
        </tr>
        <?php
    }

    // Save selected categories when creating a new event category (optional)
    public function create_tribe_events_cat_callback($term_id) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        if (isset($_POST['daysmart_event_ids'])) {
            $selected_categories = array_map('intval', $_POST['daysmart_event_ids']); // Sanitize input
            update_term_meta($term_id, 'daysmart_event_ids', $selected_categories);
        }
    }

    public function edited_tribe_events_cat_callback($term_id) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        if (!isset($_POST['daysmart_event_ids'])) 
            delete_term_meta($term_id, 'daysmart_event_ids'); // Remove meta if no categories are selected
        else {
            $selected_categories = array_map('intval', $_POST['daysmart_event_ids']); // Sanitize input
            update_term_meta($term_id, 'daysmart_event_ids', $selected_categories);
        }
    }

    public function manage_edit_tribe_events_cat_columns_callback($columns) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        $columns['davsmart_event'] = 'SmartDay Event'; // Column header
        return $columns;
    }

    public function manage_tribe_events_cat_custom_column_callback($content, $column_name, $term_id) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        if ($column_name === 'davsmart_event') {
            $custom_value = get_term_meta($term_id, 'daysmart_event_ids', true);
            if (!empty($custom_value)) {
                // Convert the stored IDs into labels using the event_types array
                $event_ids = is_array($custom_value) ? $custom_value : explode(',', $custom_value); // Handle array or comma-separated string
                $labels = array_map(function($id) {
                    return $this->event_types[$id] ?? 'Unknown Event Type'; // Map ID to label
                }, $event_ids);
            
                return esc_html(implode(', ', $labels)); // Display labels as a comma-separated list
            }
            
            return '—'; // Default value if no IDs are stored
        }
        return $content;
    }

    // 3. Make the column sortable (optional)
    public function manage_edit_tribe_events_cat_sortable_columns_callback($columns) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        $columns['davsmart_event'] = 'davsmart_event';
        return $columns;
    }
}

// Initialize Plugin
TheEventCalendarExt::get_instance();