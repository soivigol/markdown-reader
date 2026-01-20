# Social Media Integration Documentation

Complete documentation for implementing TikTok and Instagram publishing/scheduling capabilities in the AuraReels platform.

## Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Platform Requirements](#platform-requirements)
4. [Database Schema](#database-schema)
5. [WordPress Backend Implementation](#wordpress-backend-implementation)
6. [REST API Endpoints](#rest-api-endpoints)
7. [OAuth Flow Implementation](#oauth-flow-implementation)
8. [Frontend Implementation](#frontend-implementation)
9. [Publishing Workflow](#publishing-workflow)
10. [Scheduling System](#scheduling-system)
11. [n8n Workflow Integration](#n8n-workflow-integration)
12. [Security Implementation](#security-implementation)
13. [Error Handling](#error-handling)
14. [Testing Guide](#testing-guide)
15. [Deployment Checklist](#deployment-checklist)
16. [API Rate Limits & Best Practices](#api-rate-limits--best-practices)

---

## Overview

The Social Media Integration extends the AuraReels video management platform with the ability to publish and schedule videos to TikTok and Instagram directly from the dashboard.

### Key Features

- ✅ **Multi-Platform Publishing**: Publish to TikTok and Instagram simultaneously
- ✅ **OAuth Authentication**: Secure account connection via OAuth 2.0
- ✅ **AI-Powered Captions**: Pre-filled captions from Gemini AI analysis
- ✅ **Scheduling System**: Schedule posts for future publication
- ✅ **Multi-Account Support**: Connect multiple accounts per platform
- ✅ **Automatic Token Refresh**: Handles token expiration automatically
- ✅ **Retry Logic**: Automatically retries failed publications
- ✅ **Real-Time Status**: Live updates on publishing status

### User Flow

```
Video Upload → AI Analysis → Review Metadata → Publish/Schedule to Social Media
                                                        ↓
                                    ┌──────────────────┴──────────────────┐
                                    ↓                                     ↓
                              TikTok API                          Instagram API
                          (immediate/scheduled)                (immediate/scheduled)
```

---

## Architecture

### System Components

```
┌─────────────────────────────────────────────────────────────────┐
│                  Social Media Integration Architecture          │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  Frontend (Next.js)                                              │
│       ├─ Social Accounts Manager                                │
│       ├─ Publish Dialog                                         │
│       ├─ Scheduler Calendar                                     │
│       └─ Post History                                           │
│                          ↓                                        │
│  WordPress API v1/social/                                        │
│       ├─ Account Management Endpoints                           │
│       ├─ OAuth Callback Handlers                                │
│       ├─ Publishing Endpoints                                   │
│       └─ Scheduling Endpoints                                   │
│                          ↓                                        │
│  Platform API Abstraction Layer                                 │
│       ├─ TikTok API Client (class-tiktok-api.php)              │
│       └─ Instagram API Client (class-instagram-api.php)         │
│                          ↓                                        │
│  Scheduling Engine                                              │
│       ├─ WordPress Cron (fallback)                              │
│       └─ n8n Workflow (recommended)                             │
│                          ↓                                        │
│  External APIs                                                   │
│       ├─ TikTok Content Posting API                             │
│       └─ Facebook/Instagram Graph API                           │
│                                                                   │
└─────────────────────────────────────────────────────────────────┘
```

### Data Flow

```
┌──────────────────────────────────────────────────────────────────┐
│                      Publishing Flow                             │
└──────────────────────────────────────────────────────────────────┘

1. User completes video upload → AI analysis generates metadata
2. User opens "Publish to Social Media" dialog
3. Frontend loads connected social accounts
4. User selects platforms, edits caption, chooses publish time
5. Frontend → POST /wp-json/aurareels/v1/social/publish or /schedule
6. Backend validates account tokens, refreshes if needed
7. Backend stores job in wp_aurareels_social_posts table
8. IF immediate: Call platform API, update status
9. IF scheduled: Register with n8n/cron for future execution
10. Platform API returns post ID and URL
11. Backend updates job with platform_post_id and status
12. Frontend polls or receives webhook for status updates
13. User sees published post with link to platform
```

---

## Platform Requirements

### TikTok Requirements

#### API Access
- **Documentation**: [TikTok for Developers - Content Posting API](https://developers.tiktok.com/doc/content-posting-api-overview/)
- **Application Process**:
  1. Create TikTok Developer Account
  2. Register your application
  3. Request access to "Content Posting API" (requires approval)
  4. Configure OAuth redirect URLs

#### Technical Requirements
- **Account Type**: TikTok Business Account recommended
- **OAuth 2.0**: Authorization Code flow
- **Scopes Required**:
  - `user.info.basic` - Get user profile information
  - `video.publish` - Publish videos to user's account
  - `video.upload` - Upload video files
- **Video Specifications**:
  - Duration: 3 seconds - 10 minutes
  - File size: Maximum 4GB
  - Formats: MP4, WebM, MOV
  - Resolution: 540p minimum, 1080p recommended
  - Aspect ratio: 9:16 (vertical), 16:9 (horizontal), 1:1 (square)
- **Caption Limits**: 2,200 characters (includes hashtags)
- **Hashtag Limits**: Maximum 30 hashtags

#### API Endpoints
```
Authorization: https://www.tiktok.com/v2/auth/authorize/
Token Exchange: https://open.tiktokapis.com/v2/oauth/token/
Upload Video: https://open.tiktokapis.com/v2/post/publish/video/init/
Publish Video: https://open.tiktokapis.com/v2/post/publish/video/init/
```

#### Rate Limits
- **User-level**: 5 video posts per day per user
- **App-level**: Varies by approval tier (check developer dashboard)

---

### Instagram Requirements

#### API Access
- **Documentation**: [Instagram Graph API - Content Publishing](https://developers.facebook.com/docs/instagram-api/guides/content-publishing)
- **Application Process**:
  1. Create Facebook Developer Account
  2. Create a Facebook App
  3. Add Instagram Graph API product
  4. Request permissions: `instagram_basic`, `instagram_content_publish`, `pages_read_engagement`
  5. Submit app for review (required for production)

#### Technical Requirements
- **Account Type**: Instagram Business or Creator Account
- **Facebook Page**: Must be connected to a Facebook Page
- **OAuth 2.0**: Facebook Login with Instagram permissions
- **Scopes Required**:
  - `instagram_basic` - Read profile info
  - `instagram_content_publish` - Publish posts and stories
  - `pages_read_engagement` - Access Page data
  - `pages_show_list` - List Pages user manages
- **Video Specifications (Reels)**:
  - Duration: 3 seconds - 90 seconds (Reels), up to 60 minutes (Feed videos)
  - File size: Maximum 100MB
  - Formats: MP4, MOV
  - Resolution: 1080p minimum recommended
  - Aspect ratio: 9:16 (Reels), 4:5 or 1:1 (Feed)
  - Frame rate: 23-60 fps
  - Audio: AAC, 128kbps minimum
- **Caption Limits**: 2,200 characters
- **Hashtag Limits**: Maximum 30 hashtags (recommended 3-5 for best engagement)

#### API Endpoints
```
Authorization: https://www.facebook.com/v18.0/dialog/oauth
Token Exchange: https://graph.facebook.com/v18.0/oauth/access_token
Get IG Account: https://graph.facebook.com/v18.0/{page-id}?fields=instagram_business_account
Create Container: https://graph.facebook.com/v18.0/{ig-user-id}/media
Publish Container: https://graph.facebook.com/v18.0/{ig-user-id}/media_publish
```

#### Publishing Process
Instagram requires a **two-step process**:
1. **Create Media Container**: Upload video URL and metadata
2. **Publish Container**: Publish the media container (after processing completes)

#### Rate Limits
- **Content Publishing**: 25 API calls per user per 24 hours
- **Business Discovery**: 200 calls per hour
- **Container creation must wait for processing**: Poll container status before publishing

---

### Cloudflare Stream Requirements

#### Public Video Access
For social media publishing, videos must be **publicly accessible**. Cloudflare Stream videos need to be:

1. **Option 1: Enable Public Downloads** (via n8n workflow - already implemented)
   ```
   GET https://api.cloudflare.com/client/v4/accounts/{account_id}/stream/{video_uid}
   ```
   Enable `requireSignedURLs: false` and `allowedOrigins: ["*"]`

2. **Option 2: Generate Signed URLs** (temporary access)
   ```php
   // Generate signed URL valid for 24 hours
   $signed_url = cloudflare_generate_signed_url($video_uid, 86400);
   ```

3. **Option 3: Download and Re-host**
   - Download MP4 from Cloudflare
   - Upload to your own CDN/server
   - Use that URL for social media publishing

**Recommended**: Use Option 1 (already implemented in n8n workflow)

---

## Database Schema

### Table 1: `wp_aurareels_social_accounts`

Stores connected social media accounts per user.

```sql
CREATE TABLE `wp_aurareels_social_accounts` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `wp_user_id` BIGINT(20) UNSIGNED NOT NULL,
  `platform` ENUM('tiktok', 'instagram', 'facebook', 'youtube') NOT NULL,
  `account_id` VARCHAR(255) NOT NULL COMMENT 'Platform-specific user ID',
  `account_name` VARCHAR(255) NOT NULL COMMENT 'Display name or username',
  `account_username` VARCHAR(255) DEFAULT NULL COMMENT '@username',
  `account_email` VARCHAR(255) DEFAULT NULL,
  `profile_picture_url` TEXT DEFAULT NULL,
  `access_token` TEXT NOT NULL COMMENT 'Encrypted OAuth access token',
  `refresh_token` TEXT DEFAULT NULL COMMENT 'Encrypted OAuth refresh token',
  `token_expires_at` DATETIME DEFAULT NULL COMMENT 'When access token expires',
  `token_scope` TEXT DEFAULT NULL COMMENT 'OAuth scopes granted',
  `is_active` TINYINT(1) DEFAULT 1 COMMENT '1=active, 0=disconnected',
  `last_token_refresh` DATETIME DEFAULT NULL,
  `metadata` LONGTEXT DEFAULT NULL COMMENT 'JSON: platform-specific data',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_platform_account` (`platform`, `account_id`, `wp_user_id`),
  KEY `user_platform` (`wp_user_id`, `platform`),
  KEY `active_accounts` (`is_active`, `platform`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Indexes Explanation**:
- `unique_platform_account`: Prevents duplicate account connections
- `user_platform`: Fast lookup of user's accounts by platform
- `active_accounts`: Quick filtering of active accounts

---

### Table 2: `wp_aurareels_social_posts`

Stores social media posts (published, scheduled, or failed).

```sql
CREATE TABLE `wp_aurareels_social_posts` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `wp_user_id` BIGINT(20) UNSIGNED NOT NULL,
  `video_job_id` VARCHAR(255) NOT NULL COMMENT 'FK to wp_chavetas_video_uploader.job_id',
  `social_account_id` BIGINT(20) UNSIGNED NOT NULL COMMENT 'FK to wp_aurareels_social_accounts.id',
  `platform` ENUM('tiktok', 'instagram', 'facebook', 'youtube') NOT NULL,
  `status` ENUM('draft', 'scheduled', 'publishing', 'published', 'failed', 'cancelled') DEFAULT 'draft',
  `scheduled_at` DATETIME DEFAULT NULL COMMENT 'When to publish (NULL = immediate)',
  `published_at` DATETIME DEFAULT NULL COMMENT 'Actual publish time',
  `platform_post_id` VARCHAR(255) DEFAULT NULL COMMENT 'Post ID from platform API',
  `platform_post_url` TEXT DEFAULT NULL COMMENT 'Public URL to the post',
  `video_url` TEXT NOT NULL COMMENT 'Public MP4 URL for upload',
  `thumbnail_url` TEXT DEFAULT NULL,
  `caption` TEXT DEFAULT NULL,
  `hashtags` TEXT DEFAULT NULL COMMENT 'JSON array of hashtags',
  `mentions` TEXT DEFAULT NULL COMMENT 'JSON array of @mentions',
  `location` VARCHAR(255) DEFAULT NULL,
  `privacy_level` VARCHAR(50) DEFAULT 'PUBLIC' COMMENT 'PUBLIC, FRIENDS, PRIVATE',
  `allow_comments` TINYINT(1) DEFAULT 1,
  `allow_duet` TINYINT(1) DEFAULT 1 COMMENT 'TikTok: allow duets',
  `allow_stitch` TINYINT(1) DEFAULT 1 COMMENT 'TikTok: allow stitches',
  `error_message` TEXT DEFAULT NULL,
  `retry_count` INT DEFAULT 0 COMMENT 'Number of retry attempts',
  `last_retry_at` DATETIME DEFAULT NULL,
  `metadata` LONGTEXT DEFAULT NULL COMMENT 'JSON: platform-specific publish data',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_posts` (`wp_user_id`, `status`),
  KEY `scheduled_posts` (`status`, `scheduled_at`),
  KEY `video_job` (`video_job_id`),
  KEY `social_account` (`social_account_id`),
  KEY `platform_status` (`platform`, `status`),
  FOREIGN KEY (`social_account_id`) REFERENCES `wp_aurareels_social_accounts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Indexes Explanation**:
- `user_posts`: Fast filtering of user's posts by status
- `scheduled_posts`: Critical for cron job to find posts to publish
- `video_job`: Link back to original video
- `platform_status`: Analytics queries per platform

---

### Table 3: `wp_aurareels_social_analytics` (Future Enhancement)

Optional table for storing social media analytics.

```sql
CREATE TABLE `wp_aurareels_social_analytics` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `social_post_id` BIGINT(20) UNSIGNED NOT NULL,
  `views` BIGINT DEFAULT 0,
  `likes` BIGINT DEFAULT 0,
  `comments` BIGINT DEFAULT 0,
  `shares` BIGINT DEFAULT 0,
  `engagement_rate` DECIMAL(5,2) DEFAULT NULL,
  `last_synced_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_post_analytics` (`social_post_id`),
  FOREIGN KEY (`social_post_id`) REFERENCES `wp_aurareels_social_posts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## WordPress Backend Implementation

### File Structure

```
aurareels-core/
└── api/
    └── social/
        ├── class-social-constants.php          # Status constants, enums
        ├── class-social-routes.php             # REST API route registration
        ├── class-social-accounts.php           # Account CRUD operations
        ├── class-social-publisher.php          # Main publishing orchestrator
        ├── class-social-scheduler.php          # Scheduling logic
        ├── class-social-token-manager.php      # Token encryption/refresh
        ├── platforms/
        │   ├── interface-platform-api.php      # Platform interface contract
        │   ├── class-tiktok-api.php           # TikTok API implementation
        │   ├── class-instagram-api.php        # Instagram API implementation
        │   └── class-platform-factory.php     # Factory pattern for platforms
        └── cron/
            └── class-social-cron-jobs.php     # WordPress Cron handlers
```

---

### Implementation Files

#### `class-social-constants.php`

```php
<?php
/**
 * Social Media Integration Constants
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Chavetas_Social_Constants {

    // Post Status
    const STATUS_DRAFT      = 'draft';
    const STATUS_SCHEDULED  = 'scheduled';
    const STATUS_PUBLISHING = 'publishing';
    const STATUS_PUBLISHED  = 'published';
    const STATUS_FAILED     = 'failed';
    const STATUS_CANCELLED  = 'cancelled';

    // Platforms
    const PLATFORM_TIKTOK    = 'tiktok';
    const PLATFORM_INSTAGRAM = 'instagram';
    const PLATFORM_FACEBOOK  = 'facebook';
    const PLATFORM_YOUTUBE   = 'youtube';

    // Table Names
    const TABLE_SOCIAL_ACCOUNTS = 'aurareels_social_accounts';
    const TABLE_SOCIAL_POSTS    = 'aurareels_social_posts';
    const TABLE_SOCIAL_ANALYTICS = 'aurareels_social_analytics';

    // OAuth Settings (store in wp-config.php)
    const TIKTOK_CLIENT_ID     = 'TIKTOK_CLIENT_ID';
    const TIKTOK_CLIENT_SECRET = 'TIKTOK_CLIENT_SECRET';
    const INSTAGRAM_APP_ID     = 'INSTAGRAM_APP_ID';
    const INSTAGRAM_APP_SECRET = 'INSTAGRAM_APP_SECRET';

    // Encryption Key (store in wp-config.php)
    const ENCRYPTION_KEY = 'AURAREELS_SOCIAL_ENCRYPTION_KEY';

    // API Endpoints
    const TIKTOK_AUTH_URL    = 'https://www.tiktok.com/v2/auth/authorize/';
    const TIKTOK_TOKEN_URL   = 'https://open.tiktokapis.com/v2/oauth/token/';
    const TIKTOK_API_BASE    = 'https://open.tiktokapis.com/v2/';

    const FACEBOOK_AUTH_URL  = 'https://www.facebook.com/v18.0/dialog/oauth';
    const FACEBOOK_TOKEN_URL = 'https://graph.facebook.com/v18.0/oauth/access_token';
    const FACEBOOK_GRAPH_URL = 'https://graph.facebook.com/v18.0/';

    // Rate Limits
    const MAX_RETRY_ATTEMPTS = 3;
    const RETRY_DELAY_SECONDS = 300; // 5 minutes

    /**
     * Get all valid platforms
     */
    public static function get_platforms() {
        return [
            self::PLATFORM_TIKTOK,
            self::PLATFORM_INSTAGRAM,
            self::PLATFORM_FACEBOOK,
            self::PLATFORM_YOUTUBE,
        ];
    }

    /**
     * Get all valid post statuses
     */
    public static function get_post_statuses() {
        return [
            self::STATUS_DRAFT,
            self::STATUS_SCHEDULED,
            self::STATUS_PUBLISHING,
            self::STATUS_PUBLISHED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
        ];
    }

    /**
     * Get platform display name
     */
    public static function get_platform_name( $platform ) {
        $names = [
            self::PLATFORM_TIKTOK    => 'TikTok',
            self::PLATFORM_INSTAGRAM => 'Instagram',
            self::PLATFORM_FACEBOOK  => 'Facebook',
            self::PLATFORM_YOUTUBE   => 'YouTube',
        ];
        return $names[ $platform ] ?? ucfirst( $platform );
    }
}
```

---

#### `interface-platform-api.php`

```php
<?php
/**
 * Platform API Interface
 * All platform implementations must implement this interface
 */

if ( ! defined( 'ABSPATH' ) ) exit;

interface Platform_API_Interface {

    /**
     * Get OAuth authorization URL
     *
     * @param string $redirect_uri Callback URL
     * @param string $state CSRF protection state
     * @return string Authorization URL
     */
    public function get_auth_url( $redirect_uri, $state );

    /**
     * Exchange authorization code for access token
     *
     * @param string $code Authorization code
     * @param string $redirect_uri Same redirect URI used in auth
     * @return array Token data: ['access_token', 'refresh_token', 'expires_in', 'scope']
     */
    public function exchange_code_for_token( $code, $redirect_uri );

    /**
     * Refresh access token using refresh token
     *
     * @param string $refresh_token Refresh token
     * @return array New token data
     */
    public function refresh_access_token( $refresh_token );

    /**
     * Get user profile information
     *
     * @param string $access_token Access token
     * @return array User data: ['id', 'username', 'display_name', 'email', 'profile_picture']
     */
    public function get_user_info( $access_token );

    /**
     * Publish video to platform
     *
     * @param string $access_token Access token
     * @param array $video_data Video metadata
     * @return array Publish result: ['success', 'post_id', 'post_url', 'error']
     */
    public function publish_video( $access_token, $video_data );

    /**
     * Validate access token
     *
     * @param string $access_token Access token to validate
     * @return bool True if valid, false otherwise
     */
    public function validate_token( $access_token );

    /**
     * Revoke access token
     *
     * @param string $access_token Access token to revoke
     * @return bool Success status
     */
    public function revoke_token( $access_token );

    /**
     * Get platform-specific video requirements
     *
     * @return array Requirements: ['max_duration', 'max_size', 'formats', 'aspect_ratios']
     */
    public function get_video_requirements();
}
```

---

#### `class-tiktok-api.php` (Implementation Example)

```php
<?php
/**
 * TikTok API Implementation
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Chavetas_TikTok_API implements Platform_API_Interface {

    private $client_id;
    private $client_secret;

    public function __construct() {
        $this->client_id     = defined( 'TIKTOK_CLIENT_ID' ) ? TIKTOK_CLIENT_ID : '';
        $this->client_secret = defined( 'TIKTOK_CLIENT_SECRET' ) ? TIKTOK_CLIENT_SECRET : '';
    }

    /**
     * Get OAuth authorization URL
     */
    public function get_auth_url( $redirect_uri, $state ) {
        $params = [
            'client_key'    => $this->client_id,
            'scope'         => 'user.info.basic,video.publish,video.upload',
            'response_type' => 'code',
            'redirect_uri'  => $redirect_uri,
            'state'         => $state,
        ];

        return Chavetas_Social_Constants::TIKTOK_AUTH_URL . '?' . http_build_query( $params );
    }

    /**
     * Exchange authorization code for access token
     */
    public function exchange_code_for_token( $code, $redirect_uri ) {
        $response = wp_remote_post( Chavetas_Social_Constants::TIKTOK_TOKEN_URL, [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => [
                'client_key'    => $this->client_id,
                'client_secret' => $this->client_secret,
                'code'          => $code,
                'grant_type'    => 'authorization_code',
                'redirect_uri'  => $redirect_uri,
            ],
        ]);

        if ( is_wp_error( $response ) ) {
            return [ 'error' => $response->get_error_message() ];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['data'] ) ) {
            return [
                'access_token'  => $body['data']['access_token'],
                'refresh_token' => $body['data']['refresh_token'],
                'expires_in'    => $body['data']['expires_in'],
                'scope'         => $body['data']['scope'],
                'open_id'       => $body['data']['open_id'], // TikTok user ID
            ];
        }

        return [ 'error' => $body['error']['message'] ?? 'Unknown error' ];
    }

    /**
     * Refresh access token
     */
    public function refresh_access_token( $refresh_token ) {
        $response = wp_remote_post( Chavetas_Social_Constants::TIKTOK_TOKEN_URL, [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => [
                'client_key'    => $this->client_id,
                'client_secret' => $this->client_secret,
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refresh_token,
            ],
        ]);

        if ( is_wp_error( $response ) ) {
            return [ 'error' => $response->get_error_message() ];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['data'] ) ) {
            return [
                'access_token'  => $body['data']['access_token'],
                'refresh_token' => $body['data']['refresh_token'],
                'expires_in'    => $body['data']['expires_in'],
            ];
        }

        return [ 'error' => $body['error']['message'] ?? 'Unknown error' ];
    }

    /**
     * Get user info
     */
    public function get_user_info( $access_token ) {
        $response = wp_remote_get( Chavetas_Social_Constants::TIKTOK_API_BASE . 'user/info/', [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
            ],
        ]);

        if ( is_wp_error( $response ) ) {
            return [ 'error' => $response->get_error_message() ];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['data']['user'] ) ) {
            $user = $body['data']['user'];
            return [
                'id'              => $user['open_id'],
                'username'        => $user['display_name'],
                'display_name'    => $user['display_name'],
                'profile_picture' => $user['avatar_url'] ?? null,
            ];
        }

        return [ 'error' => $body['error']['message'] ?? 'Unknown error' ];
    }

    /**
     * Publish video to TikTok
     */
    public function publish_video( $access_token, $video_data ) {
        // Step 1: Initialize upload
        $init_response = wp_remote_post( Chavetas_Social_Constants::TIKTOK_API_BASE . 'post/publish/video/init/', [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json; charset=UTF-8',
            ],
            'body' => json_encode([
                'post_info' => [
                    'title'                => $video_data['caption'] ?? '',
                    'privacy_level'        => $video_data['privacy_level'] ?? 'SELF_ONLY', // PUBLIC_TO_EVERYONE, MUTUAL_FOLLOW_FRIENDS, SELF_ONLY
                    'disable_duet'         => ! ( $video_data['allow_duet'] ?? true ),
                    'disable_comment'      => ! ( $video_data['allow_comments'] ?? true ),
                    'disable_stitch'       => ! ( $video_data['allow_stitch'] ?? true ),
                    'video_cover_timestamp_ms' => 1000, // Cover frame at 1 second
                ],
                'source_info' => [
                    'source'    => 'PULL_FROM_URL',
                    'video_url' => $video_data['video_url'],
                ],
            ]),
        ]);

        if ( is_wp_error( $init_response ) ) {
            return [
                'success' => false,
                'error'   => $init_response->get_error_message(),
            ];
        }

        $init_body = json_decode( wp_remote_retrieve_body( $init_response ), true );

        if ( isset( $init_body['data'] ) ) {
            return [
                'success'  => true,
                'post_id'  => $init_body['data']['publish_id'],
                'post_url' => null, // TikTok doesn't return URL immediately
                'message'  => 'Video uploaded to TikTok. Processing may take a few minutes.',
            ];
        }

        return [
            'success' => false,
            'error'   => $init_body['error']['message'] ?? 'Unknown error',
        ];
    }

    /**
     * Validate token
     */
    public function validate_token( $access_token ) {
        $user_info = $this->get_user_info( $access_token );
        return ! isset( $user_info['error'] );
    }

    /**
     * Revoke token (TikTok doesn't provide revoke endpoint, user must do it manually)
     */
    public function revoke_token( $access_token ) {
        // TikTok doesn't provide a revoke endpoint
        // Users must revoke access manually from their TikTok settings
        return true;
    }

    /**
     * Get video requirements
     */
    public function get_video_requirements() {
        return [
            'max_duration_seconds' => 600, // 10 minutes
            'min_duration_seconds' => 3,
            'max_size_bytes'       => 4 * 1024 * 1024 * 1024, // 4GB
            'formats'              => [ 'mp4', 'webm', 'mov' ],
            'aspect_ratios'        => [ '9:16', '16:9', '1:1' ],
            'min_resolution'       => '540p',
            'recommended_resolution' => '1080p',
            'max_caption_length'   => 2200,
            'max_hashtags'         => 30,
        ];
    }
}
```

---

## REST API Endpoints

All endpoints use the base URL: `/wp-json/aurareels/v1/social/`

### Authentication

All endpoints require JWT authentication:
```http
Authorization: Bearer {JWT_TOKEN}
```

---

### Account Management Endpoints

#### 1. List Connected Accounts

**GET** `/accounts`

Get all social media accounts connected by the current user.

**Query Parameters**:
- `platform` (optional): Filter by platform (`tiktok`, `instagram`)
- `active` (optional): Filter by status (`1` = active, `0` = inactive)

**Response**:
```json
{
  "success": true,
  "accounts": [
    {
      "id": 1,
      "platform": "tiktok",
      "account_name": "MyTikTokAccount",
      "account_username": "@mytiktok",
      "profile_picture_url": "https://...",
      "is_active": true,
      "token_expires_at": "2024-12-31 23:59:59",
      "created_at": "2024-01-01 00:00:00"
    },
    {
      "id": 2,
      "platform": "instagram",
      "account_name": "MyInstagram",
      "account_username": "@myinsta",
      "profile_picture_url": "https://...",
      "is_active": true,
      "token_expires_at": "2024-12-31 23:59:59",
      "created_at": "2024-01-15 00:00:00"
    }
  ]
}
```

---

#### 2. Initiate OAuth Connection

**POST** `/accounts/connect/{platform}`

Initiate OAuth flow for connecting a social media account.

**Path Parameters**:
- `platform`: `tiktok` or `instagram`

**Response**:
```json
{
  "success": true,
  "auth_url": "https://www.tiktok.com/v2/auth/authorize/?client_key=...",
  "state": "random-csrf-state-token"
}
```

**Frontend Action**:
```javascript
// Redirect user to auth_url
window.location.href = response.auth_url;
```

---

#### 3. OAuth Callback Handler

**GET** `/callback/{platform}`

Handles OAuth redirect from TikTok/Instagram.

**Query Parameters**:
- `code`: Authorization code from platform
- `state`: CSRF state token

**Response**:
Redirects to frontend with success/error:
```
https://yourdomain.com/dashboard/social-accounts?status=success&platform=tiktok
https://yourdomain.com/dashboard/social-accounts?status=error&message=Invalid+code
```

---

#### 4. Refresh Account Token

**POST** `/accounts/{account_id}/refresh`

Manually refresh an account's access token.

**Response**:
```json
{
  "success": true,
  "message": "Token refreshed successfully",
  "expires_at": "2024-12-31 23:59:59"
}
```

---

#### 5. Disconnect Account

**DELETE** `/accounts/{account_id}`

Disconnect and remove a social media account.

**Response**:
```json
{
  "success": true,
  "message": "Account disconnected successfully"
}
```

---

### Publishing Endpoints

#### 6. Publish Immediately

**POST** `/publish`

Publish video to social media immediately.

**Request Body**:
```json
{
  "video_job_id": "uuid-from-wp_chavetas_video_uploader",
  "accounts": [1, 2], // Array of social_account_id
  "caption": "Check out this amazing video! #awesome",
  "hashtags": ["#video", "#content", "#viral"],
  "privacy_level": "PUBLIC",
  "allow_comments": true,
  "allow_duet": true,
  "allow_stitch": true
}
```

**Response**:
```json
{
  "success": true,
  "message": "Publishing initiated",
  "posts": [
    {
      "id": 123,
      "platform": "tiktok",
      "status": "publishing",
      "account_name": "MyTikTokAccount"
    },
    {
      "id": 124,
      "platform": "instagram",
      "status": "publishing",
      "account_name": "MyInstagram"
    }
  ]
}
```

---

#### 7. Schedule Post

**POST** `/schedule`

Schedule a post for future publication.

**Request Body**:
```json
{
  "video_job_id": "uuid-from-wp_chavetas_video_uploader",
  "accounts": [1, 2],
  "scheduled_at": "2024-12-25 18:00:00", // UTC or user's timezone
  "caption": "Merry Christmas! 🎄",
  "hashtags": ["#christmas", "#holiday"],
  "privacy_level": "PUBLIC",
  "allow_comments": true
}
```

**Response**:
```json
{
  "success": true,
  "message": "Posts scheduled successfully",
  "posts": [
    {
      "id": 125,
      "platform": "tiktok",
      "status": "scheduled",
      "scheduled_at": "2024-12-25 18:00:00"
    },
    {
      "id": 126,
      "platform": "instagram",
      "status": "scheduled",
      "scheduled_at": "2024-12-25 18:00:00"
    }
  ]
}
```

---

#### 8. List Posts

**GET** `/posts`

Get user's social media posts (published, scheduled, failed).

**Query Parameters**:
- `status` (optional): Filter by status
- `platform` (optional): Filter by platform
- `limit` (default: 20): Number of posts per page
- `offset` (default: 0): Pagination offset

**Response**:
```json
{
  "success": true,
  "total": 42,
  "posts": [
    {
      "id": 123,
      "platform": "tiktok",
      "status": "published",
      "caption": "Check out this video!",
      "platform_post_url": "https://www.tiktok.com/@user/video/123",
      "published_at": "2024-12-16 10:00:00",
      "video_job_id": "uuid-123",
      "account_name": "MyTikTokAccount"
    }
  ]
}
```

---

#### 9. Get Single Post

**GET** `/posts/{post_id}`

Get details of a single social media post.

**Response**:
```json
{
  "success": true,
  "post": {
    "id": 123,
    "platform": "tiktok",
    "status": "published",
    "caption": "Full caption text...",
    "hashtags": ["#tag1", "#tag2"],
    "platform_post_id": "tiktok-post-id",
    "platform_post_url": "https://www.tiktok.com/@user/video/123",
    "video_url": "https://cloudflare.com/video.mp4",
    "thumbnail_url": "https://cloudflare.com/thumb.jpg",
    "scheduled_at": null,
    "published_at": "2024-12-16 10:00:00",
    "error_message": null,
    "retry_count": 0,
    "account": {
      "id": 1,
      "account_name": "MyTikTokAccount",
      "account_username": "@mytiktok"
    }
  }
}
```

---

#### 10. Reschedule Post

**PATCH** `/posts/{post_id}/reschedule`

Reschedule a scheduled post to a different time.

**Request Body**:
```json
{
  "scheduled_at": "2024-12-26 12:00:00"
}
```

**Response**:
```json
{
  "success": true,
  "message": "Post rescheduled successfully",
  "post": {
    "id": 125,
    "scheduled_at": "2024-12-26 12:00:00"
  }
}
```

---

#### 11. Cancel Scheduled Post

**DELETE** `/posts/{post_id}`

Cancel a scheduled post (or delete a draft).

**Response**:
```json
{
  "success": true,
  "message": "Post cancelled successfully"
}
```

---

#### 12. Retry Failed Post

**POST** `/posts/{post_id}/retry`

Retry publishing a failed post.

**Response**:
```json
{
  "success": true,
  "message": "Retrying publication",
  "post": {
    "id": 127,
    "status": "publishing",
    "retry_count": 1
  }
}
```

---

## OAuth Flow Implementation

### TikTok OAuth Flow (Complete Example)

```
┌──────────────────────────────────────────────────────────────────┐
│                      TikTok OAuth Flow                           │
└──────────────────────────────────────────────────────────────────┘

Step 1: User Initiates Connection
  Frontend → POST /wp-json/aurareels/v1/social/accounts/connect/tiktok

Step 2: Backend Generates Auth URL
  Backend:
    - Generate random state token (CSRF protection)
    - Store state in transient: set_transient("oauth_state_{user_id}", $state, 600)
    - Build TikTok auth URL with client_id, redirect_uri, scope, state
    - Return auth URL to frontend

Step 3: Redirect to TikTok
  Frontend:
    - Redirect user to auth URL
    - User sees TikTok authorization page
    - User clicks "Allow"

Step 4: TikTok Redirects Back
  TikTok → GET /wp-json/aurareels/v1/social/callback/tiktok?code=XXX&state=YYY

Step 5: Backend Validates and Exchanges Code
  Backend:
    - Validate state matches stored transient
    - Delete transient (use once)
    - Exchange code for access_token via TikTok API
    - Get user info (open_id, display_name, avatar)
    - Encrypt tokens using AURAREELS_SOCIAL_ENCRYPTION_KEY
    - Store in wp_aurareels_social_accounts table
    - Redirect to frontend success page

Step 6: Success
  Frontend:
    - Parse URL params (status=success)
    - Show success notification
    - Reload accounts list
```

---

### Instagram OAuth Flow (Complete Example)

Instagram requires Facebook Login and additional steps:

```
┌──────────────────────────────────────────────────────────────────┐
│                    Instagram OAuth Flow                          │
└──────────────────────────────────────────────────────────────────┘

Step 1: User Initiates Connection
  Frontend → POST /wp-json/aurareels/v1/social/accounts/connect/instagram

Step 2: Backend Generates Facebook Auth URL
  Backend:
    - Generate state token
    - Build Facebook OAuth URL with:
      - app_id (Instagram App ID)
      - redirect_uri
      - scope: instagram_basic,instagram_content_publish,pages_read_engagement
      - state
    - Return auth URL

Step 3: User Authorizes on Facebook
  Frontend → Redirect to Facebook
  User → Logs into Facebook, authorizes app, selects Instagram account

Step 4: Facebook Redirects Back
  Facebook → GET /wp-json/aurareels/v1/social/callback/instagram?code=XXX&state=YYY

Step 5: Backend Multi-Step Process
  Backend:
    a. Validate state
    b. Exchange code for Facebook access_token
    c. Get Facebook User Pages (with Instagram accounts)
       GET https://graph.facebook.com/v18.0/me/accounts
    d. For each page, get Instagram Business Account ID
       GET https://graph.facebook.com/v18.0/{page-id}?fields=instagram_business_account
    e. Get Instagram account details
       GET https://graph.facebook.com/v18.0/{instagram-account-id}?fields=username,name,profile_picture_url
    f. Encrypt and store tokens
    g. Redirect to success

Step 6: Success
  Frontend → Show connected Instagram account
```

---

## Frontend Implementation

### File Structure

```
aurareels/src/layouts/aurareels/
├── components/
│   ├── social/
│   │   ├── social-accounts-manager.js          # Main account management UI
│   │   ├── social-account-card.js              # Individual account card
│   │   ├── social-connect-button.js            # Connect platform button
│   │   ├── social-publish-dialog.js            # Publish/schedule modal
│   │   ├── social-platform-selector.js         # Multi-platform checkbox list
│   │   ├── social-caption-editor.js            # Caption with AI suggestions
│   │   ├── social-scheduler-picker.js          # Date/time picker
│   │   ├── social-post-card.js                 # Published post card
│   │   └── social-status-badge.js              # Status indicator chip
│   └── metadata-review-form.js                 # ADD: Publish button here
├── views/
│   ├── social-accounts-view.js                 # /dashboard/social-accounts
│   ├── social-calendar-view.js                 # /dashboard/social-calendar
│   └── social-posts-history.js                 # /dashboard/social-posts
└── utils/
    └── social-api.js                            # API functions

aurareels/src/app/dashboard/
├── social-accounts/
│   └── page.jsx                                # Account management page
├── social-calendar/
│   └── page.jsx                                # Calendar page
└── social-posts/
    └── page.jsx                                # Posts history page
```

---

### Key Components

#### `social-api.js` (API Utility Functions)

```javascript
import axios from 'axios';
import { getBaseApiUrl } from '../config';

const API_BASE = `${getBaseApiUrl()}/wp-json/aurareels/v1/social`;

// Get JWT token from context
const getAuthHeaders = () => {
  const token = localStorage.getItem('jwt_token');
  return {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json',
  };
};

/**
 * Account Management
 */

export const getSocialAccounts = async (platform = null) => {
  const url = platform
    ? `${API_BASE}/accounts?platform=${platform}`
    : `${API_BASE}/accounts`;

  const response = await axios.get(url, { headers: getAuthHeaders() });
  return response.data;
};

export const connectSocialAccount = async (platform) => {
  const response = await axios.post(
    `${API_BASE}/accounts/connect/${platform}`,
    {},
    { headers: getAuthHeaders() }
  );

  // Redirect to OAuth URL
  if (response.data.success && response.data.auth_url) {
    window.location.href = response.data.auth_url;
  }

  return response.data;
};

export const disconnectSocialAccount = async (accountId) => {
  const response = await axios.delete(
    `${API_BASE}/accounts/${accountId}`,
    { headers: getAuthHeaders() }
  );
  return response.data;
};

export const refreshAccountToken = async (accountId) => {
  const response = await axios.post(
    `${API_BASE}/accounts/${accountId}/refresh`,
    {},
    { headers: getAuthHeaders() }
  );
  return response.data;
};

/**
 * Publishing
 */

export const publishToSocial = async (data) => {
  const response = await axios.post(
    `${API_BASE}/publish`,
    data,
    { headers: getAuthHeaders() }
  );
  return response.data;
};

export const scheduleSocialPost = async (data) => {
  const response = await axios.post(
    `${API_BASE}/schedule`,
    data,
    { headers: getAuthHeaders() }
  );
  return response.data;
};

export const getSocialPosts = async (filters = {}) => {
  const params = new URLSearchParams(filters).toString();
  const response = await axios.get(
    `${API_BASE}/posts?${params}`,
    { headers: getAuthHeaders() }
  );
  return response.data;
};

export const getSocialPost = async (postId) => {
  const response = await axios.get(
    `${API_BASE}/posts/${postId}`,
    { headers: getAuthHeaders() }
  );
  return response.data;
};

export const reschedulePost = async (postId, scheduledAt) => {
  const response = await axios.patch(
    `${API_BASE}/posts/${postId}/reschedule`,
    { scheduled_at: scheduledAt },
    { headers: getAuthHeaders() }
  );
  return response.data;
};

export const cancelScheduledPost = async (postId) => {
  const response = await axios.delete(
    `${API_BASE}/posts/${postId}`,
    { headers: getAuthHeaders() }
  );
  return response.data;
};

export const retryFailedPost = async (postId) => {
  const response = await axios.post(
    `${API_BASE}/posts/${postId}/retry`,
    {},
    { headers: getAuthHeaders() }
  );
  return response.data;
};
```

---

#### `social-publish-dialog.js` (Main Publish Modal)

```javascript
import React, { useState, useEffect } from 'react';
import {
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Button,
  FormGroup,
  FormControlLabel,
  Checkbox,
  TextField,
  Tabs,
  Tab,
  Box,
  Alert,
  CircularProgress,
  Chip,
  Avatar,
  List,
  ListItem,
  ListItemAvatar,
  ListItemText,
} from '@mui/material';
import { DateTimePicker } from '@mui/x-date-pickers';
import {
  getSocialAccounts,
  publishToSocial,
  scheduleSocialPost
} from '../../utils/social-api';

export default function SocialPublishDialog({
  open,
  onClose,
  videoJob,
  aiMetadata
}) {
  const [tab, setTab] = useState(0); // 0 = Publish Now, 1 = Schedule
  const [accounts, setAccounts] = useState([]);
  const [selectedAccounts, setSelectedAccounts] = useState([]);
  const [caption, setCaption] = useState('');
  const [hashtags, setHashtags] = useState([]);
  const [scheduledDate, setScheduledDate] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(false);

  useEffect(() => {
    if (open) {
      loadAccounts();
      prefillCaption();
    }
  }, [open, aiMetadata]);

  const loadAccounts = async () => {
    try {
      const result = await getSocialAccounts();
      setAccounts(result.accounts.filter(acc => acc.is_active));
    } catch (err) {
      setError('Failed to load social accounts');
    }
  };

  const prefillCaption = () => {
    // Use AI-generated description or first title
    const aiCaption = aiMetadata?.data?.description
      || aiMetadata?.data?.titles?.[0]
      || '';
    setCaption(aiCaption);

    // Use AI-generated hashtags
    const aiHashtags = aiMetadata?.data?.hashtags || [];
    setHashtags(aiHashtags);
  };

  const handleAccountToggle = (accountId) => {
    setSelectedAccounts(prev =>
      prev.includes(accountId)
        ? prev.filter(id => id !== accountId)
        : [...prev, accountId]
    );
  };

  const handlePublish = async () => {
    if (selectedAccounts.length === 0) {
      setError('Please select at least one account');
      return;
    }

    setLoading(true);
    setError(null);

    try {
      const data = {
        video_job_id: videoJob.job_id,
        accounts: selectedAccounts,
        caption: caption,
        hashtags: hashtags,
        privacy_level: 'PUBLIC',
        allow_comments: true,
        allow_duet: true,
        allow_stitch: true,
      };

      if (tab === 0) {
        // Publish immediately
        await publishToSocial(data);
        setSuccess(true);
        setTimeout(() => {
          onClose();
          setSuccess(false);
        }, 2000);
      } else {
        // Schedule
        if (!scheduledDate) {
          setError('Please select a date and time');
          setLoading(false);
          return;
        }
        await scheduleSocialPost({
          ...data,
          scheduled_at: scheduledDate.toISOString(),
        });
        setSuccess(true);
        setTimeout(() => {
          onClose();
          setSuccess(false);
        }, 2000);
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Publishing failed');
    } finally {
      setLoading(false);
    }
  };

  return (
    <Dialog open={open} onClose={onClose} maxWidth="md" fullWidth>
      <DialogTitle>Publish to Social Media</DialogTitle>

      <DialogContent>
        <Tabs value={tab} onChange={(e, v) => setTab(v)} sx={{ mb: 3 }}>
          <Tab label="Publish Now" />
          <Tab label="Schedule" />
        </Tabs>

        {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}
        {success && <Alert severity="success" sx={{ mb: 2 }}>
          {tab === 0 ? 'Publishing...' : 'Scheduled successfully!'}
        </Alert>}

        {/* Account Selection */}
        <Box sx={{ mb: 3 }}>
          <h3>Select Accounts</h3>
          {accounts.length === 0 ? (
            <Alert severity="info">
              No connected accounts. Please connect TikTok or Instagram first.
            </Alert>
          ) : (
            <List>
              {accounts.map(account => (
                <ListItem key={account.id}>
                  <FormControlLabel
                    control={
                      <Checkbox
                        checked={selectedAccounts.includes(account.id)}
                        onChange={() => handleAccountToggle(account.id)}
                      />
                    }
                    label={
                      <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                        <Avatar src={account.profile_picture_url} sx={{ width: 32, height: 32 }} />
                        <Box>
                          <strong>{account.account_name}</strong>
                          <Chip
                            label={account.platform}
                            size="small"
                            sx={{ ml: 1 }}
                          />
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
          rows={4}
          fullWidth
          value={caption}
          onChange={(e) => setCaption(e.target.value)}
          helperText={`${caption.length} / 2200 characters`}
          sx={{ mb: 2 }}
        />

        {/* Hashtags */}
        <TextField
          label="Hashtags (comma-separated)"
          fullWidth
          value={hashtags.join(', ')}
          onChange={(e) => setHashtags(
            e.target.value.split(',').map(t => t.trim()).filter(Boolean)
          )}
          helperText="Recommended: 3-5 hashtags"
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
              textField: { fullWidth: true }
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
          disabled={loading || selectedAccounts.length === 0}
        >
          {loading ? (
            <CircularProgress size={24} />
          ) : (
            tab === 0 ? 'Publish Now' : 'Schedule Post'
          )}
        </Button>
      </DialogActions>
    </Dialog>
  );
}
```

---

## Publishing Workflow

### Complete Publishing Flow Diagram

```
┌──────────────────────────────────────────────────────────────────┐
│                    Publishing Workflow                           │
└──────────────────────────────────────────────────────────────────┘

Step 1: User Completes Video Upload
  ↓
  Video uploaded to Cloudflare Stream
  ↓
  AI analysis completed (via n8n)
  ↓
  Metadata available: titles, hashtags, description, transcription

Step 2: User Opens "Publish to Social Media" Dialog
  ↓
  Frontend loads connected social accounts
  ↓
  Pre-fills caption with AI-generated description
  ↓
  Pre-fills hashtags with AI-generated tags

Step 3: User Configures Post
  ↓
  Selects platforms (TikTok, Instagram, or both)
  ↓
  Edits caption and hashtags
  ↓
  Chooses "Publish Now" or "Schedule for Later"

Step 4: Frontend Validates and Sends Request
  ↓
  Checks: At least one account selected
  ↓
  Checks: Caption within character limits
  ↓
  Checks: Video job has mp4_url available
  ↓
  POST /wp-json/aurareels/v1/social/publish (or /schedule)

Step 5: Backend Processing
  ↓
  Validate user owns the video_job_id
  ↓
  Validate selected accounts belong to user
  ↓
  For each selected account:
    ├─ Check token expiration
    ├─ Refresh token if needed (< 24 hours remaining)
    ├─ Create entry in wp_aurareels_social_posts
    │  └─ status = 'publishing' (immediate) or 'scheduled'
    └─ If immediate: Call platform API

Step 6: Platform API Interaction
  ┌────────────────────────────────────────────────────────────┐
  │ TikTok Flow                                                │
  │  1. POST /v2/post/publish/video/init/                     │
  │     - Send video URL (pull from URL)                      │
  │     - Send caption, privacy settings                      │
  │  2. TikTok processes video (async)                        │
  │  3. Get publish_id (not immediate URL)                    │
  │  4. Update wp_aurareels_social_posts:                     │
  │     - platform_post_id = publish_id                       │
  │     - status = 'published'                                │
  │     - published_at = NOW()                                │
  └────────────────────────────────────────────────────────────┘

  ┌────────────────────────────────────────────────────────────┐
  │ Instagram Flow (Two-Step)                                  │
  │  1. POST /{ig-user-id}/media (Create Container)           │
  │     - media_type = REELS                                   │
  │     - video_url = public MP4 URL                           │
  │     - caption = text + hashtags                            │
  │  2. Get creation_id from response                          │
  │  3. Poll container status until ready                      │
  │     GET /{creation_id}?fields=status_code                  │
  │  4. When status_code = FINISHED:                           │
  │     POST /{ig-user-id}/media_publish                       │
  │     - creation_id = container ID                           │
  │  5. Get post_id and permalink                              │
  │  6. Update wp_aurareels_social_posts:                      │
  │     - platform_post_id = post_id                           │
  │     - platform_post_url = permalink                        │
  │     - status = 'published'                                 │
  └────────────────────────────────────────────────────────────┘

Step 7: Error Handling
  ├─ If API call fails:
  │  ├─ Update status = 'failed'
  │  ├─ Store error_message
  │  └─ Increment retry_count
  └─ If retry_count < 3:
     └─ Allow manual retry via frontend

Step 8: Frontend Updates
  ↓
  Frontend polls or receives real-time update
  ↓
  Show success notification with link to post
  ↓
  Update post list in dashboard
```

---

## Scheduling System

### Approach 1: WordPress Cron (Simpler, Less Reliable)

```php
<?php
/**
 * Register cron schedules
 */
add_filter( 'cron_schedules', function( $schedules ) {
    $schedules['every_5_minutes'] = [
        'interval' => 300,
        'display'  => __( 'Every 5 Minutes' ),
    ];
    return $schedules;
});

/**
 * Schedule cron job on plugin activation
 */
register_activation_hook( __FILE__, function() {
    if ( ! wp_next_scheduled( 'aurareels_process_scheduled_posts' ) ) {
        wp_schedule_event( time(), 'every_5_minutes', 'aurareels_process_scheduled_posts' );
    }
});

/**
 * Process scheduled posts
 */
add_action( 'aurareels_process_scheduled_posts', function() {
    global $wpdb;

    $table = $wpdb->prefix . 'aurareels_social_posts';
    $now = current_time( 'mysql' );
    $future = date( 'Y-m-d H:i:s', strtotime( '+5 minutes' ) );

    // Find posts scheduled in next 5 minutes
    $posts = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$table}
         WHERE status = 'scheduled'
         AND scheduled_at BETWEEN %s AND %s
         ORDER BY scheduled_at ASC",
        $now, $future
    ));

    foreach ( $posts as $post ) {
        // Update status to publishing
        $wpdb->update(
            $table,
            [ 'status' => 'publishing' ],
            [ 'id' => $post->id ],
            [ '%s' ],
            [ '%d' ]
        );

        // Trigger publishing
        $publisher = new Chavetas_Social_Publisher();
        $publisher->publish_post( $post->id );
    }
});
```

---

### Approach 2: n8n Workflow (Recommended, More Reliable)

Create a new n8n workflow: `social_media_scheduler.json`

```
┌──────────────────────────────────────────────────────────────────┐
│                  n8n Scheduling Workflow                         │
└──────────────────────────────────────────────────────────────────┘

Node 1: Webhook Trigger
  - Trigger URL: /webhook/aurareels-social-schedule
  - Method: POST
  - Auth: API Key header
  - Receives:
    {
      "post_id": 125,
      "scheduled_at": "2024-12-25T18:00:00Z",
      "user_id": 1
    }

Node 2: Wait Until Scheduled Time
  - Wait Node
  - Wait until: {{ $json.scheduled_at }}

Node 3: Call WordPress Publish API
  - HTTP Request Node
  - Method: POST
  - URL: https://chavetastech.io/wp-json/aurareels/v1/social/internal/publish-scheduled
  - Headers:
    - Authorization: Bearer {{ $env.N8N_API_KEY }}
  - Body:
    {
      "post_id": {{ $json.post_id }}
    }

Node 4: Update Post Status
  - IF Node
  - If response.success === true:
    → Success branch (send notification)
  - Else:
    → Error branch (retry logic)

Node 5: Error Handling
  - Retry up to 3 times with 5-minute delay
  - If all retries fail, send error notification
```

**WordPress Integration**:
```php
<?php
/**
 * When user schedules a post, trigger n8n workflow
 */
function trigger_n8n_scheduler( $post_id, $scheduled_at, $user_id ) {
    $webhook_url = defined( 'N8N_SCHEDULER_WEBHOOK' )
        ? N8N_SCHEDULER_WEBHOOK
        : 'https://n8n.aurasynapse.ai/webhook/aurareels-social-schedule';

    wp_remote_post( $webhook_url, [
        'headers' => [
            'Content-Type' => 'application/json',
            'X-API-Key'    => N8N_API_KEY,
        ],
        'body' => json_encode([
            'post_id'      => $post_id,
            'scheduled_at' => $scheduled_at,
            'user_id'      => $user_id,
        ]),
        'timeout' => 5,
        'blocking' => false, // Don't wait for response
    ]);
}
```

---

## n8n Workflow Integration

### Create New Workflow: `social_media_publisher.json`

This workflow is separate from video processing and handles:
1. Scheduled post publishing
2. Retry logic for failed posts
3. Token refresh before publishing

```json
{
  "name": "AuraReels Social Media Publisher",
  "nodes": [
    {
      "name": "Webhook - Schedule Post",
      "type": "n8n-nodes-base.webhook",
      "parameters": {
        "path": "aurareels-social-schedule",
        "authentication": "headerAuth",
        "responseMode": "immediate"
      }
    },
    {
      "name": "Wait Until Scheduled Time",
      "type": "n8n-nodes-base.wait",
      "parameters": {
        "resume": "specificTime",
        "time": "={{ $json.scheduled_at }}"
      }
    },
    {
      "name": "Get Post Details",
      "type": "n8n-nodes-base.httpRequest",
      "parameters": {
        "url": "={{ $env.WP_API_URL }}/wp-json/aurareels/v1/social/posts/={{ $json.post_id }}",
        "authentication": "predefinedCredentialType",
        "nodeCredentialType": "httpHeaderAuth",
        "method": "GET"
      }
    },
    {
      "name": "Check Token Expiry",
      "type": "n8n-nodes-base.if",
      "parameters": {
        "conditions": {
          "dateTime": [
            {
              "value1": "={{ $json.account.token_expires_at }}",
              "operation": "before",
              "value2": "={{ $now.plus({ hours: 24 }).toISO() }}"
            }
          ]
        }
      }
    },
    {
      "name": "Refresh Token",
      "type": "n8n-nodes-base.httpRequest",
      "parameters": {
        "url": "={{ $env.WP_API_URL }}/wp-json/aurareels/v1/social/accounts/={{ $json.social_account_id }}/refresh",
        "method": "POST"
      }
    },
    {
      "name": "Publish to Platform",
      "type": "n8n-nodes-base.httpRequest",
      "parameters": {
        "url": "={{ $env.WP_API_URL }}/wp-json/aurareels/v1/social/internal/publish",
        "method": "POST",
        "body": {
          "post_id": "={{ $json.post_id }}"
        }
      }
    },
    {
      "name": "Handle Success",
      "type": "n8n-nodes-base.httpRequest",
      "parameters": {
        "url": "={{ $env.WP_API_URL }}/wp-json/aurareels/v1/social/posts/={{ $json.post_id }}",
        "method": "PATCH",
        "body": {
          "status": "published",
          "published_at": "={{ $now.toISO() }}"
        }
      }
    },
    {
      "name": "Handle Error",
      "type": "n8n-nodes-base.httpRequest",
      "parameters": {
        "url": "={{ $env.WP_API_URL }}/wp-json/aurareels/v1/social/posts/={{ $json.post_id }}",
        "method": "PATCH",
        "body": {
          "status": "failed",
          "error_message": "={{ $json.error }}"
        }
      }
    }
  ],
  "connections": {
    "Webhook - Schedule Post": {
      "main": [[{ "node": "Wait Until Scheduled Time" }]]
    },
    "Wait Until Scheduled Time": {
      "main": [[{ "node": "Get Post Details" }]]
    },
    "Get Post Details": {
      "main": [[{ "node": "Check Token Expiry" }]]
    },
    "Check Token Expiry": {
      "main": [
        [{ "node": "Refresh Token" }],
        [{ "node": "Publish to Platform" }]
      ]
    },
    "Refresh Token": {
      "main": [[{ "node": "Publish to Platform" }]]
    },
    "Publish to Platform": {
      "main": [
        [{ "node": "Handle Success" }],
        [{ "node": "Handle Error" }]
      ]
    }
  }
}
```

---

## Security Implementation

### 1. Token Encryption

```php
<?php
/**
 * Encrypt/Decrypt tokens using AES-256-CBC
 */
class Chavetas_Token_Manager {

    private static function get_encryption_key() {
        if ( ! defined( 'AURAREELS_SOCIAL_ENCRYPTION_KEY' ) ) {
            throw new Exception( 'AURAREELS_SOCIAL_ENCRYPTION_KEY not defined in wp-config.php' );
        }
        return hash( 'sha256', AURAREELS_SOCIAL_ENCRYPTION_KEY, true );
    }

    public static function encrypt( $data ) {
        $iv = openssl_random_pseudo_bytes( 16 );
        $encrypted = openssl_encrypt(
            $data,
            'AES-256-CBC',
            self::get_encryption_key(),
            OPENSSL_RAW_DATA,
            $iv
        );

        // Return IV + encrypted data as base64
        return base64_encode( $iv . $encrypted );
    }

    public static function decrypt( $data ) {
        $data = base64_decode( $data );
        $iv = substr( $data, 0, 16 );
        $encrypted = substr( $data, 16 );

        return openssl_decrypt(
            $encrypted,
            'AES-256-CBC',
            self::get_encryption_key(),
            OPENSSL_RAW_DATA,
            $iv
        );
    }
}
```

### 2. Add to `wp-config.php`

```php
// Social Media Integration Security
define( 'AURAREELS_SOCIAL_ENCRYPTION_KEY', 'YOUR-RANDOM-64-CHAR-STRING-HERE' );

// TikTok App Credentials
define( 'TIKTOK_CLIENT_ID', 'your-tiktok-client-key' );
define( 'TIKTOK_CLIENT_SECRET', 'your-tiktok-client-secret' );

// Instagram App Credentials
define( 'INSTAGRAM_APP_ID', 'your-facebook-app-id' );
define( 'INSTAGRAM_APP_SECRET', 'your-facebook-app-secret' );

// n8n Scheduler Webhook
define( 'N8N_SCHEDULER_WEBHOOK', 'https://n8n.aurasynapse.ai/webhook/aurareels-social-schedule' );
```

### 3. OAuth State Validation

```php
<?php
/**
 * Generate and validate CSRF state tokens
 */
class Chavetas_OAuth_State {

    public static function generate( $user_id, $platform ) {
        $state = wp_generate_password( 32, false );
        set_transient( "oauth_state_{$user_id}_{$platform}", $state, 600 ); // 10 minutes
        return $state;
    }

    public static function validate( $user_id, $platform, $state ) {
        $stored_state = get_transient( "oauth_state_{$user_id}_{$platform}" );
        delete_transient( "oauth_state_{$user_id}_{$platform}" ); // Use once

        return $stored_state && hash_equals( $stored_state, $state );
    }
}
```

### 4. Rate Limiting

```php
<?php
/**
 * Rate limit social media API calls
 */
class Chavetas_Rate_Limiter {

    public static function check_limit( $user_id, $platform, $limit = 5, $period = DAY_IN_SECONDS ) {
        $key = "social_posts_{$user_id}_{$platform}";
        $count = get_transient( $key );

        if ( $count === false ) {
            set_transient( $key, 1, $period );
            return true;
        }

        if ( $count >= $limit ) {
            return false; // Rate limit exceeded
        }

        set_transient( $key, $count + 1, $period );
        return true;
    }

    public static function get_remaining( $user_id, $platform, $limit = 5 ) {
        $key = "social_posts_{$user_id}_{$platform}";
        $count = get_transient( $key ) ?: 0;
        return max( 0, $limit - $count );
    }
}
```

---

## Error Handling

### Common Errors and Solutions

| Error | Cause | Solution |
|-------|-------|----------|
| **Invalid Token** | Access token expired | Auto-refresh using refresh_token |
| **Rate Limit Exceeded** | Too many API calls | Show remaining quota, suggest retry time |
| **Video Not Public** | Cloudflare Stream not public | Enable public downloads via n8n |
| **Video Too Large** | Exceeds platform limit | Re-encode or trim video |
| **Invalid Video Format** | Unsupported codec | Convert to H.264 MP4 |
| **Caption Too Long** | Exceeds 2200 chars | Truncate with warning |
| **Duplicate Post** | Already published | Check platform_post_id before retry |
| **OAuth State Mismatch** | CSRF attack or expired | Regenerate state, retry OAuth |
| **Container Not Ready** | Instagram processing | Poll status, wait before publish |

---

## Testing Guide

### Local Development Testing

#### 1. Test TikTok OAuth Flow
```bash
# Step 1: Initiate connection
curl -X POST http://localhost:3000/wp-json/aurareels/v1/social/accounts/connect/tiktok \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"

# Step 2: Follow auth_url in browser
# Step 3: Verify callback handles redirect correctly
# Step 4: Check database for encrypted tokens
```

#### 2. Test Instagram OAuth Flow
```bash
# Similar to TikTok, but verify Facebook Page connection
# Ensure Instagram Business Account is linked
```

#### 3. Test Publishing
```bash
# Publish immediately
curl -X POST http://localhost:3000/wp-json/aurareels/v1/social/publish \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "video_job_id": "your-job-id",
    "accounts": [1],
    "caption": "Test post from API",
    "hashtags": ["#test", "#api"]
  }'
```

#### 4. Test Scheduling
```bash
# Schedule for 1 hour from now
SCHEDULED_TIME=$(date -u -v+1H '+%Y-%m-%dT%H:%M:%SZ')

curl -X POST http://localhost:3000/wp-json/aurareels/v1/social/schedule \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"video_job_id\": \"your-job-id\",
    \"accounts\": [1],
    \"scheduled_at\": \"$SCHEDULED_TIME\",
    \"caption\": \"Scheduled test post\"
  }"
```

---

## Deployment Checklist

### Pre-Deployment

- [ ] **TikTok Developer Account**: Applied and approved for Content Posting API
- [ ] **Instagram Business Account**: Created and linked to Facebook Page
- [ ] **Facebook App**: Created with Instagram Graph API permissions
- [ ] **OAuth Redirect URLs**: Configured in TikTok and Facebook developer portals
- [ ] **wp-config.php**: All credentials and encryption key added
- [ ] **Database Tables**: Created via plugin activation hook
- [ ] **n8n Workflow**: Imported and tested in staging environment
- [ ] **Cloudflare Videos**: Public access enabled (or signed URL generation ready)
- [ ] **SSL Certificate**: Valid HTTPS for OAuth callbacks

### Post-Deployment

- [ ] **Test OAuth Flow**: Connect at least one account per platform
- [ ] **Test Publishing**: Publish test video to TikTok and Instagram
- [ ] **Test Scheduling**: Schedule post and verify it publishes
- [ ] **Monitor Logs**: Check WordPress debug.log and n8n execution logs
- [ ] **Rate Limit Testing**: Verify rate limiting works correctly
- [ ] **Error Handling**: Test retry logic for failed posts
- [ ] **Token Refresh**: Verify automatic token refresh before expiration
- [ ] **User Permissions**: Ensure users can only access their own accounts/posts

---

## API Rate Limits & Best Practices

### TikTok Rate Limits

| Limit Type | Value | Recommendation |
|------------|-------|----------------|
| **Daily Posts per User** | 5 videos | Show remaining quota in UI |
| **API Calls per App** | Varies by tier | Monitor via developer dashboard |
| **Video Size** | 4GB max | Warn user before upload if exceeds |
| **Video Duration** | 3s - 10min | Validate before publishing |

**Best Practices**:
- Cache user quota in transient (refresh hourly)
- Show countdown timer before daily limit resets
- Queue posts if limit reached, auto-publish next day

---

### Instagram Rate Limits

| Limit Type | Value | Recommendation |
|------------|-------|----------------|
| **Content Publishing** | 25 calls/user/24h | Track calls, show remaining |
| **Container Creation** | Processing time varies | Poll status every 10s |
| **Business Discovery** | 200 calls/hour | Limit analytics sync |

**Best Practices**:
- Implement exponential backoff for container status polling
- Cache Instagram account details (refresh every 6 hours)
- Batch analytics fetching to minimize API calls

---

### Cloudflare Stream Considerations

- **Public Access**: Videos must be publicly accessible or use signed URLs
- **MP4 Download**: Enabled via n8n workflow (already implemented)
- **CDN**: Cloudflare provides global CDN, no additional optimization needed
- **Signed URLs**: Generate with 24-hour expiration for temporary access

---

## Next Steps

### Phase 1: Core Implementation (Weeks 1-4)
1. Create database tables
2. Implement TikTok OAuth and publishing
3. Implement Instagram OAuth and publishing
4. Build frontend UI (accounts manager, publish dialog)
5. Test end-to-end flow

### Phase 2: Scheduling (Weeks 5-6)
1. Build n8n scheduling workflow
2. Implement WordPress cron fallback
3. Create calendar UI
4. Test scheduled publishing

### Phase 3: Enhancements (Weeks 7-8)
1. Add analytics dashboard (fetch post metrics)
2. Implement multi-account support
3. Add post templates and hashtag groups
4. Improve error handling and retry logic
5. Documentation and user guides

---

## Support & Resources

### Official Documentation
- **TikTok**: https://developers.tiktok.com/doc/content-posting-api-overview/
- **Instagram**: https://developers.facebook.com/docs/instagram-api/guides/content-publishing
- **n8n**: https://docs.n8n.io/

### WordPress Development
- **REST API**: https://developer.wordpress.org/rest-api/
- **Cron System**: https://developer.wordpress.org/plugins/cron/
- **Custom Tables**: https://codex.wordpress.org/Creating_Tables_with_Plugins

### Testing Tools
- **Postman**: For API testing
- **ngrok**: For local OAuth callback testing
- **Browser DevTools**: Network tab for debugging OAuth flows

---

**End of Documentation**

This documentation provides a complete blueprint for implementing social media publishing and scheduling in the AuraReels platform. Follow the phases sequentially for best results.
