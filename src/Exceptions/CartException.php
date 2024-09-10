<?php

namespace MobileNowGroup\ShoppingCart\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class CartException extends Exception
{
    /**
     * @var string
     */
    protected $message = '';

    /**
     * @var string|int|null
     */
    protected string|int|null $customCode;

    /**
     * @var string|int|null
     */
    protected string|int|null $httpCode;

    /**
     * @var array|null
     */
    protected ?array $data;

    /**
     * @param string $customCode
     * @param array|null $data
     * @param string|null $message
     * @param int $httpCode
     */
    public function __construct(string $customCode, ?array $data = [], ?string $message = null, int $httpCode = 200)
    {
        $message = $message ?: ErrorConst::ERROR_MESSAGE[$customCode];
        parent::__construct($message);

        $this->message = $message;
        $this->httpCode = $httpCode;
        $this->customCode = $customCode;
        $this->data = $data;
    }

    /**
     * @param $request
     * @return JsonResponse
     */
    public function render($request): JsonResponse
    {
        return response()->json([
            'message' => $this->message,
            'code' => $this->customCode,
            'data' => $this->data,
        ], $this->httpCode);
    }
}
