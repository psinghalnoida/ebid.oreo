<?php

namespace App\Libraries;

// Global API response envelope, filter-layer counterpart to
// BaseController::apiResponse() (see its docblock for the full
// rationale — every JSON response, success or error, goes out shaped
// {status, message, data}). Filters run BEFORE any controller and don't
// extend BaseController, so they call this directly on the raw
// `service('response')` instance instead.
class ApiResponse
{
    public static function send($response, $data = [], ?string $message = null, int $httpCode = 200)
    {
        $ok = $httpCode < 400;
        return $response->setStatusCode($httpCode)->setJSON([
            'status' => $ok,
            'message' => $message ?? ($ok ? 'Success' : 'Error'),
            'data' => $data ?? [],
        ]);
    }
}
