# Late.dev Integration - Complete Implementation Guide

**AuraReels Social Media Publishing with Late.dev (Standard Flow)**

This document provides complete implementation details for integrating Late.dev as the social media publishing middleware for AuraReels platform.

---

## Table of Contents

1. [Overview](#overview)
2. [Why Late.dev?](#why-latedev)
3. [Architecture](#architecture)
4. [Prerequisites](#prerequisites)
5. [Database Schema](#database-schema)
6. [WordPress Backend Implementation](#wordpress-backend-implementation)
7. [REST API Endpoints](#rest-api-endpoints)
8. [Frontend Implementation](#frontend-implementation)
9. [Publishing Workflow](#publishing-workflow)
10. [Scheduling System](#scheduling-system)
11. [Analytics & Metrics](#analytics--metrics)
12. [Error Handling](#error-handling)
13. [Testing Guide](#testing-guide)
14. [Deployment Checklist](#deployment-checklist)

---

## Overview

This integration uses Late.dev as middleware to:
- **Authenticate users** with their social media accounts (TikTok, Instagram, LinkedIn, Twitter, etc.)
- **Publish videos** to multiple platforms simultaneously
- **Schedule posts** for future publication
- **Track analytics** for published content

**Your platform handles**: UI/UX, scheduling interface, user management, video processing

**Late.dev handles**: OAuth flows, platform API communication, rate limiting, media optimization

---

## Why Late.dev?

### Benefits vs Direct Integration

| Feature | Direct Integration | Late.dev Integration ⭐ |
|---------|-------------------|------------------------|
| **Development Time** | 6-8 weeks | 1-2 weeks |
| **Platforms Supported** | 2 (TikTok, Instagram) | 11+ platforms |
| **OAuth Complexity** | High (per platform) | None (handled by Late) |
| **Token Management** | You implement | Automatic |
| **Rate Limiting** | Manual handling | Automatic |
| **Media Optimization** | You implement | Automatic |
| **Platform API Changes** | Break your code | Late maintains |
| **Analytics** | Separate system | Built-in |
| **Cost** | Developer time ($8k+) | $49/month |
| **Maintenance** | Ongoing burden | Minimal |

### Supported Platforms

- ✅ Instagram (Reels & Feed)
- ✅ TikTok
- ✅ LinkedIn
- ✅ Twitter/X
- ✅ YouTube (Shorts)
- ✅ Facebook
- ✅ Threads
- ✅ Reddit
- ✅ Pinterest
- ✅ Bluesky
- ✅ Google Business Profile

---

## Architecture

### System Overview

```
┌──────────────────────────────────────────────────────────────────┐
│                  Late.dev Integration Architecture               │
└──────────────────────────────────────────────────────────────────┘

User Journey:
1. User uploads video → AI analysis (existing AuraReels flow)
2. User clicks "Publish to Social Media"
3. User selects connected accounts
4. User edits caption, schedule time (optional)
5. User clicks "Publish" or "Schedule"

Data Flow:
┌─────────────────────────────────────────────────────────────┐
│  AuraReels Frontend (Next.js)                               │
│  - Social accounts list                                     │
│  - Publish dialog                                           │
│  - Scheduling calendar                                      │
│  - Analytics dashboard                                      │
└─────────────────────────────────────────────────────────────┘
                            ↓ REST API
┌─────────────────────────────────────────────────────────────┐
│  WordPress Backend (aurareels-core)                         │
│  - Late.dev API client wrapper                              │
│  - REST endpoints                                           │
│  - Database: track posts, analytics                         │
└─────────────────────────────────────────────────────────────┘
                            ↓ Late.dev API
┌─────────────────────────────────────────────────────────────┐
│  Late.dev Service                                           │
│  - OAuth authentication                                     │
│  - Multi-platform publishing                                │
│  - Media optimization                                       │
│  - Analytics aggregation                                    │
└─────────────────────────────────────────────────────────────┘
                            ↓ Platform APIs
┌─────────────────────────────────────────────────────────────┐
│  Social Media Platforms                                     │
│  TikTok | Instagram | LinkedIn | Twitter | YouTube | etc.  │
└─────────────────────────────────────────────────────────────┘
```

### Authentication Flow (Standard Mode)

```
┌──────────────────────────────────────────────────────────────────┐
│              Standard OAuth Flow (All Platforms)                 │
└──────────────────────────────────────────────────────────────────┘

Step 1: User clicks "Connect Instagram" in AuraReels
   ↓
Step 2: Frontend → POST /wp-json/aurareels/v1/social/connect/instagram
   ↓
Step 3: WordPress → POST https://api.getlate.dev/v1/connect
   Request: {
     "provider": "instagram",
     "redirect_uri": "https://chavetastech.io/wp-json/aurareels/v1/social/callback"
   }
   Response: {
     "url": "https://app.getlate.dev/connect/instagram?token=..."
   }
   ↓
Step 4: Frontend redirects user to Late.dev hosted UI
   User sees Late.dev branded OAuth interface
   ↓
Step 5: User authorizes on Instagram (via Facebook)
   ↓
Step 6: User selects Instagram account in Late.dev UI
   ↓
Step 7: Late.dev redirects back to your callback URL
   https://chavetastech.io/wp-json/aurareels/v1/social/callback?status=success
   ↓
Step 8: WordPress callback redirects to frontend
   https://chavetastech.io/dashboard/social-accounts?status=success&provider=instagram
   ↓
Step 9: Frontend fetches updated profiles
   GET /wp-json/aurareels/v1/social/profiles
   ↓
Step 10: User sees connected Instagram account in YOUR app ✅
```

**Key Point**: User only sees Late.dev for 10-15 seconds during OAuth. Rest of experience is 100% your app.

---

## Prerequisites

### 1. Late.dev Account Setup

1. **Sign up**: https://getlate.dev/
2. **Choose plan**:
   - **Build**: $19/month (120 posts/month, 10 profiles) - Good for testing
   - **Accelerate**: $49/month (unlimited posts, 50 profiles) - **Recommended**
   - **Unlimited**: Custom pricing (enterprise features)
3. **Get API Key**: Settings → API Keys → Create Key
4. **Save API Key**: Starts with `sk_` (64 hex characters)

### 2. Connect Your Test Accounts

Before going live, connect test accounts to Late.dev:
1. **Dashboard**: https://app.getlate.dev/
2. **Connect accounts**: TikTok, Instagram, LinkedIn, etc.
3. **Test publishing**: Verify each platform works

### 3. WordPress Requirements

- WordPress 5.0+
- PHP 7.4+
- MySQL 5.7+
- cURL extension enabled
- JSON extension enabled

### 4. Frontend Requirements

- Next.js 14+ (already in aurareels project)
- Material-UI v6 (already installed)
- Axios (already installed)
- @mui/x-date-pickers (install for scheduling)

```bash
cd /Users/david/work-projects/aurareels-workspace/aurareels
npm install @mui/x-date-pickers date-fns
```

---

## Database Schema

### Table: `wp_aurareels_social_posts`

Store posts published through Late.dev with analytics tracking.

```sql
CREATE TABLE `wp_aurareels_social_posts` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `wp_user_id` BIGINT(20) UNSIGNED NOT NULL COMMENT 'WordPress user ID',
  `video_job_id` VARCHAR(255) NOT NULL COMMENT 'FK to wp_chavetas_video_uploader.job_id',
  `late_post_id` VARCHAR(255) NOT NULL COMMENT 'Post ID from Late.dev',
  `late_profile_ids` TEXT NOT NULL COMMENT 'JSON array of Late.dev profile IDs',
  `platforms` TEXT NOT NULL COMMENT 'JSON array: [instagram, tiktok, linkedin]',
  `status` ENUM('draft', 'scheduled', 'publishing', 'published', 'partial', 'failed') DEFAULT 'draft',
  `scheduled_at` DATETIME DEFAULT NULL COMMENT 'UTC timestamp for scheduled posts',
  `published_at` DATETIME DEFAULT NULL COMMENT 'Actual publish time',
  `content` TEXT DEFAULT NULL COMMENT 'Post caption/text',
  `hashtags` TEXT DEFAULT NULL COMMENT 'JSON array of hashtags',
  `video_url` TEXT NOT NULL COMMENT 'Cloudflare Stream MP4 URL',
  `thumbnail_url` TEXT DEFAULT NULL COMMENT 'Video thumbnail',
  `platform_urls` TEXT DEFAULT NULL COMMENT 'JSON: {instagram: "url", tiktok: "url"}',
  `analytics_data` LONGTEXT DEFAULT NULL COMMENT 'JSON: cached analytics from Late.dev',
  `analytics_last_sync` DATETIME DEFAULT NULL COMMENT 'Last analytics fetch time',
  `error_message` TEXT DEFAULT NULL COMMENT 'Error details if failed',
  `late_response` LONGTEXT DEFAULT NULL COMMENT 'Full Late.dev API response',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_late_post` (`late_post_id`),
  KEY `user_status` (`wp_user_id`, `status`),
  KEY `video_job` (`video_job_id`),
  KEY `scheduled_posts` (`status`, `scheduled_at`),
  KEY `published_posts` (`status`, `published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Why this schema?**
- `late_post_id`: Primary reference to Late.dev post
- `late_profile_ids`: Store which profiles were selected (for re-publishing)
- `platforms`: Track target platforms separately (for filtering)
- `platform_urls`: Store direct links to posts on each platform
- `analytics_data`: Cache analytics to reduce API calls
- `analytics_last_sync`: Track when to refresh analytics

**No separate accounts table needed**: Late.dev manages account connections; you only store post references.

---

### Migration Script

Create file: `aurareels-core/migrations/create-social-posts-table.php`

```php
<?php
/**
 * Create social posts table
 * Run on plugin activation or via WP-CLI
 */

function aurareels_social_create_posts_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'aurareels_social_posts';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
        `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `wp_user_id` BIGINT(20) UNSIGNED NOT NULL,
        `video_job_id` VARCHAR(255) NOT NULL,
        `late_post_id` VARCHAR(255) NOT NULL,
        `late_profile_ids` TEXT NOT NULL,
        `platforms` TEXT NOT NULL,
        `status` ENUM('draft', 'scheduled', 'publishing', 'published', 'partial', 'failed') DEFAULT 'draft',
        `scheduled_at` DATETIME DEFAULT NULL,
        `published_at` DATETIME DEFAULT NULL,
        `content` TEXT DEFAULT NULL,
        `hashtags` TEXT DEFAULT NULL,
        `video_url` TEXT NOT NULL,
        `thumbnail_url` TEXT DEFAULT NULL,
        `platform_urls` TEXT DEFAULT NULL,
        `analytics_data` LONGTEXT DEFAULT NULL,
        `analytics_last_sync` DATETIME DEFAULT NULL,
        `error_message` TEXT DEFAULT NULL,
        `late_response` LONGTEXT DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_late_post` (`late_post_id`),
        KEY `user_status` (`wp_user_id`, `status`),
        KEY `video_job` (`video_job_id`),
        KEY `scheduled_posts` (`status`, `scheduled_at`)
    ) {$charset_collate};";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    // Check if table was created
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name ) {
        error_log( 'AuraReels Social: Posts table created successfully' );
        return true;
    } else {
        error_log( 'AuraReels Social: Failed to create posts table' );
        return false;
    }
}

// Hook to plugin activation
register_activation_hook( __FILE__, 'aurareels_social_create_posts_table' );
```

**Run migration**:
```bash
# Option 1: Activate plugin (runs automatically)
wp plugin activate aurareels-core

# Option 2: Run via WP-CLI
wp eval 'require_once "path/to/migration.php"; aurareels_social_create_posts_table();'

# Option 3: Run via WordPress admin
# Navigate to: Tools → AuraReels Migration → Create Social Tables
```

---

## WordPress Backend Implementation

### File Structure

```
aurareels-core/
└── api/
    └── social/
        ├── class-late-client.php              # Late.dev API wrapper
        ├── class-social-routes.php            # REST API endpoints
        ├── class-social-publisher.php         # Publishing logic
        ├── class-social-analytics.php         # Analytics fetcher
        └── class-social-scheduler.php         # Cron for scheduled posts
```

---

### 1. Late.dev API Client Wrapper

**File**: `aurareels-core/api/social/class-late-client.php`

```php
<?php
/**
 * Late.dev API Client
 *
 * Wrapper for all Late.dev API interactions
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Chavetas_Late_Client {

    private $api_key;
    private $base_url = 'https://api.getlate.dev/v1';

    public function __construct() {
        $this->api_key = defined( 'LATE_API_KEY' ) ? LATE_API_KEY : '';

        if ( empty( $this->api_key ) ) {
            error_log( 'Late.dev API key not configured in wp-config.php' );
        }
    }

    /**
     * Make authenticated request to Late.dev API
     */
    private function request( $method, $endpoint, $body = null ) {
        $url = $this->base_url . $endpoint;

        $args = [
            'method'  => strtoupper( $method ),
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 30,
        ];

        if ( $body ) {
            $args['body'] = json_encode( $body );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            error_log( 'Late.dev API Error: ' . $response->get_error_message() );
            return [
                'success' => false,
                'error'   => $response->get_error_message(),
            ];
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body_data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status_code >= 400 ) {
            error_log( 'Late.dev API Error ' . $status_code . ': ' . wp_remote_retrieve_body( $response ) );
            return [
                'success' => false,
                'error'   => $body_data['message'] ?? 'Unknown API error',
                'code'    => $status_code,
            ];
        }

        return [
            'success' => true,
            'data'    => $body_data,
            'code'    => $status_code,
        ];
    }

    // ==================== AUTHENTICATION ====================

    /**
     * Initiate OAuth connection (Standard Flow)
     *
     * @param string $provider instagram, tiktok, linkedin, twitter, etc.
     * @param string $redirect_uri Your callback URL
     * @return array ['success' => bool, 'url' => string, 'error' => string]
     */
    public function initiate_connect( $provider, $redirect_uri ) {
        return $this->request( 'POST', '/connect', [
            'provider'     => $provider,
            'redirect_uri' => $redirect_uri,
        ]);
    }

    // ==================== PROFILES ====================

    /**
     * Get user's connected social media profiles
     *
     * @return array ['success' => bool, 'data' => array, 'error' => string]
     */
    public function get_profiles() {
        return $this->request( 'GET', '/profiles' );
    }

    // ==================== POSTS ====================

    /**
     * Create a post (publish immediately or schedule)
     *
     * @param array $data Post data
     * @return array ['success' => bool, 'data' => array, 'error' => string]
     *
     * Example $data:
     * [
     *   'profiles' => ['profile_id_1', 'profile_id_2'],
     *   'content' => 'Your caption with hashtags',
     *   'mediaItems' => [
     *     ['url' => 'https://video-url.mp4', 'type' => 'video']
     *   ],
     *   'publishNow' => true, // OR scheduledFor + timezone
     *   'scheduledFor' => '2024-12-25T18:00:00Z',
     *   'timezone' => 'America/New_York',
     *   'hashtags' => ['video', 'content'],
     *   'firstComment' => 'Additional text' // Optional
     * ]
     */
    public function create_post( $data ) {
        return $this->request( 'POST', '/posts', $data );
    }

    /**
     * Get single post details
     *
     * @param string $post_id Late.dev post ID
     * @return array
     */
    public function get_post( $post_id ) {
        return $this->request( 'GET', "/posts/{$post_id}" );
    }

    /**
     * Update post (only works for draft/scheduled/failed)
     *
     * @param string $post_id Late.dev post ID
     * @param array $data Updated fields
     * @return array
     */
    public function update_post( $post_id, $data ) {
        return $this->request( 'PUT', "/posts/{$post_id}", $data );
    }

    /**
     * Delete post (cancel scheduled or remove draft)
     *
     * @param string $post_id Late.dev post ID
     * @return array
     */
    public function delete_post( $post_id ) {
        return $this->request( 'DELETE', "/posts/{$post_id}" );
    }

    /**
     * Retry failed post
     *
     * @param string $post_id Late.dev post ID
     * @return array
     */
    public function retry_post( $post_id ) {
        return $this->request( 'POST', "/posts/{$post_id}/retry" );
    }

    /**
     * List posts with filters
     *
     * @param array $params Query parameters
     * @return array
     *
     * Example $params:
     * [
     *   'status' => 'published', // draft, scheduled, published, failed
     *   'platform' => 'instagram',
     *   'dateFrom' => '2024-01-01',
     *   'dateTo' => '2024-12-31',
     *   'limit' => 50,
     *   'page' => 1,
     * ]
     */
    public function list_posts( $params = [] ) {
        $query_string = http_build_query( $params );
        return $this->request( 'GET', '/posts?' . $query_string );
    }

    // ==================== ANALYTICS ====================

    /**
     * Get analytics for a specific post
     *
     * @param string $post_id Late.dev post ID
     * @return array
     *
     * Returns:
     * [
     *   'analytics' => [
     *     'impressions' => 1234,
     *     'reach' => 567,
     *     'likes' => 89,
     *     'comments' => 12,
     *     'shares' => 5,
     *     'views' => 890,
     *     'engagementRate' => 7.2,
     *   ],
     *   'platformAnalytics' => [
     *     ['platform' => 'instagram', 'likes' => 45, ...],
     *     ['platform' => 'tiktok', 'likes' => 44, ...],
     *   ]
     * ]
     */
    public function get_post_analytics( $post_id ) {
        return $this->request( 'GET', "/analytics?postId={$post_id}" );
    }

    /**
     * Get analytics for multiple posts
     *
     * @param array $params Filters
     * @return array
     */
    public function list_analytics( $params = [] ) {
        $query_string = http_build_query( $params );
        return $this->request( 'GET', '/analytics?' . $query_string );
    }

    // ==================== HELPER METHODS ====================

    /**
     * Check if API key is configured
     *
     * @return bool
     */
    public function is_configured() {
        return ! empty( $this->api_key );
    }

    /**
     * Validate API key by making test request
     *
     * @return bool
     */
    public function validate_api_key() {
        $result = $this->get_profiles();
        return $result['success'] ?? false;
    }
}
```

---

### 2. REST API Routes

**File**: `aurareels-core/api/social/class-social-routes.php`

```php
<?php
/**
 * Social Media REST API Routes
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Chavetas_Social_Routes {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        $namespace = 'aurareels/v1/social';

        // ==================== AUTHENTICATION ====================

        // Initiate OAuth connection
        register_rest_route( $namespace, '/connect/(?P<provider>[a-z_]+)', [
            'methods'  => 'POST',
            'callback' => [ $this, 'connect_provider' ],
            'permission_callback' => [ $this, 'check_user_logged_in' ],
        ]);

        // OAuth callback handler
        register_rest_route( $namespace, '/callback', [
            'methods'  => 'GET',
            'callback' => [ $this, 'handle_callback' ],
            'permission_callback' => '__return_true', // Public
        ]);

        // ==================== PROFILES ====================

        // Get connected profiles
        register_rest_route( $namespace, '/profiles', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_profiles' ],
            'permission_callback' => [ $this, 'check_user_logged_in' ],
        ]);

        // ==================== PUBLISHING ====================

        // Create post (immediate or scheduled)
        register_rest_route( $namespace, '/posts', [
            'methods'  => 'POST',
            'callback' => [ $this, 'create_post' ],
            'permission_callback' => [ $this, 'check_user_logged_in' ],
        ]);

        // List user's posts
        register_rest_route( $namespace, '/posts', [
            'methods'  => 'GET',
            'callback' => [ $this, 'list_posts' ],
            'permission_callback' => [ $this, 'check_user_logged_in' ],
        ]);

        // Get single post
        register_rest_route( $namespace, '/posts/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_post' ],
            'permission_callback' => [ $this, 'check_user_logged_in' ],
        ]);

        // Update post (draft/scheduled only)
        register_rest_route( $namespace, '/posts/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods'  => 'PUT',
            'callback' => [ $this, 'update_post' ],
            'permission_callback' => [ $this, 'check_user_logged_in' ],
        ]);

        // Delete/cancel post
        register_rest_route( $namespace, '/posts/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods'  => 'DELETE',
            'callback' => [ $this, 'delete_post' ],
            'permission_callback' => [ $this, 'check_user_logged_in' ],
        ]);

        // Retry failed post
        register_rest_route( $namespace, '/posts/(?P<id>[a-zA-Z0-9_-]+)/retry', [
            'methods'  => 'POST',
            'callback' => [ $this, 'retry_post' ],
            'permission_callback' => [ $this, 'check_user_logged_in' ],
        ]);

        // ==================== ANALYTICS ====================

        // Get post analytics
        register_rest_route( $namespace, '/analytics/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_analytics' ],
            'permission_callback' => [ $this, 'check_user_logged_in' ],
        ]);

        // Refresh analytics (force sync)
        register_rest_route( $namespace, '/analytics/(?P<id>[a-zA-Z0-9_-]+)/refresh', [
            'methods'  => 'POST',
            'callback' => [ $this, 'refresh_analytics' ],
            'permission_callback' => [ $this, 'check_user_logged_in' ],
        ]);
    }

    // ==================== AUTHENTICATION HANDLERS ====================

    /**
     * Initiate OAuth connection
     */
    public function connect_provider( $request ) {
        $provider = $request->get_param( 'provider' );
        $redirect_uri = get_site_url() . '/wp-json/aurareels/v1/social/callback';

        $late = new Chavetas_Late_Client();
        $result = $late->initiate_connect( $provider, $redirect_uri );

        if ( ! $result['success'] ) {
            return new WP_Error(
                'connect_error',
                $result['error'] ?? 'Failed to initiate connection',
                [ 'status' => 500 ]
            );
        }

        return [
            'success' => true,
            'url'     => $result['data']['url'] ?? '',
            'provider' => $provider,
        ];
    }

    /**
     * Handle OAuth callback from Late.dev
     */
    public function handle_callback( $request ) {
        $status = $request->get_param( 'status' );
        $provider = $request->get_param( 'provider' );
        $error = $request->get_param( 'error' );

        $frontend_url = get_site_url() . '/dashboard/social-accounts';

        if ( $status === 'success' ) {
            wp_redirect( $frontend_url . '?status=success&provider=' . $provider );
        } else {
            wp_redirect( $frontend_url . '?status=error&message=' . urlencode( $error ?? 'Connection failed' ) );
        }
        exit;
    }

    // ==================== PROFILE HANDLERS ====================

    /**
     * Get user's connected profiles from Late.dev
     */
    public function get_profiles( $request ) {
        $late = new Chavetas_Late_Client();
        $result = $late->get_profiles();

        if ( ! $result['success'] ) {
            return new WP_Error(
                'profiles_error',
                $result['error'] ?? 'Failed to fetch profiles',
                [ 'status' => 500 ]
            );
        }

        return [
            'success'  => true,
            'profiles' => $result['data'] ?? [],
        ];
    }

    // ==================== PUBLISHING HANDLERS ====================

    /**
     * Create post (publish immediately or schedule)
     */
    public function create_post( $request ) {
        global $wpdb;

        $user_id       = get_current_user_id();
        $video_job_id  = $request->get_param( 'video_job_id' );
        $profile_ids   = $request->get_param( 'profiles' ); // Array
        $content       = $request->get_param( 'content' );
        $scheduled_at  = $request->get_param( 'scheduled_at' ); // ISO 8601 format
        $timezone      = $request->get_param( 'timezone' ) ?: 'UTC';
        $hashtags      = $request->get_param( 'hashtags' ) ?: [];
        $first_comment = $request->get_param( 'first_comment' );

        // Validate video job exists and get MP4 URL
        $job = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}chavetas_video_uploader WHERE job_id = %s AND wp_user_id = %d",
            $video_job_id,
            $user_id
        ));

        if ( ! $job || ! $job->mp4_url ) {
            return new WP_Error(
                'invalid_video',
                'Video not found or MP4 not ready',
                [ 'status' => 400 ]
            );
        }

        // Prepare Late.dev API request
        $late_data = [
            'profiles'   => $profile_ids,
            'content'    => $content,
            'mediaItems' => [
                [
                    'url'  => $job->mp4_url,
                    'type' => 'video',
                ]
            ],
            'hashtags' => $hashtags,
        ];

        if ( $first_comment ) {
            $late_data['firstComment'] = $first_comment;
        }

        // Publish now or schedule
        if ( $scheduled_at ) {
            $late_data['scheduledFor'] = $scheduled_at;
            $late_data['timezone'] = $timezone;
        } else {
            $late_data['publishNow'] = true;
        }

        // Call Late.dev API
        $late = new Chavetas_Late_Client();
        $result = $late->create_post( $late_data );

        if ( ! $result['success'] ) {
            return new WP_Error(
                'publish_error',
                $result['error'] ?? 'Failed to create post',
                [ 'status' => 500 ]
            );
        }

        $late_post = $result['data'];

        // Store in database
        $wpdb->insert(
            $wpdb->prefix . 'aurareels_social_posts',
            [
                'wp_user_id'       => $user_id,
                'video_job_id'     => $video_job_id,
                'late_post_id'     => $late_post['_id'],
                'late_profile_ids' => json_encode( $profile_ids ),
                'platforms'        => json_encode( $late_post['platforms'] ?? [] ),
                'status'           => $late_post['status'] ?? 'publishing',
                'scheduled_at'     => $scheduled_at ? gmdate( 'Y-m-d H:i:s', strtotime( $scheduled_at ) ) : null,
                'content'          => $content,
                'hashtags'         => json_encode( $hashtags ),
                'video_url'        => $job->mp4_url,
                'late_response'    => json_encode( $late_post ),
                'created_at'       => current_time( 'mysql' ),
            ],
            [
                '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
            ]
        );

        $post_id = $wpdb->insert_id;

        return [
            'success'      => true,
            'post_id'      => $post_id,
            'late_post_id' => $late_post['_id'],
            'status'       => $late_post['status'],
            'message'      => $scheduled_at ? 'Post scheduled successfully' : 'Publishing...',
        ];
    }

    /**
     * List user's posts
     */
    public function list_posts( $request ) {
        global $wpdb;

        $user_id  = get_current_user_id();
        $status   = $request->get_param( 'status' );
        $platform = $request->get_param( 'platform' );
        $limit    = $request->get_param( 'limit' ) ?: 20;
        $offset   = $request->get_param( 'offset' ) ?: 0;

        $where = [ "wp_user_id = %d" ];
        $params = [ $user_id ];

        if ( $status ) {
            $where[] = "status = %s";
            $params[] = $status;
        }

        if ( $platform ) {
            $where[] = "platforms LIKE %s";
            $params[] = '%' . $wpdb->esc_like( $platform ) . '%';
        }

        $where_sql = implode( ' AND ', $where );

        $posts = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aurareels_social_posts
             WHERE {$where_sql}
             ORDER BY created_at DESC
             LIMIT %d OFFSET %d",
            array_merge( $params, [ $limit, $offset ] )
        ));

        $total = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aurareels_social_posts WHERE {$where_sql}",
            $params
        ));

        // Format posts
        $formatted_posts = array_map( function( $post ) {
            return [
                'id'               => (int) $post->id,
                'late_post_id'     => $post->late_post_id,
                'video_job_id'     => $post->video_job_id,
                'platforms'        => json_decode( $post->platforms, true ),
                'status'           => $post->status,
                'scheduled_at'     => $post->scheduled_at,
                'published_at'     => $post->published_at,
                'content'          => $post->content,
                'hashtags'         => json_decode( $post->hashtags, true ),
                'video_url'        => $post->video_url,
                'thumbnail_url'    => $post->thumbnail_url,
                'platform_urls'    => json_decode( $post->platform_urls, true ),
                'analytics'        => json_decode( $post->analytics_data, true ),
                'error_message'    => $post->error_message,
                'created_at'       => $post->created_at,
            ];
        }, $posts );

        return [
            'success' => true,
            'total'   => (int) $total,
            'posts'   => $formatted_posts,
        ];
    }

    /**
     * Get single post with fresh data from Late.dev
     */
    public function get_post( $request ) {
        global $wpdb;

        $post_id = $request->get_param( 'id' );
        $user_id = get_current_user_id();

        // Get from database
        $post = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aurareels_social_posts
             WHERE id = %d AND wp_user_id = %d",
            $post_id,
            $user_id
        ));

        if ( ! $post ) {
            return new WP_Error( 'not_found', 'Post not found', [ 'status' => 404 ] );
        }

        // Fetch fresh data from Late.dev
        $late = new Chavetas_Late_Client();
        $result = $late->get_post( $post->late_post_id );

        if ( $result['success'] && isset( $result['data'] ) ) {
            $late_post = $result['data'];

            // Update database with fresh data
            $wpdb->update(
                $wpdb->prefix . 'aurareels_social_posts',
                [
                    'status'        => $late_post['status'] ?? $post->status,
                    'published_at'  => isset( $late_post['publishedAt'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( $late_post['publishedAt'] ) ) : $post->published_at,
                    'platform_urls' => isset( $late_post['platformPostUrl'] ) ? json_encode( $late_post['platformPostUrl'] ) : $post->platform_urls,
                    'late_response' => json_encode( $late_post ),
                ],
                [ 'id' => $post_id ],
                [ '%s', '%s', '%s', '%s' ],
                [ '%d' ]
            );

            // Update local object
            $post->status = $late_post['status'] ?? $post->status;
            $post->platform_urls = isset( $late_post['platformPostUrl'] ) ? json_encode( $late_post['platformPostUrl'] ) : $post->platform_urls;
        }

        return [
            'success' => true,
            'post'    => [
                'id'            => (int) $post->id,
                'late_post_id'  => $post->late_post_id,
                'video_job_id'  => $post->video_job_id,
                'platforms'     => json_decode( $post->platforms, true ),
                'status'        => $post->status,
                'scheduled_at'  => $post->scheduled_at,
                'published_at'  => $post->published_at,
                'content'       => $post->content,
                'hashtags'      => json_decode( $post->hashtags, true ),
                'video_url'     => $post->video_url,
                'platform_urls' => json_decode( $post->platform_urls, true ),
                'error_message' => $post->error_message,
                'created_at'    => $post->created_at,
            ],
        ];
    }

    /**
     * Update post (draft/scheduled only)
     */
    public function update_post( $request ) {
        global $wpdb;

        $post_id     = $request->get_param( 'id' );
        $user_id     = get_current_user_id();
        $content     = $request->get_param( 'content' );
        $scheduled_at = $request->get_param( 'scheduled_at' );

        // Get post
        $post = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aurareels_social_posts
             WHERE id = %d AND wp_user_id = %d",
            $post_id,
            $user_id
        ));

        if ( ! $post ) {
            return new WP_Error( 'not_found', 'Post not found', [ 'status' => 404 ] );
        }

        if ( ! in_array( $post->status, [ 'draft', 'scheduled', 'failed' ] ) ) {
            return new WP_Error(
                'invalid_status',
                'Can only update draft, scheduled, or failed posts',
                [ 'status' => 400 ]
            );
        }

        // Update via Late.dev
        $late = new Chavetas_Late_Client();
        $update_data = [];

        if ( $content ) {
            $update_data['content'] = $content;
        }

        if ( $scheduled_at ) {
            $update_data['scheduledFor'] = $scheduled_at;
        }

        $result = $late->update_post( $post->late_post_id, $update_data );

        if ( ! $result['success'] ) {
            return new WP_Error(
                'update_error',
                $result['error'] ?? 'Failed to update post',
                [ 'status' => 500 ]
            );
        }

        // Update database
        $update_fields = [];
        $update_values = [];

        if ( $content ) {
            $update_fields['content'] = '%s';
            $update_values[] = $content;
        }

        if ( $scheduled_at ) {
            $update_fields['scheduled_at'] = '%s';
            $update_values[] = gmdate( 'Y-m-d H:i:s', strtotime( $scheduled_at ) );
        }

        if ( ! empty( $update_fields ) ) {
            $update_values[] = $post_id;
            $wpdb->update(
                $wpdb->prefix . 'aurareels_social_posts',
                array_combine( array_keys( $update_fields ), array_slice( $update_values, 0, -1 ) ),
                [ 'id' => $post_id ],
                array_values( $update_fields ),
                [ '%d' ]
            );
        }

        return [
            'success' => true,
            'message' => 'Post updated successfully',
        ];
    }

    /**
     * Delete/cancel post
     */
    public function delete_post( $request ) {
        global $wpdb;

        $post_id = $request->get_param( 'id' );
        $user_id = get_current_user_id();

        // Get post
        $post = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aurareels_social_posts
             WHERE id = %d AND wp_user_id = %d",
            $post_id,
            $user_id
        ));

        if ( ! $post ) {
            return new WP_Error( 'not_found', 'Post not found', [ 'status' => 404 ] );
        }

        // Delete via Late.dev
        $late = new Chavetas_Late_Client();
        $result = $late->delete_post( $post->late_post_id );

        // Delete from database even if API call failed
        $wpdb->delete(
            $wpdb->prefix . 'aurareels_social_posts',
            [ 'id' => $post_id ],
            [ '%d' ]
        );

        return [
            'success' => true,
            'message' => 'Post deleted successfully',
        ];
    }

    /**
     * Retry failed post
     */
    public function retry_post( $request ) {
        global $wpdb;

        $post_id = $request->get_param( 'id' );
        $user_id = get_current_user_id();

        // Get post
        $post = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aurareels_social_posts
             WHERE id = %d AND wp_user_id = %d",
            $post_id,
            $user_id
        ));

        if ( ! $post ) {
            return new WP_Error( 'not_found', 'Post not found', [ 'status' => 404 ] );
        }

        if ( $post->status !== 'failed' ) {
            return new WP_Error(
                'invalid_status',
                'Can only retry failed posts',
                [ 'status' => 400 ]
            );
        }

        // Retry via Late.dev
        $late = new Chavetas_Late_Client();
        $result = $late->retry_post( $post->late_post_id );

        if ( ! $result['success'] ) {
            return new WP_Error(
                'retry_error',
                $result['error'] ?? 'Failed to retry post',
                [ 'status' => 500 ]
            );
        }

        // Update status
        $wpdb->update(
            $wpdb->prefix . 'aurareels_social_posts',
            [
                'status'        => 'publishing',
                'error_message' => null,
            ],
            [ 'id' => $post_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );

        return [
            'success' => true,
            'message' => 'Post retry initiated',
        ];
    }

    // ==================== ANALYTICS HANDLERS ====================

    /**
     * Get post analytics (from cache or Late.dev)
     */
    public function get_analytics( $request ) {
        global $wpdb;

        $post_id = $request->get_param( 'id' );
        $user_id = get_current_user_id();
        $force_refresh = $request->get_param( 'refresh' ) === 'true';

        // Get post
        $post = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aurareels_social_posts
             WHERE id = %d AND wp_user_id = %d",
            $post_id,
            $user_id
        ));

        if ( ! $post ) {
            return new WP_Error( 'not_found', 'Post not found', [ 'status' => 404 ] );
        }

        // Check if cached analytics are fresh (< 1 hour old)
        $cache_age = $post->analytics_last_sync
            ? time() - strtotime( $post->analytics_last_sync )
            : PHP_INT_MAX;

        $use_cache = ! $force_refresh && $cache_age < 3600 && ! empty( $post->analytics_data );

        if ( $use_cache ) {
            // Return cached analytics
            return [
                'success'    => true,
                'analytics'  => json_decode( $post->analytics_data, true ),
                'cached'     => true,
                'cache_age'  => $cache_age,
                'last_sync'  => $post->analytics_last_sync,
            ];
        }

        // Fetch fresh analytics from Late.dev
        $late = new Chavetas_Late_Client();
        $result = $late->get_post_analytics( $post->late_post_id );

        if ( ! $result['success'] ) {
            // Return cached data if API fails
            if ( ! empty( $post->analytics_data ) ) {
                return [
                    'success'   => true,
                    'analytics' => json_decode( $post->analytics_data, true ),
                    'cached'    => true,
                    'error'     => 'API failed, showing cached data',
                ];
            }

            return new WP_Error(
                'analytics_error',
                $result['error'] ?? 'Failed to fetch analytics',
                [ 'status' => 500 ]
            );
        }

        $analytics = $result['data'];

        // Update cache
        $wpdb->update(
            $wpdb->prefix . 'aurareels_social_posts',
            [
                'analytics_data'      => json_encode( $analytics ),
                'analytics_last_sync' => current_time( 'mysql' ),
            ],
            [ 'id' => $post_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );

        return [
            'success'    => true,
            'analytics'  => $analytics,
            'cached'     => false,
            'last_sync'  => current_time( 'mysql' ),
        ];
    }

    /**
     * Force refresh analytics
     */
    public function refresh_analytics( $request ) {
        // Just call get_analytics with force refresh
        $request->set_param( 'refresh', 'true' );
        return $this->get_analytics( $request );
    }

    // ==================== PERMISSION CALLBACKS ====================

    public function check_user_logged_in() {
        return is_user_logged_in();
    }
}

