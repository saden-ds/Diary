<?php

namespace App\Base;

class File {

    public
        $name, 
        $directory_path, 
        $path, 
        $handle, 
        $size, 
        $type, 
        $extension,
        $original_name;
    protected $config;

    public function __construct($name) {
        $this->config = Config::init();
        $this->name = basename($name);
        $this->extension = pathinfo($this->name, PATHINFO_EXTENSION);
        $this->directory_path = dirname($name);
        $this->path = $this->directory_path . '/' . $this->name;
    }

    public function open($mode = 'w') {
        if ($this->isDir($this->directory_path)) {
            $this->handle = fopen($this->path, $mode);
            return $this->handle;
        }
        return false;
    }

    public function seek($offset, $whence = SEEK_SET) {
        return fseek($this->handle, $offset, $whence);
    }

    public function write($string) {
        return fwrite($this->handle, $string);
    }

    public function putcsv($fields, $separator = "\t") {
        return fputcsv($this->handle, $fields, $separator);
    }

    public function getcsv($length = 1000, $separator = "\t") {
        return fgetcsv($this->handle, $length, $separator);
    }

    public function gets($length = null) {
        if ($length) {
            return fgets($this->handle, $length);
        }

        return fgets($this->handle);
    }

    public function getc() {
        return fgetc($this->handle);
    }

    public function getcsvSeparator($string) {
        if (strpos($string, 'sep=') !== 0) {
            return null;
        }

        list($name, $value) = explode('=', $string);

        return trim($value);
    }

    public function close() {
        return fclose($this->handle);
    }

    public function size() {
        return filesize($this->path);
    }

    public function read() {
        return readfile($this->path);
    }

    public function readBinary($length) {
        return fread($this->handle, $length);
    }

    public function unlink() {
        return unlink($this->path);
    }

    public function exists() {
        return file_exists($this->path);
    }

    public function getContents() {
        return file_get_contents($this->path);
    }

    public function putContents($content) {
        if ($this->isDir($this->directory_path)) {
            return file_put_contents($this->path, $content);
        }
        return false;
    }

    public function endOfFile() {
        return feof($this->handle);
    }

    public function moveUploadedFile($name) {
        $old_path = $this->path;

        if (!is_uploaded_file($this->path)) {
        return $this->copy($name);
        }

        if ($this->setPathAndCheckDirectory($name)) {
            return move_uploaded_file($old_path, $this->path);
        }
        return false;
    }

    public function rename($name) {
        $old_path = $this->path;

        if ($this->setPathAndCheckDirectory($name)) {
            return rename($old_path, $this->path);
        }
        return false;
    }

    public function copy($name) {
        $old_path = $this->path;

        if ($this->setPathAndCheckDirectory($name)) {
            return copy($old_path, $this->path);
        }
        return false;
    }

    public function setPathAndCheckDirectory($name) {
        $directory_path = dirname($name);

        if ($this->isDir($directory_path)) {
            $this->name = basename($name);
            $this->directory_path = $directory_path;
            $this->path = $this->directory_path . '/' . $this->name;

            return $this->path;
        }
        return null;
    }

    public function getChecksum() {
        if ($this->exists()) {
            return md5_file($this->path);
        }
        return null;
    }

    public function touchDir() {
        return $this->isDir($this->directory_path);
    }

    public function isReadable() {
        return is_readable($this->path);
    }

    public function isDir($target_directory, $rights = 0777) {
        $directories = explode('/', $target_directory);
        $gid = $this->config->get('gid');
        $is_dir = false;
        $path = '';

        foreach ($directories as $directory) {
            $path .= $directory . '/';

            if (is_dir($path)) {
                $is_dir = true;

                continue;
            }

            if (strlen($path) === 0) {
                continue;
            }

            $old = umask(0);

            if (@mkdir($path, $rights) === true) {
                $is_dir = true;
            } else {
                $is_dir = is_dir($path);
            }

            if ($is_dir && $gid) {
                $is_dir = chgrp($path, $gid);
            }

            umask($old);

            if (!$is_dir) {
                break;
            }
        }

        return $is_dir;
    }
}