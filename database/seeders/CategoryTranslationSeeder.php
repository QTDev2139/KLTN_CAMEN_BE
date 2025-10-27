<?php

namespace Database\Seeders;

use App\Models\CategoryTranslation;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategoryTranslationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CategoryTranslation::insert([
            // Category 1: Sản phẩm
            [
                'category_id' => 1,
                'language_id' => 1,
                'name' => 'Sản phẩm',
                'slug' => 'san-pham',
            ],
            [
                'category_id' => 1,
                'language_id' => 2,
                'name' => 'Products',
                'slug' => 'products',
            ],

            // Category 2: Combo 3 gói
            [
                'category_id' => 2,
                'language_id' => 1,
                'name' => 'Combo 3 gói',
                'slug' => 'combo-3-goi',
            ],
            [
                'category_id' => 2,
                'language_id' => 2,
                'name' => '3-pack Combo',
                'slug' => 'combo-3-pack',
            ],
            // Category 3: Combo 6 gói
            [
                'category_id' => 3,
                'language_id' => 1,
                'name' => 'Combo 3 gói',
                'slug' => 'combo-3-goi',
            ],
            [
                'category_id' => 3,
                'language_id' => 2,
                'name' => '3-pack Combo',
                'slug' => 'combo-3-pack',
            ],

            // Category 4: Combo 10 gói
            [
                'category_id' => 4,
                'language_id' => 1,
                'name' => 'Combo 10 gói',
                'slug' => 'combo-10-goi',
            ],
            [
                'category_id' => 4,
                'language_id' => 2,
                'name' => '10-pack Combo',
                'slug' => 'combo-10-pack',
            ],
        ]);
    }
}
