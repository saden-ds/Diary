<?php

namespace App\Base;

use App\Base\DataStore;
use App\Base\Query;
use Iterator;

class DataQuery extends Query implements Iterator {
    private DataStore $db;
    private ?array $data = [];


    public function __construct()
    {
        $this->db = DataStore::init();
    }

    public function db(): DataStore
    {
        return $this->db;
    }

    public function getData(): ?array
    {
        if (is_null($this->data)) {
            $this->fetchAll();
        }

        return $this->data;
    }

    public function first(?int $count = 1): ?array
    {
        if ($count > 1) {
            return $this->limit($count)->fetchAll();
        }

        return $this->limit(1)->fetch();
    }

    public function fetch(): ?array
    {
        $data = $this->db->row($this->get(), $this->getValues());

        $this->data = $data ?: [];

        return $this->data;
    }

    public function fetchAll(): ?array
    {
        $data = $this->db->data($this->get(), $this->getValues());

        $this->data = $data ?: [];

        return $this->data;
    }

    public function pluck(string $index): ?array
    {
        $this->data = $this->db->pluck($this->get(), $this->getValues(), $index);

        return $this->data;
    }

    public function delete(): bool
    {
        $arguments = func_get_args();

        $this->setDelete($arguments);

        return $this->db->query($this->get('delete'), $this->getValues());
    }

    // Iterator interface

    public function current(): mixed
    {
        $current = current($this->data);

        return $current;
    }

    public function key(): mixed
    {
        $key = key($this->data);

        return $key;
    }

    public function next(): void
    {
        next($this->data);
    }

    public function rewind(): void
    {
        if (is_null($this->data)) {
            $this->fetchAll();
        }

        reset($this->data);
    }

    public function valid(): bool
    {
        $key = key($this->data);
        $valid = $key !== NULL && $key !== FALSE;

        return $valid;
    }
}