// Initialize routes
new Chavetas_Social_Routes();
```

---

### 3. Configuration

**File**: `wp-config.php` (add this at the end, before "That's all, stop editing!")

```php
// ===== LATE.DEV SOCIAL MEDIA INTEGRATION =====

// Late.dev API Key (get from https://app.getlate.dev/settings/api-keys)
define( 'LATE_API_KEY', 'sk_your_64_character_api_key_here' );

// Optional: Late.dev API Base URL (only change if using custom endpoint)
// define( 'LATE_API_BASE_URL', 'https://api.getlate.dev/v1' );
```

---

## REST API Endpoints

### Base URL
```
https://chavetastech.io/wp-json/aurareels/v1/social
```

### Authentication
All endpoints require JWT authentication:
```http
Authorization: Bearer {JWT_TOKEN}
```

---

### Endpoint Summary

| Method | Endpoint | Description |
|--------|----------|-------------|
| **POST** | `/connect/{provider}` | Initiate OAuth connection |
| **GET** | `/callback` | OAuth callback handler (public) |
| **GET** | `/profiles` | Get connected profiles |
| **POST** | `/posts` | Create post (publish/schedule) |
| **GET** | `/posts` | List user's posts |
| **GET** | `/posts/{id}` | Get single post |
| **PUT** | `/posts/{id}` | Update post (draft/scheduled) |
| **DELETE** | `/posts/{id}` | Delete/cancel post |
| **POST** | `/posts/{id}/retry` | Retry failed post |
| **GET** | `/analytics/{id}` | Get post analytics |
| **POST** | `/analytics/{id}/refresh` | Force refresh analytics |

---

### Detailed Endpoint Documentation

#### 1. **POST** `/connect/{provider}`

Initiate OAuth connection for a social media platform.

**Path Parameters**:
- `provider`: Platform name (`instagram`, `tiktok`, `linkedin`, `twitter`, `youtube`, `facebook`, `threads`, `reddit`, `pinterest`, `bluesky`, `google_business_profile`)

**Response**:
```json
{
  "success": true,
  "url": "https://app.getlate.dev/connect/instagram?token=abc123...",
  "provider": "instagram"
}
```

**Frontend Action**:
```javascript
// Redirect user to the returned URL
window.location.href = response.url;
```

---

#### 2. **GET** `/profiles`

Get user's connected social media profiles from Late.dev.

**Query Parameters**: None

**Response**:
```json
{
  "success": true,
  "profiles": [
    {
      "_id": "profile_123",
      "name": "My Instagram Account",
      "color": "#E1306C",
      "accounts": [
        {
          "platform": "instagram",
          "username": "@myaccount",
          "avatar": "https://...",
          "accountId": "123456"
        }
      ]
    },
    {
      "_id": "profile_456",
      "name": "My TikTok",
      "color": "#000000",
      "accounts": [
        {
          "platform": "tiktok",
          "username": "@mytiktok",
          "avatar": "https://...",
          "accountId": "789012"
        }
      ]
    }
  ]
}
```

---

#### 3. **POST** `/posts`

Create a post (publish immediately or schedule for later).

**Request Body**:
```json
{
  "video_job_id": "uuid-from-video-upload",
  "profiles": ["profile_123", "profile_456"],
  "content": "Check out this amazing video! 🎥 #video #content #viral",
  "hashtags": ["video", "content", "viral"],
  "first_comment": "What do you think? Let me know in the comments!",
  "scheduled_at": "2024-12-25T18:00:00Z",
  "timezone": "America/New_York"
}
```

**Fields**:
- `video_job_id` (required): Video job ID from `wp_chavetas_video_uploader`
- `profiles` (required): Array of Late.dev profile IDs to publish to
- `content` (required): Post caption/text
- `hashtags` (optional): Array of hashtag strings (without #)
- `first_comment` (optional): First comment text (Instagram, TikTok)
- `scheduled_at` (optional): ISO 8601 timestamp. **Omit to publish immediately**
- `timezone` (optional): Timezone for scheduled posts (default: UTC)

**Response (Immediate)**:
```json
{
  "success": true,
  "post_id": 42,
  "late_post_id": "late_abc123",
  "status": "publishing",
  "message": "Publishing..."
}
```

**Response (Scheduled)**:
```json
{
  "success": true,
  "post_id": 43,
  "late_post_id": "late_xyz789",
  "status": "scheduled",
  "message": "Post scheduled successfully"
}
```

---

#### 4. **GET** `/posts`

List user's social media posts with filters.

**Query Parameters**:
- `status` (optional): Filter by status (`draft`, `scheduled`, `publishing`, `published`, `partial`, `failed`)
- `platform` (optional): Filter by platform (`instagram`, `tiktok`, etc.)
- `limit` (optional): Number of posts per page (default: 20)
- `offset` (optional): Pagination offset (default: 0)

**Response**:
```json
{
  "success": true,
  "total": 127,
  "posts": [
    {
      "id": 42,
      "late_post_id": "late_abc123",
      "video_job_id": "video-uuid-123",
      "platforms": ["instagram", "tiktok"],
      "status": "published",
      "scheduled_at": null,
      "published_at": "2024-12-16 10:00:00",
      "content": "Check out this video!",
      "hashtags": ["video", "content"],
      "video_url": "https://cloudflare.com/video.mp4",
      "thumbnail_url": "https://cloudflare.com/thumb.jpg",
      "platform_urls": {
        "instagram": "https://www.instagram.com/p/abc123/",
        "tiktok": "https://www.tiktok.com/@user/video/123"
      },
      "analytics": {
        "impressions": 5420,
        "likes": 287,
        "comments": 42,
        "shares": 18,
        "engagementRate": 6.4
      },
      "error_message": null,
      "created_at": "2024-12-16 09:45:00"
    }
  ]
}
```

---

#### 5. **GET** `/posts/{id}`

Get single post with fresh data from Late.dev.

**Response**:
```json
{
  "success": true,
  "post": {
    "id": 42,
    "late_post_id": "late_abc123",
    "video_job_id": "video-uuid-123",
    "platforms": ["instagram", "tiktok"],
    "status": "published",
    "scheduled_at": null,
    "published_at": "2024-12-16 10:00:00",
    "content": "Check out this video!",
    "hashtags": ["video", "content"],
    "video_url": "https://cloudflare.com/video.mp4",
    "platform_urls": {
      "instagram": "https://www.instagram.com/p/abc123/",
      "tiktok": "https://www.tiktok.com/@user/video/123"
    },
    "error_message": null,
    "created_at": "2024-12-16 09:45:00"
  }
}
```

---

#### 6. **PUT** `/posts/{id}`

Update a post (only works for draft, scheduled, or failed posts).

**Request Body**:
```json
{
  "content": "Updated caption with new hashtags #new",
  "scheduled_at": "2024-12-26T12:00:00Z"
}
```

**Response**:
```json
{
  "success": true,
  "message": "Post updated successfully"
}
```

---

#### 7. **DELETE** `/posts/{id}`

Delete or cancel a post.

- **Draft/Scheduled**: Cancels and removes
- **Published**: Cannot delete (only remove from database reference)

**Response**:
```json
{
  "success": true,
  "message": "Post deleted successfully"
}
```

---

#### 8. **POST** `/posts/{id}/retry`

Retry publishing a failed post.

**Response**:
```json
{
  "success": true,
  "message": "Post retry initiated"
}
```

---

#### 9. **GET** `/analytics/{id}`

Get analytics for a published post.

**Query Parameters**:
- `refresh` (optional): Set to `true` to force refresh (default: uses 1-hour cache)

**Response**:
```json
{
  "success": true,
  "analytics": {
    "impressions": 5420,
    "reach": 3821,
    "likes": 287,
    "comments": 42,
    "shares": 18,
    "views": 4892,
    "engagementRate": 6.4,
    "platformAnalytics": [
      {
        "platform": "instagram",
        "impressions": 3200,
        "likes": 145,
        "comments": 23,
        "shares": 9
      },
      {
        "platform": "tiktok",
        "impressions": 2220,
        "likes": 142,
        "comments": 19,
        "shares": 9
      }
    ]
  },
  "cached": false,
  "last_sync": "2024-12-16 10:30:00"
}
```

---

#### 10. **POST** `/analytics/{id}/refresh`

Force refresh analytics (ignores cache).

**Response**: Same as GET `/analytics/{id}`

---

## Frontend Implementation

### Installation

```bash
cd /Users/david/work-projects/aurareels-workspace/aurareels

