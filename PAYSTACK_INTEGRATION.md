# Paystack Payment Integration Documentation

## Overview

This document outlines the complete Paystack payment integration for the Ajo ROSCA platform. The system handles payment initialization, verification, and wallet funding through Paystack's payment gateway.

## Architecture

### Database Schema

**payment_transactions** table tracks all payment attempts:
- `reference` - Unique Paystack reference ID
- `access_code` - Paystack access code for tracking
- `authorization_code` - Code received after successful authorization
- `status` - Transaction status (pending, processing, completed, failed, abandoned)
- `amount` - Payment amount in lowest denomination (kobo)
- `idempotency_key` - Prevents duplicate payments
- `gateway_response` - Full response from Paystack API
- Soft deletes enabled for audit trails

### Service Layer

**PaystackService** (`app/Services/PaystackService.php`):
- `initializePayment()` - Start payment checkout with Paystack
- `verifyPayment()` - Verify payment completion
- `verifyWebhookSignature()` - Validate webhook authenticity
- `handleWebhookEvent()` - Process Paystack events

### Controllers

**WalletController**:
- `fund()` - Initialize payment with Paystack (replaces mock implementation)

**PaymentController**:
- `verify()` - User-initiated payment verification
- `status()` - Check payment status
- `webhook()` - Paystack webhook endpoint (unsigned, validates signature internally)

## Configuration

### Environment Variables

Add to `.env`:
```env
PAYSTACK_PUBLIC_KEY=pk_live_xxx         # Your Paystack public key
PAYSTACK_SECRET_KEY=sk_live_xxx         # Your Paystack secret key
PAYSTACK_WEBHOOK_SECRET=whsec_xxx       # Webhook signing secret
```

Get credentials from: https://dashboard.paystack.co/settings/api-keys

### Webhook Setup

1. Go to https://dashboard.paystack.co/settings/webhooks
2. Add webhook URL: `https://your-domain.com/api/webhooks/paystack`
3. Copy the webhook signing secret to `PAYSTACK_WEBHOOK_SECRET`

## Payment Flow

### 1. User Initiates Funding

```
POST /api/wallet/fund
Authorization: Bearer {token}
Content-Type: application/json

{
    "amount": 50000,                    // Amount in kobo (500 NGN)
    "payment_gateway": "paystack",
    "callback_url": "https://app.example.com/payment/verify"
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "payment_id": 1,
        "reference": "PAY-ABCD1234EFGH5678",
        "access_code": "8ulpvz3q",
        "checkout_url": "https://checkout.paystack.com/...",
        "amount": 50000,
        "currency": "NGN"
    },
    "message": "Payment checkout initialized."
}
```

### 2. User Completes Payment

- User is redirected to `checkout_url`
- Completes payment on Paystack
- Redirected back to `callback_url` with `reference` query parameter

### 3. Payment Verification (Client-Initiated)

```
GET /api/payments/verify?reference=PAY-ABCD1234EFGH5678
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "payment_id": 1,
        "reference": "PAY-ABCD1234EFGH5678",
        "status": "completed",
        "amount": 50000,
        "completed_at": "2026-10-01T10:30:45Z"
    },
    "message": "Payment verified successfully."
}
```

### 4. Automatic Processing (Webhook)

- Paystack sends `charge.success` webhook
- Webhook signature is verified
- Payment status is confirmed
- Wallet is credited automatically

## Payment Statuses

| Status | Description |
|--------|-------------|
| `pending` | Payment initialized, awaiting user action |
| `processing` | Payment is being processed |
| `completed` | Payment successful, wallet credited |
| `failed` | Payment failed, wallet not credited |
| `abandoned` | User abandoned payment (not yet implemented) |

## Security Features

### 1. Webhook Signature Verification
```php
// PaystackService::verifyWebhookSignature()
$hash = hash_hmac('sha512', $body, $webhookSecret);
return hash_equals($hash, $signature);
```

### 2. Idempotency
- Prevents duplicate payments using `idempotency_key`
- Same key returns existing payment instead of creating new one

