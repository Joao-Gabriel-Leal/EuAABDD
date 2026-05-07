<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReservationGuestSplitMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invitation $invitation) {}

    public function build(): self
    {
        return $this
            ->subject('Rateio da reserva na AABB Brasilia')
            ->text('emails.invitations.reservation-split');
    }
}
