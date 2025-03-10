<?php

namespace Marketplace\Tokens\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use GuzzleHttp\Client;
use Marketplace\Tokens\Models\Token;
use Marketplace\Transactions\Models\Transaction;

class BurnBarrelTokenJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $transaction;
    protected $token;

    public function __construct(Transaction $transaction, Token $token)
    {
        $this->transaction = $transaction;
        $this->token = $token;
    }

    public function handle()
    {
        \Log::debug('token__', [$this->token]);

        $client = new Client();
        $url = env('BLOCKCHAIN_API_URL') . 'barrels/burn';
        $requestBody = [
            'tokenId' => $this->token->external_id,
        ];
        \Log::debug('requestBody__', [$requestBody]);

        try {
            $response = $client->request('PUT', $url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'x-api-key' => env('API_KEY'),
                ],
                'json' => $requestBody
            ]);

            $data = json_decode($response->getBody(), true);
            \Log::debug('bc_response__', [$data]);

            // Update transaction
            if (isset($data['data']['status'])) {
                switch ($data['data']['status']) {
                    case 'Success':
                        $this->transaction->status = Transaction::STATUS_SUCCESS;
                        break;
                    case 'Pending':
                        $this->transaction->status = Transaction::STATUS_PENDING;
                        break;
                    case 'Error':
                        $this->transaction->status = Transaction::STATUS_ERROR;
                        break;
                    case 'Failed':
                        $this->transaction->status = Transaction::STATUS_FAILED;
                        break;
                }
            }
            if (isset($data['data']['hash'])) {
                $this->transaction->transaction_hash = $data['data']['hash'];
            }

            $this->transaction->save();

            \Log::debug('update_transaction__', [$this->transaction]);
        } catch (\Exception $e) {
            \Log::error("Error burning token: " . $e->getMessage());

            $this->transaction->update([
                'status' => Transaction::STATUS_ERROR
            ]);
        }

        $this->delete();
    }
}
