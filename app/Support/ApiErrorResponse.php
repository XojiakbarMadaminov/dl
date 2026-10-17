<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiErrorResponse
{
    /**
     * @param  array<string, mixed>|object  $fields
     */
    public static function make(
        string $code,
        string $message,
        array|object $fields = [],
        int $status = 400,
    ): JsonResponse {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'fields' => empty((array) $fields) ? (object) [] : $fields,
            ],
        ], $status);
    }
}
