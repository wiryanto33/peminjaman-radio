<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification as BaseNotification;

class NowDatabaseNotification extends BaseNotification
{
    public function __construct(
        public array $data,
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return $this->data;
    }
}

