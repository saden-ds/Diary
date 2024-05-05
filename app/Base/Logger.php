<?php

namespace App\Base;

class Logger
{
    public const DEFAULT_NAME = 'log';
    public const WARN = 'warn';
    public const ERROR = 'error';
    public const DEBUG = 'debug';
    public const FILTER_MASK = '[FILTERED]';
    public static array $FILTER = [
        'password',
        'new_password',
        'user_password',
        'plugin_api_key',
        'plugin_secret_key',
    ];
    private static ?Logger $default_log;
    private Config $config;
    private SafetyMask $safety_mask;
    private string $name;
    private bool $is_term = false;
    private bool $is_uri = false;

    public function __construct($name = self::DEFAULT_NAME, $is_uri = true)
    {
        $this->config = Config::init();
        $this->safety_mask = new SafetyMask();
        $this->name = $name;
        $this->is_term = isset($_SERVER['TERM']);
        $this->is_uri = $is_uri;
    }

    public function debug($message = '', $uuid = null): void
    {
        $this->write($this->name . '_' . self::DEBUG, $message, $uuid);
    }

    public function error($message = '', $uuid = null): void
    {
        $this->write($this->name . '_' . self::ERROR, $message, $uuid);
    }

    public function getTime($timestamp, $unit = 'ms'): string
    {
        $time = round((microtime(true) - $timestamp) * 1000, 0);

        if ($unit) {
            $time .= ' ' . $unit;
        }

        return strval($time);
    }

    public function info($message = '', $uuid = null): void
    {
        $this->write($this->name, $message, $uuid);
    }

    public static function log($message = '', $uuid = null): Logger
    {
        if (!isset(self::$default_log)) {
            self::$default_log = new self(self::DEFAULT_NAME, false);
        }
        self::$default_log->debug($message, $uuid);

        return self::$default_log;
    }

    public function sanitizeArray($array): mixed
    {
        return $this->safety_mask->array($array);
    }

    public function sanitizeXml($xml, $tag_names): mixed
    {
        return $this->safety_mask->xml($xml, $tag_names);
    }

    public function toJson($message): ?string
    {
        if (!$message) {
            return null;
        }

        $message = $this->safety_mask->array(
            json_decode(json_encode($message), true)
        );

        return json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function warn($message = '', $uuid = null): void
    {
        $this->write($this->name . '_' . self::WARN, $message, $uuid);
    }

    private function getReferer(): string
    {
        if (isset($_SERVER['HTTP_REFERER']) && $this->is_uri && !$this->is_term) {
            return ' REFERER: ' . $_SERVER["HTTP_REFERER"];
        }

        return '';
    }

    private function getUri(): string
    {
        if (isset($_SERVER['REMOTE_ADDR'], $_SERVER['REQUEST_URI']) && $this->is_uri && !$this->is_term) {
            return sprintf(" %s %s", $_SERVER['REMOTE_ADDR'], $_SERVER['REQUEST_URI']);
        }

        return '';
    }

    private function getUuid(): ?string
    {
        return $_SERVER['X_REQUEST_ID'] ?? null;
    }

    private static function isAllowedType($type): bool
    {
        $types = [self::WARN, self::ERROR, self::DEBUG];

        return !isset($type) || in_array($type, $types, true);
    }

    private function sanitizeString($string): mixed
    {
        return $this->safety_mask->string($string);
    }

    private function write(string $name, mixed $message = '', $uuid = null): void
    {
        $file = $this->config->get('tmp_dir') . '/' . $name;
        $content = '';

        if ('development' !== ENV) {
            $file .= '.' . date('Y.m.d');
            $content .= '[' . date('H:i:s'). ']';
        } else {
            $content .= '[' . date('Y.m.d H:i:s'). ']';
        }

        if (!$uuid) {
            $uuid = $this->getUuid();
        }

        if ($uuid) {
            $content .= ' [' . $uuid . ']';
        }

        $content .= $this->getUri();
        $content .= $this->getReferer();

        if (is_array($message) || is_object($message)) {
            $content .= ' ' . $this->toJson($message);
        } else {
            $content .= ' ' . $this->sanitizeString($message);
        }

        if (!file_exists($file) || is_writable($file)) {
            if ($out = fopen($file, 'ab+')) {
                fwrite($out, $content . PHP_EOL);
                fclose($out);
            }
        } else {
            /** @noinspection ForgottenDebugOutputInspection */
            error_log('File ' . $file . ' is not writable');
        }
    }
}