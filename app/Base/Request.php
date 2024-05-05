<?php

namespace App\Base;

use Exception;

class Request extends Singleton
{
    public const HOST_MAX_LENGTH = 128;
    public const PARAMS_DELIMITER = '.';

    private string $host;
    private mixed $ip;
    private array $path = [];
    private array $params;
    private string $referer = '';
    private string $url = '';
    private mixed $useragent = 'unknown';

    protected function __construct()
    {
        $this->params = $this->readRequest($_REQUEST);
        $this->setHost();

        if (isset($_SERVER["HTTP_USER_AGENT"])) {
            $this->useragent = $_SERVER["HTTP_USER_AGENT"];
        }

        if (isset($_SERVER["HTTP_REFERER"])) {
            $this->referer = $_SERVER["HTTP_REFERER"];
        }

        if (isset($_SERVER['REQUEST_URI'])) {
            $this->url = rawurldecode($_SERVER['REQUEST_URI']);
            $this->setPath($this->url);
        }

        if (isset($_SERVER['REMOTE_ADDR'])) {
            $this->ip = $_SERVER['REMOTE_ADDR'];
        }

        if ($this->isXss($this->url)) {
            $logger = new Logger('xss');
            $logger->info($this->url);
            exit;
        }
    }

    public function get(string $name, mixed $default = null): mixed
    {
        if (is_null($name)) {
            return $this->params;
        }

        if ($this->exists($name)) {
            return $this->params[$name];
        }

        if (strpos($name, self::PARAMS_DELIMITER) === false) {
            return $default;
        }

        return $this->recursive(explode(self::PARAMS_DELIMITER, $name), $default);
    }

    public function exists(string $name): bool
    {
        return array_key_exists($name, $this->params);
    }

    public function getCsrf()
    {
        return $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $this->get('csrf');
    }

    public function getFile($name = null)
    {
        if ($name) {
            return $_FILES[$name] ?? null;
        }

        return $_FILES;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getIp()
    {
        return $this->ip;
    }

    public function getJson($name = null)
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        if ($json && !$data && $error = $this->getJsonError()) {
            throw new Exception($error);
        }

        if (!empty($name)) {
            return $data[$name] ?: null;
        }

        return $data ?: null;
    }

    public function &getParams(): array
    {
        return $this->params;
    }

    public function getPath($i = null)
    {
        if ($i !== null) {
            return $this->path[$i] ?? null;
        }

        return parse_url($this->url, PHP_URL_PATH);
    }

    public function getPost(): array
    {
        return $_POST;
    }

    public function getQuery(): array
    {
        return $_GET;
    }

    public function getServerProtocol()
    {
        return $_SERVER['SERVER_PROTOCOL'];
    }

    public function getUrl($offset = null): string
    {
        if ($offset) {
            return '/' . implode('/', array_slice($this->path, $offset));
        }

        return $this->url;
    }

    public function getUrlProtocol()
    {
        return $_SERVER['REQUEST_SCHEME'];
    }

    public function getUserAgent()
    {
        return $this->useragent;
    }

    public function getReferer(): ?string
    {
        return $_SERVER['HTTP_REFERER'] ?? null;
    }

    public function isApi(): bool
    {
        return $this->getPath(0) === 'api';
    }

    public function isXhr(): bool
    {
        $is_xhr = !empty($_SERVER['HTTP_X_REQUESTED_WITH']);
        $is_xhr = $is_xhr && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        return $is_xhr || $this->getPath(0) === 'ajax';
    }

    public function isJsonRequest(): bool
    {
        if ($this->get('format') === 'json') {
            return true;
        }

        $accept = $_SERVER['HTTP_ACCEPT'] ?? null;

        if (empty($accept)) {
            return false;
        }

        return (bool) preg_match(
            '/(^|\s|,)application\/([\w!#\$&-\^\.\+]+\+)?json(\+oembed)?($|\s|;|,)/i',
            $accept
        );
    }

    public function permit($param_names, $key = null): array
    {
        $result = [];

        if ($key) {
            $params = $this->get($key);
        } else {
            $params = $this->params;
        }

        if (!$params || empty($params)) {
            return $result;
        }
        foreach ($param_names as $param_name) {
            if (isset($params[$param_name])) {
                $result[$param_name] = $params[$param_name];
            }
        }

        return $result;
    }

    public function redirect($url)
    {
        header('Location: ' . $url);
    }

    public function updateParams($params)
    {
        $this->params = array_replace_recursive($params, $this->params);
    }

    private function getJsonError(): ?string
    {
        switch (json_last_error()) {
            case JSON_ERROR_NONE:
                return null;
            case JSON_ERROR_DEPTH:
                return 'Maximum stack depth exceeded';
            case JSON_ERROR_STATE_MISMATCH:
                return 'Underflow or the modes mismatch';
            case JSON_ERROR_CTRL_CHAR:
                return 'Unexpected control character found';
            case JSON_ERROR_SYNTAX:
                return 'Syntax error, malformed JSON';
            case JSON_ERROR_UTF8:
                return 'Malformed UTF-8 characters, possibly incorrectly encoded';
            default:
                return 'Unknown JSON error';
        }
    }

    private function isXss($url): bool|int
    {
        return preg_match('/\?.*?[<>]/', rawurldecode($url));
    }

    private function mbParseUrl($url): bool|array|int|string|null
    {
        $enc_url = preg_replace_callback(
            '/[^\/@?&=#]+/u',
            static fn($matches) => urlencode($matches[0]),
            $url
        );

        return parse_url($enc_url, PHP_URL_PATH);
    }

    private function readRequest($request): array
    {
        $result = [];
        foreach ($request as $key => $value) {
            if (isset($value) && is_array($value)) {
                $result[$key] = $this->readRequest($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function setHost()
    {
        if (isset($_SERVER['HTTP_HOST']) &&
            strlen($_SERVER['HTTP_HOST']) <= self::HOST_MAX_LENGTH) {
            $this->host = mb_strtolower($_SERVER['HTTP_HOST']);
        } else if (config('host')) {
            $this->host = config('host');
        }
    }

    private function setPath($url): void
    {
        $this->path = explode('/', rawurldecode(trim((string) $this->mbParseUrl($url), '/')));
    }

    private function recursive(array $names, mixed $default = null): mixed
    {
        $params = $this->params;

        foreach ($names as $name) {
            if (!is_array($params) || !array_key_exists($name, $params)) {
                return $default;
            }

            $params = $params[$name];
        }

        return $params;
    }
}
