<?php
/**
 * Posts API Handler
 * Handles bulk post creation via REST API
 *
 * @package Go_Organic_WP_Plugin
 * @since 0.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Bulk-create posts from a REST API request.
 *
 * @param WP_REST_Request $request The REST API request object.
 * @return WP_REST_Response The response with created posts and errors.
 */
function go_organic_bulk_create_posts($request)
{
    $posts = $request->get_param('posts');
    $created = [];
    $errors = [];

    foreach ($posts as $item) {
        $source_id = $item['id'] ?? null;
        $title = sanitize_text_field($item['title'] ?? '');
        $content = $item['content'] ?? '';
        $custom_slug = sanitize_text_field($item['slug'] ?? '');

        if (empty($title) || empty($custom_slug)) {
            $errors[] = [
                'source_id' => $source_id,
                'title' => $title ?: 'N/A',
                'message' => 'Item is missing a title or slug.'
            ];
            continue;
        }

        $real_slug = '';
        $category_id = null;
        $category_name = null;

        // If slug format includes category (e.g., "category/post-slug")
        if (strpos($custom_slug, '/') !== false) {
            $parts = explode('/', $custom_slug, 2);

            if (count($parts) !== 2 || empty($parts[0]) || empty($parts[1])) {
                $errors[] = [
                    'source_id' => $source_id,
                    'title' => $title,
                    'message' => 'Invalid slug format. Use "category/slug".'
                ];
                continue;
            }

            [$category_name, $real_slug] = $parts;

            $term = get_term_by('slug', sanitize_title($category_name), 'category');
            if (!$term) {
                $new_term = wp_insert_term($category_name, 'category');
                if (is_wp_error($new_term)) {
                    $errors[] = [
                        'source_id' => $source_id,
                        'title' => $title,
                        'message' => "Failed to create category '$category_name': " . $new_term->get_error_message()
                    ];
                    continue;
                }
                $category_id = $new_term['term_id'];
            } else {
                $category_id = $term->term_id;
            }
        } else {
            $real_slug = $custom_slug;
            $category_id = get_option('default_category');
        }

        if (empty($category_id)) {
            $errors[] = [
                'source_id' => $source_id,
                'title' => $title,
                'message' => 'Could not assign category.'
            ];
            continue;
        }

        // Prepare post data
        $post_data = [
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => $item['status'] ?? 'publish',
            'post_name' => sanitize_title($real_slug),
            'post_category' => [$category_id],
        ];

        // Optional fields
        if (!empty($item['excerpt']))
            $post_data['post_excerpt'] = $item['excerpt'];
        if (!empty($item['date']))
            $post_data['post_date'] = $item['date'];
        if (!empty($item['author']))
            $post_data['post_author'] = intval($item['author']);
        if (!empty($item['comment_status']))
            $post_data['comment_status'] = $item['comment_status'];
        if (!empty($item['ping_status']))
            $post_data['ping_status'] = $item['ping_status'];

        // Insert post
        $post_id = wp_insert_post($post_data);

        if (!is_wp_error($post_id)) {
            // Handle SEO meta fields if provided
            if (!empty($item['seo_title'])) {
                update_post_meta($post_id, '_seo_title', sanitize_text_field($item['seo_title']));
            }
            if (!empty($item['seo_description'])) {
                update_post_meta($post_id, '_seo_description', sanitize_text_field($item['seo_description']));
            }
            if (!empty($item['schema_markup'])) {
                // Validate JSON for schema markup
                $schema_data = $item['schema_markup'];
                if (is_string($schema_data)) {
                    $decoded = json_decode($schema_data, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        update_post_meta($post_id, '_schema_markup', $schema_data);
                    }
                } elseif (is_array($schema_data)) {
                    update_post_meta($post_id, '_schema_markup', wp_json_encode($schema_data));
                }
            }

            $created[] = [
                'new_id' => $post_id,
                'source_id' => $source_id,
                'url' => get_permalink($post_id),
                'title' => $title,
                'slug' => $real_slug,
                'category' => $category_name ?? null
            ];
        } else {
            $errors[] = [
                'source_id' => $source_id,
                'title' => $title,
                'message' => 'Failed to insert post: ' . $post_id->get_error_message()
            ];
        }
    }

    return rest_ensure_response([
        'created_posts' => $created,
        'errors' => $errors
    ]);
}

/**
 * Export posts from WordPress.
 *
 * @param WP_REST_Request $request The REST API request object.
 * @return WP_REST_Response The response with exported posts.
 */
