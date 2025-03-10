<?php

namespace Marketplace\Tokens\DTO;

use Illuminate\Support\Facades\Validator;
use Log;
use Marketplace\Tokens\Controllers\TokenController;
use ValidationException;

class VerifyTokenOwnershipDTO
{
    const
        URI_PARAMETER = 'uri',
        SECRET_KEY_PARAMETER = 'secretKey';

    /**
     * @var int
     */
    private $tokenId;

    /**
     * @var string
     */
    private $secretKey;

    /**
     * @param string $uri
     * @param string $secretKey
     *
     * @throws ValidationException
     */
    public function __construct($uri, $secretKey)
    {
        $this->secretKey = $secretKey;
        $this->tokenId = $this->getTokenIdFromURI($uri);
    }

    /**
     * @param array $data
     * @return VerifyTokenOwnershipDTO
     *
     * @throws ValidationException
     */
    public static function hydrate($data)
    {
        $validator = Validator::make($data, [
            self::URI_PARAMETER => 'required',
            self::SECRET_KEY_PARAMETER => 'required',
        ]);

        if ($validator->fails()) {
            self::logErrors($validator->errors()->toArray(), $data);
            throw new ValidationException("Ошибка валидации данных");
        }
        $parameters = $validator->validated();
        return new VerifyTokenOwnershipDTO($parameters[self::URI_PARAMETER], $parameters[self::SECRET_KEY_PARAMETER]);
    }

    /**
     * @param string $uri
     * @return int
     *
     * @throws ValidationException
     */
    protected function getTokenIdFromURI($uri)
    {
        $matches = [];
        if (!preg_match(
            '/(^http|https):\/\/([\w\.]+)(:[\d]+)*\/collection\/token\/(?<tokenId>.[\d]+$)/',
            $uri,
            $matches
        )) {
            throw new ValidationException("uri не соотвествует формату");
        }

        if (!$tokenId = $matches['tokenId'] ?? null) {
            throw new ValidationException("В uri не найден токен");
        }

        if (!is_numeric($tokenId)) {
            throw new ValidationException("Токен должен быть численного значение");
        }

        return (int)$tokenId;
    }

    private static function logErrors($errorsPack, $data)
    {
        $message = "";
        foreach ($errorsPack as $field => $errors) {
            $message .= sprintf("%s: %s", $field, implode(',', $errors));
        }
        Log::error(
            sprintf("Ошибка валидации: %s", $message),
            [
                'section' => TokenController::LOG_SECTION,
                'data' => $data,
            ]
        );
    }

    /**
     * @return int
     */
    public function getTokenId()
    {
        return $this->tokenId;
    }

    /**
     * @param int $tokenId
     * @return VerifyTokenOwnershipDTO
     */
    public function setTokenId(int $tokenId)
    {
        $this->tokenId = $tokenId;
        return $this;
    }

    /**
     * @return string
     */
    public function getSecretKey()
    {
        return $this->secretKey;
    }

    /**
     * @param string $secretKey
     * @return VerifyTokenOwnershipDTO
     */
    public function setSecretKey(string $secretKey)
    {
        $this->secretKey = $secretKey;
        return $this;
    }
}
