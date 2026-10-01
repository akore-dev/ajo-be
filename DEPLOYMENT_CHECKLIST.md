# Paystack Integration - Deployment Checklist

## Pre-Deployment Verification

### ✅ Code Quality
- [ ] All files created successfully
- [ ] No syntax errors in PHP files
- [ ] No missing imports or namespaces
- [ ] All routes properly registered
- [ ] All models properly defined
- [ ] Service provider properly registered

### ✅ Configuration
- [ ] `.env` has Paystack credentials (test keys)
- [ ] `config/paystack.php` created
- [ ] `config/services.php` updated
- [ ] Webhook secret configured

### ✅ Database
- [ ] Migration file created
- [ ] Migration not yet run (ready to deploy)
- [ ] Table schema correct
- [ ] Indexes properly defined
- [ ] Soft deletes enabled

### ✅ Documentation
- [ ] `PAYSTACK_SETUP.md` complete
- [ ] `PAYSTACK_INTEGRATION.md` complete
- [ ] `PAYSTACK_QUICK_REFERENCE.md` complete
- [ ] `PAYSTACK_IMPLEMENTATION_SUMMARY.md` complete
- [ ] This checklist complete

### ✅ Testing
- [ ] Test file created: `PaymentIntegrationTest.php`
- [ ] 11 test cases included
- [ ] Mock implementations ready
- [ ] All edge cases covered

---

## Local Development Setup (5 minutes)

### Step 1: Configure Environment
```bash
# Edit .env with test credentials
PAYSTACK_PUBLIC_KEY=pk_test_your_key
PAYSTACK_SECRET_KEY=sk_test_your_secret
PAYSTACK_WEBHOOK_SECRET=whsec_test_secret
```

### Step 2: Run Migrations
```bash
php artisan migrate
```

Expected output:
```
Migrating: 2026_10_01_000000_create_payment_transactions_table
Migrated: 2026_10_01_000000_create_payment_transactions_table (XXXms)
```

### Step 3: Verify Installation
```bash
php artisan tinker
> PaymentTransaction::count()
0
```

Should return 0 (no payments yet).

### Step 4: Run Tests
```bash
php artisan test tests/Feature/PaymentIntegrationTest.php
```

Expected result:
```
PASS  tests/Feature/PaymentIntegrationTest.php
✓ user can initialize payment
✓ same idempotency key returns same payment
✓ user can verify payment
... (11 tests total)
```

### Step 5: Test Payment Flow Manually
```bash
# Start local server
php artisan serve

# In another terminal, test API
curl -X POST http://localhost:8000/api/wallet/fund \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"amount": 50000, "payment_gateway": "paystack"}'
```

---

## Staging Deployment

### Pre-Deployment
- [ ] Code pushed to staging branch
- [ ] All files present on staging
- [ ] No merge conflicts
- [ ] CI/CD pipeline passed (if applicable)

### Deployment Steps
```bash
# On staging server
cd /path/to/project

# Pull latest code
git pull origin staging

# Install dependencies (if needed)
composer install

# Run migrations
php artisan migrate

# Clear caches
php artisan config:clear
php artisan cache:clear

# Run tests
php artisan test tests/Feature/PaymentIntegrationTest.php
```

### Post-Deployment Verification
- [ ] Application loads without errors
- [ ] Database has payment_transactions table
- [ ] Routes are accessible
- [ ] Logs show no errors

### Webhook Setup
1. Get staging webhook URL from team
2. Add to Paystack dashboard > Settings > Webhooks
3. Copy webhook signing secret
4. Add to `.env`: `PAYSTACK_WEBHOOK_SECRET=whsec_staging_xxx`
5. Clear config cache: `php artisan config:clear`

### Testing on Staging
- [ ] Initialize payment with test card
- [ ] Complete payment on Paystack checkout
- [ ] Verify payment via API
- [ ] Check wallet is credited
- [ ] Check logs for errors
- [ ] Verify webhook was received (Paystack dashboard)

---

## Production Deployment

### 1 Week Before
- [ ] Get production Paystack credentials (pk_live_*, sk_live_*)
- [ ] Set up production Paystack account (if new)
- [ ] Plan maintenance window (if needed)
- [ ] Backup database (if applicable)
- [ ] Prepare rollback plan

### 24 Hours Before
- [ ] Final staging tests
- [ ] Brief team on changes
- [ ] Prepare deployment script
- [ ] Verify backups
- [ ] Check monitoring/alerting

### Deployment Day

#### Step 1: Database Backup
```bash
# Create backup before any changes
php artisan backup:run
```

#### Step 2: Deploy Code
```bash
cd /path/to/production
git pull origin main  # or master
composer install --no-dev
npm run build  # if applicable
```

#### Step 3: Update Configuration
```bash
# Update .env with PRODUCTION credentials
PAYSTACK_PUBLIC_KEY=pk_live_xxx
PAYSTACK_SECRET_KEY=sk_live_xxx
PAYSTACK_WEBHOOK_SECRET=whsec_live_xxx
APP_DEBUG=false
APP_ENV=production
```

