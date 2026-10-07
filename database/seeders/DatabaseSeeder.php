<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'jacky40607@gmail.com'],
            ['name' => 'Paul', 'password' => Hash::make('password')],
        );

        // 示範資料只在本地／測試環境建立，production 不塞假商品
        if (app()->environment('production') || Product::query()->exists()) {
            return;
        }

        Product::factory()->shopee()->renderable(5)->create(['title' => '【示範】無線藍牙耳機 ANC 降噪 長效續航']);
    }
}