# Install date picker for scheduling
npm install @mui/x-date-pickers date-fns
```

---

### File Structure

```
aurareels/src/layouts/aurareels/
├── components/
│   ├── social/
│   │   ├── social-connect-button.js         # Connect platform button
│   │   ├── social-profile-card.js           # Display connected profile
│   │   ├── social-publish-dialog.js         # Main publish/schedule modal
│   │   ├── social-platform-selector.js      # Select which profiles to publish
│   │   ├── social-post-card.js              # Published post display
│   │   └── social-analytics-card.js         # Analytics display
│   └── metadata-review-form.js              # ADD: Publish button here
├── views/
│   ├── social-accounts-view.js              # /dashboard/social-accounts
│   ├── social-posts-view.js                 # /dashboard/social-posts
│   └── social-analytics-view.js             # /dashboard/social-analytics
└── utils/
    └── social-api.js                         # API functions

aurareels/src/app/dashboard/
├── social-accounts/
│   └── page.jsx                             # Account management page
├── social-posts/
│   └── page.jsx                             # Posts history page
└── social-analytics/
    └── page.jsx                             # Analytics dashboard page
```

---

### API Utility Functions

**File**: `aurareels/src/layouts/aurareels/utils/social-api.js`

```javascript
import axios from 'axios';
import { getBaseApiUrl } from './config';

