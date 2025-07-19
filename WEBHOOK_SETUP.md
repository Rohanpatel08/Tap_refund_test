# Tap Webhook Setup Guide

This guide explains how to set up and use the Tap payment webhook functionality for handling refund notifications.

## Overview

The webhook system automatically handles the following Tap events:
- `refund.created` - When a refund is initiated
- `refund.updated` - When a refund status changes
- `refund.succeeded` - When a refund is successfully processed
- `refund.failed` - When a refund fails
- `charge.updated` - When a payment status changes

## Setup

### 1. Environment Configuration

Add the following to your `.env` file:

```env
TAP_SECRET_KEY=your_tap_secret_key
TAP_PUBLIC_KEY=your_tap_public_key
TAP_BASE_URL=https://api.tap.company/v2
TAP_WEBHOOK_SECRET=your_webhook_secret_from_tap
```

### 2. Webhook URL

Configure this URL in your Tap dashboard:
```
https://yourdomain.com/api/tap/webhook
```

### 3. Database Setup

Make sure you've run the migrations:
```bash
php artisan migrate
```

## Features

### Webhook Controller (`TapWebhookController`)

- **Signature Verification**: Automatically verifies webhook signatures using HMAC-SHA256
- **Event Handling**: Processes different types of webhook events
- **Database Updates**: Automatically updates refund and payment statuses
- **Comprehensive Logging**: Logs all webhook events for debugging

### Models

#### Payment Model
- Relationship with refunds
- Helper methods for refund calculations
- Casts for decimal amounts and JSON responses

#### Refund Model  
- Relationship with payments
- Status helper methods (`isSucceeded()`, `isPending()`, `isFailed()`)
- Proper casting for amounts and JSON data

### Security

- **CSRF Exemption**: Webhook endpoint is excluded from CSRF protection
- **Signature Verification**: All webhooks are verified using the configured secret
- **Optional Middleware**: `VerifyTapWebhook` middleware for additional security

## Usage

### Processing Refunds

Use the existing `TapRefundController` to process refunds:

```php
// The processRefund method will:
// 1. Create a refund via Tap API
// 2. Store the refund in your database
// 3. Tap will send webhook notifications for status updates
```

### Webhook Events

The webhook will automatically:

1. **Update refund status** when Tap sends notifications
2. **Create refund records** if they don't exist
3. **Log all activities** for audit purposes
4. **Handle errors gracefully** and return appropriate HTTP status codes

### Testing

Run the webhook tests:

```bash
php artisan test tests/Feature/TapWebhookTest.php
```

### Monitoring

Check your Laravel logs for webhook activities:

```bash
tail -f storage/logs/laravel.log | grep "Tap webhook"
```

## Error Handling

The webhook system handles:
- Invalid signatures (returns 401)
- Missing data (returns 400)
- Unknown event types (returns 200 with message)
- Database errors (returns 500)
- Missing webhook secret configuration

## Best Practices

1. **Always verify webhook signatures** in production
2. **Monitor webhook logs** for any issues
3. **Handle duplicate webhooks** gracefully (idempotency)
4. **Set up proper error alerting** for failed webhooks
5. **Test webhook endpoints** before going live

## Debugging

### Common Issues

1. **Signature Verification Fails**
   - Check `TAP_WEBHOOK_SECRET` configuration
   - Verify the webhook URL in Tap dashboard
   - Check request headers and payload format

2. **Webhook Not Received**
   - Verify the webhook URL is accessible
   - Check firewall settings
   - Ensure HTTPS is properly configured

3. **Database Updates Not Working**
   - Check if records exist in the database
   - Verify foreign key relationships
   - Check Laravel logs for errors

### Testing Webhook Locally

Use tools like ngrok to expose your local development server:

```bash
ngrok http 8000
# Use the ngrok URL in Tap dashboard: https://abc123.ngrok.io/api/tap/webhook
```

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/tap/webhook` | Receive webhook notifications from Tap |

## Response Codes

| Code | Description |
|------|-------------|
| 200 | Webhook processed successfully |
| 400 | Missing required data |
| 401 | Invalid signature |
| 500 | Internal server error |