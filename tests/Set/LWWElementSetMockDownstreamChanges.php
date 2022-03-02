<?php

namespace NicPoyia\CRDT\LWW\Graph\Test\Set;

use NicPoyia\CRDT\LWW\Graph\Element\LWWElement;

class LWWElementSetMockDownstreamChanges
{
    /**
     * @var LWWElement[]
     */
    protected array $additions;
    /**
     * @var LWWElement[]
     */
    protected array $removals;

    /**
     * @param LWWElement[] $additions
     * @param LWWElement[] $removals
     */
    public function __construct(array $additions, array $removals)
    {
        $this->additions = $additions;
        $this->removals = $removals;
    }

    /**
     * @return LWWElement[]
     */
    public function additions(): array
    {
        return $this->additions;
    }

    /**
     * @return LWWElement[]
     */
    public function removals(): array
    {
        return $this->removals;
    }
}