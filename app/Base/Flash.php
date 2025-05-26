<?php

namespace App\Base;

use App\Base\Session;

class Flash
{
    const FLASH = 'flash_messages';
    const TYPE_ERROR = 'error';
    const TYPE_NOTICE = 'notice';
    const TYPE_ALERT = 'alert';
    const TYPE_WARNING = 'warn';

    private Session $session;


    public function __construct()
    {
        $this->session = Session::init();
    }

    public function error(?string $message = null): void
    {
        $this->message(self::TYPE_ERROR, $message);
    }

    public function notice(?string $message = null): void
    {
        $this->message(self::TYPE_NOTICE, $message);
    }

    public function alert(?string $message = null): void
    {
        $this->message(self::TYPE_ALERT, $message);
    }

    public function warning(?string $message = null): void
    {
        $this->message(self::TYPE_WARNING, $message);
    }

    public function clear(): bool
    {
        $this->session->delete(self::FLASH);

        return true;
    }

    public function get(): ?array
    {
        $data = $this->read();
        $array = null;

        $this->clear();

        if (!$data) {
            return $array;
        }

        foreach ($data as $k => $v) {
            $array[] = [
                'type'    => $k,
                'message' => $v
            ];
        }

        return $array;
    }


    private function message(string $type, ?string $message = null): ?string
    {
        if ($message === null) {
            $message = $this->get($type);

            $this->delete($type);
        } else {
            $this->create($type, $message);
        }

        return $message;
    }

    private function delete($type): bool
    {
        $data = $this->read();

        if (isset($data[$type])) {
            unset($data[$type]);
        }

        if ($data) {
            return $this->save($data);
        } else {
            return $this->clear();
        }
    }

    private function create($type, $message): bool
    {
        $data = $this->read();

        $data[$type] = $message;

        return $this->save($data);
    }

    private function read(): ?array
    {
        $json_data = $this->session->get(self::FLASH);

        if (!$json_data) {
            return null;
        }

        $data = json_decode($json_data, true);

        return is_array($data) ? $data : null;
    }

    private function save($data): bool
    {
        $this->session->set(
            self::FLASH,
            json_encode($data, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)
        );

        return true;
    }
}