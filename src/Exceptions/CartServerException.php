<?php

namespace Fayne\ShoppingCart\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class CartServerException extends Exception
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
     */
    public function __construct(int $customCode, ?array $data = [], ?string $message = null, int $httpCode = 500)
    {
        $message = $message ?: ErrorConst::errorMessage($customCode);
        parent::__construct($message);

        $this->message = $message;
        $this->httpCode = $httpCode;
        $this->customCode = $customCode;
        $this->data = $data;
    }

    /**
     * Get the custom error code
     * @return int
     */
    public function getCustomCode(): int
    {
        return $this->customCode;
    }

    /**
     * Get the HTTP status code
     * @return int
     */
    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    /**
     * Get the additional data
     * @return array|null
     */
    public function getData(): ?array
    {
        return $this->data;
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