const API_BASE = `${getBaseApiUrl()}/wp-json/aurareels/v1/social`;

const getAuthHeaders = () => {
  const token = localStorage.getItem('jwt_token');
  return {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json',
  };
};

/**
 * ==================== AUTHENTICATION ====================
 */

/**
 * Initiate OAuth connection
 * @param {string} provider - Platform name (instagram, tiktok, etc.)
 * @returns {Promise<{success: boolean, url: string}>}
 */
export const connectSocialAccount = async (provider) => {
  const response = await axios.post(
    `${API_BASE}/connect/${provider}`,
    {},
    { headers: getAuthHeaders() }
  );
  return response.data;
};

/**
 * ==================== PROFILES ====================
 */

/**
 * Get connected social media profiles
 * @returns {Promise<{success: boolean, profiles: Array}>}
 */
export const getSocialProfiles = async () => {
  const response = await axios.get(`${API_BASE}/profiles`, {
    headers: getAuthHeaders()
  });
  return response.data;
};

/**
 * ==================== PUBLISHING ====================
 */

/**
 * Create post (publish immediately or schedule)
 * @param {Object} data - Post data
 * @returns {Promise<{success: boolean, post_id: number, late_post_id: string}>}
 */
export const createSocialPost = async (data) => {
  const response = await axios.post(`${API_BASE}/posts`, data, {
    headers: getAuthHeaders()
  });
  return response.data;
};

