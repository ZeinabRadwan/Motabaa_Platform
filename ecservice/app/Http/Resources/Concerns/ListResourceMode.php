<?php

namespace App\Http\Resources\Concerns;

trait ListResourceMode
{
    public bool $listOnly = false;

    public function asList(): static
    {
        $this->listOnly = true;

        return $this;
    }

    public static function listCollection($resource)
    {
        $collection = static::collection($resource);
        $collection->collection->each(function ($item) {
            if (method_exists($item, 'asList')) {
                $item->asList();
            }
        });

        return $collection;
    }
}
