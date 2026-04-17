<?php

namespace App\Traits;

trait CleansTrixContent
{
    public static function bootCleansTrixContent(): void
    {
        static::saving(function ($model) {
            foreach ($model->trixFields() as $field) {
                if ($model->isDirty($field) && $model->{$field}) {
                    $model->{$field} = self::cleanTrixHtml($model->{$field});
                }
            }
        });
    }

    abstract protected function trixFields(): array;

    protected static function cleanTrixHtml(string $html): string
    {
        // 把 figcaption 文字轉成 img alt，再移除 figcaption
        return preg_replace_callback(
            '/<figure[^>]*>(.*?)<\/figure>/s',
            function ($match) {
                $figure = $match[1];
                // 取出 caption 文字
                $caption = '';
                if (preg_match('/<figcaption[^>]*>(.*?)<\/figcaption>/s', $figure, $cap)) {
                    $caption = trim(strip_tags($cap[1]));
                }
                // 移除 figcaption
                $figure = preg_replace('/<figcaption[^>]*>.*?<\/figcaption>/s', '', $figure);
                // 把 caption 寫進 img alt
                if ($caption) {
                    $figure = preg_replace('/<img\s/', '<img alt="' . htmlspecialchars($caption, ENT_QUOTES) . '" ', $figure, 1);
                }
                return '<figure>' . $figure . '</figure>';
            },
            $html
        );
    }
}
