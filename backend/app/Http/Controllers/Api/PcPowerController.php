<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RemotePcSshService;
use Illuminate\Http\JsonResponse;

class PcPowerController extends Controller
{
    public function shutdown(RemotePcSshService $remotePcSshService): JsonResponse
    {
        return response()->json([
            'data' => $remotePcSshService->shutdown(),
        ]);
    }

    public function restart(RemotePcSshService $remotePcSshService): JsonResponse
    {
        return response()->json([
            'data' => $remotePcSshService->restart(),
        ]);
    }
}
