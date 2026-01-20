# AuraReels Subscription System - Complete Documentation

**Version**: 1.1
**Last Updated**: January 20, 2026
**Status**: Ready for Implementation

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [Pricing Structure](#pricing-structure)
3. [System Architecture](#system-architecture)
4. [Database Schema](#database-schema)
5. [Dynamic Plans Management](#dynamic-plans-management)
6. [Subscription Validation Strategy](#subscription-validation-strategy)
7. [Stripe Integration](#stripe-integration)
8. [WordPress Admin Interface](#wordpress-admin-interface)
9. [Frontend Integration](#frontend-integration)
10. [API Endpoints](#api-endpoints)
11. [Security & Compliance](#security--compliance)

---

## Executive Summary

### Goal

Implement a **subscription system** for AuraReels with:
- **Three pricing tiers** (Builder €1.99, Beta Tester €2.99, Early Access €99)
- **Full platform access** for all plans (no feature restrictions in MVP)
- **Stripe integration** for payment processing
- **Dynamic plan management** from WordPress admin
- **Real-time subscription validation**
- **Credit system prepared** for future add-on features (database only)

### Key Principles

✅ **All plans have identical access** - No feature gating in MVP
✅ **Stripe as source of truth** - Webhooks sync payment status
✅ **Dynamic plans** - Add/modify plans without code changes
✅ **Secure validation** - Multiple validation checkpoints
✅ **Future-ready** - Credit system database prepared but not implemented

---

## Pricing Structure

### Current Plans

| Plan | Price | Visibility | Target Users | Access |
|------|-------|------------|--------------|--------|
| **Builder** | €1.99/month | Admin Only | Chavetas, Mindfultravel, internal team | Full Access |
| **Beta Tester** | €2.99/month | Admin Only | Pau, GuiasViajar, Rebeca3.0, beta influencers | Full Access |
| **Early Access** | ~~€129~~ **€99/month** | Public | Anyone who wants early access | Full Access |

### All Plans Include

- ✅ Unlimited video uploads (no monthly limits)
- ✅ All video quality options (480p, 1080p, 4K)
- ✅ No watermarks
- ✅ Full AI analysis (Gemini-powered metadata)
- ✅ AI image generation
- ✅ LoRA training access (DreamShots)
- ✅ Cloudflare Stream (managed by Chavetas)
- ✅ Priority support

### Future Credit System (NOT in MVP)

**Purpose**: Credits for **add-on features** only (not core platform)

**Potential Uses**:
- Premium AI models (GPT-4, Claude)
- Advanced video editing
- Extended storage
- White-label customization
- API usage beyond quotas

**Database Ready**: Fields exist but no UI/purchasing in MVP

---

## System Architecture

### High-Level Flow

```
┌─────────────────────────────────────────────────────────┐
│              FRONTEND (Next.js)                         │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  Registration Flow:                                     │
│  1. Email/Password Signup                               │
│  2. WordPress User Creation                             │
│  3. Plan Selection (only Early Access shows)            │
│  4. Stripe Checkout                                     │
│  5. Subscription Validation + JWT                       │
│  6. Dashboard Access                                    │
│                                                         │
│  Validation Points:                                     │
│  • On login (JWT issuance)                              │
│  • Dashboard load (component mount)                     │
│  • Periodic (every 15 minutes)                          │
│  • Critical actions (upload, AI features)               │
└─────────────────────────────────────────────────────────┘
                         ↓ REST API
┌─────────────────────────────────────────────────────────┐
│         BACKEND (WordPress - aurareels-core)            │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  Module: api/common-subscriptions/                      │
│                                                         │
│  REST Endpoints:                                        │
│  • POST /create-checkout-session                        │
│  • POST /create-portal-session                          │
│  • POST /webhook (Stripe events)                        │
│  • GET /subscription-status                             │
│  • GET /validate-subscription                           │
│  • GET /available-plans                                 │
│                                                         │
│  Admin Endpoints:                                       │
│  • GET/POST/PUT/DELETE /admin/plans                     │
│  • GET/PUT /admin/users/{id}/subscription               │
└─────────────────────────────────────────────────────────┘
                         ↓ Webhooks
┌─────────────────────────────────────────────────────────┐
│                   STRIPE                                │
├─────────────────────────────────────────────────────────┤
│  • Checkout Sessions                                    │
│  • Customer Portal                                      │
│  • Webhook Events (payment success/failed/canceled)     │
│  • Products & Prices (EUR)                              │
└─────────────────────────────────────────────────────────┘
```

---

## Database Schema

### 1. Extended User Table

**Table**: `wp_core_chavetas_shorts_helper_users`

**New Fields**:

```sql
ALTER TABLE wp_core_chavetas_shorts_helper_users ADD COLUMN (
    -- Stripe Integration
    stripe_customer_id VARCHAR(255) UNIQUE,
    stripe_subscription_id VARCHAR(255),

    -- Subscription Management
    subscription_status ENUM('active', 'past_due', 'canceled', 'incomplete', 'trialing', 'paused') DEFAULT NULL,
    subscription_plan VARCHAR(100),  -- References plans.plan_slug (not ENUM for flexibility)
    subscription_started_at DATETIME,
    subscription_ends_at DATETIME,
    subscription_canceled_at DATETIME,
    subscription_cancel_at_period_end TINYINT(1) DEFAULT 0,

    -- Validation
    last_validation_check DATETIME,
    validation_failures INT DEFAULT 0,

    -- Credits (prepared for future, NOT IN MVP)
    credit_balance INT DEFAULT 0,
    credit_lifetime_purchased INT DEFAULT 0,

    -- Billing
    billing_cycle ENUM('monthly') DEFAULT 'monthly',
    next_billing_date DATETIME,

    -- Admin Overrides
    feature_overrides JSON DEFAULT NULL,
    admin_notes TEXT DEFAULT NULL,

    -- Indexes
    INDEX idx_stripe_customer (stripe_customer_id),
    INDEX idx_subscription_status (subscription_status),
    INDEX idx_subscription_plan (subscription_plan),
    INDEX idx_last_validation (last_validation_check)
);
```

**Key Change**: `subscription_plan` is VARCHAR(100) instead of ENUM to support dynamic plans.

---

### 2. Subscription Plans Table (NEW)

**Table**: `wp_aurareels_subscription_plans`

**Purpose**: Store all subscription plans with dynamic configuration

```sql
CREATE TABLE wp_aurareels_subscription_plans (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,

    -- Plan Identification
    plan_slug VARCHAR(100) NOT NULL,  -- 'builder', 'beta_tester', 'early_access'
    plan_name VARCHAR(255) NOT NULL,
    plan_name_translations JSON,      -- {"en": "Builder", "es": "Constructor"}

    -- Pricing
    price DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'EUR',
    billing_cycle ENUM('monthly', 'yearly', 'lifetime') DEFAULT 'monthly',
    original_price DECIMAL(10,2),     -- For strikethrough pricing
    discount_percentage INT,

    -- Stripe Integration
    stripe_price_id VARCHAR(255),
    stripe_product_id VARCHAR(255),

    -- Description
    short_description TEXT,
    long_description TEXT,
    description_translations JSON,

    -- Features (JSON for flexibility)
    features JSON NOT NULL,
    /* Example:
    {
        "videos_per_month": -1,       // -1 = unlimited
        "max_video_quality": "4k",
        "watermark": false,
        "ai_analysis": "full",
        "priority_support": true
    }
    */

    -- Feature List (for marketing page)
    feature_list JSON,
    /* Example:
    [
        {"icon": "video", "text": "Unlimited uploads", "highlight": true},
        {"icon": "quality", "text": "4K video quality"}
    ]
    */

    -- Visibility Control ⭐
    status ENUM('active', 'inactive', 'deprecated', 'coming_soon') DEFAULT 'active',
    visibility ENUM('public', 'admin_only', 'legacy', 'hidden') DEFAULT 'public',
    /*
    Visibility:
    - 'public': Shows on registration, anyone can subscribe
    - 'admin_only': Only admins assign, hidden from registration
    - 'legacy': Existing users keep it, new users can't subscribe
    - 'hidden': Completely hidden, for custom deals
    */

    -- Registration
    allow_self_registration TINYINT(1) DEFAULT 0,
    requires_approval TINYINT(1) DEFAULT 0,
    invitation_only TINYINT(1) DEFAULT 0,
    max_subscribers INT DEFAULT NULL,     -- NULL = unlimited
    current_subscribers INT DEFAULT 0,

    -- Targeting
    target_users JSON,
    /* Example:
    {
        "allowed_emails": ["user@chavetas.com"],
        "allowed_domains": ["@chavetas.com"],
        "specific_users": [1, 5, 10]
    }
    */

    -- Display
    display_order INT DEFAULT 0,
    is_featured TINYINT(1) DEFAULT 0,
    is_recommended TINYINT(1) DEFAULT 0,
    badge_text VARCHAR(100),             -- "Best Value", "Limited Time"
    badge_color VARCHAR(7),              -- Hex: #FF5733

    -- Credits (for future)
    monthly_credits INT DEFAULT 0,
    bonus_credits_on_signup INT DEFAULT 0,

    -- Availability
    available_from DATETIME,
    available_until DATETIME,

    -- Metadata
    created_by BIGINT(20),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME,                 -- Soft delete

    internal_notes TEXT,

    -- Indexes
    PRIMARY KEY (id),
    UNIQUE KEY unique_plan_slug (plan_slug),
    KEY idx_status (status),
    KEY idx_visibility (visibility),
    KEY idx_display_order (display_order),
    KEY idx_stripe_price (stripe_price_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 3. Subscription History

**Table**: `wp_aurareels_subscription_history`

```sql
CREATE TABLE wp_aurareels_subscription_history (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    stripe_customer_id VARCHAR(255),
    stripe_subscription_id VARCHAR(255),

    event_type ENUM('created', 'updated', 'canceled', 'payment_succeeded', 'payment_failed', 'reactivated'),
    old_status VARCHAR(50),
    new_status VARCHAR(50),
    old_plan VARCHAR(100),
    new_plan VARCHAR(100),

    amount DECIMAL(10,2),
    currency VARCHAR(3),

    event_data JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_user (user_id),
    KEY idx_stripe_customer (stripe_customer_id),
    KEY idx_event_type (event_type),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 4. Initial Plans Data

```sql
INSERT INTO wp_aurareels_subscription_plans (
    plan_slug, plan_name, price, currency, visibility, allow_self_registration,
    status, display_order, features, feature_list
) VALUES
-- Builder Plan
('builder', 'Builder', 1.99, 'EUR', 'admin_only', 0, 'active', 1,
 '{"videos_per_month": -1, "max_video_quality": "4k", "watermark": false, "ai_analysis": "full", "cloudflare_mode": "managed"}',
 '[{"icon": "video", "text": "Unlimited video uploads"}, {"icon": "quality", "text": "4K quality"}, {"icon": "ai", "text": "Full AI features"}]'
),

-- Beta Tester Plan
('beta_tester', 'Beta Tester', 2.99, 'EUR', 'admin_only', 0, 'active', 2,
 '{"videos_per_month": -1, "max_video_quality": "4k", "watermark": false, "ai_analysis": "full", "beta_features": true}',
 '[{"icon": "video", "text": "Unlimited videos"}, {"icon": "beta", "text": "Beta features access"}, {"icon": "feedback", "text": "Direct feedback channel"}]'
),

-- Early Access Plan
('early_access', 'Early Access', 99.00, 'EUR', 'public', 1, 'active', 3,
 '{"videos_per_month": -1, "max_video_quality": "4k", "watermark": false, "ai_analysis": "full", "api_access": true, "professional_use": true}',
 '[{"icon": "infinity", "text": "Unlimited uploads", "highlight": true}, {"icon": "hd", "text": "4K quality"}, {"icon": "ai", "text": "Full AI analysis"}, {"icon": "early", "text": "Early access to features"}]'
);

UPDATE wp_aurareels_subscription_plans
SET original_price = 129.00, discount_percentage = 23
WHERE plan_slug = 'early_access';
```

---

## Dynamic Plans Management

### WordPress Admin Interface

**Location**: `wp-admin/admin.php?page=aurareels-subscription-plans`

#### Plans List Page

**Features**:
- Table view with all plans
- Filters: status, visibility
- Bulk actions: activate, deactivate, delete
- Drag-and-drop reordering
- Quick edit for common fields
- Duplicate plan button

**Table Columns**:
```
┌──────────────────────────────────────────────────────────────┐
│ ☰ │ Plan Name    │ Price │ Visibility │ Status │ Subs │ Actions │
├──────────────────────────────────────────────────────────────┤
│ ☰ │ Early Access │ €99   │ Public     │ Active │ 45   │ E D V   │
│ ☰ │ Beta Tester  │ €2.99 │ Admin Only │ Active │ 12   │ E D V   │
│ ☰ │ Builder      │ €1.99 │ Admin Only │ Active │ 5    │ E D V   │
└──────────────────────────────────────────────────────────────┘
(E=Edit, D=Duplicate, V=View Subscribers)
```

#### Add/Edit Plan Form

**7 Tabs**:

1. **Basic Info**
   - Plan name (with translations)
   - Slug (auto-generated, editable)
   - Short description
   - Long description

2. **Pricing**
   - Price
   - Currency (EUR default)
   - Billing cycle (monthly, yearly, lifetime)
   - Original price (for strikethrough)
   - Discount percentage (auto-calculated)

3. **Features**
   - Videos per month (-1 for unlimited)
   - Max video quality (480p, 1080p, 4K)
   - Watermark (yes/no)
   - AI analysis level
   - Support priority
   - Other feature toggles

4. **Visibility & Access** ⭐
   - Status (active, inactive, deprecated, coming_soon)
   - Visibility (public, admin_only, legacy, hidden)
   - Allow self-registration (checkbox)
   - Requires approval (checkbox)
   - Invitation only (checkbox)
   - Max subscribers (number or unlimited)

5. **Availability**
   - Available from (date/time picker)
   - Available until (date/time picker)
   - Target users (JSON editor)

6. **Credits** (for future)
   - Monthly credits included
   - Bonus credits on signup

7. **Internal**
   - Created by
   - Created at
   - Last updated
   - Admin notes

---

### Query for Registration Page

**Get public plans**:

```sql
SELECT * FROM wp_aurareels_subscription_plans
WHERE status = 'active'
  AND visibility = 'public'
  AND allow_self_registration = 1
  AND (available_from <= NOW() OR available_from IS NULL)
  AND (available_until >= NOW() OR available_until IS NULL)
  AND (current_subscribers < max_subscribers OR max_subscribers IS NULL)
ORDER BY display_order ASC;
```

**Result**: Only "Early Access" plan shows for now.

---

## Subscription Validation Strategy

### Hybrid Validation Approach (RECOMMENDED)

**4 Validation Checkpoints**:

#### 1. On Login (100% Coverage)

**Purpose**: Prevent login if subscription inactive

```javascript
// aurareels/src/contexts/jwtContext.jsx
const login = async (email, password) => {
  const response = await axios.post(`${API_URL}/jwt-auth/v1/token`, {
    username: email,
    password: password,
  });

  const token = response.data.token;

  // VALIDATE SUBSCRIPTION
  const subStatus = await axios.get(
    `${API_URL}/subscriptions/validate-subscription`,
    { headers: { Authorization: `Bearer ${token}` }}
  );

  if (subStatus.data.status !== 'active') {
    throw new Error('Active subscription required. Please subscribe to continue.');
  }

  localStorage.setItem('token', token);
  setJWT(token);
  setUser(subStatus.data.user);
};
```

**Backend**:

```php
// GET /wp-json/aurareels/v1/subscriptions/validate-subscription
public function validate_subscription( $request ) {
    $user_id = get_current_user_id();

    global $wpdb;
    $user_data = $wpdb->get_row($wpdb->prepare(
        "SELECT subscription_status, subscription_plan, subscription_ends_at
         FROM {$wpdb->prefix}core_chavetas_shorts_helper_users
         WHERE wp_user_id = %d",
        $user_id
    ));

    if (!$user_data || $user_data->subscription_status !== 'active') {
        return new WP_Error(
            'subscription_required',
            'Active subscription required.',
            ['status' => 403]
        );
    }

    // Update last validation timestamp
    $wpdb->update(
        $wpdb->prefix . 'core_chavetas_shorts_helper_users',
        ['last_validation_check' => current_time('mysql')],
        ['wp_user_id' => $user_id]
    );

    return [
        'status' => 'active',
        'plan' => $user_data->subscription_plan,
        'valid_until' => $user_data->subscription_ends_at,
    ];
}
```

---

#### 2. Dashboard Load (Component Mount)

```javascript
// aurareels/src/app/dashboard/layout.jsx
export default function DashboardLayout({ children }) {
  const router = useRouter();
  const [subscriptionValid, setSubscriptionValid] = useState(null);

  useEffect(() => {
    checkSubscription();
  }, []);

  const checkSubscription = async () => {
    try {
      const status = await getSubscriptionStatus();
      setSubscriptionValid(status.status === 'active');
    } catch (error) {
      if (error.response?.status === 403) {
        setSubscriptionValid(false);
      }
    }
  };

  if (subscriptionValid === false) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="text-center">
          <h1>Subscription Required</h1>
          <p>Your subscription has expired.</p>
          <button onClick={() => router.push('/subscribe')}>
            View Plans
          </button>
        </div>
      </div>
    );
  }

  return <>{children}</>;
}
```

---

#### 3. Periodic Validation (Every 15 Minutes)

**Custom Hook**:

```javascript
// aurareels/src/hooks/useSubscriptionValidation.js
import { useEffect, useState } from 'react';
import { useJWT } from '@/contexts/jwtContext';
import { validateSubscription } from '@/layouts/aurareels/utils/api';

export function useSubscriptionValidation() {
  const { logout } = useJWT();
  const [isValid, setIsValid] = useState(true);

  useEffect(() => {
    checkSubscription();

    const interval = setInterval(() => {
      checkSubscription();
    }, 15 * 60 * 1000);  // 15 minutes

    return () => clearInterval(interval);
  }, []);

  const checkSubscription = async () => {
    try {
      const response = await validateSubscription();

      if (response.status !== 'active') {
        setIsValid(false);
        // Show modal with 30-second countdown
        setTimeout(() => {
          logout();
          window.location.href = '/subscribe';
        }, 30000);
      }
    } catch (error) {
      if (error.response?.status === 403) {
        logout();
        window.location.href = '/subscribe';
      }
    }
  };

  return { isValid };
}
```

**Usage**:

```javascript
// In dashboard components
import { useSubscriptionValidation } from '@/hooks/useSubscriptionValidation';

function Dashboard() {
  const { isValid } = useSubscriptionValidation();
  // Component logic...
}
```

---

#### 4. Critical Actions (Backend)

**Validate before expensive operations**:

```php
// Before video upload
public function create_job( $request ) {
    if (!$this->has_active_subscription(get_current_user_id())) {
        return new WP_Error(
            'subscription_required',
            'Active subscription required to upload videos.',
            ['status' => 403]
        );
    }

    // Proceed with upload...
}

private function has_active_subscription( $user_id ) {
    global $wpdb;
    $status = $wpdb->get_var($wpdb->prepare(
        "SELECT subscription_status
         FROM {$wpdb->prefix}core_chavetas_shorts_helper_users
         WHERE wp_user_id = %d",
        $user_id
    ));

    return $status === 'active';
}
```

**Apply to**:
- Video upload (`POST /job`)
- AI image generation (`POST /generate-image`)
- LoRA training (`POST /loras`)
- Rebuild analysis (`POST /job/{id}/rebuild`)

---

### Grace Period Handling

**For payment failures**:

```php
private function has_active_subscription( $user_id ) {
    global $wpdb;
    $user = $wpdb->get_row($wpdb->prepare(
        "SELECT subscription_status, subscription_ends_at
         FROM {$wpdb->prefix}core_chavetas_shorts_helper_users
         WHERE wp_user_id = %d",
        $user_id
    ));

    // Allow 'active' status
    if ($user->subscription_status === 'active') {
        return true;
    }

    // Allow 'past_due' with 7-day grace period
    if ($user->subscription_status === 'past_due') {
        $ends_at = strtotime($user->subscription_ends_at);
        $grace_period = strtotime('+7 days', $ends_at);

        if (time() < $grace_period) {
            return true;  // Show warning but allow access
        }
    }

    return false;
}
```

---

## Stripe Integration

### Products & Prices Setup

**In Stripe Dashboard**, create:

```javascript
// Builder Product
{
  name: "AuraReels Builder",
  description: "Full platform access for internal team",
  metadata: { plan_tier: "builder" }
}
// Price: €1.99/month → price_id: "price_xxx_builder_monthly"

// Beta Tester Product
{
  name: "AuraReels Beta Tester",
  description: "Full access for early supporters",
  metadata: { plan_tier: "beta_tester" }
}
// Price: €2.99/month → price_id: "price_xxx_beta_tester_monthly"

// Early Access Product
{
  name: "AuraReels Early Access",
  description: "Discounted early access for creators",
  metadata: { plan_tier: "early_access", original_price: "129" }
}
// Price: €99/month → price_id: "price_xxx_early_access_monthly"
```

**Currency**: EUR (not USD)
**Amounts**: In cents (199, 299, 9900)

---

### Stripe Checkout Flow

**Frontend**:

```javascript
// aurareels/src/app/register/page.jsx
const handleSubscribe = async (planSlug) => {
  try {
    const { url } = await createCheckoutSession({
      plan_slug: planSlug,
      success_url: `${window.location.origin}/dashboard?session_id={CHECKOUT_SESSION_ID}`,
      cancel_url: `${window.location.origin}/register`
    });

    window.location.href = url;
  } catch (error) {
    console.error('Checkout failed:', error);
  }
};
```

**Backend**:

```php
// POST /wp-json/aurareels/v1/subscriptions/create-checkout-session
public function create_checkout_session( $request ) {
    $user_id = get_current_user_id();
    $plan_slug = $request->get_param('plan_slug');

    // Get plan from database
    global $wpdb;
    $plan = $wpdb->get_row($wpdb->prepare(
        "SELECT stripe_price_id FROM wp_aurareels_subscription_plans WHERE plan_slug = %s",
        $plan_slug
    ));

    $stripe = new \Stripe\StripeClient(STRIPE_SECRET_KEY);

    $session = $stripe->checkout->sessions->create([
        'mode' => 'subscription',
        'customer_email' => wp_get_current_user()->user_email,
        'line_items' => [[
            'price' => $plan->stripe_price_id,
            'quantity' => 1,
        ]],
        'success_url' => $request->get_param('success_url'),
        'cancel_url' => $request->get_param('cancel_url'),
        'metadata' => [
            'wp_user_id' => $user_id,
            'plan_slug' => $plan_slug,
        ]
    ]);

    return ['url' => $session->url];
}
```

---

### Webhook Handling

**Endpoint**: `POST /wp-json/aurareels/v1/subscriptions/webhook`

**Events to Handle**:

```php
public function handle_webhook( $request ) {
    $payload = $request->get_body();
    $sig_header = $request->get_header('stripe_signature');

    $event = \Stripe\Webhook::constructEvent(
        $payload, $sig_header, STRIPE_WEBHOOK_SECRET
    );

    switch ($event->type) {
        case 'checkout.session.completed':
            $this->handle_checkout_completed($event->data->object);
            break;

        case 'customer.subscription.updated':
            $this->handle_subscription_updated($event->data->object);
            break;

        case 'customer.subscription.deleted':
            $this->handle_subscription_deleted($event->data->object);
            break;

        case 'invoice.payment_succeeded':
            $this->handle_payment_succeeded($event->data->object);
            break;

        case 'invoice.payment_failed':
            $this->handle_payment_failed($event->data->object);
            break;
    }

    return ['received' => true];
}

private function handle_checkout_completed( $session ) {
    $user_id = $session->metadata->wp_user_id;
    $plan_slug = $session->metadata->plan_slug;

    global $wpdb;
    $wpdb->update(
        $wpdb->prefix . 'core_chavetas_shorts_helper_users',
        [
            'stripe_customer_id' => $session->customer,
            'stripe_subscription_id' => $session->subscription,
            'subscription_status' => 'active',
            'subscription_plan' => $plan_slug,
            'subscription_started_at' => current_time('mysql'),
        ],
        ['wp_user_id' => $user_id]
    );

    // Log to history
    $this->log_subscription_event($user_id, 'created', null, 'active', null, $plan_slug);
}

private function handle_payment_failed( $invoice ) {
    global $wpdb;
    $wpdb->update(
        $wpdb->prefix . 'core_chavetas_shorts_helper_users',
        ['subscription_status' => 'past_due'],
        ['stripe_customer_id' => $invoice->customer]
    );

    // Send payment failed email
}
```

---

## API Endpoints

### Public Endpoints (Frontend)

```
GET  /wp-json/aurareels/v1/subscriptions/available-plans
     → Returns only public, active plans

POST /wp-json/aurareels/v1/subscriptions/create-checkout-session
     → Creates Stripe Checkout session

POST /wp-json/aurareels/v1/subscriptions/create-portal-session
     → Returns Stripe Customer Portal URL

GET  /wp-json/aurareels/v1/subscriptions/validate-subscription
     → Validates current user's subscription

GET  /wp-json/aurareels/v1/subscriptions/subscription-status
     → Returns subscription details
```

### Admin Endpoints

```
GET    /wp-json/aurareels/v1/admin/plans
       → List all plans

POST   /wp-json/aurareels/v1/admin/plans
       → Create new plan

GET    /wp-json/aurareels/v1/admin/plans/{id}
       → Get plan details

PUT    /wp-json/aurareels/v1/admin/plans/{id}
       → Update plan

DELETE /wp-json/aurareels/v1/admin/plans/{id}
       → Soft delete plan

POST   /wp-json/aurareels/v1/admin/plans/{id}/duplicate
       → Duplicate plan

GET    /wp-json/aurareels/v1/admin/users
       → List users with subscriptions

PUT    /wp-json/aurareels/v1/admin/users/{id}/subscription
       → Manually assign subscription
```

---

## Security & Compliance

### PCI Compliance
✅ Stripe handles all payment data
✅ No credit card info stored in WordPress
✅ Use Stripe.js for frontend card input

### Data Protection
✅ HTTPS required for all endpoints
✅ JWT authentication for API access
✅ Webhook signature verification
✅ Rate limiting on sensitive endpoints

### WordPress Security
✅ Capability checks for admin endpoints
✅ Nonce verification for forms
✅ SQL injection prevention (prepared statements)
✅ XSS protection (escaped output)

---

## Summary

### What's Ready

✅ **Database schema** designed and documented
✅ **Pricing structure** finalized (3 tiers, EUR)
✅ **Validation strategy** defined (hybrid approach)
✅ **Dynamic plans** system designed
✅ **Stripe integration** planned
✅ **API endpoints** documented
✅ **Admin interface** designed

### What's NOT in MVP

❌ **Credit purchasing UI** (database ready only)
❌ **Feature restrictions** (all plans have full access)
❌ **Annual billing** (monthly only for now)
❌ **Trials** (all plans are paid)
❌ **Team/multi-user subscriptions**

### Next Steps

When ready to implement, start with:

1. Create database tables
2. Seed initial 3 plans
3. Set up Stripe products/prices
4. Implement WordPress REST API endpoints
5. Build admin interface for plan management
6. Integrate frontend registration flow
7. Implement validation hooks
8. Test all flows thoroughly

---

**For quick reference and common tasks, see**: [SUBSCRIPTION-QUICK-REFERENCE.md](./SUBSCRIPTION-QUICK-REFERENCE.md)
