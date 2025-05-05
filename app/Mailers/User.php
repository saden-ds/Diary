<?php

namespace App\Mailers;

use App\Base\Mailer;

class User extends Mailer
{
    public function sendConfirmation($user, ?string $confirmation_token = null): bool
    {
        $this->addTo($user->user_email);

        $url = $this->config->get('mail.base_url');

        if ($confirmation_token) {
            $url .= '/confirm/' . urlencode($confirmation_token);
        }

        $this->is_html = true;
        $this->subject = $this->msg->t('mail.confirmation_instruction.subject');
        $this->body = $this->tmpl->mail('tmpl/mails/users/confirmation.tmpl', [
            'subject' => $this->subject,
            'body' => $this->msg->t('mail.confirmation_instruction.body'),
            'link' => $this->msg->t('mail.confirmation_instruction.link_title'),
            'url' => $url
        ], [
            'is_embeded_logo' => $this->addLogo()
        ]);
        $this->plain_text = $this->tmpl->file('tmpl/mails/users/confirmation.txt', [
            'subject' => $this->subject,
            'body' => $this->msg->t('mail.confirmation_instruction.plain_text', [
                'url' => $url
            ])
        ]);

        return $this->send();
    }
}