### 3. Amount Verification
- System verifies amount matches between request and Paystack response
- Prevents amount tampering

### 4. Ownership Verification
- Payment endpoints verify user ownership
- Only payment creator can verify or check status

### 5. Transaction Locking
- Database transactions ensure atomic operations
- Wallet balance is locked during updates

## Error Handling

### Payment Initialization Errors

```json
{
    "success": false,
    "message": "Failed to initialize payment. Please try again.",
    "code": 500
}
```

Common causes:
- Invalid Paystack credentials
- Network connectivity issues
- Invalid email address

### Payment Verification Errors

```json
{
    "success": false,
    "message": "Payment verification failed. {error_detail}",
    "code": 422
}
```

### Webhook Errors

- Invalid signature: Returns `401 Unauthorized`
- Invalid payload: Returns `400 Bad Request`
- Processing errors: Returns `200 OK` (to prevent Paystack retries)

## Testing

### Test Credentials
Use Paystack test keys (pk_test_*, sk_test_*) for testing.

### Test Cards
Paystack provides test cards:
- **Successful**: 4084 0000 0000 0001, exp: any future date, CVV: any 3 digits
- **Failed**: 4111 1111 1111 1111

### Manual Webhook Testing
```bash
curl -X POST http://localhost:8000/api/webhooks/paystack \
  -H "Content-Type: application/json" \
  -H "X-Paystack-Signature: {signature}" \
  -d '{
    "event": "charge.success",
    "data": {
      "id": 12345,
      "reference": "PAY-ABCD1234EFGH5678",
      "amount": 50000,
      "status": "success",
      "authorization": {
        "authorization_code": "AUTH_xxx",
        "bin": "408400",
        "last4": "0001"
      }
    }
  }'
```

## Monitoring & Logging

All payment events are logged to `storage/logs/laravel.log`:

```
[2026-10-01 10:30:45] local.INFO: Payment initialized successfully {
  "payment_id": 1,
  "reference": "PAY-ABCD1234EFGH5678"
}

[2026-10-01 10:35:20] local.INFO: Payment verified successfully {
  "reference": "PAY-ABCD1234EFGH5678",
  "payment_id": 1
}

[2026-10-01 10:35:25] local.INFO: Payment processed and wallet credited {
  "payment_id": 1,
  "reference": "PAY-ABCD1234EFGH5678",
  "amount": 50000
}
```

## Running Migrations

After deploying:

```bash
php artisan migrate
```

This creates the `payment_transactions` table.

## Troubleshooting

### Issue: Webhook not being received

**Solution:**
1. Verify webhook URL is publicly accessible
2. Check webhook secret is correctly configured
3. Look at Paystack dashboard webhook logs
4. Ensure HTTPS is used

### Issue: Payment verified but wallet not credited

**Solution:**
1. Check logs for wallet credit errors
2. Verify wallet status is 'active'
3. Check user has a wallet created
4. Ensure database transactions are working

### Issue: Duplicate payments

**Solution:**
1. Idempotency key prevents client-side duplicates
2. Check payment_transactions for existing reference
3. Implement client-side debouncing on submit button

### Issue: Signature verification fails

**Solution:**
1. Confirm webhook secret matches Paystack dashboard
2. Check raw request body is being used (not parsed)
3. Verify webhook was sent by Paystack using IP whitelist

## Future Enhancements

1. **Recurring Payments** - For subscription-based contributions
2. **Split Payments** - Distribute payments between platform and creators
3. **Refunds** - Handle payment refunds via Paystack API
4. **Card Management** - Save authorization for future payments
5. **Analytics Dashboard** - Track payment metrics and trends
6. **Retry Logic** - Automatic retry for failed transactions
7. **Multi-Currency** - Support other currencies besides NGN
8. **Webhook Retries** - Implement idempotent webhook handling

## Support

For Paystack API issues, refer to: https://paystack.com/docs/api/
For bugs or questions, check logs and contact the development team.
