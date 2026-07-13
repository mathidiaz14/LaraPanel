<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\UptimeMonitor;

class UptimeAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public UptimeMonitor $monitor,
        public string $status,
        public ?string $errorMsg
    ) {}

    public function envelope(): Envelope
    {
        $statusText = strtoupper($this->status);
        $emoji = $this->status === 'up' ? '✅' : '🔴';
        return new Envelope(
            subject: "{$emoji} [{$statusText}] Monitor de Servicio: {$this->monitor->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.uptime-alert',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
