<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index($lang)
    {
        $category = Category::query()
            ->with([
                    'categoryTranslation' => function ($language) use ($lang) {
                        $language->whereHas('language', fn($query) => $query->where('code', $lang));
                    },
            ])
            ->get();

        return response()->json($category);
    }
}
