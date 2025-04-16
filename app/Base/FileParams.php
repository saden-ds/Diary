<?php

namespace App\Base;

class FileParams 
{
    protected Config $config;
    protected Message $msg;

    public static function set($params): FileParams
    {
        return new self($params);
    }


    public function __construct($params)
    {
        $this->config = Config::init();
        $this->msg = Message::init();
        $this->params = $params;

        $this->params['error'] = $this->translateError(
            $params['error'] ?? null
        );
    }

    public function get(): ?array
    {
        return $this->params;
    }


    private function translateError($error): ?string
    {
        if (!$error) {
            return null;
        }

        switch ($error) {
            case UPLOAD_ERR_INI_SIZE:
                $max = $this->config->getUploadMaxFilesize();

                return $this->msg->t('error.upload.ini_size', [
                    'max' => \Base\Number::set($max)->toPrettyFileSize()
                ]);
            case UPLOAD_ERR_FORM_SIZE:
                $max = $this->config->getPostMaxSize();

                return $this->msg->t('error.upload.form_size', [
                    'max' => \Base\Number::set($max)->toPrettyFileSize()
                ]);
            case UPLOAD_ERR_PARTIAL:
                return $this->msg->t('error.upload.partial');
            case UPLOAD_ERR_NO_FILE:
                return $this->msg->t('error.upload.no_file');
            case UPLOAD_ERR_NO_TMP_DIR:
                return $this->msg->t('error.upload.no_tmp_dir');
            case UPLOAD_ERR_CANT_WRITE:
                return $this->msg->t('error.upload.cant_write');
            case UPLOAD_ERR_EXTENSION:
                return $this->msg->t('error.upload.extension');
        }

        return $error;
    }

}