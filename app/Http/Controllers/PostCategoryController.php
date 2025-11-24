<?php

namespace App\Http\Controllers;

use App\Models\PostCategory;
use App\Models\PostCategoryTranslation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PostCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($lang)
    {
        $category = PostCategory::query()
            ->with([
                'postCategoryTranslations'
            ])
            ->get();

        return response()->json($category);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'post_category_translations' => 'required|array|min:1',
            'post_category_translations.*.name' => 'required|string|max:255',
            'post_category_translations.*.slug' => 'required|string|max:255',
            'post_category_translations.*.language_id' => 'required|exists:languages,id',
        ]);

        $category = DB::transaction(function () use ($validated) {
            $cat = PostCategory::create(); // table only has id + timestamps

            foreach ($validated['post_category_translations'] as $tr) {
                PostCategoryTranslation::create([
                    'name' => $tr['name'],
                    'slug' => $tr['slug'],
                    'language_id' => $tr['language_id'],
                    'post_category_id' => $cat->id,
                ]);
            }

            return $cat->load('postCategoryTranslations');
        });

        return response()->json($category, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(PostCategory $postCategory)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $postCategory = PostCategory::findOrFail($id);

        $validated = $request->validate([
            'post_category_translations' => 'required|array|min:1',
            'post_category_translations.*.name' => 'required|string|max:255',
            'post_category_translations.*.slug' => 'required|string|max:255',
            'post_category_translations.*.language_id' => 'required|exists:languages,id',
        ]);

        $category = DB::transaction(function () use ($validated, $postCategory) {
            $languageIds = [];

            foreach ($validated['post_category_translations'] as $tr) {
                $languageIds[] = $tr['language_id'];

                PostCategoryTranslation::updateOrCreate(
                    [
                        'post_category_id' => $postCategory->id,
                        'language_id' => $tr['language_id'],
                    ],
                    [
                        'name' => $tr['name'],
                        'slug' => $tr['slug'],
                    ]
                );
            }

            // remove translations not submitted
            PostCategoryTranslation::where('post_category_id', $postCategory->id)
                ->whereNotIn('language_id', $languageIds)
                ->delete();

            return $postCategory->load('postCategoryTranslations');
        });

        return response()->json($category, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $postCategory = PostCategory::findOrFail($id);

        DB::transaction(function () use ($postCategory) {
            $postCategory->postCategoryTranslations()->delete();
            $postCategory->delete();
        });

        return response()->json(['message' => 'Xóa danh mục bài viết thành công'], 200);
    }
}
