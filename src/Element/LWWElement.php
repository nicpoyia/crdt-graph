<?php

namespace NicPoyia\CRDT\LWW\Graph\Element;

use DateTimeInterface;

/**
 * Abstract class representing any element that can be inserted into an LWW-Element-Set.
 */
abstract class LWWElement
{
    /**
     * @var DateTimeInterface
     */
    protected DateTimeInterface $timestamp;

    /**
     * @param DateTimeInterface $timestamp
     */
    protected function __construct(DateTimeInterface $timestamp)
    {
        $this->timestamp = $timestamp;
    }

    /**
     * @return DateTimeInterface
     */
    public function timestamp(): DateTimeInterface
    {
        return $this->timestamp;
    }

    /**
     * Checks equality between current and other element.
     * Only values are compared due to the nature of the implemented data structure.
     * @param LWWElement $otherElement
     * @return bool
     */
    abstract public function equals(LWWElement $otherElement): bool;

    /**
     * Replicates the element for use in the present (add/remove), i.e. maintains value(s) but changes the timestamp.
     * @return static Newly created (replicated) element of the same class
     */
    abstract public function replicateNow(): LWWElement;

    /**
     * Provides a value representing the element in a unique way independently of concrete class.
     * For example, if we have two concrete classes with the same exact values, they will not generate the same unique value.
     * @return string The unique value of the element
     */
    abstract public function uniqueValue():string;

}