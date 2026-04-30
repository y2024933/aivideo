<?php

return [
    'label' => '個人資料',
    'form' => [
        'email' => ['label' => '電子郵件'],
        'name' => ['label' => '姓名'],
        'password' => ['label' => '新密碼'],
        'password_confirmation' => ['label' => '確認新密碼'],
        'actions' => [
            'save' => ['label' => '儲存'],
        ],
    ],
    'notifications' => [
        'saved' => ['title' => '已儲存'],
    ],
    'actions' => [
        'cancel' => ['label' => '取消'],
    ],
];
