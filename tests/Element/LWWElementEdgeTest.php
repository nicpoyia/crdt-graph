<?php

use NicPoyia\CRDT\LWW\Graph\Element\LWWElement;
use NicPoyia\CRDT\LWW\Graph\Element\LWWElementEdge;
use PHPUnit\Framework\Exception;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

/**
 * Test class for LWWElementEdge
 * @covers \NicPoyia\CRDT\LWW\Graph\Element\LWWElementEdge
 * @covers \NicPoyia\CRDT\LWW\Graph\Element\LWWElement
 */
class LWWElementEdgeTest extends TestCase
{
    /**
     * Tests construction of arbitrary element with 2 vertex values.
     * @return void
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function test__construct()
    {
        // Crate mock element
        $vertexValueA = uniqid();
        $vertexValueB = uniqid();
        $timestamp = new DateTimeImmutable();
        $createdElement = new LWWElementEdge($timestamp, $vertexValueA, $vertexValueB);
        // Check equality of passed values with the ones registered
        $this->assertEquals($vertexValueA, $createdElement->vertexValA());
        $this->assertEquals($vertexValueB, $createdElement->vertexValB());
        $this->assertEquals($timestamp, $createdElement->timestamp());
    }

    /**
     * Tests equality method equals.
     * @return void
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testEquals()
    {
        // Crate mock element
        $vertexA = uniqid();
        $vertexB = uniqid();
        $timestampA = new DateTimeImmutable();
        $elementA = new LWWElementEdge($timestampA, $vertexA, $vertexB);
        // Check self equality
        $this->assertTrue($elementA->equals($elementA));
        // Test equality when timestamps are different but values the same
        $timeshiftedElementA = new LWWElementEdge($timestampA->add(new DateInterval("PT1H")), $vertexA, $vertexB);
        $this->assertTrue($elementA->equals($timeshiftedElementA));
        // Test equality with different values
        $this->assertFalse($elementA->equals(new LWWElementEdge(new DateTimeImmutable(), $vertexA, uniqid())));
        $this->assertFalse($elementA->equals(new LWWElementEdge(new DateTimeImmutable(), uniqid(), $vertexB)));
        $this->assertFalse($elementA->equals(new LWWElementEdge(new DateTimeImmutable(), uniqid(), uniqid())));
        // Test equality with different concrete class
        $this->assertFalse($elementA->equals(new class extends LWWElement {
            public function __construct()
            {
                parent::__construct(new DateTimeImmutable());
            }

            public function equals(LWWElement $otherElement): bool
            {
                // Always return true to check if equals return false if concrete class is different
                return true;
            }

            public function replicateNow(): LWWElement
            {
                // Mock implementation for testing purposes
                return $this;
            }

            public function uniqueValue(): string
            {
                // Mock implementation for testing purposes
                return uniqid();
            }
        }));
    }

    /**
     * Tests replication method replicateNow.
     * @return void
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testReplicateNow()
    {
        // Crate mock element
        $vertexA = uniqid();
        $vertexB = uniqid();
        $timestamp = new DateTimeImmutable();
        $createdElement = new LWWElementEdge($timestamp, $vertexA, $vertexB);
        // Check replication
        $replicatedElement = $createdElement->replicateNow();
        $this->assertEquals($vertexA, $replicatedElement->vertexValA());
        $this->assertEquals($vertexB, $replicatedElement->vertexValB());
        $this->assertNotEquals($timestamp, $replicatedElement->timestamp());
    }

    /**
     * @return void
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testUniqueValue()
    {
        // Crate mock element
        $vertexA = uniqid();
        $vertexB = uniqid();
        $timestamp = new DateTimeImmutable();
        $createdElement = new LWWElementEdge($timestamp, $vertexA, $vertexB);
        $uniqueValue = $createdElement->uniqueValue();
        // Check if unique value includes the vertex values
        $this->assertStringContainsString($vertexA, $uniqueValue);
        $this->assertStringContainsString($vertexB, $uniqueValue);
        // Check if unique value is constant
        $this->assertEquals($uniqueValue, $createdElement->uniqueValue());
    }
}
