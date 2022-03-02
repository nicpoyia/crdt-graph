<?php

namespace NicPoyia\CRDT\LWW\Graph\Test\Set;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use NicPoyia\CRDT\LWW\Graph\Element\LWWElement;
use NicPoyia\CRDT\LWW\Graph\Element\LWWElementEdge;
use NicPoyia\CRDT\LWW\Graph\LWWElementSet;
use PHPUnit\Framework\Exception;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;

/**
 * Test class for LWWElementSet containing tests about merge operation
 * @covers \NicPoyia\CRDT\LWW\Graph\LWWElementSet
 */
class LWWElementSetMergeTest extends TestCase
{

    /**
     * Use case where changes are merged on an empty LWW-Element-Set.
     * Makes sure that the basic merge functionality is responding as expected.
     * @return void
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testMergeOnEmptySet()
    {
        // Generate mock random downstream for testing
        $addedElements = [];
        for ($i = 0; $i < rand(3, 10); $i++) {
            $addedElements[] = new LWWElementEdge(new DateTimeImmutable(), uniqid(), uniqid());
        }
        $removedElements = [];
        for ($i = 0; $i < rand(3, 10); $i++) {
            $removedElements[] = new LWWElementEdge(new DateTimeImmutable(), uniqid(), uniqid());
        }
        // Merge downstream on empty LWW-Element-Set
        $elementSet = new LWWElementSet();
        $mergeResult = $elementSet->merge($addedElements, $removedElements);
        // Assert that all additions are effective because the set is empty and change have no collisions
        $effectiveAdditions = $mergeResult->effectiveAdditions();
        $this->assertCount(count($addedElements), $effectiveAdditions);
        $this->assertCount(count($addedElements), $elementSet->getAllElements());
        foreach ($addedElements as $addedElement) {
            $this->assertTrue($elementSet->exists($addedElement));
            $this->assertContains($addedElement, $effectiveAdditions);
        }
        // Assert that all removals are not effective because removed elements did not exist prior to the merge
        $this->assertEmpty($mergeResult->effectiveRemovals());
        // Merge the same downstream again
        // Assert that none of the changes is effective because they have been applied again
        $mergeResult = $elementSet->merge($addedElements, $removedElements);
        $this->assertEmpty($mergeResult->effectiveAdditions());
        $this->assertEmpty($mergeResult->effectiveRemovals());
        $this->assertCount(count($addedElements), $elementSet->getAllElements());
    }

    /**
     * Test case ensuring "Commutative" property (required property of merge operation)
     * See https://en.wikipedia.org/wiki/Commutative_property
     * Makes sure that the direction of merge operation will not affect the results.
     * For example, if we merge replica A on B should produce the same results as merging B on A, as shown below:
     * A merge B = B merge A
     * @return void
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testMergeCommutativeProperty()
    {
        // Generate mock set with initial state
        $elementSet = new LWWElementSet();
        $initiallyExistingElements = [];
        $initiallyRemovedElements = [];
        // Added-only elements
        for ($i = 0; $i < rand(4, 10); $i++) {
            $initiallyExistingElements[] = $this->addNewElementInSet($elementSet);
        }
        // Added and then removed elements
        for ($i = 0; $i < rand(4, 10); $i++) {
            $initiallyRemovedElements[] = $this->addAndRemoveNewElement($elementSet);
        }
        // Added, removed and then added elements
        for ($i = 0; $i < rand(4, 10); $i++) {
            $initiallyExistingElements[] = $this->addRemoveAndAddAgainNewElement($elementSet);
        }
        // Merge changes including:
        // - Additions of new elements
        // - Additions of existing elements
        // - Additions of removed elements
        // - Removals of non-existing elements
        // - Removals of existing elements (includes both pre-existing, newly-added and re-added elements)
        $mockDownstreamChanges = LWWElementSetTestUtils::generateMockDownstreamChanges(
            $initiallyExistingElements, $initiallyRemovedElements
        );
        $allAddedElements = $mockDownstreamChanges->additions();
        $allRemovedElements = $mockDownstreamChanges->removals();
        $elementSet->merge($allAddedElements, $allRemovedElements);
        $this->assertMergedSetIsCorrect(
            $allAddedElements, $allRemovedElements, $elementSet, $initiallyExistingElements
        );
        $allElementsAfterMergeA = $elementSet->getAllElements();
        // Merge changes of initial set into a set only containing the previously used downstream changes
        // This will mimic a change of direction of the merge operation
        // Then assert that the produced result is the same in both cases
        $elementSetWithDownstreamContent = new LWWElementSet();
        foreach ($mockDownstreamChanges->additions() as $nextAddition) {
            $elementSetWithDownstreamContent->add($nextAddition);
        }
        foreach ($mockDownstreamChanges->removals() as $nextRemoval) {
            $elementSetWithDownstreamContent->remove($nextRemoval);
        }
        $allAddedElementsInverse = [];
        foreach ($initiallyExistingElements as $nextElement) {
            $allAddedElementsInverse[] = $nextElement;
        }
        foreach ($initiallyRemovedElements as $nextElement) {
            // We have stored timestamp of removal, so reconstruction the element as it was added before removal,
            // which will help with simulating the inverse merge operation
            $allAddedElementsInverse[]
                = LWWElementSetTestUtils::replicateElementWithXHoursTimeshift($nextElement, -1);
        }
        $elementSetWithDownstreamContent->merge($allAddedElementsInverse, $initiallyRemovedElements);
        $allElementsAfterMergeB = $elementSetWithDownstreamContent->getAllElements();
        $this->assertSameSize($allElementsAfterMergeA, $allElementsAfterMergeB);
        foreach ($allElementsAfterMergeA as $nextElement) {
            $this->assertContainsLWWElement($nextElement, $allElementsAfterMergeB);
        }
        foreach ($allElementsAfterMergeB as $nextElement) {
            $this->assertContainsLWWElement($nextElement, $allElementsAfterMergeA);
        }
    }

    /**
     * Test case ensuring "Associative" property (required property of merge operation)
     * See https://en.wikipedia.org/wiki/Associative_property
     * Makes sure that rearranging the order of execution (or the parentheses) of MORE THAN 2 merge operations
     * will not change the results.
     * For example, if we merge replica B on A to produce a result Z, then merge a third replica C on Z,
     * should produce the same result as merging C on A, and then merge B on the produced result, as shown below:
     * (A merge B) merge C = (A merge C) merge B
     * @return void
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testMergeAssociativeProperty()
    {
        // Generate mock set with initial state
        $elementSet = new LWWElementSet();
        $currentElements = [];
        $removedElements = [];
        // Added-only elements
        for ($i = 0; $i < rand(4, 10); $i++) {
            $currentElements[] = $this->addNewElementInSet($elementSet);
        }
        // Added and then removed elements
        for ($i = 0; $i < rand(4, 10); $i++) {
            $removedElements[] = $this->addAndRemoveNewElement($elementSet);
        }
        // Added, removed and then added elements
        for ($i = 0; $i < rand(4, 10); $i++) {
            $currentElements[] = $this->addRemoveAndAddAgainNewElement($elementSet);
        }
        // We are going to use 2 mock downstream change-sets (B and C)
        // Merge changes including:
        // - Additions of new elements
        // - Additions of existing elements
        // - Additions of removed elements
        // - Removals of non-existing elements
        // - Removals of existing elements (includes both pre-existing, newly-added and re-added elements)
        $initialElementSetClone = unserialize(serialize($elementSet));
        $mockDownstreamB = LWWElementSetTestUtils::generateMockDownstreamChanges($currentElements, $removedElements);
        $mockDownstreamC = LWWElementSetTestUtils::generateMockDownstreamChanges($currentElements, $removedElements);
        $addedElementsB = $mockDownstreamB->additions();
        $removedElementsB = $mockDownstreamB->removals();
        $addedElementsC = $mockDownstreamC->additions();
        $removedElementsC = $mockDownstreamC->removals();
        $allAddedElements = array_merge($addedElementsB, $addedElementsC);
        $allRemovedElements = array_merge($removedElementsB, $removedElementsC);
        // Perform "(A merge B) merge C"
        $elementSet->merge($addedElementsB, $removedElementsB);
        $elementSet->merge($addedElementsC, $removedElementsC);
        $this->assertMergedSetIsCorrect(
            $allAddedElements, $allRemovedElements, $elementSet, $currentElements
        );
        // Rearrange the order of execution and assert that result is the same
        // Perform "(A merge C) merge B"
        $allElementsAfterMergeA = $elementSet->getAllElements();
        $initialElementSetClone->merge($addedElementsC, $removedElementsC);
        $initialElementSetClone->merge($addedElementsB, $removedElementsB);
        $this->assertMergedSetIsCorrect(
            $allAddedElements, $allRemovedElements, $initialElementSetClone, $currentElements
        );
        $allElementsAfterMergeB = $initialElementSetClone->getAllElements();
        $this->assertSameSize($allElementsAfterMergeA, $allElementsAfterMergeB);
        foreach ($allElementsAfterMergeA as $nextElement) {
            $this->assertContainsLWWElement($nextElement, $allElementsAfterMergeB);
        }
        foreach ($allElementsAfterMergeB as $nextElement) {
            $this->assertContainsLWWElement($nextElement, $allElementsAfterMergeA);
        }
    }

    /**
     * Test case ensuring "Idempotence" property (required property of merge operation)
     * See https://en.wikipedia.org/wiki/Idempotence
     * Makes sure that the result will not change regardless how many times a replica is merged on another.
     * For example, if we merge replica B on A to produce a result C,
     * then C should remain the same after merging B on it multiple times, as shown below:
     * A merge B = (A merge B) merge B = ((A merge B) merge B) merge B etc.
     * @return void
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testMergeIdempotenceProperty()
    {
        // Generate mock set with initial state
        $elementSet = new LWWElementSet();
        $currentElements = [];
        $removedElements = [];
        // Added-only elements
        for ($i = 0; $i < rand(4, 10); $i++) {
            $currentElements[] = $this->addNewElementInSet($elementSet);
        }
        // Added and then removed elements
        for ($i = 0; $i < rand(4, 10); $i++) {
            $removedElements[] = $this->addAndRemoveNewElement($elementSet);
        }
        // Added, removed and then added elements
        for ($i = 0; $i < rand(4, 10); $i++) {
            $currentElements[] = $this->addRemoveAndAddAgainNewElement($elementSet);
        }
        // Merge changes including:
        // - Additions of new elements
        // - Additions of existing elements
        // - Additions of removed elements
        // - Removals of non-existing elements
        // - Removals of existing elements (includes both pre-existing, newly-added and re-added elements)
        $mockDownstreamChanges
            = LWWElementSetTestUtils::generateMockDownstreamChanges($currentElements, $removedElements);
        $allAddedElements = $mockDownstreamChanges->additions();
        $allRemovedElements = $mockDownstreamChanges->removals();
        $elementSet->merge($allAddedElements, $allRemovedElements);
        $this->assertMergedSetIsCorrect($allAddedElements, $allRemovedElements, $elementSet, $currentElements);
        // Merge again multiple times and assert that state remains the same after each extra merge
        $allElementsAfterMerge = $elementSet->getAllElements();
        for ($i = 0; $i < rand(4, 8); $i++) {
            $elementSet->merge($allAddedElements, $allRemovedElements);
            $this->assertEquals($allElementsAfterMerge, $elementSet->getAllElements());
        }
    }

    /**
     * Use case where changes coming from another replica contain additions of elements already in the set.
     * @return void
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testMergeAddedElementsAlreadyInSet()
    {
        // Generate mock set with initial state
        $elementSet = new LWWElementSet();
        /** @var LWWElement[] $currentElements */
        $currentElements = [];
        for ($i = 0; $i < rand(4, 10); $i++) {
            $currentElements[] = $this->addNewElementInSet($elementSet);
        }
        // Merge changes with two additions of existing elements
        /** @var LWWElement[] $addedElements */
        $addedElements = [];
        for ($i = 0; $i < rand(4, 10); $i++) {
            $addedElements[] = new LWWElementEdge(new DateTimeImmutable(), uniqid(), uniqid());
        }
        // Pick 2 random existing elements to add again
        $reAddedElements = [];
        $randPickIndex = rand(0, count($currentElements) - 2);
        $reAddedElements[$currentElements[$randPickIndex]->uniqueValue()] = $currentElements[$randPickIndex];
        $reAddedElements[$currentElements[$randPickIndex + 1]->uniqueValue()] = $currentElements[$randPickIndex + 1];
        foreach ($reAddedElements as $reAddedElement) {
            $addedElements[] = $reAddedElement;
        }
        $mergeResult = $elementSet->merge($addedElements, []);
        // Assert that all additions are effective but the ones about the existing elements
        $effectiveAdditions = $mergeResult->effectiveAdditions();
        $newElementState = $elementSet->getAllElements();
        foreach ($currentElements as $currentElement) {
            $this->assertContainsLWWElement($currentElement, $newElementState);
        }
        $this->assertCount(
            count($currentElements) + count($addedElements) - count($reAddedElements)
            , $newElementState
        );
        $this->assertCount(count($addedElements) - count($reAddedElements), $effectiveAdditions);
        foreach ($reAddedElements as $reAddedElement) {
            $this->assertNotContainsLWWElement($reAddedElement, $effectiveAdditions);
        }
        foreach ($addedElements as $addedElement) {
            $this->assertContainsLWWElement($addedElement, $newElementState);
            if (array_key_exists($addedElement->uniqueValue(), $reAddedElements)) {
                continue;
            }
            $this->assertContainsLWWElement($addedElement, $effectiveAdditions);
        }
        $this->assertEmpty($mergeResult->effectiveRemovals());
    }

    /**
     * Use case where changes coming from another replica contain removals of elements not in the set.
     * @return void
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testMergeRemovedElementsNotInSet()
    {
        // Generate mock set with initial state
        $elementSet = new LWWElementSet();
        /** @var LWWElement[] $currentElements */
        $currentElements = [];
        for ($i = 0; $i < rand(5, 10); $i++) {
            $nextAddedElement = $this->addNewElementInSet($elementSet);
            $currentElements[$nextAddedElement->uniqueValue()] = $nextAddedElement;
        }
        // Merge changes with two removals of non-existing elements
        /** @var LWWElement[] $addedElements */
        $removedElements = [];
        $currentElementList = array_values($currentElements);
        for ($i = rand(0, count($currentElements) - 4); $i < rand(2, 4); $i++) {
            // Existing elements to be removed
            $nextRemovedElement
                = LWWElementSetTestUtils::replicateElementWithXHoursTimeshift($currentElementList[$i], 1);
            $removedElements[$nextRemovedElement->uniqueValue()] = $nextRemovedElement;
        }
        // Pick random non-existing elements to remove
        $nonExistingRemovedElements = [];
        for ($i = 0; $i < rand(4, 10); $i++) {
            $nextRemovedElement = new LWWElementEdge(new DateTimeImmutable(), uniqid(), uniqid());
            $removedElements[$nextRemovedElement->uniqueValue()] = $nextRemovedElement;
            $nonExistingRemovedElements[$nextRemovedElement->uniqueValue()] = $nextRemovedElement;
        }
        $mergeResult = $elementSet->merge([], $removedElements);
        // Assert that all removals are not effective, except the ones about the existing elements
        $effectiveRemovals = $mergeResult->effectiveRemovals();
        $newElementState = $elementSet->getAllElements();
        foreach ($currentElements as $currentElement) {
            if (array_key_exists($currentElement->uniqueValue(), $removedElements)) {
                continue;
            }
            $this->assertContainsLWWElement($currentElement, $newElementState);
        }
        $this->assertCount(
            count($currentElements) - count($removedElements) + count($nonExistingRemovedElements)
            , $newElementState
        );
        $this->assertCount(
            count($removedElements) - count($nonExistingRemovedElements), $effectiveRemovals
        );
        foreach ($nonExistingRemovedElements as $nonExistingRemoval) {
            $this->assertNotContainsLWWElement($nonExistingRemoval, $effectiveRemovals);
        }
        foreach ($removedElements as $removedElement) {
            $this->assertNotContainsLWWElement($removedElement, $newElementState);
            if (array_key_exists($removedElement->uniqueValue(), $nonExistingRemovedElements)) {
                continue;
            }
            $this->assertContainsLWWElement($removedElement, $effectiveRemovals);
        }
        $this->assertEmpty($mergeResult->effectiveAdditions());
    }

    /**
     * Use case where changes coming from another replica are self-neutralized,
     * i.e. there are additions and removals of the same elements,
     * which causes changes to be ineffective.
     * Changes should be merged but not be reported as effective.
     * @return void
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testMergeDownstreamContainsNeutralizedChanges()
    {
        // Generate mock set with initial state
        $elementSet = new LWWElementSet();
        /** @var LWWElement[] $currentElements */
        $currentElements = [];
        for ($i = 0; $i < rand(4, 10); $i++) {
            $currentElements[] = $this->addNewElementInSet($elementSet);
        }
        // Merge changes with additions and removals that neutralize each other
        /** @var LWWElement[] $addedElements */
        $addedElements = [];
        for ($i = 0; $i < rand(4, 10); $i++) {
            $addedElements[] = new LWWElementEdge(new DateTimeImmutable(), uniqid(), uniqid());
        }
        /** @var LWWElement[] $addedElements */
        $removedElements = [];
        for ($i = 0; $i < rand(4, 10); $i++) {
            $nextRemovedNonExistentElement = new LWWElementEdge(new DateTimeImmutable(), uniqid(), uniqid());
            $removedElements[$nextRemovedNonExistentElement->uniqueValue()] = $nextRemovedNonExistentElement;
        }
        $neutralizedElements = [];
        for ($i = 0; $i < rand(4, 10); $i++) {
            $nextNeutralElementAddition = new LWWElementEdge(new DateTimeImmutable(), uniqid(), uniqid());
            $addedElements[] = $nextNeutralElementAddition;
            $nextNeutralElementRemoval
                = LWWElementSetTestUtils::replicateElementWithXHoursTimeshift($nextNeutralElementAddition, 1);
            $removedElements[$nextNeutralElementRemoval->uniqueValue()] = $nextNeutralElementRemoval;
            $neutralizedElements[$nextNeutralElementRemoval->uniqueValue()] = $nextNeutralElementRemoval;
        }
        // Remove and then add again a pre-existing element, which will neutralize the change
        $existentNeutralElementRemoval
            = LWWElementSetTestUtils::replicateElementWithXHoursTimeshift($currentElements[0], 1);
        $removedElements[$existentNeutralElementRemoval->uniqueValue()] = $existentNeutralElementRemoval;
        $existentNeutralElementAddition
            = LWWElementSetTestUtils::replicateElementWithXHoursTimeshift($existentNeutralElementRemoval, 1);
        $addedElements[$existentNeutralElementAddition->uniqueValue()] = $existentNeutralElementAddition;
        $neutralizedElements[$existentNeutralElementAddition->uniqueValue()] = $existentNeutralElementAddition;
        // Include a removal of an existent element (which we will assert as effective)
        $existentRemovedElement
            = LWWElementSetTestUtils::replicateElementWithXHoursTimeshift($currentElements[1], 1);
        $removedElements[$existentRemovedElement->uniqueValue()] = $existentRemovedElement;
        // Perform the merge operation
        $mergeResult = $elementSet->merge($addedElements, $removedElements);
        // Assert that all additions and removals are effective, except the neutralized ones
        $effectiveAdditions = $mergeResult->effectiveAdditions();
        $effectiveRemovals = $mergeResult->effectiveRemovals();
        $this->assertCount(count($addedElements) - count($neutralizedElements), $effectiveAdditions);
        $this->assertCount(1, $effectiveRemovals);
        $newElementState = $elementSet->getAllElements();
        $this->assertCount(
            count($currentElements) + count($addedElements) - count($neutralizedElements) - 1
            , $newElementState
        );
        foreach ($currentElements as $currentElement) {
            if (array_key_exists($currentElement->uniqueValue(), $removedElements)) {
                // Successfully removed initially existent element
                continue;
            }
            $this->assertContainsLWWElement($currentElement, $newElementState);
        }
        foreach ($addedElements as $addedElement) {
            if (array_key_exists($addedElement->uniqueValue(), $neutralizedElements)) {
                // Neutralized addition
                continue;
            }
            $this->assertContainsLWWElement($addedElement, $effectiveAdditions);
        }
    }

    /**
     * Helper assertion method that checks if a set has correct state after a merge operation.
     * @param LWWElement[] $mergedAdditions Element additions merged on the LWW-Element-Set
     * @param LWWElement[] $mergedRemovals Element removals merged on the LWW-Element-Set
     * @param LWWElementSet $mergedSet LWW-Element-Set in the state after merge operation
     * @param LWWElement[] $preExistingElements Elements that were in the set
     *                                          (according to the definition of LWW-Element-Set)
     * @return void
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    protected function assertMergedSetIsCorrect(array         $mergedAdditions,
                                                array         $mergedRemovals,
                                                LWWElementSet $mergedSet,
                                                array         $preExistingElements)
    {
        // Index changes
        /** @var LWWElement[] $additionIndex */
        $additionIndex = [];
        /** @var LWWElement[] $removalIndex */
        $removalIndex = [];
        foreach ($mergedAdditions as $mergedAddition) {
            $additionUid = $mergedAddition->uniqueValue();
            if (array_key_exists($additionUid, $additionIndex)) {
                // Index the last addition
                if ($additionIndex[$additionUid]->timestamp() < $mergedAddition->timestamp()) {
                    $additionIndex[$additionUid] = $mergedAddition;
                }
            } else {
                $additionIndex[$additionUid] = $mergedAddition;
            }
        }
        foreach ($mergedRemovals as $mergedRemoval) {
            $removalUid = $mergedRemoval->uniqueValue();
            if (array_key_exists($removalUid, $removalIndex)) {
                // Index the last removal
                if ($removalIndex[$removalUid]->timestamp() < $mergedRemoval->timestamp()) {
                    $removalIndex[$removalUid] = $mergedRemoval;
                }
            } else {
                $removalIndex[$removalUid] = $mergedRemoval;
            }
        }
        $allElements = $mergedSet->getAllElements();
        // Check that all pre-existing elements are present, except of the ones removed later
        foreach ($preExistingElements as $preExistingElement) {
            $elementUid = $preExistingElement->uniqueValue();
            if (array_key_exists($elementUid, $removalIndex)) {
                $removedElement = $removalIndex[$elementUid];
                if ($removedElement->timestamp() > $preExistingElement->timestamp()) {
                    continue;
                }
            }
            $this->assertContainsLWWElement($preExistingElement, $allElements);
        }
        // Check that all added elements are present, except of the ones removed later
        foreach ($mergedAdditions as $mergedAddition) {
            $elementUid = $mergedAddition->uniqueValue();
            if (array_key_exists($elementUid, $removalIndex)) {
                $removedElement = $removalIndex[$elementUid];
                if ($removedElement->timestamp() > $mergedAddition->timestamp()) {
                    continue;
                }
            }
            $this->assertContainsLWWElement($mergedAddition, $allElements);
        }
        // Check that all removed elements are not present, except of the ones added later
        foreach ($mergedRemovals as $mergedRemoval) {
            $elementUid = $mergedRemoval->uniqueValue();
            if (array_key_exists($elementUid, $additionIndex)) {
                $addedElement = $additionIndex[$elementUid];
                if ($addedElement->timestamp() > $mergedRemoval->timestamp()) {
                    // Merged removal is followed by a later addition
                    continue;
                }
            }
            $this->assertNotContainsLWWElement($mergedRemoval, $allElements);
        }
    }

    /**
     * Helper assertion method that checks if an element is a list of elements.
     * Identification ignores timestamp due to the nature of the data-structure.
     * @param LWWElement $needle Element to inspect the existence of
     * @param LWWElement[] $haystack List of elements to check
     * @return void
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    protected function assertContainsLWWElement(LWWElement $needle, array $haystack)
    {
        $elementFound = false;
        foreach ($haystack as $nextElement) {
            if ($nextElement->equals($needle)) {
                $elementFound = true;
                break;
            }
        }
        $this->assertTrue($elementFound);
    }

    /**
     * Helper assertion method that checks if an element is not a list of elements.
     * Identification ignores timestamp due to the nature of the data-structure.
     * @param LWWElement $needle Element to inspect the existence of
     * @param LWWElement[] $haystack List of elements to check
     * @return void
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    protected function assertNotContainsLWWElement(LWWElement $needle, array $haystack)
    {
        $elementFound = false;
        foreach ($haystack as $nextElement) {
            if ($nextElement->equals($needle)) {
                $elementFound = true;
                break;
            }
        }
        $this->assertFalse($elementFound);
    }


    /**
     * Adds a new element to the set.
     * @param LWWElementSet $elementSet LWW-Element-Set to add to
     * @return LWWElementEdge The added element
     */
    protected function addNewElementInSet(LWWElementSet $elementSet): LWWElementEdge
    {
        $addedElement = new LWWElementEdge(new DateTimeImmutable(), uniqid(), uniqid());
        $elementSet->add($addedElement);
        return $addedElement;
    }

    /**
     * Adds a new element to the set and then removes it.
     * @param LWWElementSet $elementSet LWW-Element-Set to add/remove to/from
     * @return LWWElementEdge The removed element
     */
    protected function addAndRemoveNewElement(LWWElementSet $elementSet): LWWElementEdge
    {
        $vertexA = uniqid();
        $vertexB = uniqid();
        $elementSet->add(new LWWElementEdge(new DateTimeImmutable(), $vertexA, $vertexB));
        $removedElement = new LWWElementEdge(
            (new DateTimeImmutable())->add(new DateInterval("PT1H")), $vertexA, $vertexB
        );
        $elementSet->remove($removedElement);
        return $removedElement;
    }

    /**
     * Adds a new element to the set, then removes it, then adds it again.
     * @param LWWElementSet $elementSet LWW-Element-Set to add/remove to/from
     * @return LWWElementEdge The last added element
     */
    protected function addRemoveAndAddAgainNewElement(LWWElementSet $elementSet): LWWElementEdge
    {
        $vertexA = uniqid();
        $vertexB = uniqid();
        $elementSet->add(new LWWElementEdge(new DateTimeImmutable(), $vertexA, $vertexB));
        $elementSet->remove(new LWWElementEdge(
            (new DateTimeImmutable())->add(new DateInterval("PT1H")), $vertexA, $vertexB
        ));
        $reAddedElement = new LWWElementEdge(
            (new DateTimeImmutable())->add(new DateInterval("PT2H")), $vertexA, $vertexB
        );
        $elementSet->add($reAddedElement);
        return $reAddedElement;
    }
}