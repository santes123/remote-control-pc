<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WakeOnLanService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use RuntimeException;

class PcWakeController extends Controller
{
    public function __invoke(WakeOnLanService $wakeOnLanService): JsonResponse
    {
        $mac = (string) config('remote_pc.wol.mac');
        $broadcast = (string) config('remote_pc.wol.broadcast');
        $port = (int) config('remote_pc.wol.port');
        $host = (string) config('remote_pc.host');

        if (blank($mac)) {
            return response()->json([
                'data' => [
                    'success' => false,
                    'message' => 'Falta REMOTE_PC_MAC.',
                ],
            ], 422);
        }

        try {
            $result = $wakeOnLanService->wake($mac, $broadcast, $port, $host);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'data' => [
                    'success' => false,
                    'message' => $e->getMessage(),
                ],
            ], 422);
        } catch (RuntimeException $e) {
            return response()->json([
                'data' => [
                    'success' => false,
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }

        return response()->json([
            'data' => [
                ...$result,
                'broadcast' => $broadcast,
                'port' => $port,
            ],
        ]);
    }
}
