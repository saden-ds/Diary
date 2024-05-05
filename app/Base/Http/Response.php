<?php

namespace App\Base\Http;

class Response
{
    private ?string $response = null;
    private ?string $error = null;
    private int $code = 0;
    private ?array $cookie = null;
    private ?array $headers = null;

    public function __construct(?string $response, int $code)
    {
        if (empty($response)) {
            $this->error = 'Unknown error';
            return;
        }

        preg_match_all(
            '#HTTP/\d\.\d.*?$.*?\r\n\r\n#ims',
            $response,
            $matches
        );

        $this->code = $code;

        $this->setCookie($response);

        if ($headers_string = strval(array_pop($matches[0]))) {
            $this->headers = explode(
                "\r\n",
                str_replace("\r\n\r\n", '', $headers_string)
            );
            $this->response = str_replace($headers_string, '', $response);
        } else {
            $this->response = $response;
        }

        if (!$this->isSuccessCode()) {
            $this->error = 'HTTP code ' . $this->code;
        }
    }

    public function __toString()
    {
        return $this->response;
    }

    public function getBody(): ?string
    {
        return $this->response;
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function getData(?string $key = null)
    {
        if (empty($this->response)) {
            return null;
        }

        $data = json_decode($this->response, true);

        if ($key === null) {
            return $data;
        }

        return $data[$key] ?? null;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function setError($value): void
    {
        $this->error = $value;
    }

    public function hasError(): bool
    {
        return $this->error !== null;
    }

    public function isSuccess(): bool
    {
        return !$this->hasError() && $this->isSuccessCode();
    }

    public function isSuccessCode(): bool
    {
        return $this->code >= 200 && $this->code <= 299;
    }

    public function getCookie(): ?array
    {
        return $this->cookie;
    }

    private function setCookie($response): void
    {
        $cookie = null;

        if (preg_match('/^Set-Cookie:\s*([^;]*?)=(.+?)[;$]/mi', $response, $m)) {
            $cookie[] = $m[1] . '=' . $m[2];
        }

        if ($cookie) {
            $this->cookie = $cookie;
        }
    }
}