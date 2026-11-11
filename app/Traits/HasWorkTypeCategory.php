<?php

namespace App\Traits;

use App\Models\WorkType;

/**
 * For models with both work_type_id (the organisation's own type) and work_type (its category).
 * When a specific type is chosen, the category is copied from it, so stats always use categories.
 */
trait HasWorkTypeCategory
{
    public static function bootHasWorkTypeCategory(): void
    {
        static::saving(function ($model) {
            if ($model->work_type_id && $model->isDirty('work_type_id')) {
                $category = WorkType::withTrashed()->whereKey($model->work_type_id)->value('category');

                if ($category) {
                    $model->work_type = $category;
                }
            }
        });
    }

    public function workTypeRecord()
    {
        return $this->belongsTo(WorkType::class, 'work_type_id');
    }
}