function go_organic_export_posts($request)
{
    $format = $request->get_param('format') ?: 'json';
    $category = $request->get_param('category');
    $status = $request->get_param('status') ?: 'publish';
    $author = $request->get_param('author');
    $date_from = $request->get_param('date_from');
    $date_to = $request->get_param('date_to');
    $per_page = intval($request->get_param('per_page')) ?: 50;
    $page = intval($request->get_param('page')) ?: 1;

    // Build query arguments
    $args = [
        'post_type' => 'post',
        'post_status' => $status,
        'posts_per_page' => $per_page,
        'paged' => $page,
        'orderby' => 'date',
        'order' => 'DESC'
    ];

    // Add filters
    if ($author) {
        $args['author'] = intval($author);
    }

    if ($category) {
        $args['category_name'] = sanitize_text_field($category);
    }

    if ($date_from || $date_to) {
        $date_query = [];
        if ($date_from) {
            $date_query['after'] = sanitize_text_field($date_from);
        }
        if ($date_to) {
            $date_query['before'] = sanitize_text_field($date_to);
        }
        $args['date_query'] = [$date_query];
    }

    // Get posts
    $query = new WP_Query($args);
    $posts = [];
    $exported = 0;

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            $categories = get_the_category($post_id);
            $primary_category = !empty($categories) ? $categories[0]->slug : '';
            $post_slug = get_post_field('post_name', $post_id);

            // Format slug with category like the import format
            $formatted_slug = $primary_category ? $primary_category . '/' . $post_slug : $post_slug;

            $post_data = [
                'id' => $post_id,
                'title' => get_the_title(),
                'content' => get_the_content(),
                'slug' => $formatted_slug,
                'excerpt' => get_the_excerpt(),
                'status' => get_post_status(),
                'date' => get_the_date('Y-m-d H:i:s'),
                'author' => get_the_author_meta('ID'),
                'author_name' => get_the_author(),
                'category' => $primary_category,
                'categories' => array_map(function($cat) { return $cat->name; }, $categories),
                'url' => get_permalink(),
                'comment_status' => get_comments_number() > 0 ? 'open' : 'closed',
                'ping_status' => 'closed',
                // SEO and schema meta fields
                'seo_title' => get_post_meta($post_id, '_seo_title', true),
                'seo_description' => get_post_meta($post_id, '_seo_description', true),
                'schema_markup' => get_post_meta($post_id, '_schema_markup', true)
            ];

            $posts[] = $post_data;
            $exported++;
        }
        wp_reset_postdata();
    }

    // Prepare response data
    $response_data = [
        'posts' => $posts,
        'total' => $query->found_posts,
        'exported' => $exported,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => $query->max_num_pages
    ];

    // Handle different formats
    if ($format === 'csv') {
        return go_organic_export_posts_csv($posts, $response_data);
    }

    return rest_ensure_response($response_data);
}

/**
 * Export posts as CSV format.
 *
 * @param array $posts The posts data.
 * @param array $meta The metadata.
 * @return WP_REST_Response CSV response.
 */
function go_organic_export_posts_csv($posts, $meta)
{
    if (empty($posts)) {
        return new WP_Error('no_posts', 'No posts found for export', ['status' => 404]);
    }

    // CSV headers
    $headers = ['id', 'title', 'content', 'slug', 'excerpt', 'status', 'date', 'author', 'author_name', 'category', 'categories', 'url', 'comment_status', 'ping_status', 'seo_title', 'seo_description', 'schema_markup'];

    // Start building CSV content
    $csv_lines = [];

    // Add header row (don't escape headers, they're safe)
    $csv_lines[] = implode(',', $headers);

    foreach ($posts as $post) {
        $row = [];
        foreach ($headers as $header) {
            $value = $post[$header] ?? '';

            // Handle arrays (categories)
            if (is_array($value)) {
                $value = implode(';', $value);
            }

            // Clean content - remove HTML tags and normalize whitespace
            if ($header === 'content') {
                $value = strip_tags($value);
                $value = preg_replace('/\s+/', ' ', $value);
                $value = trim($value);
                // Truncate very long content
                if (strlen($value) > 500) {
                    $value = substr($value, 0, 500) . '...';
                }
            }

            // Clean excerpt
            if ($header === 'excerpt') {
                $value = strip_tags($value);
                $value = preg_replace('/\s+/', ' ', $value);
                $value = trim($value);
            }

            // Escape the field for CSV
            $row[] = go_organic_escape_csv_field($value);
        }
        $csv_lines[] = implode(',', $row);
    }

    // Join all lines with proper line breaks
    $csv_content = implode("\r\n", $csv_lines);

    // Output CSV directly to avoid JSON encoding
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="posts-export-' . date('Y-m-d-H-i-s') . '.csv"');
    header('Content-Length: ' . strlen($csv_content));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

    // Output the CSV content directly
    echo $csv_content;
    exit; // Important: exit to prevent WordPress from adding extra content
}

