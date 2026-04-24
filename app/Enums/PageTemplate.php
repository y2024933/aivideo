<?php

namespace App\Enums;

enum PageTemplate: string
{
    case Home = 'home';
    case About = 'about';
    case Projects = 'projects';
    case News = 'news';
    case Services = 'services';
    case Progress = 'progress';
    case Contact = 'contact';
    case Content = 'content';

    public function label(): string
    {
        return match ($this) {
            self::Home => '首頁',
            self::About => '關於我們',
            self::Projects => '建築業績',
            self::News => '最新消息',
            self::Services => '多元服務',
            self::Progress => '工程進度',
            self::Contact => '聯絡我們',
            self::Content => '一般內容頁',
        };
    }

    public function inertiaPage(): string
    {
        return match ($this) {
            self::Home => 'Site/Home',
            self::About => 'Site/About',
            self::Projects => 'Site/Projects/Index',
            self::News => 'Site/News/Index',
            self::Services => 'Site/Services',
            self::Progress => 'Site/Progress',
            self::Contact => 'Site/Contact',
            self::Content => 'Site/Content',
        };
    }

    /** 後台表單可編輯的欄位（共用欄位不列入，僅列模板專屬欄位） */
    public function availableFields(): array
    {
        return match ($this) {
            self::Home => [],
            self::About => ['summary', 'content', 'cover_image_path', 'gallery', 'faq_items'],
            self::Projects => ['summary', 'content', 'cover_image_path', 'faq_items'],
            self::News => ['content', 'cover_image_path', 'faq_items'],
            self::Services => ['summary', 'content', 'cover_image_path', 'faq_items'],
            self::Progress => ['summary', 'content', 'cover_image_path', 'faq_items'],
            self::Contact => ['content', 'cover_image_path', 'faq_items'],
            self::Content => ['summary', 'content', 'cover_image_path', 'gallery', 'faq_items'],
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
