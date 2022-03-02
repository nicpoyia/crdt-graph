<?php

namespace NicPoyia\CRDT\LWW\Graph\Test\Set;

use DateInterval;
use DateTimeImmutable;
use NicPoyia\CRDT\LWW\Graph\Element\LWWElementEdge;
use NicPoyia\CRDT\LWW\Graph\LWWElementSet;
use PHPUnit\Framework\Exception;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

/**
 * Test class for LWWElementSet containing tests about internal operations
 * @covers \NicPoyia\CRDT\LWW\Graph\LWWElementSet
 */
class LWWElementSetInternalTest extends TestCase
{

    /**
     * Test case where each element is added once to the LWW-Element-Set
     * @return void
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function testGetAllElementsEachElementAddedOnce()
    {
        $elementSet = new LWWElementSet();
        $addedElementCount = rand(3, 6);
        $addedElements = [];
        for ($i = 0; $i < $addedElementCount; $i++) {
            $addedElement = new LWWElementEdge(new DateTimeImmutable(), uniqid(), uniqid());
            $elementSet->add($addedElement);
            $addedElements[] = $addedElement;
        }
        $allElements = $elementSet->getAllElements();
        $this->assertCount($addedElementCount, $allElements);
        foreach ($addedElements as $addedElement) {
            $this->assertContains($addedElement, $allElements);
        }
    }

    /**
     * Test case where each element is added and also removed after the addition
     * @return void
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function testGetAllElementsEachElementAddedAndThenRemoved()
    {
        $elementSet = new LWWElementSet();
        $addedElementCount = rand(3, 6);
        for ($i = 0; $i < $addedElementCount; $i++) {
            $vertexA = uniqid();
            $vertexB = uniqid();
            $elementSet->add(new LWWElementEdge(new DateTimeImmutable(), $vertexA, $vertexB));
            $elementSet->remove(new LWWElementEdge(
                (new DateTimeImmutable())->add(new DateInterval("PT1H")), $vertexA, $vertexB
            ));
        }
        $this->assertEmpty($elementSet->getAllElements());
    }

    /**
     * Test case where:
     *   - All items are added and removed
     *   - Half of the added items are added again after their removal
     * @return void
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function testGetAllElementsHalfElementsAddedTwice()
    {
        $elementSet = new LWWElementSet();
        $addedElementCount = 2 * rand(3, 6);
        $addedAndRemovedElements = [];
        for ($i = 0; $i < $addedElementCount / 2; $i++) {
            // Half elements that are added and then removed (after one hour)
            $vertexA = uniqid();
            $vertexB = uniqid();
            $elementSet->add(new LWWElementEdge(new DateTimeImmutable(), $vertexA, $vertexB));
            $removedElement = new LWWElementEdge(
                (new DateTimeImmutable())->add(new DateInterval("PT1H")), $vertexA, $vertexB
            );
            $elementSet->remove($removedElement);
            $addedAndRemovedElements[] = $removedElement;
        }
        $reAddedElements = [];
        for ($i = 0; $i < $addedElementCount / 2; $i++) {
            // Half elements that are added, then removed (after 1 hour) and then added again (after 2 hours)
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
            $reAddedElements[] = $reAddedElement;
        }
        // Only half of the elements should be returned (the ones that were added again)
        $allElements = $elementSet->getAllElements();
        $this->assertCount($addedElementCount / 2, $allElements);
        foreach ($reAddedElements as $reAddedElement) {
            $this->assertContains($reAddedElement, $allElements);
        }
        foreach ($addedAndRemovedElements as $addedAndRemovedElement) {
            $this->assertNotContains($addedAndRemovedElement, $allElements);
        }
    }

    /**
     * Test case where:
     *   - All items are added, removed and then added again
     *   - Half of those items are removed again after their second addition
     * @return void
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function testGetAllElementsHalfElementsRemovedTwice()
    {
        $elementSet = new LWWElementSet();
        $addedElementCount = 2 * rand(3, 6);
        $elementsNotRemovedTwice = [];
        for ($i = 0; $i < $addedElementCount / 2; $i++) {
            // Half elements that are added, then removed (after 1 hour) and then added again (after 2 hours)
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
            $elementsNotRemovedTwice[] = $reAddedElement;
        }
        $elementsRemovedTwice = [];
        for ($i = 0; $i < $addedElementCount / 2; $i++) {
            // Half elements that are added-removed-added and then removed again (after 1,2,3 hours respectively)
            $vertexA = uniqid();
            $vertexB = uniqid();
            $elementSet->add(new LWWElementEdge(new DateTimeImmutable(), $vertexA, $vertexB));
            $elementSet->remove(new LWWElementEdge(
                (new DateTimeImmutable())->add(new DateInterval("PT1H")), $vertexA, $vertexB
            ));
            $elementSet->add(new LWWElementEdge(
                (new DateTimeImmutable())->add(new DateInterval("PT2H")), $vertexA, $vertexB
            ));
            $reRemovedElement = new LWWElementEdge(
                (new DateTimeImmutable())->add(new DateInterval("PT2H")), $vertexA, $vertexB
            );
            $elementSet->remove($reRemovedElement);
            $elementsRemovedTwice[] = $reRemovedElement;
        }
        // Only half of the elements should be returned (the ones that were added again)
        $allElements = $elementSet->getAllElements();
        $this->assertCount($addedElementCount / 2, $allElements);
        foreach ($elementsNotRemovedTwice as $expectedElement) {
            $this->assertContains($expectedElement, $allElements);
        }
        foreach ($elementsRemovedTwice as $unexpectedElement) {
            $this->assertNotContains($unexpectedElement, $allElements);
        }
    }

    /**
     * Use-case where elements are only added to the LWW-Element-Set
     * @return void
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testExistsAddOnly()
    {
        $elementSet = new LWWElementSet();
        // Check default behaviour
        $addedElement = new LWWElementEdge(new DateTimeImmutable(), uniqid(), uniqid());
        $this->assertFalse($elementSet->exists($addedElement));
        // Add element and check
        $elementSet->add($addedElement);
        $this->assertTrue($elementSet->exists($addedElement));
        $otherElement = new LWWElementEdge(new DateTimeImmutable(), uniqid(), uniqid());
        // Check for non-added elements
        $this->assertFalse($elementSet->exists($otherElement));
        // Cross-validate behaviour by changing the expected result
        $elementSet->add($otherElement);
        $this->assertTrue($elementSet->exists($otherElement));
    }

    /**
     * Use-case where elements are added and removed twice (add-remove-add-remove) to the LWW-Element-Set
     * @return void
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testExistsAddAndRemoveTwice()
    {
        $elementSet = new LWWElementSet();
        // Check behaviour after add and remove
        $addedElement = new LWWElementEdge(new DateTimeImmutable(), uniqid(), uniqid());
        $elementSet->add($addedElement);
        $elementSet->remove($addedElement->replicateNow());
        $this->assertFalse($elementSet->exists($addedElement));
        // Check second addition
        $elementSet->add($addedElement->replicateNow());
        $this->assertTrue($elementSet->exists($addedElement));
        // Check second removal
        $elementSet->remove($addedElement->replicateNow());
        $this->assertFalse($elementSet->exists($addedElement));
    }

    /**
     * This test case checks the behaviour of exists method when additions/removals are not stored in a sorted sequence.
     * The described use case is possible when add/remove operations are synced with other replicas.
     * @return void
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testExistsAddAndRemoveUnsortedElements()
    {
        $elementSet = new LWWElementSet();
        // Check behaviour after additions with unsorted timestamps
        $vertexA = uniqid();
        $vertexB = uniqid();
        $addedElementFirstTry = new LWWElementEdge(new DateTimeImmutable(), $vertexA, $vertexB);
        $addedElementSecondTry = new LWWElementEdge(
            (new DateTimeImmutable())->add(new DateInterval("PT1H")), $vertexA, $vertexB
        );
        $elementSet->add($addedElementSecondTry);
        $elementSet->remove($addedElementFirstTry->replicateNow());
        $elementSet->add($addedElementFirstTry);
        // The element should exist because second addition is after one hour
        // and removal happened before second addition
        $this->assertTrue($elementSet->exists($addedElementFirstTry));
        // Check behaviour after removals with unsorted timestamps
        $elementSet = new LWWElementSet();
        $removedElementFirstTry = new LWWElementEdge(new DateTimeImmutable(), $vertexA, $vertexB);
        $removedElementSecondTry = new LWWElementEdge(
            (new DateTimeImmutable())->add(new DateInterval("PT1H")), $vertexA, $vertexB
        );
        $elementSet->remove($removedElementSecondTry);
        $elementSet->remove($removedElementFirstTry);
        $elementSet->add($removedElementFirstTry->replicateNow());
        // The element should not exist because second removal is after one hour
        // and addition happened before second removal
        $this->assertFalse($elementSet->exists($removedElementFirstTry));
    }

    /**
     * Use-case where some added elements are also removed, while some others are added but not removed
     * @return void
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testExistsAddTwoElementsAndRemoveOne()
    {
        $elementSet = new LWWElementSet();
        // Check behaviour when one of the two added elements is also removed
        $element1 = new LWWElementEdge(new DateTimeImmutable(), uniqid(), uniqid());
        $element2 = new LWWElementEdge(new DateTimeImmutable(), uniqid(), uniqid());
        $elementSet->add($element1);
        $elementSet->add($element2);
        $elementSet->remove($element2->replicateNow());
        $this->assertTrue($elementSet->exists($element1));
        $this->assertFalse($elementSet->exists($element2));
    }

}
