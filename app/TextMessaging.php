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
        $source = config('app.sms.senderId');

        $recipients = $msisdn;
        Log::info('Sending text message', [$api_key, $source, $recipients]);

        // $message = str_replace('','',$message); api_key=${smsApiKey}&to=${phoneNumber}&from=${senderId}&sms=${smsMessage}
        $message = urlencode($message);
        $url = config('app.sms.url') . "&api_key=$api_key&to=$recipients&from=$source&sms=$message";
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
