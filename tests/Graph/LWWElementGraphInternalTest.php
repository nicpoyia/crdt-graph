<?php

namespace NicPoyia\CRDT\LWW\Graph\Test\Graph;

use NicPoyia\CRDT\LWW\Graph\Exception\PathNotFoundInGraphException;
use NicPoyia\CRDT\LWW\Graph\Exception\VertexNotFoundInGraphException;
use NicPoyia\CRDT\LWW\Graph\LWWElementGraph;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Exception;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

/**
 * Test class for LWWElementGraph containing tests about internal operations
 * @covers \NicPoyia\CRDT\LWW\Graph\LWWElementGraph
 * @covers \NicPoyia\CRDT\LWW\Graph\Exception\VertexNotFoundInGraphException
 * @covers \NicPoyia\CRDT\LWW\Graph\Exception\PathNotFoundInGraphException
 */
class LWWElementGraphInternalTest extends TestCase
{

    /**
     * @return void
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testAddVertex()
    {
        $graph = new LWWElementGraph();
        $vertexValue = uniqid();
        $graph->addVertex($vertexValue);
        $this->assertTrue($graph->doesContainVertex($vertexValue));
    }

    /**
     * @return void
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testRemoveVertex()
    {
        $graph = new LWWElementGraph();
        // Check default behaviour
        $vertexValue = uniqid();
        $graph->removeVertex($vertexValue);
        $this->assertFalse($graph->doesContainVertex($vertexValue));
        // Check behaviour after addition and second removal
        $graph->addVertex($vertexValue);
        $this->assertTrue($graph->doesContainVertex($vertexValue));
        $graph->removeVertex($vertexValue);
        $this->assertFalse($graph->doesContainVertex($vertexValue));
    }

    /**
     * @return void
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testDoesContainVertex()
    {
        $graph = new LWWElementGraph();
        // Check default behaviour (unknown vertex)
        $this->assertFalse($graph->doesContainVertex(uniqid()));
        // Check behaviour after addition
        $vertexValue = uniqid();
        $graph->addVertex($vertexValue);
        $this->assertTrue($graph->doesContainVertex($vertexValue));
        // Check behaviour after removal
        $graph->removeVertex($vertexValue);
        $this->assertFalse($graph->doesContainVertex($vertexValue));
    }

    /**
     * @return void
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws AssertionFailedError
     */
    public function testAddEdgeVertexNotFound()
    {
        $graph = new LWWElementGraph();
        $vertexA = uniqid();
        $vertexB = uniqid();
        $graph->addVertex($vertexA);
        $exceptionThrown = false;
        try {
            // Use case with second vertex not found
            $graph->addEdge($vertexA, $vertexB);
        } catch (VertexNotFoundInGraphException $e) {
            $exceptionThrown = true;
            // Inspect thrown exception
            $this->assertEquals($vertexB, $e->vertexValue());
        }
        if (!$exceptionThrown) {
            $this->fail("Expected exception VertexNotFoundInGraphException not thrown");
        }
        $exceptionThrown = false;
        try {
            // Check with first vertex not found
            $graph->addEdge($vertexB, $vertexA);
        } catch (VertexNotFoundInGraphException $e) {
            $exceptionThrown = true;
            // Inspect thrown exception
            $this->assertEquals($vertexB, $e->vertexValue());
        }
        if (!$exceptionThrown) {
            $this->fail("Expected exception VertexNotFoundInGraphException not thrown");
        }
    }

