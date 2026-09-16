<?php

namespace App\Exceptions;

use Exception;

class InsufficientStockException extends Exception
{
    protected array $errors = [];

    public function __construct(array $errors)
    {
        parent::__construct('Insufficient stock for some items.');
        $this->errors = $errors;
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render($request)
    {
        return response()->json([
            'message' => 'Insufficient stock in source warehouse.',
            'errors' => $this->errors,
        ], 422);
    }
}
