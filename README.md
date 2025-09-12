# Go/Organic WordPress Plugin

A powerful WordPress plugin designed to bulk-create posts via a REST API and manage integration credentials for external SEO content generation services.

## Description

The Go/Organic WP Plugin provides a seamless integration between WordPress and external content generation services. It allows you to bulk-create posts through a REST API endpoint and automatically manages authentication credentials for secure API access.

## Features

- **Bulk Post Creation**: Create multiple WordPress posts in a single API call
- **Automatic Category Management**: Create categories on-the-fly or assign posts to existing ones
- **Flexible Slug Handling**: Support for category-based URL structures (e.g., `category/post-slug`)
- **Secure Authentication**: Application Password-based authentication for API access
- **Admin Interface**: Easy-to-use admin panel for managing integration credentials
- **Automatic Updates**: Built-in update checker for seamless plugin updates

## Installation

1. Download the plugin files to your WordPress `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to the 'Go/Organic' menu in your WordPress admin panel to configure credentials

## API Usage

### Bulk Create Posts Endpoint

**Endpoint**: `POST /wp-json/seo-gen/v1/posts`

**Authentication**: Basic Authentication using Application Password

**Headers**:
```
Content-Type: application/json
Authorization: Basic [base64_encoded_credentials]
```

### Request Format

```json
{
  "posts": [
    {
      "id": "external_id_1",
      "title": "Your Post Title",
      "content": "<p>Your post content here...</p>",
      "slug": "category/post-slug",
      "excerpt": "Optional post excerpt",
      "status": "publish",
      "date": "2024-01-01 12:00:00",
      "author": 1,
      "comment_status": "open",
      "ping_status": "open"
    }
  ]
}
```

### Request Parameters

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `id` | string | No | External reference ID for tracking |
| `title` | string | **Yes** | Post title |
| `content` | string | No | Post content (HTML allowed) |
| `slug` | string | **Yes** | Post slug, supports `category/slug` format |
| `excerpt` | string | No | Post excerpt |
| `status` | string | No | Post status (default: `publish`) |
| `date` | string | No | Post date in MySQL format |
| `author` | integer | No | Author user ID |
| `comment_status` | string | No | Comment status (`open`, `closed`) |
| `ping_status` | string | No | Ping status (`open`, `closed`) |

### Response Format

```json
{
  "created_posts": [
    {
      "new_id": 123,
      "source_id": "external_id_1",
      "url": "https://yoursite.com/category/post-slug/",
      "title": "Your Post Title",
      "slug": "post-slug",
      "category": "category"
    }
  ],
  "errors": [
    {
      "source_id": "external_id_2",
      "title": "Failed Post",
      "message": "Error description"
    }
  ]
}
```

## Configuration

### Setting Up API Credentials

1. Go to **WordPress Admin > Go/Organic**
2. Click "Generate New Application Password"
3. Copy the generated password immediately (it won't be shown again)
4. Use the provided username and password for API authentication

### Authentication Details

- **Username**: `seo_gen_api_user` (automatically created)
- **Password**: Application Password (generated through admin interface)
- **User Role**: Editor (has permission to create and edit posts)

## Slug Formats

The plugin supports two slug formats:

1. **Simple Slug**: `my-post-title`
   - Assigns post to the default category

2. **Category/Slug Format**: `category-name/my-post-title`
   - Creates the category if it doesn't exist
   - Assigns the post to the specified category

## Error Handling

The API provides detailed error information for failed post creations:

- Missing title or slug
- Invalid slug format
- Category creation failures
- Post insertion errors

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- User with `edit_posts` capability for API access
- User with `manage_options` capability for admin configuration

## Security

- Uses WordPress Application Passwords for secure API authentication
- Sanitizes all input data
- Validates user permissions
- Prevents direct file access

## Version Information

- **Current Version**: 0.3.0
- **Author**: Purple Box AI
- **Update Server**: Automatic updates via built-in update checker

## Support

For support and updates, the plugin automatically checks for new versions from the configured update server. Make sure your WordPress installation can make outbound HTTP requests for automatic updates to work.

## License

This plugin includes the Plugin Update Checker library, which has its own licensing terms. Please refer to the `plugin-update-checker/license.txt` file for details.

## Changelog

### Version 0.3.0
- Initial release with bulk post creation functionality
- Admin interface for credential management
- Automatic category creation
- Flexible slug handling
- Built-in update checker
