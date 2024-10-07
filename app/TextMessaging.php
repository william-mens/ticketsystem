<?php

namespace App;

use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Http;
use Log;

class TextMessaging
{
    public function sendTextMessage($message = null, $msisdn)
    {
        Log::info('Sending text message');
        $api_key = config('app.sms.apiKey');
        $source = config('app.sms.sendId');
        $async = 0;
        $recipients = $msisdn;

        // $message = str_replace('','',$message); api_key=${smsApiKey}&to=${phoneNumber}&from=${senderId}&sms=${smsMessage}
        $message = urlencode($message);
        $url = config('app.sms.url') . "api_key=$api_key&sms=$message&to=$recipients&from=$source";
        try {
            $recipients = $msisdn;
            $request = Http::timeout(3)->get($url);
            $response = $request->getBody();
            $data = json_decode($response);
            Log::info('Text message response', ['response' => $response, 'data' => $data, 'url' => $url]);
            return true;
        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::critical('Message not sent.', [$e->getMessage()]);
            return false;
        } catch (ClientException $e) {
            Log::critical('Message not sent.', [$e]);
            return false;
        }
    }
}
