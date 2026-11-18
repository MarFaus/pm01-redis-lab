<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // Без событий модели: при посеве не нужно чистить кеш в Redis
    // (start.bat сеет базу до запуска Redis и чистит кеш перед стартом сайта).
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(EventSeeder::class);
    }
}
