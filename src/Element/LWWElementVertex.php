<?php

namespace NicPoyia\CRDT\LWW\Graph\Element;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Concrete class representing Vertex elements that can be inserted into an LWW-Element-Set.
 */
class LWWElementVertex extends LWWElement
{

    /**
     * @var string
     */
    protected string $vertex;

    /**
     * @param DateTimeInterface $timestamp
     * @param string $vertex
     */
    public final function __construct(DateTimeInterface $timestamp, string $vertex)
    {
        parent::__construct($timestamp);
        $this->vertex = $vertex;
    }

    /**
     * @inheritDoc
     */
    public function replicateNow(): LWWElement
    {
        return new self(new DateTimeImmutable(), $this->vertex);
    }

    /**
     * @inheritDoc
     */
    public function equals(LWWElement $otherElement): bool
    {
        if (!$otherElement instanceof LWWElementVertex) {
            return false;
        }
        if ($this->vertex !== $otherElement->vertex) {
            return false;
        }
        return true;
    }

    /**
     * @@inheritDoc
     */
    public function uniqueValue(): string
    {
        return "v" . $this->vertex;
    }

    /**
     * Returns the vertex value
     * @return string Vertex value
     */
    public function vertexValue(): string
    {
        return $this->vertex;
    }
}