<?php

namespace Santosdave\VerteilWrapper\Notifications;

use Illuminate\Support\Facades\Notification;


class VerteilNotifier
{
    protected array $config;
    protected array $notificationLevels = ['emergency', 'alert', 'critical'];

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Send notification
     */
    public function notify(string $level, string $message, array $context = []): void
    {
        if (!in_array($level, $this->notificationLevels)) {
            return;
        }

        $this->sendSlackNotification($level, $message, $context);
        $this->sendEmailNotification($level, $message, $context);
    }

    /**
     * Send Slack notification
     */
    protected function sendSlackNotification(string $level, string $message, array $context): void
    {
        if (empty($this->config['slack_webhook_url'])) {
            return;
        }


        // Implementation of slack notification...
    }

    /**
     * Send email notification
     */
    protected function sendEmailNotification(string $level, string $message, array $context): void
    {
        if (empty($this->config['notification_email'])) {
            return;
        }

        // Implementation of email notification...
    }
}
