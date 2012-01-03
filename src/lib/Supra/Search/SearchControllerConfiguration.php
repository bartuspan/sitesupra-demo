<?php

namespace Supra\Search;

use Supra\Controller\Pages\Configuration\BlockControllerConfiguration;

class SearchControllerConfiguration extends BlockControllerConfiguration
{

	/**
	 * @var string
	 */
	public $formTemplateFilename;

	/**
	 * @var string
	 */
	public $resultsTemplateFilename;

	/**
	 * @var string
	 */
	public $noResultsTemplateFilename;
	
	/**
	 * @var int
	 */
	public $resultsPerPage = 10;

	/**
	 * Main method
	 */
	public function configure()
	{
		$this->controllerClass = SearchController::CN();
		$this->title = 'Search';
		$this->group = 'System';
		$this->description = 'Search controller';
		$this->cmsClassname = 'Editable';
		$this->iconWebPath = '/assets/img/blocks/system_block.png';

		parent::configure();
	}

}
