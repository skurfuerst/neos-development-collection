<?php
namespace TYPO3\TYPO3CR\Service\Utility;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "TYPO3CR".               *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3 of the   *
 * License, or (at your option) any later version.                        *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\TYPO3CR\Domain\Model\Node;
use TYPO3\TYPO3CR\Exception\WorkspaceException;

/**
 * Solve / sort nodes by dependencies for publishing
 */
class NodePublishingDependencySolver {

	/**
	 * @var array
	 */
	protected $nodesByPath;

	/**
	 * @var array
	 */
	protected $nodesByNodeData;

	/**
	 * @var array
	 */
	protected $dependenciesOutgoing;

	/**
	 * @var array
	 */
	protected $dependenciesIncoming;

	/**
	 * @var array
	 */
	protected $nodesWithoutIncoming;

	/**
	 * Sort nodes by an order suitable for publishing
	 *
	 * This makes sure all parent and moved-to relations are resolved and changes that need to be published
	 * before other changes will be published first.
	 *
	 * Uses topological sorting of node dependencies (http://en.wikipedia.org/wiki/Topological_sorting) to build a publishable order of nodes.
	 *
	 * @param array $nodes Array of nodes to sort, if dependencies are missing in this list an exception will be thrown
	 * @return array Array of nodes sorted by dependencies for publishing
	 * @throws WorkspaceException
	 */
	public function sort(array $nodes) {
		$this->buildNodeDependencies($nodes);
		$sortedNodes = $this->resolveDependencies();

		$dependencyCount = array_filter($this->dependenciesOutgoing, function($a) { return $a !== array(); });
		if (count($dependencyCount) > 0) {
			throw new WorkspaceException('Cannot publish a list of nodes because of cycles', 1416484223);
		}

		return $sortedNodes;
	}

	/**
	 * Prepare dependencies for the given list of nodes
	 *
	 * @param array $nodes Unsorted list of nodes
	 * @throws WorkspaceException
	 */
	protected function buildNodeDependencies(array $nodes) {
		$this->nodesByPath = array();
		$this->nodesByNodeData = array();
		$this->dependenciesOutgoing = array();
		$this->dependenciesIncoming = array();
		$this->nodesWithoutIncoming = array();

		/** @var Node $node */
		foreach ($nodes as $node) {
			$this->nodesByPath[$node->getPath()][] = $node;
			$this->nodesByNodeData[spl_object_hash($node->getNodeData())] = $node;
			$this->nodesWithoutIncoming[spl_object_hash($node)] = $node;
		}

		/** @var Node $node */
		foreach ($nodes as $node) {
			$nodeHash = spl_object_hash($node);

			// Add dependencies for (direct) parents, this will also cover moved and created nodes
			if (isset($this->nodesByPath[$node->getParentPath()])) {
				/** @var Node $parentNode */
				foreach ($this->nodesByPath[$node->getParentPath()] as $parentNode) {
					$dependencyHash = spl_object_hash($parentNode);
					$this->dependenciesIncoming[$nodeHash][$dependencyHash] = $parentNode;
					$this->dependenciesOutgoing[$dependencyHash][$nodeHash] = $node;
					unset($this->nodesWithoutIncoming[$nodeHash]);
				}
			}

			// Add a dependency for a moved-to reference
			$movedToNodeData = $node->getNodeData()->getMovedTo();
			if ($movedToNodeData !== NULL) {
				$movedToHash = spl_object_hash($movedToNodeData);
				if (!isset($this->nodesByNodeData[$movedToHash])) {
					throw new WorkspaceException('Cannot publish a list of nodes with missing dependency (' . $node->getPath() . ' needs ' . $movedToNodeData->getPath() . ' to be published)', 1416483470);
				}
				$dependencyHash = spl_object_hash($this->nodesByNodeData[$movedToHash]);
				$this->dependenciesIncoming[$nodeHash][$dependencyHash] = $this->nodesByNodeData[$movedToHash];
				$this->dependenciesOutgoing[$dependencyHash][$nodeHash] = $node;
				unset($this->nodesWithoutIncoming[$nodeHash]);
			}
		}
	}

	/**
	 * Resolve node dependencies
	 *
	 * 1. Pick a node from the set of nodes without incoming dependencies
	 * 2. For all dependencies of that node:
	 * 2a. Remove the dependency
	 * 2b. If the dependency has no other incoming dependencies itself, add it to the set of nodes without incoming dependencies
	 *
	 * @return array Sorted list of nodes (not all dependencies might be solved)
	 */
	protected function resolveDependencies() {
		$sortedNodes = array();
		while (count($this->nodesWithoutIncoming) > 0) {
			$node = array_pop($this->nodesWithoutIncoming);
			$sortedNodes[] = $node;
			$nodeHash = spl_object_hash($node);
			if (isset($this->dependenciesOutgoing[$nodeHash])) {
				foreach ($this->dependenciesOutgoing[$nodeHash] as $dependencyHash => $dependencyNode) {
					unset($this->dependenciesOutgoing[$nodeHash][$dependencyHash]);
					unset($this->dependenciesIncoming[$dependencyHash][$nodeHash]);

					if (count($this->dependenciesIncoming[$dependencyHash]) === 0) {
						$this->nodesWithoutIncoming[$dependencyHash] = $dependencyNode;
					}
				}
			}
		}
		return $sortedNodes;
	}
}
