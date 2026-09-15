<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoginOtpNotification extends Notification
{
    public function __construct(
        protected User $attemptingUser,
        protected string $code,
        protected string $ipAddress,
        protected \DateTimeInterface $attemptedAt,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->priority(1)
            ->subject('Urgent: Login Verification Code')
            ->greeting('Login Verification Code')
            ->line("A login attempt was made using the account: {$this->attemptingUser->email}")
            ->line('Date & time: ' . $this->attemptedAt->format('Y-m-d H:i:s') . ' (' . config('app.timezone') . ')')
            ->line("IP address: {$this->ipAddress}")
            ->line("Verification code: {$this->code}")
            ->line('This code expires in 10 minutes. If you did not attempt to log in, you can ignore this email.');
    }
}
