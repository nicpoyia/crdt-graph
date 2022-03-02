<?php

namespace NicPoyia\CRDT\LWW\Graph\Test\Graph;

use DateTimeImmutable;
use InvalidArgumentException;
use NicPoyia\CRDT\LWW\Graph\Element\LWWElementEdge;
use NicPoyia\CRDT\LWW\Graph\Element\LWWElementVertex;
use NicPoyia\CRDT\LWW\Graph\Exception\VertexNotFoundInGraphException;
use NicPoyia\CRDT\LWW\Graph\LWWElementGraph;
use NicPoyia\CRDT\LWW\Graph\Test\Set\LWWElementSetTestUtils;
use PHPUnit\Framework\Exception;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;

/**
 * Test class for LWWElementGraph containing tests about merge operation
 * @covers \NicPoyia\CRDT\LWW\Graph\LWWElementGraph
 */
class LWWElementGraphMergeTest extends TestCase
{

    /**
     * Use case where changes are merged on an empty LWW-Element-Graph.
     * Makes sure that the basic merge functionality is responding as expected.
     * @return void
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testMergeOnEmptyGraph()
    {
        // Create empty graph
        $graph = new LWWElementGraph();
        // Merge changes including:
        // - Additions of new edges
        // - Additions of existing edges
        // - Additions of removed edges
        // - Removals of non-existing edges
        $mockVertexDownstream = LWWElementSetTestUtils::generateMockDownstreamChanges([], [], false);
        $mockEdgeDownstream = LWWElementSetTestUtils::generateMockDownstreamChanges([], [], true);
        /** @var LWWElementVertex[] $addedVertices */
        $addedVertices = $mockVertexDownstream->additions();
        /** @var LWWElementVertex[] $removedVertices */
        $removedVertices = $mockVertexDownstream->removals();
        /** @var LWWElementEdge[] $addedEdges */
        $addedEdges = $mockEdgeDownstream->additions();
        /** @var LWWElementEdge[] $removedEdges */
        $removedEdges = $mockEdgeDownstream->removals();
        // Calculate expected adjacency lists
        $expectedAdjacency = [];
        foreach ($addedEdges as $addedEdge) {
            $vertexA = $addedEdge->vertexValA();
            $vertexB = $addedEdge->vertexValB();
            if (array_key_exists($vertexA, $expectedAdjacency)) {
                $expectedAdjacency[$vertexA][$vertexB] = true;
            } else {
                $expectedAdjacency[$vertexA] = [$vertexB => true];
            }
            if (array_key_exists($vertexB, $expectedAdjacency)) {
                $expectedAdjacency[$vertexB][$vertexA] = true;
            } else {
                $expectedAdjacency[$vertexB] = [$vertexA => true];
            }
        }
        foreach ($removedEdges as $removedEdge) {
            $vertexA = $removedEdge->vertexValA();
            $vertexB = $removedEdge->vertexValB();
            if (array_key_exists($vertexA, $expectedAdjacency)) {
                unset($expectedAdjacency[$vertexA][$vertexB]);
            }
            if (array_key_exists($vertexB, $expectedAdjacency)) {
                unset($expectedAdjacency[$vertexB][$vertexA]);
            }
        }
        // Add all required vertices for the assertions to make sense
        foreach ($addedEdges as $addedEdge) {
            $graph->addVertex($addedEdge->vertexValA());
            $graph->addVertex($addedEdge->vertexValB());
        }
        // Merge changes and assert that the latest state has been populated as expected
        $graph->merge($addedVertices, $removedVertices, $addedEdges, $removedEdges);
        foreach ($expectedAdjacency as $vertex => $expectedConnections) {
            $actualConnections = $graph->getConnectedVertices($vertex);
            $this->assertSameSize($expectedConnections, $actualConnections);
            foreach (array_keys($expectedConnections) as $expectedConnection) {
                $this->assertContains($expectedConnection, $actualConnections);
            }
        }
    }

    /**
     * Test case ensuring "Commutative" property (required property of merge operation)
     * See https://en.wikipedia.org/wiki/Commutative_property
     * Makes sure that the direction of merge operation will not affect the results.
     * For example, if we merge replica A on B should produce the same results as merging B on A, as shown below:
     * A merge B = B merge A
     * @dataProvider getMockInitialGraph
     * @param LWWElementGraph $initialGraph Mock initial graph
     * @param LWWElementVertex[] $alreadyAddedVertices Vertices added at least once to the initial graph
     * @param LWWElementEdge[] $alreadyAddedEdges Edges added at least once to the initial graph
     * @param LWWElementVertex[] $alreadyRemovedVertices Vertices that are removed from the initial graph
     * @param LWWElementEdge[] $alreadyRemovedEdges Edges that are removed from the initial graph
     * @return void
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws VertexNotFoundInGraphException
     */
    public function testMergeCommutativeProperty(
        LWWElementGraph $initialGraph,
        array           $alreadyAddedVertices,
        array           $alreadyAddedEdges,
        array           $alreadyRemovedVertices,
        array           $alreadyRemovedEdges
    )
    {
        // Merge changes including:
        // - Additions of new edges
        // - Additions of existing edges
        // - Additions of removed edges
        // - Removals of non-existing edges
        // - Removals of existing edges (includes both pre-existing, newly-added and re-added edges)
        $mockVertexDownstream = LWWElementSetTestUtils::generateMockDownstreamChanges(
            $alreadyAddedVertices, $alreadyRemovedVertices, false
        );
        $mockEdgeDownstream = LWWElementSetTestUtils::generateMockDownstreamChanges(
            $alreadyAddedEdges, $alreadyRemovedEdges, true
        );
        /** @var LWWElementVertex[] $addedVertices */
        $addedVertices = $mockVertexDownstream->additions();
        /** @var LWWElementVertex[] $removedVertices */
        $removedVertices = $mockVertexDownstream->removals();
        /** @var LWWElementEdge[] $addedEdges */
        $addedEdges = $mockEdgeDownstream->additions();
        /** @var LWWElementEdge[] $removedEdges */
        $removedEdges = $mockEdgeDownstream->removals();
        $initialGraph->merge($addedVertices, $removedVertices, $addedEdges, $removedEdges);
        $adjacencyAfterMerge = [];
        foreach (array_merge($alreadyAddedVertices, $addedVertices) as $nextVertex) {
            $nextVertexValue = $nextVertex->vertexValue();
            $adjacencyAfterMerge[$nextVertexValue] = $initialGraph->getConnectedVertices($nextVertexValue);
        }
        // Merge edges of the initial graph into a graph only containing the previously used downstream changes
        // This will mimic a change of direction of the merge operation
        // Then assert that the produced result is the same in both cases
        $graphWithDownstreamContent = new LWWElementGraph();
        foreach ($addedEdges as $nextAddition) {
            $graphWithDownstreamContent->addVertex($nextAddition->vertexValA());
            $graphWithDownstreamContent->addVertex($nextAddition->vertexValB());
            $graphWithDownstreamContent->addEdge($nextAddition->vertexValA(), $nextAddition->vertexValB());
        }
        foreach ($removedEdges as $nextRemoval) {
            $graphWithDownstreamContent->removeEdge($nextRemoval->vertexValA(), $nextRemoval->vertexValB());
        }
        $allAddedEdgesInverse = [];
        foreach ($alreadyAddedEdges as $nextEdge) {
            $allAddedEdgesInverse[] = $nextEdge;
        }
        foreach ($alreadyRemovedEdges as $nextEdge) {
            // We have stored timestamp of removal, so reconstruction the edge as it was added before removal,
            // which will help with simulating the inverse merge operation
            $allAddedEdgesInverse[] = LWWElementSetTestUtils::replicateElementWithXHoursTimeshift($nextEdge, -1);
        }
        $graphWithDownstreamContent->merge([], [], $allAddedEdgesInverse, $alreadyRemovedEdges);
        $adjacencyAfterInverseMerge = [];
        foreach (array_merge($alreadyAddedVertices, $addedVertices) as $nextVertex) {
            $nextVertexValue = $nextVertex->vertexValue();
            $adjacencyAfterInverseMerge[$nextVertexValue] = $initialGraph->getConnectedVertices($nextVertexValue);
        }
        $this->assertEquals($adjacencyAfterMerge, $adjacencyAfterInverseMerge);
    }

    /**
     * Test case ensuring "Associative" property (required property of merge operation)
     * See https://en.wikipedia.org/wiki/Associative_property
     * Makes sure that rearranging the order of execution (or the parentheses) of MORE THAN 2 merge operations
     * will not change the results.
     * For example, if we merge replica B on A to produce a result Z, then merge a third replica C on Z,
     * should produce the same result as merging C on A, and then merge B on the produced result, as shown below:
     * (A merge B) merge C = (A merge C) merge B
     * @dataProvider getMockInitialGraph
     * @param LWWElementGraph $initialGraph Mock initial graph
     * @param LWWElementVertex[] $alreadyAddedVertices Vertices added at least once to the initial graph
     * @param LWWElementEdge[] $alreadyAddedEdges Edges added at least once to the initial graph
     * @param LWWElementVertex[] $alreadyRemovedVertices Vertices that are removed from the initial graph
     * @param LWWElementEdge[] $alreadyRemovedEdges Edges that are removed from the initial graph
     * @return void
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testMergeAssociativeProperty(
        LWWElementGraph $initialGraph,
        array           $alreadyAddedVertices,
        array           $alreadyAddedEdges,
        array           $alreadyRemovedVertices,
        array           $alreadyRemovedEdges
    )
    {
        // We are going to use 2 mock downstream change-sets (B and C)
        // Merge changes including:
        // - Additions of new edges
        // - Additions of existing edges
        // - Additions of removed edges
        // - Removals of non-existing edges
        // - Removals of existing edges (includes both pre-existing, newly-added and re-added edges)
        $initialGraphClone = unserialize(serialize($initialGraph));
        $mockVertexDownstreamB = LWWElementSetTestUtils::generateMockDownstreamChanges(
            $alreadyAddedVertices, $alreadyRemovedVertices, false
        );
        $mockEdgeDownstreamB = LWWElementSetTestUtils::generateMockDownstreamChanges(
            $alreadyAddedEdges, $alreadyRemovedEdges, true
        );
        $mockVertexDownstreamC = LWWElementSetTestUtils::generateMockDownstreamChanges(
            $alreadyAddedVertices, $alreadyRemovedVertices, false
        );
        $mockEdgeDownstreamC = LWWElementSetTestUtils::generateMockDownstreamChanges(
            $alreadyAddedEdges, $alreadyRemovedEdges, true
        );
        /** @var LWWElementVertex[] $addedVerticesB */
        $addedVerticesB = $mockVertexDownstreamB->additions();
        /** @var LWWElementVertex[] $removedVerticesB */
        $removedVerticesB = $mockVertexDownstreamB->removals();
        /** @var LWWElementEdge[] $addedEdgesB */
        $addedEdgesB = $mockEdgeDownstreamB->additions();
        /** @var LWWElementEdge[] $removedEdgesB */
        $removedEdgesB = $mockEdgeDownstreamB->removals();
        /** @var LWWElementVertex[] $addedVerticesC */
        $addedVerticesC = $mockVertexDownstreamC->additions();
        /** @var LWWElementVertex[] $removedVerticesC */
        $removedVerticesC = $mockVertexDownstreamC->removals();
        /** @var LWWElementEdge[] $addedEdgesC */
        $addedEdgesC = $mockEdgeDownstreamC->additions();
        /** @var LWWElementEdge[] $removedEdgesC */
        $removedEdgesC = $mockEdgeDownstreamC->removals();
        // Perform "(A merge B) merge C"
        $initialGraph->merge($addedVerticesB, $removedVerticesB, $addedEdgesB, $removedEdgesB);
        $initialGraph->merge($addedVerticesC, $removedVerticesC, $addedEdgesC, $removedEdgesC);
        $adjacencyAfterMergeA = [];
        foreach (array_merge($alreadyAddedVertices, $addedVerticesB, $addedVerticesC) as $nextVertex) {
            $nextVertexValue = $nextVertex->vertexValue();
            $adjacencyAfterMergeA[$nextVertexValue] = $initialGraph->getConnectedVertices($nextVertexValue);
        }
        // Rearrange the order of execution and assert that result is the same
        // Perform "(A merge C) merge B"
        $initialGraphClone->merge($addedVerticesC, $removedVerticesC, $addedEdgesC, $removedEdgesC);
        $initialGraphClone->merge($addedVerticesB, $removedVerticesB, $addedEdgesB, $removedEdgesB);
        $adjacencyAfterMergeB = [];
        foreach (array_merge($alreadyAddedVertices, $addedVerticesB, $addedVerticesC) as $nextVertex) {
            $nextVertexValue = $nextVertex->vertexValue();
            $adjacencyAfterMergeB[$nextVertexValue] = $initialGraph->getConnectedVertices($nextVertexValue);
        }
        $this->assertSameSize($adjacencyAfterMergeA, $adjacencyAfterMergeB);
        foreach ($adjacencyAfterMergeA as $nextVertex => $nextAdjacencyList) {
            foreach ($nextAdjacencyList as $nextConnectedVertex) {
                $this->assertArrayHasKey($nextVertex, $adjacencyAfterMergeB);
                $this->assertContains($nextConnectedVertex, $adjacencyAfterMergeB[$nextVertex]);
            }
        }
        foreach ($adjacencyAfterMergeB as $nextVertex => $nextAdjacencyList) {
            foreach ($nextAdjacencyList as $nextConnectedVertex) {
                $this->assertArrayHasKey($nextVertex, $adjacencyAfterMergeA);
                $this->assertContains($nextConnectedVertex, $adjacencyAfterMergeA[$nextVertex]);
            }
        }
    }

    /**
     * Test case ensuring "Idempotence" property (required property of merge operation)
     * See https://en.wikipedia.org/wiki/Idempotence
     * Makes sure that the result will not change regardless how many times a replica is merged on another.
     * For example, if we merge replica B on A to produce a result C,
     * then C should remain the same after merging B on it multiple times, as shown below:
     * A merge B = (A merge B) merge B = ((A merge B) merge B) merge B etc.
     * @dataProvider getMockInitialGraph
     * @param LWWElementGraph $initialGraph Mock initial graph
     * @param LWWElementVertex[] $alreadyAddedVertices Vertices added at least once to the initial graph
     * @param LWWElementEdge[] $alreadyAddedEdges Edges added at least once to the initial graph
     * @param LWWElementVertex[] $alreadyRemovedVertices Vertices that are removed from the initial graph
     * @param LWWElementEdge[] $alreadyRemovedEdges Edges that are removed from the initial graph
     * @return void
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testMergeIdempotenceProperty(
        LWWElementGraph $initialGraph,
        array           $alreadyAddedVertices,
        array           $alreadyAddedEdges,
        array           $alreadyRemovedVertices,
        array           $alreadyRemovedEdges
    )
    {
        // Merge changes including:
        // - Additions of new edges
        // - Additions of existing edges
        // - Additions of removed edges
        // - Removals of non-existing edges
        // - Removals of existing edges (includes both pre-existing, newly-added and re-added edges)
        $mockVertexDownstream = LWWElementSetTestUtils::generateMockDownstreamChanges(
            $alreadyAddedVertices, $alreadyRemovedVertices, false
        );
        $mockEdgeDownstream = LWWElementSetTestUtils::generateMockDownstreamChanges(
            $alreadyAddedEdges, $alreadyRemovedEdges, true
        );
        /** @var LWWElementVertex[] $addedVertices */
        $addedVertices = $mockVertexDownstream->additions();
        /** @var LWWElementVertex[] $removedVertices */
        $removedVertices = $mockVertexDownstream->removals();
        /** @var LWWElementEdge[] $addedEdges */
        $addedEdges = $mockEdgeDownstream->additions();
        /** @var LWWElementEdge[] $removedEdges */
        $removedEdges = $mockEdgeDownstream->removals();
        $initialGraph->merge($addedVertices, $removedVertices, $addedEdges, $removedEdges);
        $adjacencyAfterFirstMerge = [];
        foreach (array_merge($alreadyAddedVertices, $addedVertices) as $nextVertex) {
            $nextVertexValue = $nextVertex->vertexValue();
            $adjacencyAfterFirstMerge[$nextVertexValue] = $initialGraph->getConnectedVertices($nextVertexValue);
        }
        // Merge again multiple times and assert that state remains the same after each extra merge
        for ($i = 0; $i < rand(4, 8); $i++) {
            $initialGraph->merge($addedVertices, $removedVertices, $addedEdges, $removedEdges);
            $adjacencyAfterNextMerge = [];
            foreach (array_merge($alreadyAddedVertices, $addedVertices) as $nextVertex) {
                $nextVertexValue = $nextVertex->vertexValue();
                $adjacencyAfterNextMerge[$nextVertexValue] = $initialGraph->getConnectedVertices($nextVertexValue);
            }
            $this->assertEquals($adjacencyAfterFirstMerge, $adjacencyAfterNextMerge);
        }
    }

    /**
     * Data provider for mock initial graph state.
     * @return array[][]
     * @throws VertexNotFoundInGraphException
     */
    public function getMockInitialGraph(): array
    {
        // Generate mock graph with initial state
        $graph = new LWWElementGraph();
        $currentVertices = [];
        $currentEdges = [];
        $removedVertices = [];
        $removedEdges = [];
        // Added-only edges
        for ($i = 0; $i < rand(4, 10); $i++) {
            $addedEdge = $this->addNewEdgeInGraph($graph);
            $currentEdges[] = new LWWElementEdge(new DateTimeImmutable(), $addedEdge[0], $addedEdge[1]);
            $currentVertices[] = new LWWElementVertex(new DateTimeImmutable(), $addedEdge[0]);
            $currentVertices[] = new LWWElementVertex(new DateTimeImmutable(), $addedEdge[1]);
        }
        // Added and then removed edges
        for ($i = 0; $i < rand(4, 10); $i++) {
            $removedEdge = $this->addAndRemoveNewEdge($graph);
            $removedEdges[] = new LWWElementEdge(new DateTimeImmutable(), $removedEdge[0], $removedEdge[1]);
            $removedVertices[] = new LWWElementVertex(new DateTimeImmutable(), $removedEdge[0]);
            $removedVertices[] = new LWWElementVertex(new DateTimeImmutable(), $removedEdge[1]);
        }
        // Added, removed and then added edges
        for ($i = 0; $i < rand(4, 10); $i++) {
            $addedEdge = $this->addRemoveAndAddAgainNewEdge($graph);
            $currentEdges[] = new LWWElementEdge(new DateTimeImmutable(), $addedEdge[0], $addedEdge[1]);
            $currentVertices[] = new LWWElementVertex(new DateTimeImmutable(), $addedEdge[0]);
            $currentVertices[] = new LWWElementVertex(new DateTimeImmutable(), $addedEdge[1]);
        }
        return [[$graph, $currentVertices, $currentEdges, $removedVertices, $removedEdges]];
    }

    /**
     * Adds a new edge to the graph.
     * @param LWWElementGraph $elementGraph LWW-Element-Graph to add to
     * @return string[] Vertex values of the added edge
     * @throws VertexNotFoundInGraphException
     */
    protected function addNewEdgeInGraph(LWWElementGraph $elementGraph): array
    {
        $vertexA = uniqid();
        $vertexB = uniqid();
        $elementGraph->addVertex($vertexA);
        $elementGraph->addVertex($vertexB);
        $elementGraph->addEdge($vertexA, $vertexB);
        return [$vertexA, $vertexB];
    }

    /**
     * Adds a new element to the graph and then removes it.
     * @param LWWElementGraph $elementGraph LWW-Element-Graph to add/remove to/from
     * @return string[] Vertex values of the added edge
     * @throws VertexNotFoundInGraphException
     */
    protected function addAndRemoveNewEdge(LWWElementGraph $elementGraph): array
    {
        $vertexA = uniqid();
        $vertexB = uniqid();
        $elementGraph->addVertex($vertexA);
        $elementGraph->addVertex($vertexB);
        $elementGraph->addEdge($vertexA, $vertexB);
        $elementGraph->removeEdge($vertexA, $vertexB);
        return [$vertexA, $vertexB];
    }

    /**
     * Adds a new element to the graph, then removes it, then adds it again.
     * @param LWWElementGraph $elementGraph LWW-Element-Graph to add/remove to/from
     * @return string[] Vertex values of the added edge
     * @throws VertexNotFoundInGraphException
     */
    protected function addRemoveAndAddAgainNewEdge(LWWElementGraph $elementGraph): array
    {
        $vertexA = uniqid();
        $vertexB = uniqid();
        $elementGraph->addVertex($vertexA);
        $elementGraph->addVertex($vertexB);
        $elementGraph->addEdge($vertexA, $vertexB);
        $elementGraph->removeEdge($vertexA, $vertexB);
        $elementGraph->addEdge($vertexA, $vertexB);
        return [$vertexA, $vertexB];
    }
}