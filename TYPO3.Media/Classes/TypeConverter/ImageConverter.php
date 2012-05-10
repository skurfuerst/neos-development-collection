<?php
namespace TYPO3\Media\TypeConverter;

/*                                                                        *
 * This script belongs to the FLOW3 package "TYPO3.Media".                *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3 of the   *
 * License, or (at your option) any later version.                        *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\FLOW3\Annotations as FLOW3;

/**
 * This converter transforms arrays to \TYPO3\Media\Domain\Model\Image objects
 *
 * @api
 * @FLOW3\Scope("singleton")
 */
class ImageConverter extends \TYPO3\FLOW3\Property\TypeConverter\AbstractTypeConverter {

	/**
	 * @var array
	 */
	protected $sourceTypes = array('array');

	/**
	 * @var string
	 */
	protected $targetType = 'TYPO3\Media\Domain\Model\Image';

	/**
	 * @var integer
	 */
	protected $priority = 1;

	/**
	 * Convert all properties in the source array
	 *
	 * @param mixed $source
	 * @return array
	 */
	public function getSourceChildPropertiesToBeConverted($source) {
		return $source;
	}

	/**
	 * Convert the property "resource"
	 *
	 * @param string $targetType
	 * @param string $propertyName
	 * @param \TYPO3\FLOW3\Property\PropertyMappingConfigurationInterface $configuration
	 * @return string
	 */
	public function getTypeOfChildProperty($targetType, $propertyName, \TYPO3\FLOW3\Property\PropertyMappingConfigurationInterface $configuration) {
		if ($propertyName === 'resource') {
			return 'TYPO3\FLOW3\Resource\Resource';
		}
	}

	/**
	 * Convert an object from $source to an \TYPO3\Media\Domain\Model\Image
	 *
	 * @param mixed $source
	 * @param string $targetType must be 'TYPO3\Media\Domain\Model\Image'
	 * @param array $convertedChildProperties
	 * @param \TYPO3\FLOW3\Property\PropertyMappingConfigurationInterface $configuration
	 * @return \TYPO3\Media\Domain\Model\Image|\TYPO3\FLOW3\Validation\Error The converted Image, a Validation Error or NULL
	 */
	public function convertFrom($source, $targetType, array $convertedChildProperties = array(), \TYPO3\FLOW3\Property\PropertyMappingConfigurationInterface $configuration = NULL) {
		if (!isset($convertedChildProperties['resource']) || !$convertedChildProperties['resource'] instanceof \TYPO3\FLOW3\Resource\Resource) {
			return NULL;
		}
		try {
			return new \TYPO3\Media\Domain\Model\Image($convertedChildProperties['resource']);
		} catch(\TYPO3\Media\Exception\ImageFileException $exception) {
			return new \TYPO3\FLOW3\Validation\Error($exception->getMessage(), $exception->getCode());
		}
	}

}
?>