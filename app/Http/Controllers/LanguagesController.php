<?php

namespace App\Http\Controllers;

use App\Models\Languages;
use Illuminate\Http\Request;

class LanguagesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $languages = Languages::all();

        return response() -> json($languages);
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
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Languages $languages)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Languages $languages)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Languages $languages)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Languages $languages)
    {
        //
    }
}
