<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class MailService
{
    private MailerInterface $mailer;
    private string $from;

    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
        $this->from = 'organ.tournament.supinfo@gmail.com'; // Mets ici l’adresse qui envoie les mails (doit être celle de ton MAILER_DSN)
    }

    /**
     * Envoie un mail simple à un ou plusieurs destinataires
     */
    public function send(
        string|array $to,
        string $subject,
        string $htmlContent,
        string|null $textContent = null
    ): void {
        $email = (new Email())
            ->from($this->from)
            ->subject($subject)
            ->html($htmlContent);

        if ($textContent) {
            $email->text($textContent);
        }

        foreach ((array) $to as $recipient) {
            $email->to($recipient);
            $this->mailer->send($email);
        }
    }
}