/**
 * List user's posts
 * @param {Object} filters - Query filters
 * @returns {Promise<{success: boolean, total: number, posts: Array}>}
 */
export const listSocialPosts = async (filters = {}) => {
  const params = new URLSearchParams(filters).toString();
  const response = await axios.get(`${API_BASE}/posts?${params}`, {
    headers: getAuthHeaders()
  });
  return response.data;
};

/**
 * Get single post
 * @param {number} postId - Post ID
 * @returns {Promise<{success: boolean, post: Object}>}
 */
export const getSocialPost = async (postId) => {
  const response = await axios.get(`${API_BASE}/posts/${postId}`, {
    headers: getAuthHeaders()
  });
  return response.data;
};

/**
 * Update post (draft/scheduled only)
 * @param {number} postId - Post ID
 * @param {Object} data - Updated fields
 * @returns {Promise<{success: boolean, message: string}>}
 */
export const updateSocialPost = async (postId, data) => {
  const response = await axios.put(`${API_BASE}/posts/${postId}`, data, {
    headers: getAuthHeaders()
  });
  return response.data;
};

/**
 * Delete/cancel post
 * @param {number} postId - Post ID
 * @returns {Promise<{success: boolean, message: string}>}
 */
export const deleteSocialPost = async (postId) => {
  const response = await axios.delete(`${API_BASE}/posts/${postId}`, {
    headers: getAuthHeaders()
  });
  return response.data;
};

