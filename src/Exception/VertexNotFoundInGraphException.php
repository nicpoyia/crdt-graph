<?php

namespace NicPoyia\CRDT\LWW\Graph\Exception;

use Exception;
use Throwable;

/**
 * Custom exception used when a vertex is not found on a graph, while it is required for the intended operation.
 */
class VertexNotFoundInGraphException extends Exception
{

    /**
     * @var string
     */
    protected string $vertexValue;

    /**
     * @param string $vertexValue
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(string $vertexValue, string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->vertexValue = $vertexValue;
    }

    /**
     * @return string
     */
    public function vertexValue(): string
    {
        return $this->vertexValue;
    }

}