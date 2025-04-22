<?php

namespace App\MailSender;

use Nette\Mail\Message;
use Nette\Mail\Mailer;
use Latte\Engine;

class MailSender
{
    public function __construct(
        private Mailer $mailer
        
    ) {}


    public function sendEmail(
        string $email,
        string $template,
        string $subject,
        array $params = [],
        ?string $attachmentPath = null
    ): void {
        $latte = new Engine();
        $mail = new Message;

        // Add attachment if provided
        if ($attachmentPath && file_exists($attachmentPath)) {
            $mail->addAttachment(basename($attachmentPath), file_get_contents($attachmentPath), mime_content_type($attachmentPath));
        }

        // Render the template from the Templates directory
        $templatePath = __DIR__ . '/Templates/' . $template . '.latte';
        $html = $latte->renderToString($templatePath, $params);

        // Configure and send the email
        $mail->setFrom('burdadko.cczz@seznam.cz')
            ->addTo($email)
            ->setSubject($subject)
            ->setHtmlBody($html);

        $this->mailer->send($mail);
        bdump("Email sent to {$email} using template {$template}.");
    }
}