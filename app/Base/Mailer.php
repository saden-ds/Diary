<?php

namespace App\Base;

include_once(__ROOT__.'/vendor/autoload.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    protected Config $config;
    protected Message $msg;
    protected Logger $logger;
    protected Tmpl $tmpl;
    protected ?string $to = null;

    private PHPMailer $mailer;

    public function __construct()
    {
        $this->config = Config::init();
        $this->msg = Message::init();
        $this->tmpl = Tmpl::init();
        $this->logger = new Logger('mail');
        $this->mailer = new PHPMailer(true);

        if ($this->config->get('mail.smtp')) {
            $this->mailer->isSMTP();

            if ($value = $this->config->get('mail.smtp.host')) {
                $this->mailer->Host = $value;
            }

            if ($this->config->get('mail.smtp.username')) {
                $this->mailer->SMTPAuth = true;
                $this->mailer->Username = $this->config->get('mail.smtp.username');
                $this->mailer->Password = $this->config->get('mail.smtp.password');
                $this->mailer->SMTPSecure = 'tls';
            } else {
                $this->mailer->SMTPAuth = false;
                $this->mailer->SMTPSecure = false;
                $this->mailer->SMTPAutoTLS = false;
            }

            if ($value = $this->config->get('mail.smtp.port')) {
                $this->mailer->Port = $value;
            }
        }

        $this->mailer->CharSet = 'UTF-8';
        $this->mailer->setFrom(
            $this->config->get('mail.from'),
            $this->config->get('mail.name')
        );
    }

    public function __set($name, $value)
    {
        $method_name = 'set'.str_replace('_', '', ucwords($name, '_'));

        if (method_exists($this, $method_name)) {
            return $this->$method_name($value);
        }

        trigger_error("Undefined mailer property: {$name}", E_USER_NOTICE);
    }

    public function &__get($name)
    {
        $method_name = 'get'.str_replace('_', '', ucwords($name, '_'));

        if (method_exists($this, $method_name)) {
            $value = $this->$method_name();
            return $value;
        }

        return null;
    }

    public function setIsHtml($value): void
    {
        $this->mailer->isHTML($value);
    }

    public function setSubject($value): void
    {
        $this->mailer->Subject = $value;
    }

    public function getSubject(): ?string
    {
        return $this->mailer->Subject;
    }

    public function setBody($value): void
    {
        $this->mailer->Body = $value;
    }

    public function getBody(): ?string
    {
        return $this->mailer->Body;
    }

    public function setPlainText($value): void
    {
        $this->mailer->AltBody = $value;
    }

    public function getPlainText(): ?string
    {
        return $this->mailer->AltBody;
    }

    public function addReplyTo($email, $name = ''): void
    {
        $this->mailer->addReplyTo($email, $name);
    }

    public function addTo($value): void
    {
        $value = preg_replace('/\s+/', '', $value);
        $values = explode(',', $value);

        $this->to = $this->to ? $this->to . ', ' . $value : $value;

        foreach ($values as $v) {
            $this->mailer->addAddress($v);
        }
    }

    public function addBCC($value): void
    {
        $value = preg_replace('/\s+/', '', $value);
        $values = explode(',', $value);

        foreach ($values as $v) {
            $this->mailer->addBCC($v);
        }
    }

    public function addAttachment($path, $name = null): void
    {
        $this->mailer->addAttachment($path, $name);
    }

    public function addStringAttachment($content, $name = null): void
    {
        $this->mailer->addStringAttachment($content, $name);
    }

    public function addLogo($tmpl_dirpath = null)
    {
        if ($this->config->isEnv('development')) {
            return null;
        }

        if ($tmpl_dirpath) {
            $path = $this->config->get('dir') . '/' . $tmpl_dirpath . '/logo.png';
        } else {
            $path = $this->config->get('dir') . '/tmpl/mails/logo.png';
        }

        return $this->mailer->addEmbeddedImage(
            $path,
            'logo',
            'logo',
            'base64',
            'image/png',
            'inline'
        );
    }

    public function send(): bool
    {
        if (!$this->config->isEnv('production') && !$this->config->get('mail.force', false)) {
            $this->logger->info('TEST MODE '.$this->to.' '.$this->getSubject());

            if ($this->config->isEnv('development')) {
                $this->logger->info(PHP_EOL . $this->mailer->Body);
            }

            return true;
        }

        try {
            $this->mailer->send();
            $this->logger->info($this->to.' '.$this->getSubject());
            return true;
        } catch (Exception $e) {
            $this->logger->error($e->getMessage().PHP_EOL.$e->getTraceAsString());
            return false;
        }
    }
}