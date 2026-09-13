<?php

namespace App\Models\Concerns;

trait StoresNamedFilePath
{
    public static function bootStoresNamedFilePath(): void
    {
        static::creating(function ($model) {
            foreach ($model->storedNamedFileColumns() as $column) {
                if ($model->getAttribute($column) === null) {
                    $model->setAttribute($column, '');
                }
            }
        });
    }

    protected function persistStoredNamedPath(string $column, ?string $fileName): void
    {
        $value = $fileName ?? '';
        if (!$this->exists) {
            $this->setAttribute($column, $value);

            return;
        }

        $this->forceFill([$column => $value])->save();
    }

    /**
     * @return array<int, string>
     */
    abstract protected function storedNamedFileColumns(): array;
}
