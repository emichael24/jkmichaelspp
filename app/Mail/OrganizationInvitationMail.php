<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;

class OrganizationInvitationMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public string $organizationName, public string $acceptUrl)
    {
        $this->afterCommit();
    }

    public function build()
    {
        return $this->subject('Join '.$this->organizationName.' on JK Michaels')->view('mail.organization-invitation');
    }
}
