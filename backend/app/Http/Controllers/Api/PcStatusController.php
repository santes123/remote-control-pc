<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RemotePcStatusService;
use Illuminate\Http\JsonResponse;

class PcStatusController extends Controller
{
    public function __invoke(RemotePcStatusService $remotePcStatusService): JsonResponse
    {
        return response()->json([
            'data' => $remotePcStatusService->getStatus(),
        ]);
    }
}
