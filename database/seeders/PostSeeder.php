<?php

namespace Database\Seeders;

use App\Models\Post;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Post::insert([
            [
                'user_id' => 1,
                'languages_id' => 1,
                'title' => 'Test bai viet 1',
                'slug' => 'test-bai-viet-1',
                'content' => 'Noi dung test bai viet 1',
                'meta_title' => 'abc',
                'meta_description' => 'abc',
                'thumbnail' => 'image',
            ],
            [
                'user_id' => 1,
                'languages_id' => 2,
                'title' => 'Test bai viet 2',
                'slug' => 'test-bai-viet-2',
                'content' => 'Noi dung test bai viet 2',
                'meta_title' => 'abc',
                'meta_description' => 'abc',
                'thumbnail' => 'image',
            ],
            [
                'user_id' => 1,
                'languages_id' => 2,
                'title' => 'Test bai viet 3',
                'slug' => 'test-bai-viet-3',
                'content' => 'Noi dung test bai viet 3',
                'meta_title' => 'abc',
                'meta_description' => 'abc',
                'thumbnail' => 'image',
            ],
            [
                'user_id' => 1,
                'languages_id' => 1,
                'title' => 'Test bai viet 4',
                'slug' => 'test-bai-viet-4',
                'content' => 'Noi dung test bai viet 4',
                'meta_title' => 'abc',
                'meta_description' => 'abc',
                'thumbnail' => 'image',
            ],
        ]);
    }
}


                