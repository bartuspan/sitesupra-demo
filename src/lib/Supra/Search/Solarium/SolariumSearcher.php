<?php

namespace Supra\Search\Solarium;

use Solarium_Client;
use Supra\Search\AbstractSearcher;
use Supra\Search\Request\SearchRequestInterface;
use Supra\Search\Result\SearchResultSetInterface;

class SolariumSearcher extends AbstractSearcher
{
	/**
	 * @var Solarium_Client 
	 */
	protected $solariumClient;
	
	/**
	 * @param Solarium_Client $solariumClient
	 */
	public function __construct(Solarium_Client $solariumClient)
	{
		$this->solariumClient = $solariumClient;
	}

	/**
	 * @param SearchRequestInterface $request
	 * @return SearchResultSetInterface
	 */
	public function processRequest(SearchRequestInterface $request)
	{
		$selectQuery = $this->solariumClient->createSelect();

		$request->addSimpleFilter('systemId', $this->getSystemId());

		$request->applyParametersToSelectQuery($selectQuery);

		$filters = array();
		
		foreach ($selectQuery->getFilterQueries() as $filterQuery) {
			$filters[] = $filterQuery->getQuery();
		}
		
		$selectResults = $this->solariumClient->select($selectQuery);

		return $request->processResults($selectResults);
	}

	/**
	 * {@inheritDoc}
	 */
	public function isKeywordSuggestionSupported()
	{
		return true;
	}
}