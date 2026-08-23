<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

trait ApiResponse
{
    protected function success($data = null, string $message = null, int $code = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message ?? __('auth.general.ok'),
        ];

        if ($data instanceof ResourceCollection || $data instanceof LengthAwarePaginator) {
            $response['data'] = $data->response()->getData(true)['data'] ?? $data;
            if ($data instanceof LengthAwarePaginator) {
                $response['meta'] = [
                    'current_page' => $data->currentPage(),
                    'last_page'    => $data->lastPage(),
                    'per_page'     => $data->perPage(),
                    'total'        => $data->total(),
                ];
            }
        } elseif ($data instanceof JsonResource) {
            $response['data'] = $data->response()->getData(true)['data'] ?? $data;
        } else {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }

    protected function created($data = null, string $message = null): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    protected function error(string $message = null, int $code = 400, $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message ?? __('auth.general.validation_failed'),
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    protected function notFound(string $message = null): JsonResponse
    {
        return $this->error($message ?? __('auth.general.not_found'), 404);
    }

    protected function forbidden(string $message = null): JsonResponse
    {
        return $this->error($message ?? __('auth.general.unauthorized'), 403);
    }

    protected function unauthorized(string $message = null): JsonResponse
    {
        return $this->error($message ?? __('auth.general.unauthenticated'), 401);
    }

    protected function paginated(LengthAwarePaginator $paginator, string $message = null): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message ?? __('auth.general.ok'),
            'data'    => $paginator->items(),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }
}