/**
 * Retry failed post
 * @param {number} postId - Post ID
 * @returns {Promise<{success: boolean, message: string}>}
 */
export const retrySocialPost = async (postId) => {
  const response = await axios.post(
    `${API_BASE}/posts/${postId}/retry`,
    {},
    { headers: getAuthHeaders() }
  );
  return response.data;
};

/**
 * ==================== ANALYTICS ====================
 */

/**
 * Get post analytics
 * @param {number} postId - Post ID
 * @param {boolean} forceRefresh - Force refresh from Late.dev
 * @returns {Promise<{success: boolean, analytics: Object}>}
 */
export const getPostAnalytics = async (postId, forceRefresh = false) => {
  const url = forceRefresh
    ? `${API_BASE}/analytics/${postId}/refresh`
    : `${API_BASE}/analytics/${postId}`;

  const method = forceRefresh ? 'post' : 'get';

  const response = await axios[method](url, {
    headers: getAuthHeaders()
  });
  return response.data;
};
```

---

### Main Publish Dialog Component

**File**: `aurareels/src/layouts/aurareels/components/social/social-publish-dialog.js`

```javascript
import React, { useState, useEffect } from 'react';
import {
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Button,
  TextField,
  Tabs,
  Tab,
  Box,
  Alert,
  CircularProgress,
  Checkbox,
  FormControlLabel,
  List,
  ListItem,
  ListItemAvatar,
  Avatar,
  ListItemText,
  Chip,
  Stack,
} from '@mui/material';
import { DateTimePicker } from '@mui/x-date-pickers';
import { LocalizationProvider } from '@mui/x-date-pickers/LocalizationProvider';
import { AdapterDateFns } from '@mui/x-date-pickers/AdapterDateFns';
import { getSocialProfiles, createSocialPost } from '../../utils/social-api';