#### Step 4: Run Migrations
```bash
php artisan migrate --force
```

#### Step 5: Clear Caches
```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:cache  # optional, for performance
```

#### Step 6: Health Check
```bash
curl https://your-domain.com/api/health
# Should return 200 OK
```

#### Step 7: Verify Payment System
```bash
# Check routes are registered
php artisan route:list | grep payment

# Check database table exists
php artisan tinker
> Schema::hasTable('payment_transactions')
true
```

### Post-Deployment (First Hour)

- [ ] Monitor application logs for errors
- [ ] Watch error tracking (Sentry, etc.)
- [ ] Check payment transaction table remains empty
- [ ] Verify webhooks are configured correctly
- [ ] Test payment flow end-to-end (optional small amount)

### Post-Deployment (24 Hours)

- [ ] Monitor for any payment-related errors
- [ ] Check webhook delivery success rate
- [ ] Review payment transaction logs
- [ ] Verify wallet balances are correct
- [ ] Get stakeholder sign-off

---

## Rollback Plan

If issues occur:

### Option 1: Rollback Code
```bash
# Revert to previous version
git revert HEAD
git push origin main

# Clear caches
php artisan config:clear
```

### Option 2: Disable Payment Feature
```bash
# In PaymentController::verify()
// return $this->error('Feature temporarily disabled', 500);

# Or disable routes
Route::middleware('disabled_feature')->group(function () {
    // payment routes
});
```

### Option 3: Database Rollback
```bash
# Rollback migrations
php artisan migrate:rollback

# Or restore from backup
php artisan backup:restore
```

---

## Monitoring & Alerting

### Set Up Alerts For

✅ **Payment Failures**
```
Any PaymentTransaction with status = 'failed'
Alert on: 5+ failures in 1 hour
```

✅ **Webhook Failures**
```
Log entry: "Webhook processing error"
Alert on: Any occurrence
```

✅ **Signature Failures**
```
Log entry: "Invalid webhook signature"
Alert on: Any occurrence
```

✅ **Database Errors**
```
Log entry: "Failed to credit wallet"
Alert on: Any occurrence
```

### Daily Checks

```bash
# Check payment volume
tail -100 storage/logs/laravel.log | grep "Payment"

# Check failed payments
php artisan tinker
> PaymentTransaction::where('status', 'failed')->count()

# Check completed today
> PaymentTransaction::whereDate('created_at', today())->where('status', 'completed')->sum('amount')
```

---

## Documentation Handoff

Provide the following to the team:

1. **Setup Guide:** `PAYSTACK_SETUP.md`
2. **Technical Docs:** `PAYSTACK_INTEGRATION.md`
3. **Quick Reference:** `PAYSTACK_QUICK_REFERENCE.md`
4. **Implementation Summary:** `PAYSTACK_IMPLEMENTATION_SUMMARY.md`
5. **This Checklist:** `DEPLOYMENT_CHECKLIST.md`

### Team Access Needed

- [ ] Paystack Dashboard access (for admin)
- [ ] Production server access (for devops)
- [ ] GitHub repo access (for developers)
- [ ] Error tracking access (Sentry, etc.)
- [ ] Monitoring/alerting access (DataDog, etc.)

---

## Success Criteria

### Deployment Success
- ✅ No errors during migration
- ✅ Payment routes are accessible
- ✅ Database table created with correct schema
- ✅ All environment variables configured
- ✅ Logs show no critical errors
- ✅ Health check returns 200 OK

### Functional Success
- ✅ User can initialize payment
- ✅ Paystack checkout URL is correct
- ✅ Payment can be verified
- ✅ Wallet is credited on success
- ✅ Failed payments are properly handled
- ✅ Webhook receives and processes events

### Security Success
- ✅ Webhook signature verification works
- ✅ User authorization verified
- ✅ Amount validation in place
- ✅ Idempotency prevents duplicates
- ✅ Database transactions are atomic
- ✅ Logs don't expose sensitive data

---

## Timeline

| Phase | Duration | Tasks |
|-------|----------|-------|
| Development | Complete | All code implemented ✅ |
| Local Testing | 1 hour | Run tests, verify locally |
| Staging Deploy | 30 min | Deploy code, run migrations |
| Staging Testing | 4 hours | Full payment flow testing |
| Production Deploy | 30 min | Deploy, migrate, verify |
| Production Testing | 2 hours | End-to-end test, monitoring |
| Post-Deployment | 24 hours | Monitor, verify, sign-off |

---

## Support Contacts

- **Paystack Support:** support@paystack.com
- **API Docs:** https://paystack.com/docs/api/
- **Status Page:** https://status.paystack.com/
- **Community:** https://paystack.com/docs/

---

## Sign-Off

- [ ] Development Lead: _________________ Date: _____
- [ ] QA Lead: _________________ Date: _____
- [ ] DevOps/Infrastructure: _________________ Date: _____
- [ ] Product Manager: _________________ Date: _____

---

**Last Updated:** October 1, 2026  
**Deployment Status:** Ready for Production ✅
