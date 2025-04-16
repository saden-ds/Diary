<?php

namespace App\Base;

class Query {
    private ?int $offset = null;
    private ?int $limit = null;
    private bool $distinct = false;
    private array $select = [];
    private array $select_values = [];
    private array $delete = [];
    private array $from = [];
    private array $joins = [];
    private array $joins_values = [];
    private array $where = [];
    private array $having = [];
    private array $groups = [];
    private array $orders = [];
    private array $values = [];


    public function __toString()
    {
        return $this->get();
    }

    public function get(?string $operation = 'select'): string
    {
        $query_string = '';

        if ($operation == 'delete') {
            $query_string = '
                delete' . $this->getDeleteString();
        } else {
            $query_string = '
                select' . $this->getDistinct() .
                $this->getSelectString();
        }

        return $query_string .
            $this->getFromString() .
            $this->getJoinsString() .
            $this->getWhereString() .
            $this->getHavingString() .
            $this->getGroupString() .
            $this->getOrderString() .
            $this->getLimitString() . '
        ';
    }

    public function select(): Query
    {
        $arguments = func_get_args();

        $this->add('select', $arguments);

        return $this;
    }

    public function getSelectString(): string
    {
        if (!$this->select) {
            return ' *';
        }

        return ' ' . implode(', ', $this->select);
    }

    public function setDelete($tables = null): void
    {
        $this->delete = $tables ?: [];
    }

    public function getDeleteString(): string
    {
        if (!$this->delete) {
            return '';
        }

        return ' ' . implode(', ', $this->delete);
    }

    public function distinct(bool $value = true): Query
    {
        $this->distinct = $value;

        return $this;
    }

    public function getDistinct(): string
    {
        return $this->distinct ? ' distinct' : '';
    }

    public function from(): Query
    {
        $arguments = func_get_args();

        $this->add('from', $arguments);

        return $this;
    }

    public function getFromString(): string
    {
        return '
        from ' . implode(', ', $this->from);
    }

    public function join(string $join, ?array $values = null): Query
    {
        $this->joins[] = 'join ' . $join;
        $this->add('joins_values', $values);

        return $this;
    }

    public function leftJoin(string $join, $values = null): Query
    {
        $this->joins[] = 'left join ' . $join;
        $this->add('joins_values', $values);

        return $this;
    }

    public function getJoinsString(): string
    {
        if (!$this->joins) {
            return '';
        }

        return '
        ' . implode('
        ', $this->joins);
    }

    public function where(string $where, $values = null): Query
    {
        $this->where[] = $where;
        $this->add('values', $values);

        return $this;
    }

    public function having(string $having, ?array $values = null): Query
    {
        $this->having[] = $having;
        $this->add('values', $values);

        return $this;
    }

    public function getWhereString(): string
    {
        if (!$this->where) {
            return '';
        }

        return '
        where ' . implode('
            and ', $this->where);
    }

    public function getHavingString(): string
    {
        if (!$this->having) {
            return '';
        }

        return '
        having ' . implode('
            and ', $this->having);
    }

    public function order(): Query
    {
        $arguments = func_get_args();

        $this->add('orders', $arguments);

        return $this;
    }

    public function getOrderString(): string
    {
        if (!$this->orders) {
            return '';
        }

        return '
        order by ' . implode(', ', $this->orders);
    }

    public function group(): Query
    {
        $arguments = func_get_args();

        $this->add('groups', $arguments);

        return $this;
    }

    public function getGroupString(): string
    {
        if (!$this->groups) {
            return '';
        }

        return '
        group by ' . implode(', ', $this->groups);
    }

    public function limit(int $value): Query
    {
        $this->limit = $value;

        return $this;
    }

    public function offset(int $value): Query
    {
        $this->offset = $value;

        return $this;
    }

    public function getLimitString(): string
    {
        if (trim($this->limit ?: '') === '') {
            return '';
        }

        $offset = '';
        $limit = (int)$this->limit;

        if (trim($this->offset ?: '') !== '') {
            $offset = (int)$this->offset . ', ';
        }

        return '
        limit ' . $offset . $limit;
    }

    public function value(string $value, ?array $name = null): Query
    {
        if ($name) {
            $this->values[$name] = $value;
        } else {
            $this->values[] = $value;
        }

        return $this;
    }

    public function values(array $values): Query
    {
        $this->values = array_merge($this->values, $values);

        return $this;
    }

    public function selectValues(array $values): Query
    {
        $this->select_values = array_merge($this->select_values, $values);

        return $this;
    }

    public function getValues(): array
    {
        return array_merge($this->select_values, $this->joins_values, $this->values);
    }

    public function placeholders(array $keys): string
    {
        return str_repeat('?,', count($keys) - 1) . '?';
    }


    private function add(string $name, $value): void
    {
        if (!property_exists($this, $name)) {
            return;
        }

        if (is_array($value)) {
            $this->$name = array_merge($this->$name, $value);
        } elseif (trim($value ?: '') !== '') {
            $this->$name[] = $value;
        }
    }
}