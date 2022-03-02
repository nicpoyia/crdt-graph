<?php

namespace NicPoyia\CRDT\LWW\Graph;

use NicPoyia\CRDT\LWW\Graph\Element\LWWElement;

/**
 * Implementation of an LWW-Element-Set (Last-Write-Wins-Element-Set).
 */
class LWWElementSet
{
    /**
     * @var LWWElement[]
     */
    protected array $addSet;
    /**
     * @var LWWElement[]
     */
    protected array $removeSet;

    public function __construct()
    {
        $this->addSet = [];
        $this->removeSet = [];
    }

    /**
     * Adds an element in the LWW-Element-Set
     * @param LWWElement $element
     * @return void
     */
    public function add(LWWElement $element)
    {
        $this->addSet[] = $element;
    }

    /**
     * Removes an element from the LWW-Element-Set
     * @param LWWElement $element
     * @return void
     */
    public function remove(LWWElement $element)
    {
        $this->removeSet[] = $element;
    }

    /**
     * Resolves and returns all current elements of the set,
     * i.e. which of the registered elements are currently in the set (according to the definition of LWW-Element-Set)
     * @return LWWElement[]
     */
    public function getAllElements(): array
    {
        /** @var LWWElement[] $latestAddPerElement Associative array mapping element-UID to element-Object */
        $latestAddPerElement = [];
        foreach ($this->addSet as $addedElement) {
            $elementUId = $addedElement->uniqueValue();
            // Associative array acts like a hashtable here (implemented as hashtable)
            if (!array_key_exists($elementUId, $latestAddPerElement)) {
                $latestAddPerElement[$elementUId] = $addedElement;
            } elseif ($addedElement->timestamp() > $latestAddPerElement[$elementUId]->timestamp()) {
                $latestAddPerElement[$elementUId] = $addedElement;
            }
        }
        $latestRemPerElement = $this->findLatestRemPerElement();
        $allElements = [];
        foreach ($latestAddPerElement as $uid => $elementWithLatestAdd) {
            // Associative array acts like a hashtable here (implemented as hashtable)
            if (array_key_exists($uid, $latestRemPerElement)) {
                $elementWithLatestRem = $latestRemPerElement[$uid];
                if ($elementWithLatestRem->timestamp() > $elementWithLatestAdd->timestamp()) {
                    // Element does not exist - Latest removal was after latest addition
                    continue;
                }
                // Element exists - Item has been removed before the latest addition
            }
            // Element exists - Element was added and either not removed or removed before the latest addition
            $allElements[] = $elementWithLatestAdd;
        }
        return $allElements;
    }

    /**
     * Checks if an element exists in the LWW-Element-Set
     * @param LWWElement $element Element to check
     * @return bool Whether element is in the LWW-Element-Set
     */
    public function exists(LWWElement $element): bool
    {
        $latestAdd = null;
        foreach ($this->addSet as $addedElement) {
            if (!$addedElement->equals($element)) {
                continue;
            }
            if ($addedElement->timestamp() < $latestAdd) {
                continue;
            }
            $latestAdd = $addedElement->timestamp();
        }
        $latestRem = null;
        foreach ($this->removeSet as $remElement) {
            if (!$remElement->equals($element)) {
                continue;
            }
            if ($remElement->timestamp() < $latestRem) {
                continue;
            }
            $latestRem = $remElement->timestamp();
        }
        if (!$latestAdd) {
            // Element has not been added
            return false;
        }
        if (!$latestRem) {
            // Element has been added but not removed
            return true;
        }
        // Check if element was removed with an earlier timestamp than the latest timestamp in the add set
        return $latestRem < $latestAdd;
    }

