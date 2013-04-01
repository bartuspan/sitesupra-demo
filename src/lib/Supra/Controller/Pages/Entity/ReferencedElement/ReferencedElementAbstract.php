<?php

namespace Supra\Controller\Pages\Entity\ReferencedElement;

use Supra\Controller\Pages\Entity\Abstraction\Entity;
use Supra\Controller\Pages\Entity\Abstraction\AuditedEntityInterface;
use Supra\Controller\Pages\Exception;
use Supra\Controller\Pages\Entity\Abstraction\OwnedEntityInterface;

/**
 * @Entity
 * @InheritanceType("JOINED")
 * @DiscriminatorColumn(name="discr", type="string")
 * @DiscriminatorMap({
 *		"link" = "LinkReferencedElement", 
 *		"image" = "ImageReferencedElement",
 *		"video" = "VideoReferencedElement",
 *		"icon" = "IconReferencedElement",
 * })
 */
abstract class ReferencedElementAbstract extends Entity implements AuditedEntityInterface
{
	/**
	 * Convert object to array
	 * @return array
	 */
	abstract public function toArray();
	
	/**
	 * @FIXME: should move to CMS
	 * @param array $array
	 */
	public static function fromArray(array $array)
	{
		$element = null;
		
		switch ($array['type']) {
			case LinkReferencedElement::TYPE_ID:
				$element = new LinkReferencedElement();
				break;
			
			case ImageReferencedElement::TYPE_ID:
				$element = new ImageReferencedElement();
				break;
			
			case VideoReferencedElement::TYPE_ID:
				$element = new VideoReferencedElement();
				break;
			
			case IconReferencedElement::TYPE_ID:
				$element = new IconReferencedElement();
				break;
			
			default:
				throw new Exception\RuntimeException("Invalid metadata array: " . print_r($array, 1));
		}
		
		$element->fillArray($array);
		
		return $element;
	}
	
	/**
	 * Set properties from array
	 * @param array $array
	 */
	abstract public function fillArray(array $array);
}
