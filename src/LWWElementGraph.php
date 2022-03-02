<?php

namespace NicPoyia\CRDT\LWW\Graph;

use DateTimeImmutable;
use NicPoyia\CRDT\LWW\Graph\Element\LWWElementEdge;
use NicPoyia\CRDT\LWW\Graph\Element\LWWElementVertex;
use NicPoyia\CRDT\LWW\Graph\Exception\PathNotFoundInGraphException;
use NicPoyia\CRDT\LWW\Graph\Exception\VertexNotFoundInGraphException;

/**
 * Implementation of an LWW-Element-Graph (Last-Write-Wins-Element-Set).
 * The implementation uses 2 instances of the LWW-Element-Set (one for Vertices and one for edges).
 */
class LWWElementGraph
{
    /**
     * @var LWWElementSet LWW-Element-Set of vertices
     */
    protected LWWElementSet $vertices;
    /**
     * @var LWWElementSet LWW-Element-Set of edges
     */
    protected LWWElementSet $edges;
    /**
     * @var string[] List with currently existing vertices. This is a projection of the latest known state of the vertices.
     *               It is used for complexity balancing purposes,
     *               i.e. slightly increases the complexity of updates but rapidly decreases complexity of reads.
     */
    protected array $vertState;
    /**
     * @var string[][] Array of lists which contain the adjacent vertices for each vertex.
     *                 This is a projection of the latest known state of the graph.
     *                 It is used for complexity balancing purposes,
     *                 i.e. slightly increases the complexity of updates but rapidly decreases complexity of reads.
     */
    protected array $adjacencyLists;

    public function __construct()
    {
        $this->vertices = new LWWElementSet();
        $this->edges = new LWWElementSet();
        $this->vertState = [];
        $this->adjacencyLists = [];
    }

    /**
     * Adds a vertex in the Graph
     * This update method is intended for execution only at the source.
     * @param string $vertexValue
     * @return void
     */
    public function addVertex(string $vertexValue)
    {
        $this->vertices->add(new LWWElementVertex(new DateTimeImmutable(), $vertexValue));
        // Update vertex state
        if (!in_array($vertexValue, $this->vertState)) {
            $this->vertState[] = $vertexValue;
        }
    }

    /**
     * Removes a vertex from the Graph
     * This update method is intended for execution only at the source.
     * @param string $vertexValue
     * @return void
     */
    public function removeVertex(string $vertexValue)
    {
        $this->vertices->remove(new LWWElementVertex(new DateTimeImmutable(), $vertexValue));
        // Update vertex state
        $vertexLookup = array_search($vertexValue, $this->vertState);
        if ($vertexLookup !== false) {
            unset($this->vertState[$vertexLookup]);
        }
    }

    /**
     * Checks if a vertex is on the Graph
     * @param string $vertexValue
     * @return bool
     */
    public function doesContainVertex(string $vertexValue): bool
    {
        return in_array($vertexValue, $this->vertState);
    }

    /**
     * Adds an edge on the Graph.
     * This update method is intended for execution only at the source.
     * @param string $vertexValueA Value of vertex A of the added edge
     * @param string $vertexValueB Value of vertex B of the added edge
     * @return void
     * @throws VertexNotFoundInGraphException
     */
    public function addEdge(string $vertexValueA, string $vertexValueB)
    {
        // Check if both vertices exist (only at source)
        if (!in_array($vertexValueA, $this->vertState)) {
            throw new VertexNotFoundInGraphException($vertexValueA);
        }
        if (!in_array($vertexValueB, $this->vertState)) {
            throw new VertexNotFoundInGraphException($vertexValueB);
        }
        $this->edges->add(new LWWElementEdge(new DateTimeImmutable(), $vertexValueA, $vertexValueB));
        // Update adjacency lists
        $this->updateAdjListsForAddedEdge($vertexValueA, $vertexValueB);
    }

    /**
     * Removes an edge from the Graph.
     * This update method is intended for execution only at the source.
     * @return void
     */
    public function removeEdge(string $vertexValueA, string $vertexValueB)
    {
        $this->edges->remove(new LWWElementEdge(new DateTimeImmutable(), $vertexValueA, $vertexValueB));
        // Update adjacency lists
        $this->updateAdjListsForRemEdge($vertexValueA, $vertexValueB);
    }