    /**
     * Merge with concurrent changes from other set
     * @param LWWElement[] $addElements Element additions
     * @param LWWElement[] $remElements Element removals
     * @return LWWElementSetMergeResult Merge result containing effective additions and removals of elements
     */
    public function merge(array $addElements, array $remElements): LWWElementSetMergeResult
    {
        // Index current state for comparison
        $currentElements = $this->getAllElements();
        $latestRemPerElement = $this->findLatestRemPerElement();
        $curElTimestamps = [];
        foreach ($currentElements as $currentElement) {
            $curElTimestamps[$currentElement->uniqueValue()] = $currentElement->timestamp();
        }
        // Merge additions
        $effectiveAdditions = [];
        $candidateAdditions = [];
        foreach ($addElements as $addedElement) {
            $candidateAdditions[$addedElement->uniqueValue()] = $addedElement;
            // Merge add sets
            $this->addSet[] = $addedElement;
            // Detect affected added elements
            $elementUid = $addedElement->uniqueValue();
            if (array_key_exists($elementUid, $curElTimestamps)) {
                // Element already exists
                $newTimestamp = $addedElement->timestamp();
                if ($curElTimestamps[$elementUid] < $newTimestamp) {
                    // Merged addition has a later timestamp (MAX)
                    $effectiveAdditions[$elementUid] = $addedElement;
                }
            } else {
                // New element
                $effectiveAdditions[$elementUid] = $addedElement;
            }
        }
        // Merge removals
        $effectiveRemovals = [];
        $candidateRemovals = [];
        foreach ($remElements as $removedElement) {
            $candidateRemovals[$removedElement->uniqueValue()] = $removedElement;
            // Merge add sets
            $this->removeSet[] = $removedElement;
            // Detect affected removed elements
            $elementUid = $removedElement->uniqueValue();
            if (array_key_exists($elementUid, $curElTimestamps)) {
                // Removal of existing element
                $effectiveRemovals[$elementUid] = $removedElement;
            } else {
                // Element does not exist
                $newTimestamp = $removedElement->timestamp();
                if (array_key_exists($elementUid, $latestRemPerElement)
                    && $latestRemPerElement[$elementUid]->timestamp() < $newTimestamp) {
                    // Merged removal has a later timestamp (MAX)
                    $effectiveRemovals[$elementUid] = $removedElement;
                }
            }
        }
        // Detect neutralization between affected addition and removals
        foreach ($effectiveAdditions as $elementUid => $affectedAddition) {
            if (array_key_exists($elementUid, $candidateRemovals)) {
                if ($affectedAddition->timestamp() < $candidateRemovals[$elementUid]->timestamp()) {
                    // Item added and then removed - neutralized
                    unset($effectiveAdditions[$elementUid]);
                    unset($effectiveRemovals[$elementUid]);
                }
            }
        }
        foreach ($effectiveRemovals as $elementUid => $affectedRemoval) {
            if (array_key_exists($elementUid, $candidateAdditions)) {
                if ($affectedRemoval->timestamp() < $candidateAdditions[$elementUid]->timestamp()) {
                    // Item removed and then added - neutralized
                    unset($effectiveRemovals[$elementUid]);
                    unset($effectiveAdditions[$elementUid]);
                }
            }
        }
        return new LWWElementSetMergeResult($effectiveAdditions, $effectiveRemovals);
    }

    /**
     * Finds the timestamp of the latest removal for each distinct element that is registered
     * @return LWWElement[] Associative array mapping element-UID to element-Object
     */
    protected function findLatestRemPerElement(): array
    {
        $latestRemPerElement = [];
        foreach ($this->removeSet as $remElement) {
            $elementUId = $remElement->uniqueValue();
            // Associative arrays act like a hashtable here (hashtable implementation)
            if (!array_key_exists($elementUId, $latestRemPerElement)) {
                $latestRemPerElement[$elementUId] = $remElement;
            } elseif ($remElement->timestamp() > $latestRemPerElement[$elementUId]->timestamp()) {
                $latestRemPerElement[$elementUId] = $remElement;
            }
        }
        return $latestRemPerElement;
    }
}