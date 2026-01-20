# AuraReels Subscription System - Quick Reference

**Last Updated**: January 20, 2026
**For**: Admins, Product Managers, Quick Implementation Reference

---

## Quick Facts

### Current Plans

| Plan | Price | Who Can See | Who Can Subscribe |
|------|-------|-------------|-------------------|
| Builder | €1.99/mo | Admin only | Admin assigns manually |
| Beta Tester | €2.99/mo | Admin only | Admin assigns manually |
| Early Access | €99/mo | Everyone (registration page) | Anyone can self-register |

### All Plans Include

✅ Unlimited video uploads
✅ 4K quality
✅ No watermarks
✅ Full AI features
✅ Priority support

### Credit System Status

🔧 **Database prepared** but **NO UI in MVP**
📅 **Future use**: Add-on features only (not core platform)

---

## Common Admin Tasks

### 1. Add New Public Plan (e.g., "Professional")

**WordPress Admin → AuraReels → Plans → Add New**

1. **Basic Info Tab**:
   - Name: Professional
   - Slug: professional (auto-generates)
   - Description: Mid-tier plan for professional creators

2. **Pricing Tab**:
   - Price: €49.00
   - Currency: EUR
   - Billing: Monthly

3. **Visibility Tab** ⭐:
   - Status: **Active**
   - Visibility: **Public**
   - Allow self-registration: ✅ **Checked**

4. **Create in Stripe**:
   - Go to Stripe Dashboard
   - Create product "AuraReels Professional"
   - Create price €49/month
   - Copy Price ID (e.g., `price_xxx_professional_monthly`)

5. **Back to WordPress**:
   - Paste Stripe Price ID
   - Click **Publish**

✅ **Result**: Plan immediately shows on registration page

---

### 2. Manually Assign Plan to User

**WordPress Admin → AuraReels → Users → Select User**

1. Find user or create new user
2. Scroll to "Subscription" section
3. Select plan: Builder / Beta Tester / Early Access
4. Save changes

✅ **Result**: User has immediate access

**Alternative** (Stripe):
1. WordPress Admin → AuraReels → Users → View Stripe Customer
2. Click "Open in Stripe"
3. Create subscription in Stripe
4. Webhook auto-updates WordPress

---

### 3. Run Limited-Time Promotion (Black Friday)

**Duplicate existing plan**:

**WordPress Admin → AuraReels → Plans → Early Access → Duplicate**

1. **Basic Info**:
   - Name: Black Friday Early Access
   - Slug: black_friday_2026

2. **Pricing**:
   - Price: €49.50
   - Original Price: €99.00 (shows strikethrough)
   - Badge: "50% OFF" (red color)

3. **Availability Tab**:
   - Available from: 2026-11-24 00:00:00
   - Available until: 2026-12-01 23:59:59

4. **Create Stripe price** for tracking:
   - Create new price €49.50/month in Stripe
   - Add to plan

5. **Publish**

✅ **Result**:
- Auto-appears on Nov 24
- Auto-disappears on Dec 2
- Separate tracking in Stripe

---

### 4. Retire Plan (Keep Existing Users)

**Scenario**: Replace "Early Access" but keep current €99 subscribers

**WordPress Admin → Plans → Edit "Early Access"**

1. **Visibility Tab**:
   - Change visibility to: **Legacy**
   - Keep status: Active

2. **Save**

3. **Create replacement plan** (e.g., "Premium" at €149)

✅ **Result**:
- Current Early Access users: Keep €99/month forever
- New users: Cannot see Early Access
- Registration page: Shows new Premium plan

---

### 5. Custom Enterprise Pricing

**For specific client (e.g., Acme Corp)**:

**WordPress Admin → Plans → Add New**

1. **Basic Info**:
   - Name: Enterprise - Acme Corp
   - Slug: enterprise_acme_corp

2. **Pricing**:
   - Price: €999.00

3. **Visibility Tab** ⭐:
   - Visibility: **Hidden**
   - Allow self-registration: ❌ Unchecked

4. **Targeting Tab** (JSON):
   ```json
   {
     "specific_users": [123]
   }
   ```

5. **Create Stripe price**, add to plan, **Publish**

6. **Assign to user**:
   - Users → Edit User #123
   - Assign "Enterprise - Acme Corp"

✅ **Result**: Plan never appears publicly, only User #123 has it

---

## Plan Visibility Reference

| Visibility | Registration Page | Admin Can Assign | Existing Users Keep |
|------------|-------------------|------------------|---------------------|
| **public** | ✅ Shows | ✅ Yes | ✅ Yes |
| **admin_only** | ❌ Hidden | ✅ Yes | ✅ Yes |
| **legacy** | ❌ Hidden | ❌ No | ✅ Yes (grandfathered) |
| **hidden** | ❌ Hidden | ✅ Yes (manual only) | ✅ Yes |

---

## Subscription Status Reference

| Status | User Access | What It Means |
|--------|-------------|---------------|
| **active** | ✅ Full access | Payment successful, subscription active |
| **past_due** | ⚠️ 7-day grace | Payment failed, Stripe retrying |
| **canceled** | ✅ Until end date | User canceled, access until period ends |
| **incomplete** | ❌ No access | Payment never succeeded |
| **trialing** | ✅ Full access | Free trial period (not used in MVP) |

---

## Validation Checkpoints

Users are validated at **4 points**:

