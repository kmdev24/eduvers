<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;

/**
 * Sends mail through Brevo's HTTPS API instead of SMTP.
 *
 * Why: Railway blocks outbound SMTP on the Free, Trial and Hobby plans, so the usual
 * smtp mailer can't connect from there. This transport only needs HTTPS (port 443).
 * Enable it with MAIL_MAILER=brevo and BREVO_API_KEY=... (see .env.example).
 *
 * API reference: https://developers.brevo.com/reference/sendtransacemail
 */
class BrevoApiTransport extends AbstractTransport
{
    public function __construct(
        private readonly string $key,
        private readonly string $endpoint = 'https://api.brevo.com/v3/smtp/email',
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        if ($this->key === '') {
            throw new TransportException('BREVO_API_KEY is not set.');
        }

        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $from  = $email->getFrom()[0] ?? $message->getEnvelope()->getSender();

        $payload = array_filter([
            'sender'      => $this->address($from),
            'to'          => array_map($this->address(...), $email->getTo()),
            'cc'          => array_map($this->address(...), $email->getCc()),
            'bcc'         => array_map($this->address(...), $email->getBcc()),
            'replyTo'     => ($replyTo = $email->getReplyTo()[0] ?? null) ? $this->address($replyTo) : null,
            'subject'     => $email->getSubject(),
            'htmlContent' => $this->body($email->getHtmlBody()),
            'textContent' => $this->body($email->getTextBody()),
            'attachment'  => array_map(fn ($part) => [
                'name'    => $part->getFilename() ?? 'attachment',
                'content' => base64_encode($part->getBody()),
            ], $email->getAttachments()),
        ]);

        $response = Http::withHeaders(['api-key' => $this->key])
            ->acceptJson()
            ->timeout(20)
            ->post($this->endpoint, $payload);

        if ($response->failed()) {
            throw new TransportException(sprintf(
                'Brevo API rejected the email (HTTP %d): %s',
                $response->status(),
                $response->json('message') ?? $response->body(),
            ));
        }

        if ($id = $response->json('messageId')) {
            $message->setMessageId((string) $id);
        }
    }

    public function __toString(): string
    {
        return 'brevo+api://api.brevo.com';
    }

    /** @return array{email: string, name?: string} */
    private function address(Address $address): array
    {
        return array_filter(['email' => $address->getAddress(), 'name' => $address->getName()]);
    }

    private function body(mixed $body): ?string
    {
        return is_resource($body) ? stream_get_contents($body) : $body;
    }
}
