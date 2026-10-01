# Paystack Integration Setup Guide

## Quick Start (5 minutes)

### 1. Get Paystack Credentials

1. Sign up/login at https://paystack.com/
2. Go to **Dashboard > Settings > API Keys**
3. Copy your test keys (for development):
   - Public Key: `pk_test_xxxxx`
   - Secret Key: `sk_test_xxxxx`

### 2. Configure Environment

Edit `.env`:
```env
PAYSTACK_PUBLIC_KEY=pk_test_your_key_here
PAYSTACK_SECRET_KEY=sk_test_your_key_here
PAYSTACK_WEBHOOK_SECRET=whsec_test_xxx_here
```

Get webhook secret from **Settings > Webhooks** in Paystack dashboard.

### 3. Run Migration

```bash
php artisan migrate
```

This creates the `payment_transactions` table.

### 4. Test Locally with Ngrok (Optional)

If you need to test webhooks locally:

```bash
ngrok http 8000
# Copy the https URL and add to Paystack webhook settings
```

Then update `.env`:
```env
APP_URL=https://your-ngrok-url.ngrok.io
```

## API Endpoints

### Initialize Payment
```bash
curl -X POST http://localhost:8000/api/wallet/fund \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 50000,
    "payment_gateway": "paystack"
  }'
```

Response includes `checkout_url` where user completes payment.

### Verify Payment
```bash
curl -X GET "http://localhost:8000/api/payments/verify?reference=PAY-XXXXX" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Check Payment Status
```bash
curl -X GET http://localhost:8000/api/payments/PAY-XXXXX \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## Testing Payments

### Using Paystack Test Cards

**Successful Payment:**
- Card: `4084 0000 0000 0001`
- Exp: Any future date (e.g., `09/25`)
- CVV: Any 3 digits (e.g., `123`)
- Amount: Any amount
- OTP: `123456`

**Failed Payment:**
- Card: `4111 1111 1111 1111`
- Will fail with "Insufficient funds" error

### Payment Flow

1. **POST** `/api/wallet/fund`
   - Get `checkout_url`
   
2. **Redirect** user to `checkout_url`
   - User enters payment details
   - User completes OTP verification
   
3. **Redirect** back to app with `reference` parameter
   - Or wait for webhook event
   
4. **Verify** payment:
   - Client: `GET /api/payments/verify?reference=XXX`
   - Or automatic via webhook

## Webhook Configuration

### 1. In Paystack Dashboard

1. Go to **Settings > Webhooks**
2. Add webhook URL:
   ```
   https://your-domain.com/api/webhooks/paystack
   ```
3. Copy the webhook signing secret
4. Add to `.env`:
   ```env
   PAYSTACK_WEBHOOK_SECRET=whsec_xxx
   ```

### 2. Test Webhook Locally

Use Paystack dashboard to send test webhook or use curl:

```bash
# Generate signature
SECRET="your_webhook_secret"
BODY='{"event":"charge.success","data":{"reference":"PAY-TEST","amount":50000}}'
SIGNATURE=$(echo -n "$BODY" | openssl dgst -sha512 -hmac "$SECRET" -hex | cut -d' ' -f2)

curl -X POST http://localhost:8000/api/webhooks/paystack \
  -H "Content-Type: application/json" \
  -H "X-Paystack-Signature: $SIGNATURE" \
  -d "$BODY"
```

## Troubleshooting

### Error: "Paystack API keys not configured"

**Solution:** Check `.env` has `PAYSTACK_SECRET_KEY` and `PAYSTACK_PUBLIC_KEY`

### Error: "Invalid webhook signature"

**Solution:** 
- Verify webhook secret matches Paystack dashboard
- Use raw request body, not parsed JSON
- Check timezone is correct

### Payment initialized but never verified

**Solution:**
1. Check Paystack dashboard for payment record
2. Look at logs: `tail -f storage/logs/laravel.log`
3. Manually verify: `GET /api/payments/verify?reference=PAY-XXX`
4. Check webhook was received: Paystack > Webhooks > Logs

### Wallet not credited after verification

**Solution:**
1. Check logs for wallet credit errors
2. Verify wallet exists: `GET /api/wallet`
3. Check payment status: `GET /api/payments/{reference}`
4. Ensure database migration ran: `php artisan migrate`

## Development Commands

### Check Payment Status in DB

```bash
php artisan tinker
```

Then:
```php
PaymentTransaction::latest()->first();
```

### View Logs

```bash
# Real-time
tail -f storage/logs/laravel.log

# Search for payment events
grep -i "payment" storage/logs/laravel.log

# Last 50 lines
tail -50 storage/logs/laravel.log
```

### Clear Cache (after env changes)

```bash
php artisan config:clear
php artisan cache:clear
```

## Production Checklist

- [ ] Use production Paystack keys (`pk_live_*`, `sk_live_*`)
- [ ] Update webhook URL to production domain
- [ ] Get production webhook secret
- [ ] Set `APP_DEBUG=false` in `.env`
- [ ] Test payment flow end-to-end
- [ ] Monitor webhook logs in Paystack dashboard
- [ ] Set up alerts for failed payments
- [ ] Test with real payment amounts
- [ ] Verify wallet credit happens correctly
- [ ] Set up monitoring/logs aggregation

## File Structure

```
app/
├── Models/
│   └── PaymentTransaction.php         # New model
├── Services/
│   └── PaystackService.php            # Core service
└── Http/Controllers/Api/
    ├── WalletController.php           # Updated
    └── PaymentController.php           # New controller

config/
├── paystack.php                        # New config
└── services.php                        # Updated

database/migrations/
└── 2026_10_01_000000_create_payment_transactions_table.php  # New

tests/Feature/
└── PaymentIntegrationTest.php          # Test suite

PAYSTACK_INTEGRATION.md                 # Full documentation
PAYSTACK_SETUP.md                       # This file
```

## Next Steps

1. ✅ Configure `.env` with Paystack keys
2. ✅ Run migrations: `php artisan migrate`
3. ✅ Test locally with test cards
4. ✅ Set up ngrok for webhook testing (optional)
5. ✅ Run test suite: `php artisan test`
6. ✅ Deploy to staging
7. ✅ Get production keys when ready
8. ✅ Deploy to production

## Support

- **Paystack Docs:** https://paystack.com/docs/
- **API Reference:** https://paystack.com/docs/api/
- **Support Email:** support@paystack.com
- **Dashboard:** https://dashboard.paystack.co/

For integration issues, check:
1. Logs: `storage/logs/laravel.log`
2. Paystack dashboard events/webhooks
3. Database: `payment_transactions` table
4. Network requests in browser dev tools
