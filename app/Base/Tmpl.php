<?php

namespace App\Base;

class Tmpl extends Singleton
{
    public const OPTION_HTML_ESCAPE = 'h';
    public const OPTION_JAVASCRIPT_ESCAPE = 'j';
    public const OPTION_RAW = 'r';
    public const OPTION_REGEXP = '[hjr]';
    private array $tmpl = [];
    private string $csrf = '';
    private Config $config;
    private Message $msg;

    protected function __construct()
    {
        $this->msg = Message::init();
        $this->config = Config::init();
    }

    /**
     * @throws BindingResolutionException
     */
    public function body(?array $variables = [], ?string $layout = null): array|string|null
    {   
        $variables = array_replace_recursive(
            [
                'host' => $this->config->get('home'),
                'lang' => $this->config->locale,
                'title' => $this->config->get('default_title'),
                'version' => $this->config->get('version'),
                'assets_version' => $this->config->get('version_timestamp'),
                'body' => '',
            ],
            $variables
        );

        if (isset($this->csrf)) {
            $variables['meta'][] = [
                'name' => 'csrf',
                'content' => htmlspecialchars($this->csrf)
            ];
        }

        if (isset($variables['meta']) && is_array($variables['meta'])) {
            foreach ($variables['meta'] as $k => $v) {
                $variables['meta'][$k] = [
                    'name' => $v['name'] ?? null,
                    'property' => $v['property'] ?? null,
                    'itemprop' => $v['itemprop'] ?? null,
                    'content' => $v['content'] ?? null,
                ];
            }
        }

        return $this->file('tmpl/' . ($layout ?: 'layout') . '.tmpl', $variables);
    }

    public function error(string $path, ?array $data = null): string
    {
        $title = $data['title'] ?? '';

        $title .= $title ? ' | ' : '';
        $title .= $this->config->get('default_title');

        return $this->file('tmpl/error.tmpl', [
            'title' => $title,
            'body' => $path ? $this->file($path) : null,
            'assets_version' => $this->config->get('version_timestamp')
        ]);
    }

    public function file(string $path, ?array $data = null): array|string|null
    {
        if (!isset($this->tmpl[$path])) {
            $this->tmpl[$path] = file_get_contents($this->config->get('dir') .
                '/' . $path);
        }

        return $this->parse($this->tmpl[$path], $data);
    }

    public function getCSRF(): string
    {
        return htmlspecialchars($this->csrf);
    }

    public function setCSRF(?string $csrf): void
    {
        $this->csrf = htmlspecialchars($csrf);
    }

    public function mail(
        string $path,
        mixed $data = null,
        ?array $options = []
    ): array|string|null
    {
        return $this->file('tmpl/mails/layout.tmpl', [
            'title' => $this->config->get('default_title'),
            'body' => $this->file($path, $data),
            'is_embeded_logo' => $options['is_embeded_logo'] ?? false
        ]);
    }

    public function parse(string $in, ?array $data = null): array|string|null
    {
        $self = $this;
        $in = preg_replace_callback(
            '/<msg:(.*?)(:(' . self::OPTION_REGEXP . ')?)?>/s',
            static function ($match) use ($self) {
                return $self->msg($match[1], $match[3] ?? null);
            },
            $in
        );

        $in = preg_replace_callback(
            '/\s*?<block:(.*?)>(.*?)\s*<\/block:(\\1)>\s*?/s',
            static function ($match) use ($self, $data) {
                return $self->parseBlock($match[2], $self->variable($data, $match[1]));
            },
            $in
        );
        $in = preg_replace_callback(
            '/\s*?<if:([a-zA-Z_\-0-9]+?)>(.*?)<\/if:(\\1)>\s*?/s',
            static function ($match) use ($self, $data) {
                return $self->ifStatement($data, $match[1], $match[2], true);
            },
            $in
        );
        $in = preg_replace_callback(
            '/\s*?<ifnot:([a-zA-Z_\-0-9]+?)>(.*?)<\/ifnot:(\\1)>\s*?/s',
            static function ($match) use ($self, $data) {
                return $self->ifStatement($data, $match[1], $match[2], false);
            },
            $in
        );
        $in = preg_replace_callback(
            '/\s*?<if:([a-zA-Z_\-|0-9]+?):(.*?):(.*?)>\s*?/s',
            static function ($match) use ($self, $data) {
                return $self->ifTernary($data, $match[1], $match[2], $match[3]);
            },
            $in
        );
        $in = preg_replace_callback(
            '/<var:(.*?)(:(' . self::OPTION_REGEXP . ')?)?>/s',
            static function ($match) use ($self, $data) {
                return $self->variable($data, $match[1], $match[3] ?? self::OPTION_HTML_ESCAPE);
            },
            $in
        );

        return $in;
    }

    public function parseBlock(string $in, mixed $data): string
    {
        $out = '';

        if (isset($data) && is_array($data) && !empty($data)) {
            foreach ($data as $v) {
                if (is_array($v)) {
                    $out .= $this->parse($in, $v);
                }
            }
        }

        return $out;
    }

    private function getSafeValue(mixed $value, ?string $safe = null): array|string|null
    {
        return match ($safe) {
            self::OPTION_HTML_ESCAPE => SafeBuffer::set($value)->htmlEscape(),
            self::OPTION_JAVASCRIPT_ESCAPE => SafeBuffer::set($value)->escapeJavascript(),
            default => $value
        };
    }

    private function ifStatement($b, $if, $in, $i): array|string|null
    {
        $true = is_array($b) && array_key_exists($if, $b);
        $true = $true && (($b[$if] && $i) || (!$b[$if] && !$i));

        return $true ? $this->parse($in, $b) : '';
    }

    private function ifTernary($vars, $if, $t, $f)
    {
        if (strpos($if, '|') !== false) {
            $ifa = explode('|', $if);
            foreach ($ifa as $ifi) {
                if (isset($vars[$ifi]) && $vars[$ifi] == true) {
                    return $t;
                }
            }

            return $f;
        } else {
            return (isset($vars[$if]) && $vars[$if] == true) ? $t : $f;
        }
    }

    private function msg(string $key, ?string $safe = null): array|string|null
    {
        return $this->getSafeValue($this->msg->t($key), $safe);
    }

    private function variable($a, $b, $safe = null): array|string|null
    {
        if (!is_array($a) || !array_key_exists($b, $a)) {
            return "#var:$b#";
        }

        return $this->getSafeValue($a[$b], $safe);
    }
}