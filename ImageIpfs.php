<?php

namespace Marketplace\Tokens;

use DB;
use GuzzleHttp\Client;
use Log;
use Marketplace\Traits\Logger;

class ImageIpfs
{
    use Logger;

    function fire($job, $data)
    {
        $client = new Client();

        $response = $client->post('https://api.pinata.cloud/pinning/pinFileToIPFS', [
            'http_errors' => false,
            'headers' => [
                'pinata_api_key' => env('PINATA_API_KEY'),
                'pinata_secret_api_key' => env('PINATA_API_SECRET_KEY'),
            ],
            'multipart' => [
                [
                    'name' => 'file',
                    'contents' => file_get_contents($data['file']),
                    'filename' => $data['filename'],
                ],
            ],
        ]);

        $content = json_decode($response->getBody()->getContents(), true);
        if ($content && $content['IpfsHash']) {
            DB::table('marketplace_tokens_tokens')
                ->where('id', $data['token'])
                ->update(['file' => $content['IpfsHash']]);
        } else {
            $job->release(100);
        }

        Log::info('PinatIPFS' . json_encode($content['IpfsHash']));
        $job->delete();
    }
}
