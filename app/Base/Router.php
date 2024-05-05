<?php

namespace App\Base;

use JsonException;
use RuntimeException;

class Router
{
    public const DEFAULT_MIME_TYPE = 'text/html';
    public static array $MIME_TYPES = [
        'text/x-comma-separated-values' => 'csv',
        'text/comma-separated-values' => 'csv',
        'application/vnd.msexcel' => 'csv',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/x-gtar' => 'gtar',
        'application/x-gzip' => 'gzip',
        'text/html' => 'html',
        'application/json' => 'json',
        'text/json' => 'json',
        'application/x-httpd-php' => 'php',
        'application/php' => 'php',
        'application/x-php' => 'php',
        'text/php' => 'php',
        'text/x-php' => 'php',
        'application/x-httpd-php-source' => 'php',
        'image/svg+xml' => 'svg',
        'application/x-tar' => 'tar',
        'application/x-gzip-compressed' => 'tgz',
        'text/plain' => 'txt',
        'application/excel' => 'xl',
        'application/msexcel' => 'xls',
        'application/x-msexcel' => 'xls',
        'application/x-ms-excel' => 'xls',
        'application/x-excel' => 'xls',
        'application/x-dos_ms_excel' => 'xls',
        'application/xls' => 'xls',
        'application/x-xls' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.ms-excel' => 'xlsx',
        'application/xml' => 'xml',
        'text/xml' => 'xml',
        'text/xsl' => 'xsl',
        'application/x-zip' => 'zip',
        'application/zip' => 'zip',
        'application/x-zip-compressed' => 'zip',
        'application/s-compressed' => 'zip',
        'multipart/x-zip' => 'zip',
    ];
    private mixed $request;
    private mixed $default_controller_method;
    private array $options;
    private array $routes = [];

    public function __construct($options, $default_controller_method = null)
    {
        $this->options = $options;
        $this->request = Request::init();
        $this->default_controller_method = $default_controller_method;
    }

    public function __destruct()
    {
        $this->execute();
    }

    public function any($pattern, $controller_method, $options = null)
    {
        $this->route(null, $pattern, $controller_method, $options);
    }

    public function delete($pattern, $controller_method, $options = null)
    {
        $this->route('delete', $pattern, $controller_method, $options);
    }

    public function execute(): bool
    {
        $uri = explode('?', $this->options['REQUEST_URI'])[0];

        if (str_contains($uri, '.')) {
            [$uri, $format] = explode('.', $uri);
        } else {
            $format = null;
        }

        foreach ($this->routes as $pattern => $actions) {
            if (
                is_null($actions['http_method'])
                || $this->options['REQUEST_METHOD'] === strtoupper($actions['http_method'])
            ) {
                if ($this->executeRoute($uri, $actions, $format)) {
                    return true;
                }
            }
        }

        $this->executeDefaultRoute();

        return false;
    }

    public function get($pattern, $controller_method, $options = null)
    {
        $this->route('get', $pattern, $controller_method, $options);
    }

    public function patch($pattern, $controller_method, $options = null)
    {
        $this->route('patch', $pattern, $controller_method, $options);
    }

    public function post($pattern, $controller_method, $options = null)
    {
        $this->route('post', $pattern, $controller_method, $options);
    }

    public function puts($pattern, $controller_method, $options = null)
    {
        $this->route('puts', $pattern, $controller_method, $options);
    }

    public function route($http_method, $pattern, $controller_method, $options = null): void
    {
        $pattern = $this->routeToRegex($pattern);

        $this->routes[strtoupper($http_method ?: '') . $pattern] = [
            'http_method' => $http_method,
            'url_pattern' => $pattern,
            'controller_method' => $controller_method,
            'options' => $options,
        ];
    }

    public function routes($routes)
    {
        if (is_array($routes)) {
            foreach ($routes as $r) {
                $this->route($r[0], $r[1], $r[2], $r[3] ?? null);
            }
        }
    }

    /**
     * @throws JsonException
     */
    private function callControllerClass(array $controllerMethod): array
    {
        if (!isset($controllerMethod[0]) || !class_exists($controllerMethod[0])) {
            throw new RuntimeException(
                'Controller class not specified: ' . json_encode(
                    $controllerMethod,
                    JSON_THROW_ON_ERROR
                )
            );
        }

        if (!isset($controllerMethod[1])) {
            throw new RuntimeException(
                'Controller method not specified: ' . json_encode(
                    $controllerMethod,
                    JSON_THROW_ON_ERROR
                )
            );
        }

        return ['controller' => $controllerMethod[0], 'action' => $controllerMethod[1]];
    }

    private function executeDefaultRoute(): bool
    {
        if ($this->default_controller_method) {
            $callables = $this->callControllerClass($this->default_controller_method);
            $controller = new $callables['controller'];
            // $controller->{$callables['action']}();
            $controller->action($callables['action']);

            return true;
        }

        return false;
    }

    private function executeRoute($uri, $actions, $format = null): bool
    {
        if (preg_match($actions['url_pattern'], $uri, $params) === 1) {
            try {
                $callables = $this->callControllerClass($actions['controller_method']);
            } catch (JsonException $e) {
                error_log($e->getMessage());

                return false;
            }

            $controller = new $callables['controller'];

            array_shift($params);

            if ($format) {
                $params['format'] = $format;
            }

            $params['method'] = $this->options['REQUEST_METHOD'];

            $this->request->updateParams($params);
            $controller->action($callables['action']);

            return true;
        }

        return false;
    }

    private function getFormat()
    {
        $content_type = array_key_exists('CONTENT_TYPE', $this->options)
            ?
            explode(';', $this->options['CONTENT_TYPE'])[0]
            :
            self::DEFAULT_MIME_TYPE;
        if (array_key_exists($content_type, self::$MIME_TYPES)) {
            return self::$MIME_TYPES[$content_type];
        }

        return self::$MIME_TYPES[self::DEFAULT_MIME_TYPE];
    }

    private function routeToRegex($path)
    {
        $slashes_escaped = str_replace('/', '\/', $path);
        $route = preg_replace_callback(
            '/({:.+?})/',
            function ($matches) {
                $param_name = preg_replace("/[^A-Za-z0-9\s_]/", '', $matches[0]);

                return "(?<{$param_name}>[^\/]+)";
            },
            $slashes_escaped
        );

        return '/^' . $route . '$/';
    }

}