/**
 * Properly escape a field for CSV output.
 *
 * @param string $field The field value to escape.
 * @return string The escaped field value.
 */
function go_organic_escape_csv_field($field)
{
    // Convert to string and handle null values
    $field = (string) $field;

    // Remove null bytes and normalize line endings
    $field = str_replace(["\0", "\r\n", "\r", "\n"], ['', ' ', ' ', ' '], $field);

    // If field contains comma, quote, or needs quoting, wrap in quotes
    if (strpos($field, ',') !== false || strpos($field, '"') !== false || strpos($field, ' ') === 0 || substr($field, -1) === ' ') {
        // Escape internal quotes by doubling them
        $field = str_replace('"', '""', $field);
        $field = '"' . $field . '"';
    }

    return $field;
}/**
 * Get the dynamic API username for this installation.
 *
 * @return string The API username.
 */
function go_organic_get_api_username()
{
    $site_domain = parse_url(get_home_url(), PHP_URL_HOST) ?: 'localhost';
    $site_domain = preg_replace('/[^a-zA-Z0-9]/', '_', $site_domain);
    return 'go_organic_' . $site_domain;
}

/**
 * Register the posts REST API routes.
 *
 * @return void
 */
function go_organic_register_posts_api()
{
    // Bulk create posts endpoint
    register_rest_route('seo-gen/v1', '/posts', [
        'methods' => 'POST',
        'callback' => 'go_organic_bulk_create_posts',
        'permission_callback' => function () {
            return current_user_can('go_organic_manage_posts');
        }
    ]);

    // Export posts endpoint
    register_rest_route('seo-gen/v1', '/posts/export', [
        'methods' => 'GET',
        'callback' => 'go_organic_export_posts',
        'permission_callback' => function () {
            return current_user_can('go_organic_export_posts');
        },
        'args' => [
            'format' => [
                'description' => 'Export format: json or csv',
                'type' => 'string',
                'enum' => ['json', 'csv'],
                'default' => 'json'
            ],
            'category' => [
                'description' => 'Filter by category slug',
                'type' => 'string'
            ],
            'status' => [
                'description' => 'Filter by post status',
                'type' => 'string',
                'default' => 'publish'
            ],
            'author' => [
                'description' => 'Filter by author ID',
                'type' => 'integer'
            ],
            'date_from' => [
                'description' => 'Filter posts from date (Y-m-d)',
                'type' => 'string'
            ],
            'date_to' => [
                'description' => 'Filter posts to date (Y-m-d)',
                'type' => 'string'
            ],
            'per_page' => [
                'description' => 'Posts per page',
                'type' => 'integer',
                'default' => 50
            ],
            'page' => [
                'description' => 'Page number',
                'type' => 'integer',
                'default' => 1
            ]
        ]
    ]);
}
add_action('rest_api_init', 'go_organic_register_posts_api');

/**
 * Create custom role and capabilities for Go/Organic.
 *
 * @return void
 */
function go_organic_create_custom_role()
{
    // Add custom capabilities to administrator
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $admin_role->add_cap('go_organic_manage_posts');
        $admin_role->add_cap('go_organic_export_posts');
        $admin_role->add_cap('go_organic_manage_settings');
    }

    // Add capabilities to editor role
    $editor_role = get_role('editor');
    if ($editor_role) {
        $editor_role->add_cap('go_organic_manage_posts');
        $editor_role->add_cap('go_organic_export_posts');
    }
}
register_activation_hook(GO_ORGANIC_PLUGIN_DIR . 'go-organic.php', 'go_organic_create_custom_role');

/**
 * Remove custom capabilities on deactivation.
 *
 * @return void
 */
function go_organic_remove_custom_role()
{
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $admin_role->remove_cap('go_organic_manage_posts');
        $admin_role->remove_cap('go_organic_export_posts');
        $admin_role->remove_cap('go_organic_manage_settings');
    }

    $editor_role = get_role('editor');
    if ($editor_role) {
        $editor_role->remove_cap('go_organic_manage_posts');
        $editor_role->remove_cap('go_organic_export_posts');
    }
}
register_deactivation_hook(GO_ORGANIC_PLUGIN_DIR . 'go-organic.php', 'go_organic_remove_custom_role');
