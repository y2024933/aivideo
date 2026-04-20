<?php

namespace App\Traits;

use App\Services\ImageOptimizer;

trait OptimizesImages
{
    public static function bootOptimizesImages(): void
    {
        static::saving(function ($model) {
            $optimizer = app(ImageOptimizer::class);
            foreach ($model->imageFields() as $field) {
                if ($model->isDirty($field) && $model->$field) {
                    $optimizer->optimize($model->$field);
                }
            }
        });
    }

    /** 需要自動壓縮的圖片欄位名稱 */
    abstract public function imageFields(): array;
}
