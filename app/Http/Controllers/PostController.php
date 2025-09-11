<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $posts = Post::query()
            ->with([
                'user:id,name',
                'language:id,code'
            ])
            ->latest('id')
            ->get();

        return PostResource::collection($posts);
        // return response()->json($posts);
    }


    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePostRequest $request)
    {

        $thumbnail = null;
        // Xứ lý lưu file
        if ($request->hasFile('thumbnail')) {
            $file = $request->file('thumbnail');
            $file_name = time() . '_' . $file->getClientOriginalName();
            $thumbnail = $file->storeAs('image', $file_name);
        }

        $validated = $request->safe()->only('languages_id', 'title', 'slug', 'content', 'meta_title', 'meta_description');
        $validated['user_id'] = Auth::guard('api')->id();
        $validated['thumbnail'] = $thumbnail;

        $post = Post::create($validated);
        return $post->toResource();
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $post = Post::findOrFail($id);

        return response()->json($post);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(StorePostRequest $request, $id)
    {
        $post = Post::findOrFail($id);
        $thumbnail = null;

        if($request -> hasFile('thumbnail')) {
            Storage::delete($post->thumbnail);

            $file = $request->file('thumbnail');
            $file_name = time() . '_' . $file->getClientOriginalName();
            $thumbnail = $file->storeAs('image', $file_name);
        }

        $post->update([
            'languages_id' => $request->get('languages_id'),
            'title' => $request->get('title'),
            'slug' => $request->get('slug'),
            'content' => $request->get('content'),
            'meta_title' => $request->get('meta_title'),
            'meta_description' => $request->get('meta_description'),
            'thumbnail' => $thumbnail,
            
        ]);

        return response()->json($post);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $post = Post::findOrFail($id);
        
        $post->delete();
        Storage::delete($post->thumbnail);

        return response()->json($post, 204);
    }
}
