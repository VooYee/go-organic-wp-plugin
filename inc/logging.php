<?php
/**
 * Logging System
 * Handles database logging for Go/Organic plugin activities.
 *
 * @package Go_Organic_WP_Plugin
 * @since 0.12.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create the logging database table.
 *
 * @return void
 */
function go_organic_create_log_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'go_organic_logs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        post_id mediumint(9) NOT NULL,
        title text NOT NULL,
        status varchar(20) NOT NULL,
        action varchar(50) NOT NULL,
        message text DEFAULT '' NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY post_id (post_id),
        KEY action (action)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Log an activity to the database.
 *
 * @param int    $post_id Post ID.
 * @param string $title   Post title.
 * @param string $status  Post status.
 * @param string $action  Action type (e.g., 'created', 'updated').
 * @param string $message Optional message.
 * @return int|false The number of rows inserted, or false on error.
 */
function go_organic_log_activity($post_id, $title, $status, $action, $message = '')
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'go_organic_logs';

    return $wpdb->insert(
        $table_name,
        [
            'post_id' => $post_id,
            'title' => $title,
            'status' => $status,
            'action' => $action,
            'message' => $message,
            'created_at' => current_time('mysql')
        ],
        [
            '%d',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s'
        ]
    );
}

/**
 * Retrieve logs from the database.
 *
 * @param int $limit  Number of logs to retrieve.
 * @param int $offset Offset for pagination.
 * @return array List of logs.
 */
function go_organic_get_logs($limit = 50, $offset = 0)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'go_organic_logs';

    // Check if table exists to avoid errors on fresh installs before activation runs fully
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        return [];
    }

    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $limit,
            $offset
        )
    );
}

/**
 * Get total log count.
 *
 * @return int Total number of logs.
 */
function go_organic_count_logs()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'go_organic_logs';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        return 0;
    }

    return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
}
