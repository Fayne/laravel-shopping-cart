<?php

namespace MobileNowGroup\ShoppingCart\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class CartClientException extends Exception
{
    /**
     * @var string
     */
    protected $message = '';

    /**
     * @var int|null
     */
    protected ?int $customCode;

    /**
     * @var int|null
     */
    protected ?int $httpCode;

    /**
     * @var array|null
     */
    protected ?array $data;

    /**
     * @param int $customCode
     * @param array|null $data
     * @param string|null $message
     * @param int $httpCode
     * @param Exception|null $previous
     */
    public function __construct(int $customCode, ?array $data = [], ?string $message = null, int $httpCode = 200, ?Exception $previous = null)
    {
        $message = $message ?: ErrorConst::errorMessage($customCode);
        parent::__construct($message, 0, $previous);

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
