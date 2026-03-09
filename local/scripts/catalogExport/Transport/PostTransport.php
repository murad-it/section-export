<?php

use Bitrix\Main\Web\HttpClient;

class PostTransport
{
    public function send(array $payload): array
    {
        $client = new HttpClient([
            'redirect'=>true,
            'disableSslVerification'=>true
        ]);

        $client->setHeader('Content-Type','application/json',true);

        $response = $client->post(
            TARGET_URL,
            json_encode($payload,JSON_UNESCAPED_UNICODE)
        );

        if(!$response){
            return ['status'=>'error','message'=>'Empty response'];
        }

        $decoded = json_decode($response,true);

        return $decoded ?: [
            'status'=>'error',
            'message'=>'Invalid JSON',
            'raw'=>$response
        ];
    }
}