1. **Login**: Checked before JWT issued → Reject if inactive
2. **Dashboard Load**: Checked when dashboard mounts → Redirect to /subscribe
3. **Every 15 Minutes**: Background check → Logout with warning
4. **Critical Actions**: Video upload, AI features → 403 error if inactive

**Grace Period**: 7 days for `past_due` status (payment failures)

---

## API Endpoints Quick List

### For Frontend Developers

```
GET  /wp-json/aurareels/v1/subscriptions/available-plans
     → Get plans for registration page (public only)

POST /wp-json/aurareels/v1/subscriptions/create-checkout-session
     → Create Stripe Checkout session

GET  /wp-json/aurareels/v1/subscriptions/validate-subscription
     → Validate current user's subscription

POST /wp-json/aurareels/v1/subscriptions/create-portal-session
     → Get Stripe Customer Portal URL
```

### For Admin Panel

```
GET    /wp-json/aurareels/v1/admin/plans
       → List all plans (with filters)

POST   /wp-json/aurareels/v1/admin/plans
       → Create plan

PUT    /wp-json/aurareels/v1/admin/plans/{id}
       → Update plan

DELETE /wp-json/aurareels/v1/admin/plans/{id}
       → Delete plan (soft delete)

POST   /wp-json/aurareels/v1/admin/plans/{id}/duplicate
       → Duplicate plan

PUT    /wp-json/aurareels/v1/admin/users/{id}/subscription
       → Manually assign subscription to user
```

---

## Database Quick Queries

### Get all active public plans

```sql
SELECT plan_slug, plan_name, price, current_subscribers
FROM wp_aurareels_subscription_plans
WHERE status = 'active'
  AND visibility = 'public'
  AND allow_self_registration = 1
ORDER BY display_order ASC;
```

### Get user's subscription details

```sql
SELECT u.user_email, s.subscription_status, s.subscription_plan, s.subscription_ends_at
FROM wp_core_chavetas_shorts_helper_users s
JOIN wp_users u ON u.ID = s.wp_user_id
WHERE s.subscription_status = 'active';
```

### Count subscribers per plan

```sql
SELECT subscription_plan, COUNT(*) as total
FROM wp_core_chavetas_shorts_helper_users
WHERE subscription_status = 'active'
GROUP BY subscription_plan;
```

---

## Troubleshooting

### User paid but can't log in

**Check**:
1. WordPress → Tools → Stripe Webhook Logs
2. Verify webhook was received
3. Check user's `subscription_status` in database

**Fix**:
1. Verify Stripe subscription is active
2. Manually update user in WordPress Admin
3. Ask user to logout/login again

---

### Plan doesn't show on registration page

**Checklist**:
- [ ] Status = Active
- [ ] Visibility = Public
- [ ] Allow self-registration = Checked
- [ ] Available from date is in past (or NULL)
- [ ] Available until date is in future (or NULL)
- [ ] Max subscribers not reached

---

### User keeps getting logged out

**Possible causes**:
1. Stripe subscription in `past_due` (beyond 7-day grace)
2. Subscription canceled

**Fix**:
1. Check Stripe Dashboard
2. WordPress Admin → Users → Check subscription_status
3. If payment failed, update payment method in Stripe
4. If false positive, reset `validation_failures` counter

---

## Stripe Configuration

### Products to Create

```
1. AuraReels Builder
   Price: €1.99/month
   Price ID: price_xxx_builder_monthly

2. AuraReels Beta Tester
   Price: €2.99/month
   Price ID: price_xxx_beta_tester_monthly

3. AuraReels Early Access
   Price: €99/month
   Price ID: price_xxx_early_access_monthly
```

### Webhook Events

**Endpoint**: `https://yourdomain.com/wp-json/aurareels/v1/subscriptions/webhook`

**Events to enable**:
- `checkout.session.completed`
- `customer.subscription.created`
- `customer.subscription.updated`
- `customer.subscription.deleted`
- `invoice.payment_succeeded`
- `invoice.payment_failed`

---

## Frontend Integration Snippets

### Get available plans

```javascript
const { data: plans } = await getAvailablePlans();
// Returns only public, active plans
```

### Create checkout session

```javascript
const handleSubscribe = async (planSlug) => {
  const { url } = await createCheckoutSession({
    plan_slug: planSlug,
    success_url: `${window.location.origin}/dashboard`,
    cancel_url: `${window.location.origin}/register`
  });

  window.location.href = url;
};
```

### Validate subscription

```javascript
import { useSubscriptionValidation } from '@/hooks/useSubscriptionValidation';

function Dashboard() {
  const { isValid } = useSubscriptionValidation();

  if (!isValid) {
    return <SubscriptionExpiredBanner />;
  }

  return <DashboardContent />;
}
```

---

## What's NOT in MVP

❌ Credit purchasing UI
❌ Feature restrictions (all plans = full access)
❌ Annual billing
❌ Free trials
❌ Team subscriptions

**Database prepared** for future credits but no UI.

---

## Support Resources

**Full Documentation**: [SUBSCRIPTION-SYSTEM-COMPLETE.md](./SUBSCRIPTION-SYSTEM-COMPLETE.md)

**Key Sections**:
- Database schema details
- Complete API endpoint documentation
- Stripe integration guide
- Security & compliance
- Validation strategy deep dive

---

**Last Updated**: January 20, 2026
