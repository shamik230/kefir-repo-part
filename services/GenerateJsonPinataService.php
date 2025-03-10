<?php

namespace Marketplace\Tokens\Services;


use Project\Services\RequestsService;

class GenerateJsonPinataService
{
    public function generate(array $tokenData): string
    {
        $request = new RequestsService(env('APP_URL'), ['headers' =>
                [
                    'pinata_api_key' => env('PINATA_API_KEY'),
                    'pinata_secret_api_key' => env('PINATA_API_SECRET_KEY')]
            ]
        );

        $data = [
            "pinataMetadata" => [
                "name" => "metadata.json",
            ],
            "pinataContent" => $tokenData,
        ];
        $response = $request->postJson('https://api.pinata.cloud/pinning/pinJSONToIPFS', true, $data);

        $jsonData = $response->payload();

        return $jsonData['IpfsHash'] ? env('PINATA_URL', 'https://gateway.pinata.cloud/ipfs/') . $jsonData['IpfsHash'] : "";

    }
}

