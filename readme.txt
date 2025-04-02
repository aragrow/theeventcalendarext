This document provides comprehensive documentation for "The Events Calendar Extender" plugin.

**1. Plugin Overview**

*   **Plugin Name:** The Events Calendar Extender [1]
*   **Description:** Extends The Events Calendar. Controls the custom styles, and manage the api endpoint to retrieve Events from DaySmart [1].

**2. Installation**

This plugin can be installed through the standard WordPress plugin installation process:

*   **Via WordPress Admin Panel:**
    1.  Navigate to **Plugins** > **Add New**.
    2.  Click **Upload Plugin** and upload the plugin zip file.
    3.  Click **Install Now** and then **Activate Plugin**.
*   **Via FTP:**
    1.  Upload the entire plugin folder to the `/wp-content/plugins/` directory of your WordPress installation.
    2.  Go to the **Plugins** page in your WordPress admin panel and click **Activate** under "The Events Calendar Extender".

**3. Core Functionality**

The primary functions of this plugin are to:

*   **Extend The Events Calendar:** Provide additional features and functionalities to the popular "The Events Calendar" plugin.
*   **Control Custom Styles:** Manage custom CSS styles for the events calendar [1].
*   **Manage DaySmart API Endpoint:** Facilitate the retrieval and synchronization of events from a DaySmart API [1].

**4. File Structure and Purpose**

The plugin consists of the following main PHP files within its directory:

*   `the-events-calendar-extender/` (Plugin root directory)
    *   `the-events-calendar-extender.php`: The main plugin file containing plugin metadata and defining key constants [1].
    *   `classes/`
        *   `the-event-calendar.php`: Potentially contains general extensions or modifications related to The Events Calendar [2].
        *   `api-manager.php`: Handles the communication and management of the DaySmart API, including settings, token retrieval, and content fetching [2, 3].
        *   `sync-the-calendar.php`: Responsible for synchronizing events fetched from the DaySmart API with The Events Calendar [4, 5].

**5. Defined Constants**

The main plugin file (`the-events-calendar-extender.php`) defines several constants to improve code readability and maintainability [1, 6]:

*   `THE_EVENT_CALENDAR_EXT_BASE_DIR`: Defines the absolute path to the plugin's base directory [6].
*   `THE_EVENT_CALENDAR_EXT_BASE_URI`: Defines the base URL (URI) of the plugin [6].
*   `THE_EVENT_CALENDAR_EXT_CLASSES`: Defines the absolute path to the plugin's `classes` directory [6].
*   `THE_EVENT_CALENDAR_EXT_ADMIN_CAP`: Defines the capability required for accessing admin-level features of this plugin, set to `'the_events_calendar_ext_admin'` [6].
*   `THE_EVENT_CALENDAR_EXT_OWNER_CAP`: Defines a capability likely intended for plugin owners, set to `'the_events_calendar_ext_owner'` [2].
*   `THE_EVENT_CALENDAR_EXT_EDITOR_CAP`: Defines a capability for editors related to this plugin, set to `'the_events_calendar_ext_editor'` [2].
*   `THE_EVENT_CALENDAR_EXT_PARENT_MENU`: Defines the slug for the parent menu in the WordPress admin area, set to `'the-event-calendar-ext'` [2].

**6. `TheEventCalendarExt` Class**

