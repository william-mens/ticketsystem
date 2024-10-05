<?php

namespace App;

use Log;

/*
|--------------------------------------------------------------------------
| Response Class
|--------------------------------------------------------------------------
|
| A class to hold all the application's responses.
|
 */

trait Responses
{
    public function success($data = [], $message = 'Operation Successful.', $code = '200', $logResponseData = false)
    {
        if ($logResponseData) {
            Log::info($message, [$data]);
        }
        return $this->response($data, $message, $code);
    }
    public function error($data = [], $message = 'Operation failed.', $code = '500')
    {
        return $this->response($data, $message, $code);
    }
    public function validationFailed($data = [], $message = 'Validation failed.', $code = '400')
    {
        Log::info($message, [$data]);
        return $this->response($data, $message, $code);
    }

    public function callbackResponse()
    {
        return response()->json([
            'responseCode' => '01',
            'responseMessage' => 'Callback Successful',
        ], 200);
    }

    public function response($data = [], $message = '', $code = '', $statusCode = '200')
    {
        return response()->json([
            'response_code' => $code,
            'response_message' => $message,
            'data' => $data ?? [],
        ], $statusCode);
    }
}
