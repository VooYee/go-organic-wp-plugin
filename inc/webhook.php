<?php
/**
 * Webhook Handler
 * Handles triggering webhooks on content changes
 *
 * @package Go_Organic_WP_Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Trigger webhook validation and sending on post save
 *
 * @param int $post_id Post ID.
 * @param WP_Post $post Post object.
 * @param bool $update Whether this is an existing post being updated.
 */
function go_organic_trigger_content_webhook($post_id, $post, $update)
{
    // existing check logic from plan
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (wp_is_post_revision($post_id)) {
        return;
    }

    $allowed_post_types = ['post', 'page'];
    if (!in_array($post->post_type, $allowed_post_types)) {
        return;
    }

    // Check if post status is publish or draft (or whatever statuses we care about, maybe all?)
    // Let's trigger for any status change to be safe, or sticking to standard

    // Get Webhook URL
    $webhook_url = defined('BE_GO_ORGANIC_URL') ? BE_GO_ORGANIC_URL . '/wordpress/webhooks/article' : '';

    if (empty($webhook_url)) {
        return;
    }

    // Build Payload
    $payload = [
        'event'       => $update ? 'update' : 'create',
        'post_id'     => $post_id,
        'post_title'  => $post->post_title,
        'post_status' => $post->post_status,
        'post_type'   => $post->post_type,
        'post_url'    => get_permalink($post_id),
        'created_at'  => $post->post_date,
        'modified_at' => $post->post_modified,
        'author_id'   => $post->post_author,
    ];

    // Send Webhook
    $args = [
        'body'        => json_encode($payload),
        'headers'     => [
            'Content-Type' => 'application/json',
        ],
        'timeout'     => 5,
        'blocking'    => false, // Non-blocking
    ];

    wp_remote_post($webhook_url, $args);
}
add_action('save_post', 'go_organic_trigger_content_webhook', 10, 3);
