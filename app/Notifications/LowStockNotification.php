<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $product_name,
        public string $product_sku,
        public int $current_stock,
        public int $minimum_stock,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("⚠️ Alerta de stock bajo - {$this->product_name}")
            ->greeting('¡Atención!')
            ->line("El producto {$this->product_name} (SKU: {$this->product_sku}) tiene stock bajo.")
            ->line("Stock actual: {$this->current_stock}, Mínimo: {$this->minimum_stock}.")
            ->line('Por favor, revisa el inventario y realiza los ajustes necesarios.');
    }
}
