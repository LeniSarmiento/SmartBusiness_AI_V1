<?php

namespace App\Notifications;

use App\Models\OrganizationInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(public OrganizationInvitation $invitation) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $organizationName = $this->invitation->organization->name;
        $inviterName = $this->invitation->inviter->name;
        $roleName = $this->invitation->role->name;
        $url = config('app.frontend_url').'/invitations/'.$this->invitation->token;

        return (new MailMessage)
            ->subject("Invitación a unirte a {$organizationName} en SmartBusiness")
            ->greeting('¡Hola!')
            ->line("{$inviterName} te ha invitado a formar parte del equipo de **{$organizationName}** con el rol de **{$roleName}**.")
            ->action('Aceptar Invitación', $url)
            ->line('Este enlace de invitación expirará en 7 días.')
            ->line('Si no esperabas recibir esta invitación, puedes ignorar este mensaje.');
    }
}
