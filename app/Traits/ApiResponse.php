<?php

namespace App\Traits;

trait ApiResponse
{

    protected function successResponse($data = [], $message = 'تمت العملية بنجاح', $code = 200)
    {
        return response()->json([
            'status'  => 'success',
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    protected function errorResponse($message = 'حدث خطأ ما', $code = 400, $errors = null)
    {
        return response()->json([
            'status'  => 'error',
            'code'    => $code,
            'message' => $message,
            'errors'  => $errors,
        ], $code);
    }
}
