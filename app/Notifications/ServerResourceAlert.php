<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramMessage;

class ServerResourceAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public string $resourceType;
    public float $usage;
    public string $message;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $resourceType, float $usage, string $message)
    {
        $this->resourceType = $resourceType;
        $this->usage = $usage;
        $this->message = $message;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Use Telegram only if a chat ID is configured, otherwise fallback to mail.
        // Assuming we will configure telegram chat ID in config/services.php or .env later.
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
                    ->subject("ALERTA CRÍTICA: Uso elevado de {$this->resourceType}")
                    ->greeting("Hola {$notifiable->name},")
                    ->line("El servidor ha superado el umbral seguro de uso de recursos.")
                    ->line("Detalle: {$this->message}")
                    ->line("Por favor revisa el estado del servidor de inmediato.")
                    ->action('Abrir Panel de Control', url('/'))
                    ->line('LaraPanel Monitoring System');
    }

    /**
     * Get the Telegram representation of the notification.
     */
    public function toTelegram($notifiable)
    {
        return TelegramMessage::create()
            ->to(config('services.telegram-bot-api.chat_id'))
            ->content("🚨 *ALERTA CRÍTICA DE SERVIDOR* 🚨\n\n" .
                      "*Recurso:* {$this->resourceType}\n" .
                      "*Uso Actual:* {$this->usage}%\n\n" .
                      "{$this->message}\n\n" .
                      "Accede al panel para más detalles.")
            ->button('Abrir Panel', url('/'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'resource_type' => $this->resourceType,
            'usage' => $this->usage,
            'message' => $this->message,
        ];
    }
}
