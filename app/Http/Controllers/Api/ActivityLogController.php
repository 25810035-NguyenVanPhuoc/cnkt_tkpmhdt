<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ActivityLogController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:settings.view'),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $lines = ActivityLogger::instance()->tail($request->integer('lines', 200));

        return response()->json(['data' => array_reverse($lines)]);
    }
}
