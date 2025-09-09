<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Posts;
use Illuminate\Http\Request;

class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $post = Posts::all();

        // return response() -> json($post);
        return PostResource::collection($post);
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
        $validated = $request->safe()->only('title', 'content');

        $post = Posts::create($validated);

        // return response()->json($post, 201);
        return $post->toResource();
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $post = Posts::findOrFail($id);

        return response()->json($post);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(StorePostRequest $request, $id)
    {
        $post = Posts::findOrFail($id);

        $post->update([
            'title' => $request->get('title'),
            'content' => $request->get('content')
        ]);

        return response()->json($post);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $post = Posts::findOrFail($id);

        $post->delete();

        return response()->json($post, 204);
    }
}
