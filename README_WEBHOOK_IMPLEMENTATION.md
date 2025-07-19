# Tap Payment Gateway Webhook Implementation

This document explains how to implement webhooks for the refund flow from Tap payment gateway without modifying existing controller files.

## Overview

The webhook implementation follows a clean architecture approach that integrates seamlessly with your existing refund infrastructure:

- **Dedicated Controller**: `TapWebhookController` handles all webhook requests
- **Service Layer**: `TapWebhookService` processes webhook logic and verification
- **Queue Processing**: `ProcessTapRefundWebhook` job handles heavy operations asynchronously
- **Notifications**: `RefundStatusUpdated` sends email notifications to customers

## Installation Steps

### 1. Environment Configuration

Add the following variables to your `.env` file:

```env
# Tap Payment Gateway Configuration
TAP_SECRET_KEY=your_tap_secret_key_here
TAP_PUBLIC_KEY=your_tap_public_key_here
TAP_BASE_URL=https://api.tap.company/v2/
TAP_WEBHOOK_SECRET=your_webhook_secret_here
```

### 2. Queue Configuration

Set up your queue system (recommended for production):

```env
QUEUE_CONNECTION=database
```

Run the following commands to set up queue tables:

```bash
php artisan queue:table
php artisan migrate
```

### 3. Webhook Endpoints

The implementation provides the following webhook endpoints:

- **Refund Webhook**: `POST /api/webhooks/tap/refunds`
- **Payment Webhook**: `POST /api/webhooks/tap/payments` (for future use)

### 4. Configure Tap Dashboard

In your Tap payment gateway dashboard:

1. Go to Webhooks settings
2. Add webhook endpoint: `https://yourdomain.com/api/webhooks/tap/refunds`
3. Select the following events:
   - `refund.created`
   - `refund.updated`
   - `refund.succeeded`
   - `refund.failed`
4. Set the webhook secret (use the same value as `TAP_WEBHOOK_SECRET`)

## Features

### Security
- **Signature Verification**: All webhooks are verified using HMAC SHA256
- **CSRF Exemption**: Webhook routes are automatically exempted from CSRF verification
- **IP Validation**: Optional IP whitelisting for additional security

### Reliability
- **Queue Processing**: Heavy operations are processed asynchronously
- **Retry Logic**: Failed jobs are retried up to 3 times
- **Comprehensive Logging**: All webhook activities are logged for debugging

### Integration
- **Non-Intrusive**: No changes to existing controllers required
- **Database Integration**: Works with existing `TapRefund` and `Payment` models
- **Notification System**: Automatic email notifications to customers

## Webhook Processing Flow

1. **Webhook Received**: Tap sends webhook to your endpoint
2. **Signature Verification**: System verifies webhook authenticity
3. **Quick Response**: Returns 200 status immediately to Tap
4. **Queue Processing**: Heavy operations are queued for background processing
5. **Data Update**: Refund status is updated in database
6. **Customer Notification**: Email notification sent to customer
7. **Payment Status Update**: Original payment status updated if needed

## Supported Webhook Events

### Refund Events
- `refund.created`: New refund created
- `refund.updated`: Refund information updated
- `refund.succeeded`: Refund processed successfully
- `refund.failed`: Refund processing failed

## Usage Examples

### Testing Webhook Locally

Use tools like ngrok to expose your local development server:

```bash
ngrok http 8000
```

Then use the ngrok URL in Tap dashboard: `https://your-ngrok-url.ngrok.io/api/webhooks/tap/refunds`

### Manual Webhook Testing

You can test the webhook endpoint using curl:

```bash
curl -X POST https://yourdomain.com/api/webhooks/tap/refunds \
  -H "Content-Type: application/json" \
  -H "X-Tap-Signature: your_calculated_signature" \
  -d '{
    "object": "refund",
    "event": "refund.succeeded",
    "data": {
      "id": "ref_test_123",
      "charge": {"id": "chg_test_123"},
      "amount": 1000,
      "currency": "USD",
      "status": "succeeded"
    }
  }'
```

### Queue Worker

In production, run the queue worker to process webhook jobs:

```bash
php artisan queue:work --tries=3 --timeout=60
```

## Monitoring and Debugging

### Log Files

All webhook activities are logged in Laravel's log files:

- Webhook reception: `storage/logs/laravel.log`
- Job processing: Queue job logs
- Notification sending: Mail logs

### Database Records

Monitor webhook processing through database:

```sql
-- Check recent refunds
SELECT * FROM tap_refunds ORDER BY created_at DESC LIMIT 10;

-- Check failed jobs
SELECT * FROM failed_jobs WHERE payload LIKE '%ProcessTapRefundWebhook%';
```

## Error Handling

### Common Issues and Solutions

1. **Invalid Signature**
   - Check `TAP_WEBHOOK_SECRET` configuration
   - Verify Tap dashboard webhook secret matches

2. **Queue Jobs Failing**
   - Check database connectivity
   - Verify model relationships
   - Review job logs

3. **Notifications Not Sending**
   - Check mail configuration
   - Verify customer email addresses
   - Review notification logs

### Troubleshooting Commands

```bash
# Check queue status
php artisan queue:work --once

# Process failed jobs
php artisan queue:retry all

# Clear application cache
php artisan cache:clear
php artisan config:clear
```

## Security Best Practices

1. **Always verify webhook signatures**
2. **Use HTTPS in production**
3. **Implement rate limiting for webhook endpoints**
4. **Monitor webhook logs for suspicious activity**
5. **Keep webhook secrets secure and rotate regularly**

## Extending the Implementation

### Adding New Webhook Events

To handle additional webhook events:

1. Add event handling in `TapWebhookService::processRefundWebhook()`
2. Update `ProcessTapRefundWebhook` job if needed
3. Add new notification types if required

### Custom Business Logic

Add your business logic in the job handlers:

```php
// In ProcessTapRefundWebhook::handleRefundSucceeded()
if ($refund->type === 'full') {
    // Cancel related orders
    // Update inventory
    // Send special notifications
}
```

## Performance Considerations

- **Queue Processing**: Use Redis or database queues for better performance
- **Batch Processing**: Consider batching multiple webhook events
- **Database Indexing**: Ensure proper indexes on `refund_id` and `charge_id`
- **Caching**: Cache frequently accessed data

## Support

For issues or questions:

1. Check Laravel logs: `storage/logs/laravel.log`
2. Review Tap documentation: [Tap Webhooks Documentation](https://developers.tap.company/reference/webhooks)
3. Monitor queue jobs: `php artisan queue:work --verbose`

---

This implementation provides a robust, scalable webhook system that integrates seamlessly with your existing codebase while maintaining separation of concerns and following Laravel best practices.