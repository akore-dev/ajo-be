# Paystack Integration - Quick Reference

## One-Minute Setup

```bash
# 1. Add to .env
PAYSTACK_PUBLIC_KEY=pk_test_xxx
PAYSTACK_SECRET_KEY=sk_test_xxx
PAYSTACK_WEBHOOK_SECRET=whsec_xxx

# 2. Run migrations
php artisan migrate

# 3. You're done!
```

## API Quick Reference

### Initialize Payment
```bash
POST /api/wallet/fund
{
  "amount": 50000,              # in kobo (50000 = 500 NGN)
  "payment_gateway": "paystack"
}

# Response
{
  "payment_id": 1,
  "checkout_url": "https://checkout.paystack.com/...",
  "reference": "PAY-XXXXX"
}
```

### Verify Payment
```bash
GET /api/payments/verify?reference=PAY-XXXXX
# Returns: { status: "completed|pending|failed" }
```

### Check Status
```bash
GET /api/payments/PAY-XXXXX
# Returns: { status, amount, completed_at, failed_at }
```

## Test Cards

| Scenario | Card | Exp | CVV |
|----------|------|-----|-----|
| Success | 4084 0000 0000 0001 | 09/25 | 123 |
| Failure | 4111 1111 1111 1111 | 09/25 | 123 |

OTP: `123456`

## Files at a Glance

| File | Purpose |
|------|---------|
| `app/Services/PaystackService.php` | Core payment logic |
| `app/Http/Controllers/Api/PaymentController.php` | API endpoints |
| `app/Models/PaymentTransaction.php` | Payment records |
| `database/migrations/2026_10_01_000000_create_payment_transactions_table.php` | Schema |
| `config/paystack.php` | Configuration |

## Common Commands

```bash
# Run tests
php artisan test tests/Feature/PaymentIntegrationTest.php

# Check logs
tail -f storage/logs/laravel.log

# View payment in DB
php artisan tinker
> PaymentTransaction::latest()->first()

# Clear cache (after .env changes)
php artisan config:clear

# Check payment status
Payment::where('reference', 'PAY-XXX')->first()
```

## Debugging

```php
// In tinker
PaymentTransaction::latest()->first()->getAttributes()
PaymentTransaction::where('status', 'failed')->get()
PaymentTransaction::where('user_id', 1)->paginate()
```

## Webhook Events

Paystack sends these events:
- `charge.success` - Payment successful
- `charge.failed` - Payment failed
- `charge.dispute.create` - Dispute created
- `charge.dispute.resolve` - Dispute resolved

## Security Checklist

✅ Webhook signature verified (HMAC SHA512)  
✅ Amount validated against Paystack  
✅ User ownership verified  
✅ Idempotency key prevents duplicates  
✅ Database locking prevents race conditions  
✅ Transaction rolled back on errors  

## Useful Links

- **Get Keys:** https://dashboard.paystack.co/settings/api-keys
- **Webhooks:** https://dashboard.paystack.co/settings/webhooks
- **API Docs:** https://paystack.com/docs/api/
- **Test Cards:** https://paystack.com/docs/payments/test-authentication

## Payment States

```
pending ──→ verified ──→ completed
           ↓
           failed
           
abandoned (optional state)
```

## Error Messages

| Error | Cause | Solution |
|-------|-------|----------|
| Paystack API keys not configured | Missing env vars | Update .env |
| Invalid webhook signature | Wrong secret | Check dashboard |
| Amount mismatch | Tampered amount | Verify request |
| Unauthorized | Not payment owner | Check user_id |
| Wallet not credited | DB error | Check logs |

## Troubleshooting Commands

```bash
# Check if keys are set
php artisan tinker
> config('services.paystack.secret_key')

# View payment error
PaymentTransaction::where('status', 'failed')->latest()->first()->failure_reason

# Check wallet balance
Wallet::where('user_id', 1)->first()->balance

# View all payments for user
User::find(1)->paymentTransactions()->get()
```

## Payment Object Structure

```php
PaymentTransaction {
  id: 1
  user_id: 1
  wallet_id: 1
  reference: "PAY-XXXXX"
  status: "completed"
  amount: 50000
  currency: "NGN"
  authorization_code: "AUTH_XXX"
  verified_at: "2026-10-01 10:35:20"
  completed_at: "2026-10-01 10:35:25"
  gateway_response: {...}
}
```

## Request/Response Cycle

```
User
  ↓
POST /api/wallet/fund
  ↓ PaystackService::initializePayment()
  ↓ Calls Paystack API
  ↓
Returns: { checkout_url, reference }
  ↓
User redirected to checkout_url
  ↓ (User enters payment details)
  ↓
Webhook received OR user returns with reference
  ↓ PaystackService::verifyPayment()
  ↓ WalletService::credit()
  ↓
Wallet updated with balance
  ↓
Response: { status: "completed" }
```

## Rate Limiting (Optional)

Add to your routes:
```php
Route::post('/api/wallet/fund', [WalletController::class, 'fund'])
    ->middleware('throttle:5,1'); // 5 requests per minute

Route::get('/api/payments/verify', [PaymentController::class, 'verify'])
    ->middleware('throttle:10,1'); // 10 requests per minute
```

## Monitoring Dashboard Queries

```php
// Today's payments
PaymentTransaction::whereDate('created_at', today())->get()

// Completed today
PaymentTransaction::whereDate('created_at', today())
    ->where('status', 'completed')->sum('amount')

// Failed payments
PaymentTransaction::where('status', 'failed')->latest()->paginate()

// By user
User::find(1)->paymentTransactions()->get()

// Total volume
PaymentTransaction::where('status', 'completed')->sum('amount')
```

## Need Help?

1. Check `PAYSTACK_SETUP.md` - Setup issues
2. Check `PAYSTACK_INTEGRATION.md` - Technical details
3. Check logs - `storage/logs/laravel.log`
4. Paystack dashboard - Webhook logs, transaction history
5. Read `PaystackService.php` - See actual implementation

---

**Last Updated:** October 1, 2026
