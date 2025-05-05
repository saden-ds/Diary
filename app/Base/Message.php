<?php

namespace App\Base;

use Exception;
use DateTime;
use DateTimeZone;

class Message
{
    const KEYS_DELIMITER = '.';

    private static Message $instance;
    private Config $config;
    private array $messages = [];

    public function __wakeup()
    {
        throw new Exception('Cannot unserialize singleton');
    }

    public static function init(): Message
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function translate($key, $options = [])
    {
        $default = '#'.$key.'#';
        $escape = false;

        if (isset($options['default'])) {
            $default = $options['default'];
            unset($options['default']);
        }
        if (isset($options['escape'])) {
            $escape = $options['escape'];
            unset($options['escape']);
        }
        if (isset($options['locale'])) {
            $message = $this->getLocaleMessages($options['locale']);
        } else {
            $message = $this->getLocaleMessages();
        }

        $scope = explode(self::KEYS_DELIMITER, $key);

        while ($message && count($scope) > 0) {
            $i = array_shift($scope);
            $message = isset($message[$i]) ? $message[$i] : null;
        }

        if (!$message) {
            return $default;
        }
        if (isset($options['count']) && isset($message['one'])) {
            $message = $this->pluralize(
                $options['count'],
                $this->replaceVariables($message['one'], $options),
                $this->replaceVariables($message['few'], $options),
                $this->replaceVariables($message['many'], $options)
            );
        } else {
            $message = $this->replaceVariables($message, $options);
        }
        if ($escape) {
            $message = addslashes(addslashes(htmlspecialchars($message)));
        }

        return $message;
    }

    public function t()
    {
        return call_user_func_array(array($this, 'translate'), func_get_args());
    }

    public function localize($time_string, $options = [])
    {
        if (!$time_string) {
            return $time_string;
        }

        $format = isset($options['format']) ? $options['format'] : 'default';
        $time = new DateTime($time_string, new DateTimeZone('UTC'));
        $localized_format = $this->t('datetime.format.'.$format, [
            'default' => $format
        ]);

        if (preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/', $time_string)) {
            return $time->format($localized_format);
        }

        $time->setTimezone(new DateTimeZone($this->config->get('timezone')));

        return $time->format($localized_format);
    }

    public function l()
    {
    return call_user_func_array(array($this, 'localize'), func_get_args());
    }

    public function date($date_string)
    {
        return $this->localize($date_string, ['format' => 'date']);
    }

    public function pluralize($n, $one, $few, $many)
    {
        if ($n % 10 == 1 && $n % 100 != 11) {
            return $one;
        } else if (array_search($n % 10, [2, 3, 4]) !== false &&
                                array_search($n % 100, [12, 13, 14]) === false) {
            return $few;
        } else if ($n % 10 == 0 ||
                                array_search($n % 10, [5, 6, 7, 8, 9]) !== false ||
                                array_search($n % 100, [11, 12, 13, 14]) !== false) {
            return $many;
        } else {
            return '';
        }
    }

    public function loadLocaleMessages($locale = null)
    {
        $locale = $locale ?: $this->config->getLocale();

        if (array_key_exists($locale, $this->messages)) {
            return;
        }

        $this->messages[$locale] = $this->load($locale);
    }


    private function __construct($locale = null)
    {
        $this->config = Config::init();

        $this->loadLocaleMessages();
    }

    private function __clone()
    {
    }

    private function load($locale)
    {
        $path = __ROOT__.'/locales/' . $locale . '.yml';

        try {
            if (function_exists('yaml_parse') && file_exists($path) &&
                    $yaml = file_get_contents($path)
            ) {
                return yaml_parse($yaml);
            }
        } catch (Exception $e) {
            return [];
        }
        return [];
    }

    private function replaceVariables($message, $options = [])
    {
        if (is_array($options) && count($options)) {
            $variables = [];
            foreach ($options as $k => $v) {
                $variables['%{'.$k.'}'] = $v;
            }
            $message = strtr($message, $variables);
        }
        return $message;
    }

    private function getLocaleMessages($locale = null)
    {
        $locale = $locale ?: $this->config->getLocale();

        $this->loadLocaleMessages($locale);

        return $this->messages[$locale];
    }
}