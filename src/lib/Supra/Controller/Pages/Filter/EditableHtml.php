<?php

namespace Supra\Controller\Pages\Filter;

use Supra\Editable\Filter\FilterInterface;
use Supra\Controller\Pages\Entity\BlockProperty;
use Twig_Markup;

/**
 * Filters the value to enable Html editing for CMS
 */
class EditableHtml extends ParsedHtmlFilter
{
	/**
	 * @var BlockProperty
	 */
	public $property;
	
	public function __construct()
	{
		$this->requestType = parent::REQUEST_TYPE_EDIT;
		parent::__construct();
	}
	
	/**
	 * Filters the editable content's data, adds Html Div node for CMS
	 * @params string $content
	 * @return string
	 */
	public function filter($content)
	{
		$value = $this->property->getValue();
		$metadata = $this->property->getMetadata();
		
		$content = $this->parseSupraMarkup($value, $metadata);
		
		$propertyName = $this->property->getName();
			
		$block = $this->property->getBlock();
		$blockId = $block->getId();

		// Normalize block name
		$blockName = $block->getComponentName();

		$html = '<div id="content_' . $blockId . '_' . $propertyName 
				. '" class="yui3-content-inline yui3-input-html-inline-content">';
		$html .= $content;
		$html .= '</div>';
		
		$markup = new Twig_Markup($html, 'UTF-8');
		
		return $markup;
	}
}
