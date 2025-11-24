<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\Language;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($lang = null)
    {
        // nếu không truyền lang hoặc truyền 'all' => lấy tất cả bản dịch
        if (is_null($lang) || $lang === '' || $lang === 'all') {
            $languageId = null;
        } else {
            $languageId = is_numeric($lang) ? (int) $lang : Language::where('code', $lang)->value('id');
        }

        $posts = Post::query()
            ->with([
                'user:id,name',
                'postCategory' => function ($q) use ($languageId) {
                    if ($languageId !== null) {
                        $q->whereRelation('postCategoryTranslations', 'language_id', $languageId)
                          ->with(['postCategoryTranslations' => fn($qt) => $qt->where('language_id', $languageId)]);
                    } else {
                        $q->with('postCategoryTranslations');
                    }
                },
                'postTranslations' => function ($q) use ($languageId) {
                    if ($languageId !== null) {
                        $q->where('language_id', $languageId);
                    }
                    // nếu $languageId === null => không filter, lấy tất cả translations
                },
            ])
            ->orderBy('id', 'desc')
            ->get();

        return PostResource::collection($posts);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePostRequest $request)
    {
        $thumbnail = null;
        if ($request->hasFile('thumbnail')) {
            $file = $request->file('thumbnail');
            $file_name = time() . '_' . $file->getClientOriginalName();
            $thumbnail = $file->storeAs('post_img', $file_name, 'public');
        }

        $data = $request->validated();

        $post = Post::create([
            'user_id' => Auth::guard('api')->id(),
            'thumbnail' => $thumbnail,
            'status' => $data['status'] ?? true,
            'post_category_id' => $data['post_category_id'] ?? null,
        ]);

        foreach ($data['post_translations'] as $tr) {
            PostTranslation::create([
                'post_id' => $post->id,
                'language_id' => $tr['language_id'],
                'title' => $tr['title'],
                'slug' => $tr['slug'],
                'content' => $tr['content'],
                'meta_title' => $tr['meta_title'] ?? null,
                'meta_description' => $tr['meta_description'] ?? null,
            ]);
        }

        return new PostResource($post->load('postTranslations', 'user'));
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $post = Post::with([
            'user:id,name',
            'postTranslations'
        ])->findOrFail($id);

        return new PostResource($post);
    }



    /**
     * Update the specified resource in storage.
     */
    public function update(StorePostRequest $request, $id)
    {
        $post = Post::findOrFail($id);

        $thumbnail = $post->thumbnail;
        if ($request->hasFile('thumbnail')) {
            Storage::disk('public')->delete($post->thumbnail);
            $file = $request->file('thumbnail');
            $file_name = time() . '_' . $file->getClientOriginalName();
            $thumbnail = $file->storeAs('post_img', $file_name, 'public');
        }

        $data = $request->validated();

        $post->update([
            'thumbnail' => $thumbnail,
            'status' => $data['status'] ?? $post->status,
            'post_category_id' => $data['post_category_id'] ?? $post->post_category_id,
        ]);

        // cập nhật / tạo translations
        foreach ($data['post_translations'] as $tr) {
            PostTranslation::updateOrCreate(
                [
                    'post_id' => $post->id,
                    'language_id' => $tr['language_id'],
                ],
                [
                    'title' => $tr['title'],
                    'slug' => $tr['slug'],
                    'content' => $tr['content'],
                    'meta_title' => $tr['meta_title'] ?? null,
                    'meta_description' => $tr['meta_description'] ?? null,
                ]
            );
        }

        // xóa translations không được gửi (nếu cần)
        $sentLangs = collect($data['post_translations'])->pluck('language_id')->toArray();
        PostTranslation::where('post_id', $post->id)
            ->whereNotIn('language_id', $sentLangs)
            ->delete();

        return new PostResource($post->load('postTranslations', 'user'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $post = Post::findOrFail($id);

        // xóa thumbnail trước khi xóa bản ghi
        Storage::disk('public')->delete($post->thumbnail);

        // xóa translations và post
        $post->postTranslations()->delete();
        $post->delete();

        return response()->json(null, 204);
    }
}
