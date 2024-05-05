<?php

namespace App\Base\Http;

use App\Base\Logger;
use App\Base\Config;
use CurlHandle;
use Exception;

class Request
{
    private array $headers = [];
    private array $options = [];
    private ?string $referer = null;
    private string $uuid = '';
    private ?Logger $logger = null;
    private string $user_agent = '';
    private bool $follow_redirects = false;

    public function __construct(array $settings = [])
    {
        $config = Config::init();
        $settings = array_merge([
            'user_agent' => 'smart',
            'log' => 'http'
        ], $settings);

        $this->user_agent = $settings['user_agent'] . ' ' .
            $config->get('version');

        if ($settings['log']) {
            $this->logger = new Logger($settings['log']);
        }
    }

    public function delete(string $url, string|array $vars = null): Response
    {
        return $this->request('DELETE', $url, $vars);
    }

    public function get(string $url, string|array $vars = null): Response
    {
        if (!empty($vars)) {
            $url .= (str_contains($url, '?')) ? '&' : '?';
            $url .= (is_string($vars)) ? $vars : http_build_query($vars, '', '&');
        }

        return $this->request('GET', $url);
    }

    public function head(string $url, string|array $vars = null): Response
    {
        return $this->request('HEAD', $url, $vars);
    }

    public function post(string $url, string|array $vars = null): Response
    {
        return $this->request('POST', $url, $vars);
    }

    public function put(string $url, string|array $vars = null): Response
    {
        return $this->request('PUT', $url, $vars);
    }

    public function request(string $method, string $url, string|array $vars = null): Response
    {
        try {
            $request = curl_init();

            if (is_array($vars)) {
                $vars = http_build_query($vars, '', '&');
            }

            $this->setMethod($request, $method);
            $this->setOptions($request, $url, $vars);
            $this->setHeaders($request);

            $curl_response = curl_exec($request);
            $response = new Response(
                $curl_response ?: null,
                curl_getinfo($request, CURLINFO_HTTP_CODE)
            );
            $htp_code = curl_getinfo($request, CURLINFO_HTTP_CODE);
            $message = PHP_EOL . '[' . $method . '] ' . $url . ' ' . $vars;

            if ($curl_response === false) {
                $error = curl_error($request);
                $error_code = curl_errno($request);
                $message .= PHP_EOL . '[' . $error_code . '] ' . $error;

                $response->setError($error);
                $this->log('error', $message, $this->uuid);
            } else {
                $message .= PHP_EOL . $curl_response . PHP_EOL;

                $this->log('info', $message, $this->uuid);

                if ($htp_code < 200 || $htp_code > 299) {
                    $this->log('warn', $message, $this->uuid);
                }
            }

            curl_close($request);
        } catch (Exception $e) {
            $response = new Response(null, 0);
            $response->setError($e->getMessage());
            $this->log('error', $e->getMessage(), $this->uuid);
        }

        return $response;
    }

    public function setFollowRedirects(bool $value = true): void
    {
        $this->follow_redirects = $value;
    }

    public function setHeader(string $name, ?string $value): void
    {
        if ($value !== null && trim($value) !== '') {
            $this->headers[$name] = $value;
        }
    }

    public function setOption(string $name, string $value): void
    {
        $this->options[$name] = $value;
    }

    public function setReferer(string $value): void
    {
        $this->referer = $value;
    }

    public function setUserAgent(string $value): void
    {
        $this->user_agent = $value;
    }

    public function setUuid(string $value): void
    {
        $this->uuid = $value;
    }

    protected function setHeaders(CurlHandle $request): void
    {
        if (count($this->headers) > 0) {
            $headers = null;

            foreach ($this->headers as $key => $value) {
                $headers[] = $key . ': ' . $value;
            }

            curl_setopt($request, CURLOPT_HTTPHEADER, $headers);
        }
    }

    protected function setMethod(CurlHandle $request, string $method): void
    {
        switch (strtoupper($method)) {
            case 'HEAD':
                curl_setopt($request, CURLOPT_NOBODY, true);
                break;
            case 'GET':
                curl_setopt($request, CURLOPT_HTTPGET, true);
                break;
            case 'POST':
                curl_setopt($request, CURLOPT_POST, true);
                break;
            default:
                curl_setopt($request, CURLOPT_CUSTOMREQUEST, $method);
        }
    }

    protected function setOptions(
        CurlHandle $request,
        string $url,
        array|string|null $vars = null
    ): void
    {
        curl_setopt($request, CURLOPT_URL, $url);

        if (!empty($vars)) {
            curl_setopt($request, CURLOPT_POSTFIELDS, $vars);
        }

        curl_setopt($request, CURLOPT_HEADER, true);
        curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($request, CURLINFO_HEADER_OUT, true);
        curl_setopt($request, CURLOPT_USERAGENT, $this->user_agent);

        if ($this->follow_redirects) {
            curl_setopt($request, CURLOPT_FOLLOWLOCATION, true);
        }

        if ($this->referer) {
            curl_setopt($request, CURLOPT_REFERER, $this->referer);
        }

        if ($this->options) {
            foreach ($this->options as $option => $value) {
                curl_setopt(
                    $request,
                    constant('CURLOPT_' . str_replace('CURLOPT_', '', strtoupper($option))),
                    $value
                );
            }
        }
    }


    private function log(string $type, mixed $message): void
    {
        if ($this->logger) {
            $this->logger->{$type}($message, $this->uuid);
        }
    }
}