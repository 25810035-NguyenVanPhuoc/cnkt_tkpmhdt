<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use App\Services\SettingsManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Cấu hình hệ thống là 1 form key-value duy nhất (không phải danh sách nhiều
 * bản ghi), nên chỉ dùng 2/4 quyền đã seed cho module settings: view để xem,
 * update để lưu.
 */
class SettingsController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:settings.view', only: ['index']),
            new Middleware('permission:settings.update', only: ['update']),
        ];
    }

    public function index(): JsonResponse
    {
        return response()->json(['data' => SettingsManager::instance()->all()]);
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $manager = SettingsManager::instance();

        foreach ($request->validated('settings') as $item) {
            $manager->set($item['key'], $item['value'] ?? null, $request->user(), group: 'store');
        }

        return response()->json(['data' => $manager->all()]);
    }
}
