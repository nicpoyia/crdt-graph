<?php

namespace NicPoyia\CRDT\LWW\Graph;

use NicPoyia\CRDT\LWW\Graph\Element\LWWElement;

/**
 * Class that describes the result of merge operation on an LWW-Element-Set.
 */
class LWWElementSetMergeResult
{
    /**
     * @var LWWElement[] Additions of elements received from replicas that are effective, i.e. affect the current state
     */
    protected array $effectiveAdditions;
    /**
     * @var LWWElement[] Removals of elements received from replicas that are effective, i.e. affect the current state
     */
    protected array $effectiveRemovals;

    /**
     * @param LWWElement[] $effectiveAdditions
     * @param LWWElement[] $effectiveRemovals
     */
    public function __construct(array $effectiveAdditions, array $effectiveRemovals)
    {
        $this->effectiveAdditions = $effectiveAdditions;
        $this->effectiveRemovals = $effectiveRemovals;
    }

    /**
     * @return LWWElement[]
     */
    public function effectiveAdditions(): array
    {
        return $this->effectiveAdditions;
    }

    /**
     * @return LWWElement[]
     */
    public function effectiveRemovals(): array
    {
        return $this->effectiveRemovals;
    }
}