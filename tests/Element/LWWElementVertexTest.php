<?php

use NicPoyia\CRDT\LWW\Graph\Element\LWWElement;
use NicPoyia\CRDT\LWW\Graph\Element\LWWElementVertex;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;

/**
 * Test class for LWWElementVertex
 * @covers \NicPoyia\CRDT\LWW\Graph\Element\LWWElementVertex
 * @covers \NicPoyia\CRDT\LWW\Graph\Element\LWWElement
 */
class LWWElementVertexTest extends TestCase
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
        $vertex = uniqid();
        $timestamp = new DateTimeImmutable();
        $createdElement = new LWWElementVertex($timestamp, $vertex);
        // Check equality of passed values with the ones registered
        $this->assertEquals($vertex, $createdElement->vertexValue());
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
        $vertex = uniqid();
        $timestampA = new DateTimeImmutable();
        $elementA = new LWWElementVertex($timestampA, $vertex);
        // Check self equality
        $this->assertTrue($elementA->equals($elementA));
        // Test equality when timestamps are different but values the same
        $timeshiftedElementA = new LWWElementVertex($timestampA->add(new DateInterval("PT1H")), $vertex);
        $this->assertTrue($elementA->equals($timeshiftedElementA));
        // Test equality with different value
        $this->assertFalse($elementA->equals(new LWWElementVertex(new DateTimeImmutable(), uniqid())));
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
        $vertex = uniqid();
        $timestamp = new DateTimeImmutable();
        $createdElement = new LWWElementVertex($timestamp, $vertex);
        // Check replication
        $replicatedElement = $createdElement->replicateNow();
        $this->assertEquals($vertex, $replicatedElement->vertexValue());
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
        $vertex = uniqid();
        $timestamp = new DateTimeImmutable();
        $createdElement = new LWWElementVertex($timestamp, $vertex);
        $uniqueValue = $createdElement->uniqueValue();
        // Check if unique value includes the vertex value
        $this->assertStringContainsString($vertex, $uniqueValue);
        // Check if unique value is constant
        $this->assertEquals($uniqueValue, $createdElement->uniqueValue());
    }
}
