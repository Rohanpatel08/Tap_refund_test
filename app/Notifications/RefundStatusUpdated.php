<?php

namespace App\Notifications;

use App\Models\TapRefund;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RefundStatusUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    private TapRefund $refund;
    private string $status;

    /**
     * Create a new notification instance.
     */
    public function __construct(TapRefund $refund, string $status)
    {
        $this->refund = $refund;
        $this->status = $status;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = new MailMessage();

        switch ($this->status) {
            case 'succeeded':
            case 'refunded':
                return $message
                    ->subject('Refund Processed Successfully')
                    ->greeting('Good news!')
                    ->line('Your refund has been processed successfully.')
                    ->line('Refund ID: ' . $this->refund->refund_id)
                    ->line('Amount: ' . $this->formatAmount($this->refund->amount, $this->refund->currency))
                    ->line('The refunded amount will appear in your account within 3-5 business days.')
                    ->line('If you have any questions, please contact our support team.');

            case 'failed':
                return $message
                    ->subject('Refund Processing Failed')
                    ->greeting('Refund Update')
                    ->line('Unfortunately, your refund could not be processed.')
                    ->line('Refund ID: ' . $this->refund->refund_id)
                    ->line('Amount: ' . $this->formatAmount($this->refund->amount, $this->refund->currency))
                    ->line('Reason: ' . ($this->refund->reason ?? 'Please contact support for more details'))
                    ->line('Please contact our support team for assistance.')
                    ->action('Contact Support', url('/support'));

            default:
                return $message
                    ->subject('Refund Status Updated')
                    ->greeting('Refund Update')
                    ->line('Your refund status has been updated.')
                    ->line('Refund ID: ' . $this->refund->refund_id)
                    ->line('Status: ' . ucfirst($this->status))
                    ->line('Amount: ' . $this->formatAmount($this->refund->amount, $this->refund->currency))
                    ->line('If you have any questions, please contact our support team.');
        }
    }

    /**
     * Format amount with currency
     */
    private function formatAmount(float $amount, string $currency): string
    {
        return number_format($amount, 2) . ' ' . strtoupper($currency);
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'refund_id' => $this->refund->refund_id,
            'charge_id' => $this->refund->charge_id,
            'amount' => $this->refund->amount,
            'currency' => $this->refund->currency,
            'status' => $this->status,
            'message' => $this->getStatusMessage()
        ];
    }

    /**
     * Get status message for array representation
     */
    private function getStatusMessage(): string
    {
        switch ($this->status) {
            case 'succeeded':
            case 'refunded':
                return 'Your refund has been processed successfully.';
            case 'failed':
                return 'Your refund could not be processed.';
            default:
                return 'Your refund status has been updated to: ' . $this->status;
        }
    }
}