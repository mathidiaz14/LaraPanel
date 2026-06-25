<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramMessage;

class DomainDownAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public string $domainName;
    public string $errorReason;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $domainName, string $errorReason)
    {
        $this->domainName = $domainName;
        $this->errorReason = $errorReason;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['mail'];
        
        if (config('services.telegram-bot-api.chat_id')) {
            $channels[] = 'telegram';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->error()
                    ->subject("ALERTA: Sitio web caído ({$this->domainName})")
                    ->greeting("Hola {$notifiable->name},")
                    ->line("Nuestro sistema de monitoreo ha detectado que el sitio web **{$this->domainName}** no está respondiendo correctamente.")
                    ->line("Razón del fallo: {$this->errorReason}")
                    ->action('Revisar Dominio', url("/domains"))
                    ->line('LaraPanel Monitoring System');
    }

    /**
     * Get the Telegram representation of the notification.
     */
    public function toTelegram($notifiable)
    {
        return TelegramMessage::create()
            ->to(config('services.telegram-bot-api.chat_id'))
            ->content("⚠️ *ALERTA DE DOMINIO* ⚠️\n\n" .
                      "*Sitio:* {$this->domainName}\n" .
                      "*Error:* {$this->errorReason}\n\n" .
                      "El sitio web no está respondiendo a las peticiones.")
            ->button('Abrir Panel', url('/domains'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'domain' => $this->domainName,
            'error' => $this->errorReason,
        ];
    }
}