The `TheEventCalendarExt` class (likely located in `the-event-calendar.php` although not explicitly defined in the excerpts, it's instantiated at the end of `the-events-calendar-extender.php` which suggests its existence [7]) appears to handle front-end styling and the integration with The Events Calendar category taxonomy [8].

*   **`$event_types` Property:** An array that maps internal DaySmart event type IDs to human-readable labels [8].
    *   `3 => 'Camp'` [8]
    *   `7 => 'Public Skate'` [8]
    *   `8 => 'Stick & Puck'` [8]
    *   `9 => 'Adult Stick & Puck'` [8]
    *   `10 => 'Freestyle'` [8]
    *   `12 => 'Hockey Private Lessons'` [8]
    *   `17 => 'Catch Corner'` [8]
*   **`enqueue_custom()`:** This method enqueues a custom stylesheet (`styles.css`) located in the plugin's `assets/css/` directory. This allows the plugin to apply its own styling to the front-end [9-11].
*   **`tribe_events_cat_add_form_fields_callback()`:** This function is hooked into the action that displays fields when adding a new The Events Calendar category. It adds a section allowing administrators to select which DaySmart event types should be associated with the new category [9, 11]. It iterates through the `$event_types` and presents checkboxes for each.
*   **`tribe_events_cat_edit_form_fields_callback()`:** Similar to the add form callback, this function is triggered when editing an existing The Events Calendar category. It retrieves any previously saved DaySmart event type mappings for the current category and pre-checks the corresponding checkboxes in the edit form [9, 12].
*   **`create_tribe_events_cat_callback()`:** This function is executed when a new The Events Calendar category is created. It saves the selected DaySmart event type IDs as term meta for the newly created category [9, 13].
*   **`edited_tribe_events_cat_callback()`:** This function is executed when an existing The Events Calendar category is updated. It saves the updated selection of DaySmart event type IDs as term meta for the edited category [9, 13, 14]. If no event types are selected, it deletes the term meta.
*   **`manage_edit-tribe_events_cat_columns_callback()`:** This filter modifies the columns displayed in the "Events Categories" admin table. It adds a new column labeled "SmartDay Event" [9, 14].
*   **`manage_tribe_events_cat_custom_column_callback()`:** This action hook is used to populate the custom column ("SmartDay Event") in the "Events Categories" table. It retrieves the saved DaySmart event type IDs for each category and displays their corresponding labels (from the `$event_types` array) as a comma-separated list [10, 15]. If no DaySmart event types are associated, it displays "—".
*   **`manage_edit-tribe_events_cat_sortable_columns_callback()`:** This filter makes the "SmartDay Event" column sortable in the "Events Categories" admin table [7, 10].

**7. `TheEventCalendarExt_APIManager` Class**

The `TheEventCalendarExt_APIManager` class (located in `api-manager.php`) is responsible for handling the communication with the DaySmart API [3].

*   **Singleton Pattern:** The class implements the singleton design pattern, ensuring that only one instance of the class exists throughout the plugin [3].
    *   `get_instance()`: Static method to retrieve the single instance of the class [3].
    *   `$instance`: Private static variable to hold the instance [3].
    *   `__construct()`: Private constructor to prevent direct instantiation [3, 16].
*   **`$option_group` Property:** Defines the option group name used for registering the plugin's settings in the WordPress options table (`tr_daysmart_api_manager_options_group`) [3].
*   **`__construct()`:** The constructor registers various WordPress action hooks:
    *   `admin_menu`: Adds the "DaySmart API" menu to the WordPress admin area [16, 17].
    *   `admin_init`: Registers the plugin's settings using the `register_settings()` method [16].
    *   `wp`: Schedules the `schedule_token_refresh()` and `schedule_content_refresh()` methods to run at specific intervals [16].
    *   `daysmart_api_manager_refresh_jwt_token`: Hooks into a custom action to fetch a new JWT token [16].
    *   `daysmart_api_manager_refresh_content`: Hooks into a custom action to fetch event content from the DaySmart API [16].
    *   `update_option_daysmart_event_ids`: Hooks into the action triggered after the `daysmart_event_ids` option is updated [16].
*   **`add_admin_menu()`:** Adds a top-level menu item "DaySmart API" under the "manage_options" capability. This menu points to the `settings_page()` method [17].
*   **`settings_page()`:** Renders the settings page for the DaySmart API configuration. It uses WordPress settings API functions like `settings_fields()`, `do_settings_sections()`, and `submit_button()` to display and save the settings [17].
*   **`register_settings()`:** Registers the settings fields and sections for the DaySmart API configuration. It defines an array of fields (`$fields`) with their labels and then uses `register_setting()` and `add_settings_field()` to create the settings within the 'daysmart_api_manager_section' [18, 19]. The registered fields include:
    *   `daysmart_api_base_url` [18]
    *   `daysmart_api_client_id` [18]
    *   `daysmart_api_client_secret` [18]
    *   `daysmart_api_grant_type` [18]
    *   `daysmart_api_content_type` [18]
    *   `daysmart_event_ids` [18]
    *   `daysmart_events_last_url` [18]
    *   `daysmart_api_jwt_token` [18]
    *   `daysmart_events_last_run` [18]
*   **`settings_field_callback()`:** This function is the callback used to render each individual setting field on the settings page. It retrieves the current value of the field using `get_option()` and displays an appropriate input field (`text` or `password`). The `daysmart_events_last_run` and `daysmart_api_jwt_token` fields are set to `readonly` [19, 20]. For the `daysmart_event_ids` field, it displays a list of The Events Calendar categories with checkboxes, allowing the user to select which categories' DaySmart event IDs should be synchronized [20, 21].
*   **`update_option_daysmart_event_ids_callback()`:** This callback function is executed when the `daysmart_event_ids` option is updated. It sanitizes the input to ensure only integers are stored and updates the option in the database. It also logs the selected categories if `WP_DEBUG` is enabled [16, 21-23].
*   **`fetch_jwt_token()`:** This method sends a POST request to the DaySmart API's token endpoint to retrieve a JSON Web Token (JWT). It uses the API base URL, client ID, client secret, and grant type stored in the plugin settings. If the request is successful, it extracts the access token, sanitizes it, and saves it as the `daysmart_api_jwt_token` option [16, 23-25].
*   **`fetch_content_content()`:** This method fetches event content from the DaySmart API. It retrieves the JWT token, content type, and selected event category mappings from the plugin settings. It constructs the API URL with filters for events starting within the next six months and includes the DaySmart event IDs associated with the selected The Events Calendar categories [16, 25-27]. It sends a GET request to the DaySmart API with the JWT token in the Authorization header. If the token is expired, it attempts to refresh it and retry the request [28, 29]. Upon successfully retrieving the event data, it passes it to the `TheEventCalendarExt_Sync` class for synchronization [29]. It also updates the `daysmart_events_last_run` option with the current timestamp [30].
*   **`schedule_token_refresh()`:** This method schedules a daily WordPress cron event (`daysmart_api_manager_refresh_jwt_token`) to run at 12:01 AM for automatically refreshing the JWT token [16, 30].
*   **`schedule_content_refresh()`:** This method schedules an hourly WordPress cron event (`daysmart_api_manager_refresh_content`) to automatically fetch and synchronize event content from the DaySmart API [16, 31].
*   **`is_token_expired()`:** This private helper method checks if the DaySmart API response indicates that the JWT token is expired by looking for an 'error' key with the value 'token_expired' in the response body [32].

**8. `TheEventCalendarExt_Sync` Class**

The `TheEventCalendarExt_Sync` class (located in `sync-the-calendar.php`) is responsible for taking the event data fetched from the DaySmart API and synchronizing it with The Events Calendar in WordPress [5].

*   **Singleton Pattern:** This class also implements the singleton design pattern [5].
*   **`$wpdb` Property:** Holds an instance of the global `$wpdb` class for database interactions [5].
*   **`$events_data` Property:** A private property to store the array of event data fetched from the DaySmart API before processing [5, 33].
*   **`set_event_data()`:** This method is used to set the `$events_data` property with the event data received from the API manager [33].
*   **`sync_events()`:** This is the main method responsible for synchronizing the events. It iterates through the `$events_data`, parses each event using `parse_event_data()`, and then calls methods to synchronize the event post (`sync_tribe_event_post()`), the `tec_events` table record (`sync_trive_events_event()`), and the `tec_occurrences` table record (`sync_trive_events_occurrence()`). It uses WordPress database transactions (`START TRANSACTION` and `COMMIT`/`ROLLBACK`) to ensure data integrity [33, 34].
*   **`parse_event_data()`:** This private method takes a single event data item from the DaySmart API response and extracts relevant information to create an array of data suitable for creating or updating WordPress posts and event metadata [34-39]. This includes setting the post title, content, start and end dates (including GMT), status, and custom meta fields like `_EventStartDate`, `_EventEndDate`, `_EventSourceID`, etc. It also checks if an event with the same `event_source_id` already exists [37].
*   **`sync_tribe_event_post()`:** This method handles the creation or updating of the main WordPress post for an event of the `tribe_events` post type. It checks if an existing post with the same `event_source_id` exists. If so, it updates the existing post; otherwise, it inserts a new one. It also updates the event-related meta fields for the post [39-42].
*   **`sync_trive_events_event()`:** This method synchronizes the event data with The Events Calendar's main event table (`wp_tec_events`). It checks if an entry exists for the given `post_id`. If so, it updates the existing record; otherwise, it inserts a new one. It also generates a unique hash for each event [43-46].
*   **`sync_trive_events_occurrence()`:** This method synchronizes the event occurrence data with The Events Calendar's occurrences table (`wp_tec_occurrences`). It checks if an occurrence exists for the given `post_id` and `event_id`. If so, it updates the existing record; otherwise, it inserts a new one [46-49].
*   **`get_event_duration()`:** A private helper function that calculates the duration of an event in seconds based on its start and end dates [50].
*   **`get_existing_event_by_event_id()`:** This private helper function queries WordPress to check if an event with a specific `_EventSourceID` meta value already exists [50-52].
*   **`get_existing_tec_event()`:** This private helper function queries the `wp_tec_events` table to check if an event record exists for a given `post_id` [52].
*   **`get_existing_tec_occurrence()`:** This private helper function queries the `wp_tec_occurrences` table to check if an occurrence record exists for a given `post_id` and `event_id` [52].

**9. Admin Interface**

Upon activation, the plugin adds a new top-level menu item in the WordPress admin panel called **DaySmart API** [17]. Clicking on this menu item leads to the plugin's settings page, where administrators can configure the connection to the DaySmart API [17-19]:

*   **API Configuration Section:**
    *   **API Base URL:** The base URL of the DaySmart API endpoint [18].
    *   **Client ID:** The Client ID provided by DaySmart for API authentication [18].
    *   **Client Secret:** The Client Secret provided by DaySmart for API authentication (displayed as a password field) [18, 20].
    *   **Grant Type:** The grant type to use for obtaining the JWT token (e.g., 'client_credentials') [18].
    *   **Content Type:** The expected content type for API requests (e.g., 'application/json') [18].
    *   **DaySmart Events:** A list of The Events Calendar categories with checkboxes. Administrators can select which categories should have their associated DaySmart event IDs synchronized [18, 20-22].
    *   **Last URL:** (Read-only) Displays the last API URL used to fetch events [18, 20].
    *   **JWT Token:** (Read-only password field) Displays the currently active JWT token retrieved from the DaySmart API [18, 20].
    *   **Last Run GTM:** (Read-only) Displays the timestamp (in GMT) of the last successful event synchronization [18, 20, 30].

**10. Scheduling**

The plugin utilizes WordPress's built-in cron system for automated tasks [16, 30, 31]:

*   **Daily Token Refresh:** The plugin schedules a daily event (`daysmart_api_manager_refresh_jwt_token`) to run at 12:01 AM. This ensures that the JWT token used for API communication remains valid [16, 30].
*   **Hourly Content Refresh:** The plugin schedules an hourly event (`daysmart_api_manager_refresh_content`) to fetch the latest event content from the DaySmart API and synchronize it with The Events Calendar [16, 31].

**11. Customization**

While the provided code excerpts don't explicitly define specific filters or action hooks for third-party customization beyond the standard WordPress action and filter system used for its own functionality, developers could potentially extend the plugin's behavior by hooking into existing WordPress actions (e.g., those related to saving post meta or terms) or by adding their own custom actions and filters within the plugin's code.

**12. Troubleshooting**

*   **Check Error Logs:** If you encounter issues, ensure that `WP_DEBUG` is enabled in your `wp-config.php` file. The plugin uses `error_log()` to record potential errors during API communication and synchronization [3, 11-14, 16-19, 21, 22, 24, 27-34, 40, 43, 46, 49-52]. Review your WordPress debug log for any relevant error messages.
*   **Verify API Credentials:** Double-check that the API Base URL, Client ID, and Client Secret entered on the DaySmart API settings page are correct.
*   **Check DaySmart Event ID Mappings:** Ensure that you have correctly selected the DaySmart event types to include for each relevant The Events Calendar category.
*   **Monitor Cron Jobs:** Verify that the scheduled cron jobs for token and content refresh are running correctly. You might need a plugin to inspect and manage WordPress cron events.