<?php

namespace Marketplace\Tokens\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use GuzzleHttp\Client;
use Marketplace\Barrel\Models\Barrel;
use Marketplace\BlockchainAccount\Models\BlockchainAccount;
use Marketplace\KefiriumCashback\Models\KefiriumCashback;
use Marketplace\Transactions\Models\Transaction;

class MintBarrelTokenJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $transaction;
    protected $blockchainAccount;
    protected $barrel;
    protected $kefiriumCashback;

    public function __construct(
        Transaction $transaction,
        BlockchainAccount $blockchainAccount,
        Barrel $barrel,
        KefiriumCashback $kefiriumCashback
    ) {
        $this->transaction = $transaction;
        $this->blockchainAccount = $blockchainAccount;
        $this->barrel = $barrel;
        $this->kefiriumCashback = $kefiriumCashback;
    }

    public function handle()
    {
        \Log::debug('transaction__', [$this->transaction]);
        \Log::debug('blockchainAccount__', [$this->blockchainAccount]);
        \Log::debug('barrel__', [$this->barrel]);
        \Log::debug('kefiriumCashback__', [$this->kefiriumCashback]);

        try {
            $bcBarrelType = $this->barrel->bc_barrel_type_id;

            $client = new Client();
            $url = env('BLOCKCHAIN_API_URL') . 'barrels/mint';
            $requestBody = [
                'to' => $this->blockchainAccount->address,
                'typeId' => $bcBarrelType,
            ];
            \Log::debug('requestBody__', [$requestBody]);

            $response = $client->request('PUT', $url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'x-api-key' => env('API_KEY'),
                ],
                'json' => $requestBody
            ]);

            $responseBody = json_decode($response->getBody(), true);
            \Log::debug('responseBody__', [$responseBody]);

            $data = $responseBody['data'];
            \Log::debug('data__', [$data]);

            // Update Transaction
            if (isset($data['hash'])) {
                $this->transaction->transaction_hash = $data['hash'];
            }

            if (isset($data['status'])) {
                switch ($data['status']) {
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

            $this->transaction->save();

            \Log::debug('update_transaction__', [$this->transaction]);
        } catch (\Exception $e) {
            \Log::error("Error minting token: " . $e->getMessage());

            $this->transaction->update([
                'status' => Transaction::STATUS_ERROR,
            ]);

            $this->kefiriumCashback->delete();
        }

        $this->delete();
    }
}
