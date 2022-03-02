<?php

namespace NicPoyia\CRDT\LWW\Graph\Exception;

use Exception;
use Throwable;

/**
 * Custom exception used when no path can be found between two vertices of a graph.
 */
class PathNotFoundInGraphException extends Exception
{

    /**
     * @var string
     */
    protected string $vertexA;
    /**
     * @var string
     */
    protected string $vertexB;

    /**
     * @param string $vertexA
     * @param string $vertexB
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(string $vertexA, string $vertexB, string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->vertexA = $vertexA;
        $this->vertexB = $vertexB;
    }

    /**
     * @return string
     */
    public function vertexA(): string
    {
        return $this->vertexA;
    }

    /**
     * @return string
     */
    public function vertexB(): string
    {
        return $this->vertexB;
    }
}