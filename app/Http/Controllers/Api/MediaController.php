<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MediaFileResource;
use App\Models\MediaFile;
use App\Services\Media\ImageOptimizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Thư viện media dùng chung: một nơi để tra và dùng lại ảnh đã tải lên, thay vì mỗi chức
 * năng tự upload rời rạc không ai tra lại được.
 */
class MediaController extends Controller implements HasMiddleware
{
    private const PHYSICAL_FOLDER = 'media';

    public static function middleware(): array
    {
        return [
            new Middleware('permission:products.view', only: ['index']),
            new Middleware('permission:products.update', only: ['upload']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $files = MediaFile::query()
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%'))
            ->latest()
            ->paginate($request->integer('per_page', 60));

        return response()->json(MediaFileResource::collection($files)->response()->getData(true));
    }

    public function upload(Request $request, ImageOptimizer $optimizer): JsonResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'max:20'],
            'files.*' => ['required', 'image', 'max:8192'],
        ]);

        $created = [];

        foreach ($request->file('files') as $file) {
            /** @var UploadedFile $file */
            $meta = $optimizer->storeWithMetadata($file, self::PHYSICAL_FOLDER);

            $created[] = MediaFile::create($meta + [
                'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'anh',
                'created_by' => $request->user()?->id,
            ]);
        }

        return response()->json([
            'data' => MediaFileResource::collection($created),
            'message' => count($created).' ảnh đã lên thư viện.',
        ], 201);
    }
}
