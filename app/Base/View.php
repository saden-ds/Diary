<?php

namespace App\Base;

class View
{
    const TYPE_JSON = 'json';
    const TYPE_XHR = 'xhr';

    protected Config $config;
    protected Message $msg;
    protected Session $session;
    protected Tmpl $tmpl;
    protected ?string $path = null;
    protected ?string $layout = null;
    protected ?array $data = [];
    protected ?array $main = [];
    protected ?array $meta = [];
    protected ?string $type = null;
    protected ?string $csrf = null;
    protected ?string $title = null;
    protected bool $is_exception = false;

    public static function init(string|array|null $path = null, ?array $data = []): View
    {
        $instance = new self($path, $data);

        return $instance;
    }

    public function __construct(string|array|null $path = null, ?array $data = [])
    {
        $this->config = Config::init();
        $this->msg = Message::init();
        $this->session = Session::init();
        $this->tmpl = Tmpl::init();
        $this->title = $this->config->get('title');

        if (is_array($path)) {
            $data = $path;
            $path = null;
        }

        if ($path) {
            $this->path($path);
        }

        if ($data) {
            $this->data($data);
        }
    }

    public function file(string $file, ?array $data = null): string
    {
        return $this->tmpl->file($file, $data);
    } 

    public function __toString()
    {
        return $this->toString();
    }

    public function path(string $value): View
    {
        $this->path = $value;

        return $this;
    }

    public function layout(string $value): View
    {
        $this->layout = $value;

        return $this;
    }

    public function data(array $value): View
    {
        $this->data = $value;

        return $this;
    }

    public function main(array $value): View
    {
        if ($this->main) {
            $this->main = array_merge($value, $this->main);
        } else {
            $this->main = $value;
        }

        return $this;
    }

    public function meta(string $name, string $value): View
    {
        $this->meta[] = [
            'name' => $name,
            'content' => htmlspecialchars($value),
        ];

        return $this;
    }

    public function type(string $value): View
    {
        $this->type = $value;

        return $this;
    }

    public function exception(bool $value): View
    {
        $this->is_exception = $value;

        return $this;
    }

    public function error(?string $value): View
    {
        $this->data['error'] = $value ?: $this->msg->t('error.message');
        $this->data['error_code'] = 1;

        return $this;
    }

    public function errors(array $errors, ?string $error = null): View
    {
        $this->data['errors'] = $errors;

        return $this->error($error);
    }

    public function csrf($value): View
    {
        $this->tmpl->setCSRF($value);

        return $this;
    }

    public function title(string $value, bool $rewrite = false): View
    {
        if ($rewrite) {
            $this->title = $value;
        } else {
            $this->title = $value . ' | ' . $this->title;
        }

        return $this;
    }

    public function isException(): bool
    {
        return $this->is_exception;
    }

    public function toString(): string
    {
        if ($this->type === self::TYPE_JSON) {
            return $this->toJson($this->data);
        }

        if (!$this->path) {
            return '';
        }

        if ($this->type === self::TYPE_XHR) {
            return $this->tmpl->file($this->path, $this->data ?: null);
        } elseif ($this->is_exception) {
            return $this->tmpl->error($this->path, $this->main);
        } else {
            $main = $this->main;
            $main['title'] = $this->title;
            $main['meta'] = $this->meta;

            $main['body'] = $this->tmpl->file($this->path, $this->data ?: null);

            return $this->tmpl->body($main, $this->layout);
        }
    }

    protected function toJson(?array $data): string
    {
        if ($data === []) {
            return '[]';
        }

        if (empty($data)) {
            return '{}';
        }

        return json_encode($data, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    }
}