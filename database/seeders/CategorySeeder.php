<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Category::insert([
            [
                'name' => 'sản phẩm',
                'slug' => 'san-pham',
                'language_id' => 1,
            ],
            [
                'name' => 'Combo 3 gói',
                'slug' => 'combo-3-goi',
                'language_id' => 1,
            ],
            [
                'name' => 'Combo 10 gói',
                'slug' => 'combo-10-goi',
                'language_id' => 1,
            ],
        ]);
    }
}