    /**
     * Checks if an edge is on the graph.
     * @param string $vertexValueA Value of vertex A of the edge
     * @param string $vertexValueB Value of vertex A of the edge
     * @return bool Whether the edge exists
     */
    public function doesContainEdge(string $vertexValueA, string $vertexValueB): bool
    {
        if (!in_array($vertexValueA, $this->vertState)) {
            // Vertex A does not exist
            return false;
        }
        if (!in_array($vertexValueB, $this->vertState)) {
            // Vertex A does not exist
            return false;
        }
        if (array_key_exists($vertexValueA, $this->adjacencyLists)
            && in_array($vertexValueB, $this->adjacencyLists[$vertexValueA])) {
            // Check second
            if (array_key_exists($vertexValueB, $this->adjacencyLists)
                && in_array($vertexValueA, $this->adjacencyLists[$vertexValueB])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Queries for all vertices connected to a vertex
     * @param string $refVertex Value of vertex to retrieve connections of
     * @return string[] All vertices connected to a specific vertex
     */
    public function getConnectedVertices(string $refVertex): array
    {
        if (!in_array($refVertex, $this->vertState)) {
            // Reference vertex does not exist
            return [];
        }
        if (array_key_exists($refVertex, $this->adjacencyLists)) {
            // Filter out only existent vertices in O(N)
            return array_intersect($this->adjacencyLists[$refVertex], $this->vertState);
        }
        return [];
    }

    /**
     * Finds any path between two vertices.
     * @param string $vertexA Source vertex
     * @param string $vertexB Target vertex
     * @return string[] Found path as array of vertices
     * @throws PathNotFoundInGraphException If NO path was found
     */
    public function findPath(string $vertexA, string $vertexB): array
    {
        // Using DFS because it tends to be quicker at finding ANY path (depends on the nature of the graph's data)
        // Stack here is implemented using a doubly linked list
        $visitedSet = [];
        $foundPath = $this->dfsFindAnyPath($vertexA, $vertexB, [], $visitedSet);
        if ($foundPath === null) {
            throw new PathNotFoundInGraphException($vertexA, $vertexB);
        }
        return $foundPath;
    }

    /**
     * Merge with concurrent changes from other graph/replica
     * @param LWWElementVertex[] $addVertices Vertex additions
     * @param LWWElementVertex[] $remVertices Vertex removals
     * @param LWWElementEdge[] $addEdges Edge additions
     * @param LWWElementEdge[] $remEdges Edge removals
     * @return void
     */
    public function merge(array $addVertices, array $remVertices, array $addEdges, array $remEdges)
    {
        // Merge LWW-Element-Sets
        $vertMergeRes = $this->vertices->merge($addVertices, $remVertices);
        $edgeMergeRes = $this->edges->merge($addEdges, $remEdges);
        // Update local projection/cache to reflect effective changes
        foreach ($vertMergeRes->effectiveAdditions() as $vertexAddition) {
            /** @var LWWElementVertex $vertexAddition */
            $this->vertState[] = $vertexAddition->vertexValue();
        }
        foreach ($vertMergeRes->effectiveRemovals() as $vertexRemoval) {
            /** @var LWWElementVertex $vertexRemoval */
            $vertexValue = $vertexRemoval->vertexValue();
            $vertexLookup = array_search($vertexValue, $this->vertState);
            if ($vertexLookup !== false) {
                unset($this->vertState[$vertexLookup]);
            }
        }
        foreach ($edgeMergeRes->effectiveAdditions() as $edgeAddition) {
            /** @var LWWElementEdge $edgeAddition */
            $this->updateAdjListsForAddedEdge($edgeAddition->vertexValA(), $edgeAddition->vertexValB());
        }
        foreach ($edgeMergeRes->effectiveRemovals() as $edgeRemoval) {
            /** @var LWWElementEdge $edgeRemoval */
            $this->updateAdjListsForRemEdge($edgeRemoval->vertexValA(), $edgeRemoval->vertexValB());
        }
    }

    /**
     * Finds ANY path (first found) using a recursive DFS implementation.
     * @param string $source Source vertex
     * @param string $target Target vertex
     * @param array $path Path of vertices so far
     * @param array $visitedSet Set of visited vertices as an associative array (use as hash-set)
     * @return string[]|null Found path as array of vertices OR null if NO path found
     */
    protected function dfsFindAnyPath(string $source, string $target, array $path, array &$visitedSet): ?array
    {
        $visitedSet[$source] = true;
        $path[] = $source;
        if ($source === $target) {
            // Path is found
            return $path;
        }
        foreach ($this->getConnectedVertices($source) as $connectedVertex) {
            if (!array_key_exists($connectedVertex, $visitedSet)) {
                $foundPath = $this->dfsFindAnyPath($connectedVertex, $target, $path, $visitedSet);
                if ($foundPath !== null) {
                    // Backtrack on first found path
                    return $foundPath;
                }
            }
        }
        return null;
    }

    /**
     * Updates the adjacency lists (local projection/cache) to reflect the addition of an edge
     * @param string $vertexValueA Vertex value A of added edge
     * @param string $vertexValueB Vertex value B of added edge
     * @return void
     */
    protected function updateAdjListsForAddedEdge(string $vertexValueA, string $vertexValueB)
    {
        // Update adjacency lists
        if (array_key_exists($vertexValueA, $this->adjacencyLists)) {
            if (!in_array($vertexValueB, $this->adjacencyLists[$vertexValueA])) {
                $this->adjacencyLists[$vertexValueA][] = $vertexValueB;
            }
        } else {
            $this->adjacencyLists[$vertexValueA] = [$vertexValueB];
        }
        if (array_key_exists($vertexValueB, $this->adjacencyLists)) {
            if (!in_array($vertexValueA, $this->adjacencyLists[$vertexValueB])) {
                $this->adjacencyLists[$vertexValueB][] = $vertexValueA;
            }
        } else {
            $this->adjacencyLists[$vertexValueB] = [$vertexValueA];
        }
    }

    /**
     * Updates the adjacency lists (local projection/cache) to reflect the removal of an edge
     * @param string $vertexValueA Vertex value A of removed edge
     * @param string $vertexValueB Vertex value B of removed edge
     * @return void
     */
    protected function updateAdjListsForRemEdge(string $vertexValueA, string $vertexValueB)
    {
        if (array_key_exists($vertexValueA, $this->adjacencyLists)) {
            $lookupPosition = array_search($vertexValueB, $this->adjacencyLists[$vertexValueA]);
            if ($lookupPosition !== false) {
                unset($this->adjacencyLists[$vertexValueA][$lookupPosition]);
            }
        }
        if (array_key_exists($vertexValueB, $this->adjacencyLists)) {
            $lookupPosition = array_search($vertexValueA, $this->adjacencyLists[$vertexValueB]);
            if ($lookupPosition !== false) {
                unset($this->adjacencyLists[$vertexValueB][$lookupPosition]);
            }
        }
    }
}