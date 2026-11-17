<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class HealthCheckController extends Controller
{
    public function check(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'storage' => $this->checkStorage(),
            'cache' => $this->checkCache(),
        ];

        $isHealthy = collect($checks)->every(fn ($check) => $check['status'] === 'ok');

        $status = $isHealthy ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE;

        return response()->json([
            'status' => $isHealthy ? 'healthy' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'environment' => config('app.env'),
            'checks' => $checks,
        ], $status);
    }

    /**
     * @return array{status: string, message?: string}
     */
    protected function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return ['status' => 'ok'];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{status: string, message?: string}
     */
    protected function checkStorage(): array
    {
        try {
            $testFile = 'health_check_test_'.uniqid().'.tmp';
            Storage::disk('local')->put($testFile, 'health-check');
            $contents = Storage::disk('local')->get($testFile);
            Storage::disk('local')->delete($testFile);

            if ($contents !== 'health-check') {
                return [
                    'status' => 'error',
                    'message' => 'Read content does not match written content.',
                ];
            }

            return ['status' => 'ok'];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{status: string, message?: string}
     */
    protected function checkCache(): array
    {
        try {
            $key = 'health_check_key_'.uniqid();
            Cache::put($key, 'val', 10);
            $val = Cache::get($key);
            Cache::forget($key);

            if ($val !== 'val') {
                return [
                    'status' => 'error',
                    'message' => 'Cache read did not match cache write.',
                ];
            }

            return ['status' => 'ok'];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }
}
