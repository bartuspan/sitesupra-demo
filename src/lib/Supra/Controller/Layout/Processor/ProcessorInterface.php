<?php

namespace Supra\Controller\Layout\Processor;

use Supra\Response\ResponseInterface;
use Supra\Controller\Pages\Entity\Theme\ThemeLayout;
use Supra\Request\RequestInterface;

/**
 * Layout processor interface
 */
interface ProcessorInterface
{
	/**
	 * Process the layout
	 * @param ResponseInterface $response
	 * @param array $placeResponses
	 * @param string $layoutSrc
	 */
	public function process(ResponseInterface $response, array $placeResponses, $layoutSrc);

	/**
	 * Return list of place names inside the layout
	 * @param string $layoutSrc
	 * @return array
	 */
	public function getPlaces($layoutSrc);
	
	/**
	 * Set request object to use
	 * @param RequestInterface $request
	 */
	public function setRequest(RequestInterface $request);
}