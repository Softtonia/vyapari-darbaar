<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;

trait AutoSortOrder
{
    /**
     * Boot the trait to attach the creating event.
     */
    protected static function bootAutoSortOrder(): void
    {
        static::creating(function (Model $model) {
            $sortColumn = $model->getSortOrderColumn();

            // If sort_order is entirely unset or explicitly null
            if (! array_key_exists($sortColumn, $model->getAttributes()) || $model->getAttribute($sortColumn) === null) {
                $maxSortOrder = $model->newQuery()->max($sortColumn);
                $model->setAttribute($sortColumn, $maxSortOrder !== null ? $maxSortOrder + 1 : 1);
            }
        });
    }

    /**
     * Get the name of the sort order column.
     * Can be overridden by the model if it uses a different column name.
     */
    public function getSortOrderColumn(): string
    {
        return 'sort_order';
    }
}
