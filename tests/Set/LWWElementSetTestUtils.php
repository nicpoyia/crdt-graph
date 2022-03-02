<?php

namespace NicPoyia\CRDT\LWW\Graph\Test\Set;

use DateInterval;
use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use NicPoyia\CRDT\LWW\Graph\Element\LWWElement;
use NicPoyia\CRDT\LWW\Graph\Element\LWWElementEdge;
use NicPoyia\CRDT\LWW\Graph\Element\LWWElementVertex;

class LWWElementSetTestUtils
{

    /**
     * Generates mock downstream changes (additions and removals) including:
     * - Additions of new elements
     * - Additions of existing elements
     * - Additions of removed elements
     * - Removals of non-existing elements
     * - Removals of existing elements (includes both pre-existing, newly-added and re-added elements)
     * @param LWWElement[] $currentElements Elements that are currently in the set
     * @param LWWElement[] $removedElements Elements that have a history of removal
     * @param bool $edgesOrVertices Whether to generate mock edges or vertices (true for edges - false for vertices)
     * @return LWWElementSetMockDownstreamChanges
     * @throws InvalidArgumentException
     */
    public static function generateMockDownstreamChanges(array $currentElements,
                                                         array $removedElements,
                                                         bool  $edgesOrVertices = true
    ): LWWElementSetMockDownstreamChanges
    {
        /** @var LWWElement[] $addedElements */
        $addedNewElements = [];
        for ($i = 0; $i < rand(2, 5); $i++) {
            if ($edgesOrVertices) {
                $addedNewElements[] = new LWWElementEdge(new DateTimeImmutable(), uniqid(), uniqid());
            } else {
                $addedNewElements[] = new LWWElementVertex(new DateTimeImmutable(), uniqid());
            }
        }
        $addedExistingElements = [];
        foreach (array_slice($currentElements, 0, count($currentElements) / 2) as $nextElementToCopy) {
            /** @var LWWElementEdge $nextElementToCopy */
            $addedExistingElements[] = self::replicateElementWithXHoursTimeshift($nextElementToCopy, 5);
        }
        $addedRemovedElements = array_slice($removedElements, 0, count($removedElements) / 2);
        $removedNonExistingElements = [];
        for ($i = 0; $i < rand(2, 5); $i++) {
            $removedNonExistingElements[] = new LWWElementEdge(new DateTimeImmutable(), uniqid(), uniqid());
        }
        $removedExistingElements = [];// Pre-existing elements to be removed
        foreach (array_slice($currentElements, count($currentElements) / 2, 1) as $nextElementToCopy) {
            $removedExistingElements[] = self::replicateElementWithXHoursTimeshift($nextElementToCopy, 5);
        }
        $removedExistingElements[]
            = self::replicateElementWithXHoursTimeshift($addedNewElements[1], 5); // Newly-added element to be removed
        if (count($addedExistingElements) > 0) {
            $removedExistingElements[]
                = self::replicateElementWithXHoursTimeshift($addedExistingElements[0], 5); // Re-added element to be removed
            $removedExistingElements[]
                = self::replicateElementWithXHoursTimeshift($addedRemovedElements[0], 5); // Re-added removed element to be removed
        }
        $allAddedElements = array_merge($addedNewElements, $addedExistingElements, $addedRemovedElements);
        $allRemovedElements = array_merge($removedNonExistingElements, $removedExistingElements);
        return new LWWElementSetMockDownstreamChanges($allAddedElements, $allRemovedElements);
    }

    /**
     * Replicates an element with X hours difference in timestamp.
     * @param LWWElement $element Element to replicate with timeshift
     * @param int $hours Hours to timeshift the change for the replicated element
     * @return LWWElement
     * @throws InvalidArgumentException ion In case of unsupported LWWElement type
     * @throws Exception
     */
    public static function replicateElementWithXHoursTimeshift(LWWElement $element, int $hours): LWWElement
    {
        if ($hours >= 0) {
            $newTimestamp = (new DateTimeImmutable())->add(new DateInterval(sprintf("PT%dH", $hours)));
        } else {
            $newTimestamp = (new DateTimeImmutable())->sub(new DateInterval(sprintf("PT%dH", -$hours)));
        }
        if ($element instanceof LWWElementEdge) {
            return new LWWElementEdge(
                $newTimestamp,
                $element->vertexValA(),
                $element->vertexValB()
            );
        } elseif ($element instanceof LWWElementVertex) {
            return new LWWElementVertex(
                $newTimestamp,
                $element->vertexValue()
            );
        }
        throw new InvalidArgumentException("Unsupported LWWElement type");
    }

}