    /**
     * @return void
     * @throws VertexNotFoundInGraphException
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testAddEdge()
    {
        $graph = new LWWElementGraph();
        $vertexA = uniqid();
        $vertexB = uniqid();
        $graph->addVertex($vertexA);
        $graph->addVertex($vertexB);
        $graph->addEdge($vertexA, $vertexB);
        $connectedVerticesOnA = $graph->getConnectedVertices($vertexA);
        $this->assertCount(1, $connectedVerticesOnA);
        $this->assertContains($vertexB, $connectedVerticesOnA);
        $connectedVerticesOnB = $graph->getConnectedVertices($vertexB);
        $this->assertCount(1, $connectedVerticesOnB);
        $this->assertContains($vertexA, $connectedVerticesOnB);
    }

    /**
     * @return void
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws VertexNotFoundInGraphException
     */
    public function testRemoveEdge()
    {
        $graph = new LWWElementGraph();
        $vertexA = uniqid();
        $vertexB = uniqid();
        // Check default behaviour
        $this->assertFalse($graph->doesContainEdge($vertexA, $vertexB));
        $this->assertFalse($graph->doesContainEdge($vertexB, $vertexA));
        $this->assertEmpty($graph->getConnectedVertices($vertexA));
        $this->assertEmpty($graph->getConnectedVertices($vertexB));
        // Check behaviour after addition and removal
        $graph->addVertex($vertexA);
        $this->assertFalse($graph->doesContainEdge($vertexA, $vertexB));
        $this->assertFalse($graph->doesContainEdge($vertexB, $vertexA));
        $graph->addVertex($vertexB);
        $graph->addEdge($vertexA, $vertexB);
        $this->assertTrue($graph->doesContainEdge($vertexA, $vertexB));
        $this->assertTrue($graph->doesContainEdge($vertexB, $vertexA));
        $connectedVerticesOnA = $graph->getConnectedVertices($vertexA);
        $this->assertCount(1, $connectedVerticesOnA);
        $this->assertContains($vertexB, $connectedVerticesOnA);
        $graph->removeEdge($vertexA, $vertexB);
        $this->assertFalse($graph->doesContainEdge($vertexA, $vertexB));
        $this->assertFalse($graph->doesContainEdge($vertexB, $vertexA));
        $this->assertEmpty($graph->getConnectedVertices($vertexA));
        $this->assertEmpty($graph->getConnectedVertices($vertexB));
    }

    /**
     * @return void
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws VertexNotFoundInGraphException
     */
    public function testGetConnectedVertices()
    {
        $graph = new LWWElementGraph();
        // Check default behaviour (unknown vertex)
        $vertexA = uniqid();
        $this->assertEmpty($graph->getConnectedVertices($vertexA));
        // Check default behaviour (existent unconnected vertex)
        $graph->addVertex($vertexA);
        $this->assertEmpty($graph->getConnectedVertices($vertexA));
        // Check behaviour after addition (validate that it is different from default)
        $vertexB = uniqid();
        $graph->addVertex($vertexB);
        $graph->addEdge($vertexA, $vertexB);
        $connectedVertices = $graph->getConnectedVertices($vertexA);
        $this->assertCount(1, $connectedVertices);
        $this->assertEquals($vertexB, $connectedVertices[0]);
    }

    /**
     * Use case where the same edge has been added twice
     * @return void
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws VertexNotFoundInGraphException
     */
    public function testGetConnectedVerticesEdgeAddedTwice()
    {
        $graph = new LWWElementGraph();
        // Check behaviour after edge has been added twice
        $vertexA = uniqid();
        $vertexB = uniqid();
        $graph->addVertex($vertexA);
        $graph->addVertex($vertexB);
        $graph->addEdge($vertexA, $vertexB);
        $graph->addEdge($vertexA, $vertexB);
        $connectedVertices = $graph->getConnectedVertices($vertexA);
        $this->assertCount(1, $connectedVertices);
        $this->assertEquals($vertexB, $connectedVertices[0]);
    }

