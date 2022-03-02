<?php

namespace NicPoyia\CRDT\LWW\Graph\Test\Set;

use DateTimeImmutable;
use NicPoyia\CRDT\LWW\Graph\Element\LWWElementEdge;
use NicPoyia\CRDT\LWW\Graph\Element\LWWElementVertex;
use NicPoyia\CRDT\LWW\Graph\LWWElementSetMergeResult;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

/**
 * Test class for LWWElementSetMergeResult
 * @covers \NicPoyia\CRDT\LWW\Graph\LWWElementSetMergeResult
 */
class LWWElementSetMergeResultTest extends TestCase
{
    /**
     * @return void
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testGetters()
    {
        // Generate mock random sample for testing
        $effectiveAdditions = [];
        for ($i = 0; $i < rand(3, 10); $i++) {
            if (rand(1, 10) > 5) {
                $effectiveAdditions[] = new LWWElementVertex(new DateTimeImmutable(), uniqid());
            } else {
                $effectiveAdditions[] = new LWWElementEdge(new DateTimeImmutable(), uniqid(), uniqid());
            }
        }
        $effectiveRemovals = [];
        for ($i = 0; $i < rand(3, 10); $i++) {
            if (rand(1, 10) > 5) {
                $effectiveRemovals[] = new LWWElementVertex(new DateTimeImmutable(), uniqid());
            } else {
                $effectiveRemovals[] = new LWWElementEdge(new DateTimeImmutable(), uniqid(), uniqid());
            }
        }
        $mockResult = new LWWElementSetMergeResult($effectiveAdditions, $effectiveRemovals);
        $this->assertEquals($effectiveAdditions, $mockResult->effectiveAdditions());
        $this->assertEquals($effectiveRemovals, $mockResult->effectiveRemovals());
    }
}
