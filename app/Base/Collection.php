<?php

namespace App\Base;

class Collection
{
    private ?array $data = null;
    private int $page = 1;
    private int $limit = 20;
    private int $offset = 0;
    private ?string $path = null;
    private ?string $sort = null;
    private bool $has_more = false;

    public function __construct(?array $params = null, ?array $data = null)
    {
        $this->data = $data;

        if (isset($params['page']) && intval($params['page']) > 1) {
            $this->page = intval($params['page']);
        }

        if (isset($params['limit']) && intval($params['limit'])) {
            $this->limit = intval($params['limit']);
        }

        if (isset($params['sort'])) {
            $this->sort = $params['sort'];
        }

        if (isset($params['path'])) {
            $this->path = $params['path'];
        }

        if ($this->page > 1) {
            $this->offset = ($this->page - 1) * $this->limit;
        }
    }

    public function __set($name, $value)
    {
        $method_name = 'set'.str_replace('_', '', ucwords($name, '_'));

        if (method_exists($this, $method_name)) {
            return $this->$method_name($value);
        }

        trigger_error("Undefined collection property: {$name}", E_USER_NOTICE);
    }

    public function &__get($name)
    {
        $method_name = 'get'.str_replace('_', '', ucwords($name, '_'));

        if (method_exists($this, $method_name)) {
            $value = $this->$method_name();
            return $value;
        }
        return null;
    }

    public function __invoke($i = null)
    {
        if ($i) {
            return isset($this->data[$i]) ? $this->data[$i] : null;
        }

        return $this->data;
    }

    public function __toString(): string
    {
        return json_encode(
            $this->data,
            JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT
        );
    }

    public function getSortParam(): array
    {
        $sort_column = null;
        $sort_direction = null;
        $sort_param = $this->sort;

        if ($sort_param && strpos($sort_param, '.') !== false) {
            list($sort_column, $sort_direction) = explode('.', $sort_param);
        } else {
            $sort_column = $sort_param;
        }

        if ($sort_direction != 'asc') {
            $sort_direction = 'desc';
        }

        return [$sort_column, $sort_direction];
    }

    public function getSortBlock($column, $title): array
    {
        $sort = [$column];
        $sort_param = $this->getSortParam();
        $params = null;
        $path = $this->path;
        $class_name = 'sort';

        if ($sort_param[0] != $column) {
            $sort[] = 'desc';
        } elseif ($sort_param[1] == 'desc') {
            $class_name .= ' sort_desc';
            $sort[] = 'asc';
        } else {
            $class_name .= ' sort_asc';
            $sort = [];
        }

        if (strpos($path, '?') === false) {
            $path .= '?';
        } else {
            $path .= '&';
        }

        return [
            'title' => $title,
            'path' => $path . http_build_query([
                'sort' => implode('.', $sort)
            ]),
            'class_name' => $class_name
        ];
    }

    public function append($value): void
    {
        $this->data[] = $value;
    }

    public function isEmpty(): bool
    {
        return empty($this->data) && $this->page == 1;
    }

    public function setData($value): void
    {
        if (!$value) {
            $this->data = null;

            return;
        }

        if (count($value) > $this->limit) {
            $this->has_more = true;

            array_pop($value);
        }

        $this->data = $value;
    }

    public function getData()
    {
        return $this->data;
    }

    public function setHasMore($value): void
    {
        $this->has_more = !!$value;
    }

    public function getHasMore(): bool
    {
        return $this->has_more;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getNextPage(): int
    {
        return $this->page + 1;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function hasMore(): bool
    {
        return $this->getHasMore();
    }

    public function toArray()
    {
        if ($this->data) {
            return json_decode(json_encode($this->data), true);
        }

        return $this->data;
    }
}