<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AnnouncementController extends Controller
{
    // List tất cả file PDF trong storage/app/public/announcement
    public function index(Request $request)
    {
        $disk = Storage::disk('public');
        $allFiles = $disk->allFiles('announcement');

        $pdfFiles = array_values(array_filter($allFiles, function ($file) {
            return strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'pdf';
        }));

        $groups = [];

        foreach ($pdfFiles as $file) {
            $parts = explode('/', str_replace('\\', '/', $file));
            // lấy phần tử thứ 1 sau 'announcement' làm category (nếu không có thì 'root')
            $category = isset($parts[1]) && $parts[1] !== '' ? $parts[1] : 'root';
            $categoryPath = 'announcement/' . $category;

            if (! isset($groups[$categoryPath])) {
                $groups[$categoryPath] = [
                    'category_name' => $category,
                    'category_path' => $categoryPath,
                    'files' => []
                ];
            }

            $groups[$categoryPath]['files'][] = [
                'name' => basename($file),
                'path' => $file,
                'encoded' => base64_encode($file),
                'url' => asset('storage/' . str_replace('\\', '/', $file)),
                'size' => $disk->size($file),
                'last_modified' => date('c', $disk->lastModified($file)),
            ];
        }

        return response()->json(array_values($groups));
    }

    public function show($encoded)
    {
        $path = base64_decode($encoded);
        if (! $path) {
            return response()->json(['message' => 'Invalid path'], 400);
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'pdf') {
            return response()->json(['message' => 'Not a PDF file'], 400);
        }

        $fullPath = $disk->path($path);

        return response()->file($fullPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
        ]);
    }
}
