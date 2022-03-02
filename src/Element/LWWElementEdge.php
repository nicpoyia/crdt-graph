<?php

namespace NicPoyia\CRDT\LWW\Graph\Element;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Concrete class representing Edge elements that can be inserted into an LWW-Element-Set.
 */
class LWWElementEdge extends LWWElement
{

    /**
     * @var string
     */
    protected string $vertexA;
    /**
     * @var string|null
     */
    protected string $vertexB;

    /**
     * @param DateTimeInterface $timestamp
     * @param string $vertexA
     * @param string $vertexB
     */
    public final function __construct(DateTimeInterface $timestamp, string $vertexA, string $vertexB)
    {
        parent::__construct($timestamp);
        $this->vertexA = $vertexA;
        $this->vertexB = $vertexB;
    }


    /**
     * @inheritDoc
     */
    public function replicateNow(): LWWElement
    {
        return new self(new DateTimeImmutable(), $this->vertexA, $this->vertexB);
    }

    /**
     * @inheritDoc
     */
    public function equals(LWWElement $otherElement): bool
    {
        if (!$otherElement instanceof LWWElementEdge) {
            return false;
        }
        if ($this->vertexA !== $otherElement->vertexA) {
            return false;
        }
        if ($this->vertexB !== $otherElement->vertexB) {
            return false;
        }
        return true;
    }

    /**
     * @@inheritDoc
     */
    public function uniqueValue(): string
    {
        return "e" . $this->vertexA . "," . $this->vertexB;
    }

    /**
     * Returns the vertex A value
     * @return string Value of vertex A
     */
    public function vertexValA(): string
    {
        return $this->vertexA;
    }

    /**
     * Returns the vertex B value
     * @return string Value of vertex B
     */
    public function vertexValB(): string
    {
        return $this->vertexB;
    }
}