export default function SocialPublishDialog({
  open,
  onClose,
  videoJob,
  aiMetadata
}) {
  const [tab, setTab] = useState(0); // 0 = Publish Now, 1 = Schedule
  const [profiles, setProfiles] = useState([]);
  const [selectedProfiles, setSelectedProfiles] = useState([]);
  const [caption, setCaption] = useState('');
  const [hashtags, setHashtags] = useState([]);
  const [firstComment, setFirstComment] = useState('');
  const [scheduledDate, setScheduledDate] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(false);

  useEffect(() => {
    if (open) {
      loadProfiles();
      prefillFromAI();
    }
  }, [open, aiMetadata]);

  const loadProfiles = async () => {
    try {
      const result = await getSocialProfiles();
      setProfiles(result.profiles || []);
    } catch (err) {
      setError('Failed to load social accounts');
      console.error(err);
    }
  };

  const prefillFromAI = () => {
    if (!aiMetadata?.data) return;

    // Pre-fill caption with AI-generated description
    const aiDescription = aiMetadata.data.description || '';
    const aiTitle = aiMetadata.data.titles?.[0] || '';
    setCaption(aiDescription || aiTitle);

    // Pre-fill hashtags
    const aiHashtags = aiMetadata.data.hashtags || [];
    setHashtags(aiHashtags.map(tag => tag.replace('#', '')));
  };

  const handleProfileToggle = (profileId) => {
    setSelectedProfiles(prev =>
      prev.includes(profileId)
        ? prev.filter(id => id !== profileId)
        : [...prev, profileId]
    );
  };

  const handlePublish = async () => {
    if (selectedProfiles.length === 0) {
      setError('Please select at least one account');
      return;
    }

    if (!caption.trim()) {
      setError('Please enter a caption');
      return;
    }

    if (tab === 1 && !scheduledDate) {
      setError('Please select a date and time');
      return;
    }

    setLoading(true);
    setError(null);

    try {
      const data = {
        video_job_id: videoJob.job_id,
        profiles: selectedProfiles,
        content: caption,
        hashtags: hashtags,
      };

      if (firstComment.trim()) {
        data.first_comment = firstComment;
      }

      if (tab === 1) {
        // Schedule for later
        data.scheduled_at = scheduledDate.toISOString();
        data.timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
      }

      await createSocialPost(data);

      setSuccess(true);
      setTimeout(() => {
        onClose();
        setSuccess(false);
        // Optionally refresh posts list
        window.location.reload();
      }, 2000);
    } catch (err) {
      setError(err.response?.data?.message || 'Publishing failed');
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const getPlatformColor = (platform) => {
    const colors = {
      instagram: '#E1306C',
      tiktok: '#000000',
      linkedin: '#0077B5',
      twitter: '#1DA1F2',
      youtube: '#FF0000',
      facebook: '#1877F2',
    };
    return colors[platform] || '#999999';
  };

  return (
    <LocalizationProvider dateAdapter={AdapterDateFns}>
      <Dialog open={open} onClose={onClose} maxWidth="md" fullWidth>
        <DialogTitle>Publish to Social Media</DialogTitle>

        <DialogContent>
          {/* Tabs: Publish Now / Schedule */}
          <Tabs value={tab} onChange={(e, v) => setTab(v)} sx={{ mb: 3 }}>
            <Tab label="Publish Now" />
            <Tab label="Schedule" />
          </Tabs>

          {/* Error/Success Messages */}
          {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}
          {success && (
            <Alert severity="success" sx={{ mb: 2 }}>
              {tab === 0 ? 'Publishing...' : 'Scheduled successfully!'}
            </Alert>
          )}

          {/* Profile Selection */}
          <Box sx={{ mb: 3 }}>
            <h3 style={{ marginTop: 0 }}>Select Accounts</h3>
            {profiles.length === 0 ? (
              <Alert severity="info">
                No connected accounts. Please{' '}
                <a href="/dashboard/social-accounts">connect accounts</a> first.
              </Alert>
            ) : (
              <List>
                {profiles.map(profile => (
                  <ListItem key={profile._id} disablePadding>
                    <FormControlLabel
                      control={
                        <Checkbox
                          checked={selectedProfiles.includes(profile._id)}
                          onChange={() => handleProfileToggle(profile._id)}
                        />
                      }
                      label={
                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                          <Avatar
                            src={profile.accounts?.[0]?.avatar}
                            sx={{ width: 32, height: 32 }}
                          />
                          <Box>
                            <strong>{profile.name}</strong>
                            <Box sx={{ display: 'flex', gap: 0.5, mt: 0.5 }}>
                              {profile.accounts?.map(account => (
                                <Chip
                                  key={account.platform}
                                  label={account.platform}
                                  size="small"
                                  sx={{
                                    backgroundColor: getPlatformColor(account.platform),
                                    color: 'white',
                                    textTransform: 'capitalize',
                                  }}
                                />
                              ))}
                            </Box>
                          </Box>
                        </Box>
                      }
                    />
                  </ListItem>
                ))}
              </List>
            )}
          </Box>

          {/* Caption */}
          <TextField
            label="Caption"
            multiline
            rows={5}
            fullWidth
            value={caption}
            onChange={(e) => setCaption(e.target.value)}
            helperText={`${caption.length} / 2200 characters`}
            sx={{ mb: 2 }}
          />

          {/* Hashtags */}
          <TextField
            label="Hashtags (comma-separated, without #)"
            fullWidth
            value={hashtags.join(', ')}
            onChange={(e) =>
              setHashtags(
                e.target.value
                  .split(',')
                  .map(t => t.trim().replace('#', ''))
                  .filter(Boolean)
              )
            }
            helperText="Recommended: 3-5 hashtags"
            sx={{ mb: 2 }}
          />

          {/* First Comment */}
          <TextField
            label="First Comment (Optional)"
            multiline
            rows={2}
            fullWidth
            value={firstComment}
            onChange={(e) => setFirstComment(e.target.value)}
            helperText="Appears as first comment on Instagram & TikTok"
            sx={{ mb: 2 }}
          />

          {/* Schedule Date/Time */}
          {tab === 1 && (
            <DateTimePicker
              label="Schedule Date & Time"
              value={scheduledDate}
              onChange={setScheduledDate}
              minDateTime={new Date()}
              slotProps={{
                textField: {
                  fullWidth: true,
                  helperText: 'Select when to publish (your local time)',
                }
              }}
            />
          )}
        </DialogContent>

        <DialogActions>
          <Button onClick={onClose} disabled={loading}>
            Cancel
          </Button>
          <Button
            onClick={handlePublish}
            variant="contained"
            disabled={loading || selectedProfiles.length === 0}
          >
            {loading ? (
              <CircularProgress size={24} />
            ) : tab === 0 ? (
              'Publish Now'
            ) : (
              'Schedule Post'
            )}
          </Button>
        </DialogActions>
      </Dialog>
    </LocalizationProvider>
  );
}
```

---

### Integration with Existing Video Flow

**File**: `aurareels/src/layouts/aurareels/components/metadata-review-form.js`

Add the publish button after AI metadata is displayed:

```javascript
import React, { useState } from 'react';
import { Button } from '@mui/material';
import ShareIcon from '@mui/icons-material/Share';
import SocialPublishDialog from './social/social-publish-dialog';

export default function MetadataReviewForm({ videoJob, aiMetadata }) {
  const [publishDialogOpen, setPublishDialogOpen] = useState(false);

  return (
    <div>
      {/* Existing metadata review UI */}
      {/* ... */}

      {/* Add this button after metadata display */}
      {videoJob.status === 'finished' && videoJob.mp4_url && (
        <Button
          variant="contained"
          color="primary"
          startIcon={<ShareIcon />}
          onClick={() => setPublishDialogOpen(true)}
          fullWidth
          sx={{ mt: 2 }}
        >
          Publish to Social Media
        </Button>
      )}

      {/* Publish Dialog */}
      <SocialPublishDialog
        open={publishDialogOpen}
        onClose={() => setPublishDialogOpen(false)}
        videoJob={videoJob}
        aiMetadata={aiMetadata}
      />
    </div>
  );
}
```

---

## Publishing Workflow

### Complete User Journey

```
┌──────────────────────────────────────────────────────────────────┐
│                    Complete Publishing Workflow                  │
└──────────────────────────────────────────────────────────────────┘

Step 1: User uploads video
   ↓
Step 2: AI analysis completes (existing AuraReels flow)
   ↓
Step 3: User reviews metadata
   ↓
Step 4: User clicks "Publish to Social Media"
   ↓
Step 5: Publish dialog opens
   ├─ Loads connected profiles from Late.dev
   ├─ Pre-fills caption with AI description
   ├─ Pre-fills hashtags with AI tags
   └─ Shows publish/schedule tabs
   ↓
Step 6: User selects platforms (Instagram, TikTok, etc.)
   ↓
Step 7: User edits caption & hashtags (optional)
   ↓
Step 8: User chooses "Publish Now" or "Schedule"
   ├─ Publish Now: Immediate publication
   └─ Schedule: Selects date/time
   ↓
Step 9: Frontend → WordPress API → Late.dev API
   ↓
Step 10: Late.dev publishes to platforms
   ├─ Instagram: Creates reel
   ├─ TikTok: Creates video
   ├─ LinkedIn: Creates video post
   └─ Other platforms...
   ↓
Step 11: Status updates
   ├─ publishing → published (success)
   └─ publishing → partial/failed (error)
   ↓
Step 12: User sees results
   ├─ Published: Links to posts on each platform
   ├─ Scheduled: Confirmation with date/time
   └─ Failed: Error message with retry option
```

---

## Scheduling System

### How Scheduling Works

**Late.dev handles ALL scheduling logic automatically!**

You just need to:
1. Pass `scheduled_at` (ISO 8601 timestamp) instead of `publishNow: true`
2. Optionally pass `timezone` (defaults to UTC)
3. Late.dev publishes at the specified time

**No cron jobs needed!** Late.dev's infrastructure handles the timing.

### Example: Schedule Post

```javascript
const data = {
  video_job_id: 'uuid-123',
  profiles: ['profile_1', 'profile_2'],
  content: 'Merry Christmas! 🎄',
  hashtags: ['christmas', 'holiday'],
  scheduled_at: '2024-12-25T18:00:00Z', // Christmas at 6 PM UTC
  timezone: 'America/New_York', // Convert to EST
};

await createSocialPost(data);
```

Late.dev will:
- Store the scheduled post
- Wait until the specified time
- Publish automatically
- Update post status to "published"
- Return platform URLs

---

## Analytics & Metrics

### Available Metrics

Late.dev provides comprehensive analytics for published posts:

**Overall Metrics**:
- **Impressions**: Total number of times post was displayed
- **Reach**: Unique users who saw the post
- **Likes**: Total likes/reactions
- **Comments**: Total comments
- **Shares**: Total shares/reposts
- **Views**: Video views
- **Engagement Rate**: (Likes + Comments + Shares) / Impressions × 100

**Per-Platform Metrics**:
- Same metrics broken down by platform
- Platform-specific data (e.g., TikTok play completion rate)

### Fetching Analytics

**Method 1: Cached (Fast)**
```javascript
// Uses 1-hour cache
const result = await getPostAnalytics(postId);
console.log(result.analytics);
```

**Method 2: Force Refresh (Accurate)**
```javascript
// Bypasses cache, fetches fresh from Late.dev
const result = await getPostAnalytics(postId, true);
console.log(result.analytics);
```

### Analytics Component

**File**: `aurareels/src/layouts/aurareels/components/social/social-analytics-card.js`

```javascript
import React, { useState, useEffect } from 'react';
import {
  Card,
  CardContent,
  Typography,
  Grid,
  Box,
  Button,
  CircularProgress,
  Chip,
} from '@mui/material';
import RefreshIcon from '@mui/icons-material/Refresh';
import { getPostAnalytics } from '../../utils/social-api';

export default function SocialAnalyticsCard({ postId }) {
  const [analytics, setAnalytics] = useState(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [lastSync, setLastSync] = useState(null);

  useEffect(() => {
    loadAnalytics();
  }, [postId]);

  const loadAnalytics = async (forceRefresh = false) => {
    if (forceRefresh) {
      setRefreshing(true);
    } else {
      setLoading(true);
    }

    try {
      const result = await getPostAnalytics(postId, forceRefresh);
      setAnalytics(result.analytics);
      setLastSync(result.last_sync);
    } catch (err) {
      console.error('Failed to load analytics', err);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  if (loading) {
    return (
      <Card>
        <CardContent sx={{ textAlign: 'center', py: 4 }}>
          <CircularProgress />
        </CardContent>
      </Card>
    );
  }

  if (!analytics) {
    return (
      <Card>
        <CardContent>
          <Typography color="text.secondary">
            Analytics not available yet. Check back later.
          </Typography>
        </CardContent>
      </Card>
    );
  }

  const MetricCard = ({ label, value, icon }) => (
    <Card variant="outlined" sx={{ textAlign: 'center', py: 2 }}>
      <Typography variant="h4" color="primary">
        {value?.toLocaleString() || 0}
      </Typography>
      <Typography variant="body2" color="text.secondary">
        {label}
      </Typography>
    </Card>
  );

  return (
    <Card>
      <CardContent>
        <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 2 }}>
          <Typography variant="h6">Analytics</Typography>
          <Button
            size="small"
            startIcon={refreshing ? <CircularProgress size={16} /> : <RefreshIcon />}
            onClick={() => loadAnalytics(true)}
            disabled={refreshing}
          >
            Refresh
          </Button>
        </Box>

        {lastSync && (
          <Typography variant="caption" color="text.secondary" sx={{ mb: 2, display: 'block' }}>
            Last updated: {new Date(lastSync).toLocaleString()}
          </Typography>
        )}

        {/* Overall Metrics */}
        <Grid container spacing={2} sx={{ mb: 3 }}>
          <Grid item xs={6} sm={3}>
            <MetricCard label="Impressions" value={analytics.impressions} />
          </Grid>
          <Grid item xs={6} sm={3}>
            <MetricCard label="Likes" value={analytics.likes} />
          </Grid>
          <Grid item xs={6} sm={3}>
            <MetricCard label="Comments" value={analytics.comments} />
          </Grid>
          <Grid item xs={6} sm={3}>
            <MetricCard label="Shares" value={analytics.shares} />
          </Grid>
        </Grid>

        <Box sx={{ mb: 2 }}>
          <Typography variant="body2" color="text.secondary">
            Engagement Rate
          </Typography>
          <Typography variant="h5" color="primary">
            {analytics.engagementRate?.toFixed(1)}%
          </Typography>
        </Box>

        {/* Per-Platform Metrics */}
        {analytics.platformAnalytics && analytics.platformAnalytics.length > 0 && (
          <>
            <Typography variant="subtitle2" sx={{ mt: 3, mb: 1 }}>
              By Platform
            </Typography>
            {analytics.platformAnalytics.map(platform => (
              <Card key={platform.platform} variant="outlined" sx={{ mb: 1, p: 2 }}>
                <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                  <Box>
                    <Chip
                      label={platform.platform}
                      size="small"
                      sx={{ textTransform: 'capitalize', mb: 1 }}
                    />
                    <Grid container spacing={2}>
                      <Grid item xs={3}>
                        <Typography variant="caption" color="text.secondary">
                          Likes
                        </Typography>
                        <Typography variant="body2">{platform.likes || 0}</Typography>
                      </Grid>
                      <Grid item xs={3}>
                        <Typography variant="caption" color="text.secondary">
                          Comments
                        </Typography>
                        <Typography variant="body2">{platform.comments || 0}</Typography>
                      </Grid>
                      <Grid item xs={3}>
                        <Typography variant="caption" color="text.secondary">
                          Shares
                        </Typography>
                        <Typography variant="body2">{platform.shares || 0}</Typography>
                      </Grid>
                      <Grid item xs={3}>
                        <Typography variant="caption" color="text.secondary">
                          Views
                        </Typography>
                        <Typography variant="body2">{platform.views || 0}</Typography>
                      </Grid>
                    </Grid>
                  </Box>
                </Box>
              </Card>
            ))}
          </>
        )}
      </CardContent>
    </Card>
  );
}
```

---

## Error Handling

### Common Errors & Solutions

| Error | Cause | Solution |
|-------|-------|----------|
| **Invalid API Key** | Late.dev API key not configured or invalid | Check `wp-config.php`, verify key in Late.dev dashboard |
| **No Connected Profiles** | User hasn't connected any accounts | Redirect to `/dashboard/social-accounts` |
| **Video Not Ready** | MP4 URL not available | Wait for video processing to complete |
| **Publishing Failed** | Platform API error | Check Late.dev logs, retry post |
| **Partial Success** | Some platforms failed | Show which succeeded/failed, allow retry |
| **Rate Limit Exceeded** | Too many API calls | Implement caching, show rate limit info |
| **Invalid Media** | Video format not supported | Convert video, check platform requirements |
| **Authentication Expired** | OAuth token expired | Re-connect account via OAuth flow |

### Error Display Component

```javascript
import { Alert, Button } from '@mui/material';

function ErrorAlert({ error, onRetry }) {
  return (
    <Alert
      severity="error"
      action={
        onRetry && (
          <Button color="inherit" size="small" onClick={onRetry}>
            Retry
          </Button>
        )
      }
    >
      {error}
    </Alert>
  );
}
```

---

## Testing Guide

### Local Testing Checklist

#### 1. Setup
- [ ] Install Late.dev API key in `wp-config.php`
- [ ] Run database migration (create posts table)
- [ ] Install frontend dependencies (`@mui/x-date-pickers`)

#### 2. Authentication Flow
- [ ] Navigate to `/dashboard/social-accounts`
- [ ] Click "Connect Instagram"
- [ ] Verify redirect to Late.dev hosted UI
- [ ] Authorize Instagram account
- [ ] Verify redirect back to your app
- [ ] Verify profile appears in connected accounts list

#### 3. Publishing Flow
- [ ] Upload test video
- [ ] Wait for AI analysis to complete
- [ ] Click "Publish to Social Media"
- [ ] Select connected profile
- [ ] Enter caption and hashtags
- [ ] Click "Publish Now"
- [ ] Verify post appears on Instagram/TikTok
- [ ] Verify post saved in database

#### 4. Scheduling Flow
- [ ] Click "Publish to Social Media"
- [ ] Switch to "Schedule" tab
- [ ] Select future date/time (5 minutes from now)
- [ ] Click "Schedule Post"
- [ ] Wait for scheduled time
- [ ] Verify post publishes automatically
- [ ] Verify status updates to "published"

#### 5. Analytics Flow
- [ ] Wait 1 hour after publishing
- [ ] Navigate to `/dashboard/social-posts`
- [ ] Click on published post
- [ ] Verify analytics display
- [ ] Click "Refresh" button
- [ ] Verify metrics update

---

## Deployment Checklist

### Pre-Deployment

- [ ] **Late.dev Account**: Sign up and choose plan (Accelerate $49/month recommended)
- [ ] **API Key**: Generate and save securely
- [ ] **Test Accounts**: Connect test social media accounts
- [ ] **wp-config.php**: Add `LATE_API_KEY` constant
- [ ] **Database**: Run migration to create posts table
- [ ] **Frontend**: Install dependencies, build production bundle
- [ ] **OAuth Redirect**: Configure correct callback URL in Late.dev (if needed)

### Testing in Staging

- [ ] Test Instagram connection and publishing
- [ ] Test TikTok connection and publishing
- [ ] Test scheduling (10 minutes in future)
- [ ] Test analytics fetching
- [ ] Test error scenarios (network failure, invalid video)
- [ ] Test mobile responsiveness

### Production Deployment

- [ ] Update `LATE_API_KEY` in production `wp-config.php`
- [ ] Deploy WordPress backend code
- [ ] Deploy Next.js frontend code
- [ ] Run database migration in production
- [ ] Test OAuth flow with production URLs
- [ ] Monitor error logs for first 24 hours
- [ ] Set up analytics tracking for feature usage

### Post-Deployment

- [ ] Monitor Late.dev usage dashboard
- [ ] Check API rate limits
- [ ] Gather user feedback
- [ ] Plan analytics enhancement features

---

## Cost Analysis

### Late.dev Pricing

| Plan | Price | Posts/Month | Profiles | Best For |
|------|-------|-------------|----------|----------|
| **Build** | $19/month | 120 | 10 | Testing, small teams |
| **Accelerate** | $49/month | Unlimited | 50 | **Production (Recommended)** |
| **Unlimited** | Custom | Unlimited | Unlimited | Enterprise |

### ROI Calculation

**Without Late.dev (Direct Integration)**:
- Development time: 160 hours × $50/hour = **$8,000**
- Ongoing maintenance: 10 hours/month × $50 = **$500/month**
- Platform API issues: Unpredictable downtime and costs

**With Late.dev**:
- Setup time: 40 hours × $50/hour = **$2,000**
- Monthly subscription: **$49/month**
- Maintenance: Minimal (2 hours/month) = **$100/month**
- Total first year: $2,000 + ($49 × 12) + ($100 × 12) = **$3,788**

**Savings**: $8,000 - $3,788 = **$4,212** in year 1
**Ongoing savings**: $500 - $149 = **$351/month**

---

## Next Steps

1. **Sign up for Late.dev**: https://getlate.dev/ (Accelerate plan)
2. **Add API key** to `wp-config.php`
3. **Run database migration**
4. **Install frontend dependencies**
5. **Test with personal accounts**
6. **Deploy to production**
7. **Gather user feedback**
8. **Add analytics dashboard enhancements**

---

## Support & Resources

### Documentation
- **Late.dev API Docs**: https://docs.getlate.dev/
- **Late.dev Dashboard**: https://app.getlate.dev/
- **AuraReels CLAUDE.md**: Comprehensive platform documentation

### Support
- **Late.dev Support**: support@getlate.dev
- **Community**: Discord/Slack (check Late.dev website)

---

**🎉 You now have everything you need to integrate social media publishing with Late.dev!**

This implementation is:
- ✅ **Simple**: Standard OAuth flow, minimal code
- ✅ **Scalable**: 11+ platforms, unlimited posts
- ✅ **Maintainable**: Late.dev handles API changes
- ✅ **Cost-Effective**: $49/month vs $8,000+ DIY
- ✅ **Feature-Rich**: Publishing, scheduling, analytics built-in

**Happy building! 🚀**