    /**
     * Use case where one of the connected vertices has been removed
     * @return void
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws VertexNotFoundInGraphException
     */
    public function testGetConnectedVerticesVertexRemoved()
    {
        $graph = new LWWElementGraph();
        $vertexA = uniqid();
        $vertexB = uniqid();
        $vertexC = uniqid();
        // Check default behaviour
        $this->assertFalse($graph->doesContainEdge($vertexA, $vertexB));
        $this->assertEmpty($graph->getConnectedVertices($vertexA));
        $this->assertEmpty($graph->getConnectedVertices($vertexB));
        // Check behaviour after addition
        $graph->addVertex($vertexA);
        $graph->addVertex($vertexB);
        $graph->addVertex($vertexC);
        $graph->addEdge($vertexA, $vertexB);
        $graph->addEdge($vertexA, $vertexC);
        $this->assertTrue($graph->doesContainEdge($vertexA, $vertexB));
        $this->assertTrue($graph->doesContainEdge($vertexA, $vertexC));
        $connectedVerticesOnA = $graph->getConnectedVertices($vertexA);
        $this->assertCount(2, $connectedVerticesOnA);
        $this->assertContains($vertexB, $connectedVerticesOnA);
        $this->assertContains($vertexC, $connectedVerticesOnA);
        // Check behaviour after removal of connected vertex
        $graph->removeVertex($vertexB);
        $connectedVerticesOnA = $graph->getConnectedVertices($vertexA);
        $this->assertCount(1, $connectedVerticesOnA);
        $this->assertContains($vertexC, $connectedVerticesOnA);
        // Check behaviour after removal of source vertex (the one passed as a parameter)
        $graph->addVertex($vertexC);
        $graph->removeVertex($vertexA);
        $this->assertEmpty($graph->getConnectedVertices($vertexA));
    }

    /**
     * @return void
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws VertexNotFoundInGraphException
     * @throws PathNotFoundInGraphException
     */
    public function testFindPath()
    {
        $graph = new LWWElementGraph();
        $vertexA = uniqid();
        $vertexB = uniqid();
        $vertexC = uniqid();
        $vertexD = uniqid();
        $graph->addVertex($vertexA);
        $graph->addVertex($vertexB);
        $graph->addVertex($vertexC);
        $graph->addVertex($vertexD);
        $graph->addEdge($vertexA, $vertexC);
        $graph->addEdge($vertexC, $vertexB);
        $graph->addEdge($vertexB, $vertexD);
        $this->assertEquals([$vertexA, $vertexC, $vertexB, $vertexD], $graph->findPath($vertexA, $vertexD));
    }

    /**
     * @return void
     * @throws AssertionFailedError
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws VertexNotFoundInGraphException
     */
    public function testFindPathPathNotFound()
    {
        $graph = new LWWElementGraph();
        $vertexA = uniqid();
        $vertexB = uniqid();
        $vertexC = uniqid();
        $vertexD = uniqid();
        $graph->addVertex($vertexA);
        $graph->addVertex($vertexB);
        $graph->addVertex($vertexC);
        $graph->addVertex($vertexD);
        $graph->addEdge($vertexA, $vertexC);
        $graph->addEdge($vertexC, $vertexD);
        $exceptionThrown = false;
        try {
            $graph->findPath($vertexA, $vertexB);
        } catch (PathNotFoundInGraphException $e) {
            $exceptionThrown = true;
            // Inspect thrown exception
            $this->assertEquals($vertexA, $e->vertexA());
            $this->assertEquals($vertexB, $e->vertexB());
        }
        if (!$exceptionThrown) {
            $this->fail("Expected exception PathNotFoundInGraphException not thrown");
        }
    }

    /**
     * Intensive testing of the lookup algorithm using a full graph
     * @return void
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws PathNotFoundInGraphException
     * @throws VertexNotFoundInGraphException
     */
    public function testFindPathFullGraph()
    {
        // Create a full graph
        $graph = new LWWElementGraph();
        $graphVertices = [];
        for ($i = 0; $i < rand(10, 20); $i++) {
            $vertexA = uniqid();
            $graph->addVertex($vertexA);
            $graphVertices[] = $vertexA;
            // Create all possible edges in the graph
            foreach ($graphVertices as $nextVertex) {
                $graph->addEdge($vertexA, $nextVertex);
            }
        }
        // Pick a random pair of vertices
        $sourceVertex = $graphVertices[rand(0, count($graphVertices) - 1)];
        $targetVertex = $graphVertices[rand(0, count($graphVertices) - 1)];
        // A path should be found because all possible edges were added
        $this->assertIsArray($graph->findPath($sourceVertex, $targetVertex));
    }
}
