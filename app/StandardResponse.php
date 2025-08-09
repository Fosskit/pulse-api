<?php

namespace App;

use Illuminate\Http\Response;

trait StandardResponse
{
    private function success($message, $data = null, $status = Response::HTTP_OK)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    private function failure($message, $status = Response::HTTP_INTERNAL_SERVER_ERROR)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
        ], $status);
    }
}
