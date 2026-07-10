<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GuacamoleLinkService;
use App\Services\RemotePcStatusService;
use Illuminate\Http\JsonResponse;

class PcControlLinkController extends Controller
{
    public function __invoke(
        RemotePcStatusService $remotePcStatusService,
        GuacamoleLinkService $guacamoleLinkService
    ): JsonResponse {
        $status = $remotePcStatusService->getStatus();

        if ($status['status'] !== 'available') {
            return response()->json([
                'data' => [
                    'ready' => false,
                    'message' => 'El PC todavía no está disponible para control remoto.',
                    'status' => $status['status'],
                    'control_url' => null,
                ],
            ], 409);
        }

        return response()->json([
            'data' => [
                'ready' => true,
                'message' => 'Control remoto disponible.',
                'status' => $status['status'],
                'control_url' => $guacamoleLinkService->getControlUrl(),
            ],
        ]);
    }
}
