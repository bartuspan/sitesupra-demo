<?php

namespace Supra\Cms\ContentManager\Sitemap;

use Supra\Controller\SimpleController;
use Supra\Cms\ContentManager\PageManagerAction;
use Supra\Controller\Pages\Entity;
use Supra\Controller\Pages\Request\PageRequest;
use Supra\Controller\Pages\Exception\DuplicatePagePathException;
use Supra\Cms\Exception\CmsException;
use Supra\Controller\Pages\Application\PageApplicationCollection;
use Supra\Uri\Path;
use Supra\Controller\Pages\Application\PageApplicationInterface;

/**
 * Sitemap
 */
class SitemapAction extends PageManagerAction
{

	/**
	 * Main method passing the sitemap tree
	 */
	public function sitemapAction()
	{
		$response = $this->loadSitemapTree(PageRequest::PAGE_ENTITY);

		$this->getResponse()
				->setResponseData($response);
	}

	/**
	 * Main method passing the templates tree
	 */
	public function templatesAction()
	{
		$response = $this->loadSitemapTree(PageRequest::TEMPLATE_ENTITY);

		$this->getResponse()
				->setResponseData($response);
	}

	/**
	 * Page move action
	 */
	public function moveAction()
	{
		$this->isPostRequest();

		$page = $this->getPageLocalization()->getMaster();
		$parent = $this->getPageByRequestKey('parent_id');
		$reference = $this->getPageByRequestKey('reference_id');
		
		try {
			if (is_null($reference)) {
				if (is_null($parent)) {
					throw new CmsException('sitemap.error.parent_page_not_found');
				}
				$parent->addChild($page);
			} else {
				$page->moveAsPrevSiblingOf($reference);
			}
		} catch (DuplicatePagePathException $uniqueException) {
			throw new CmsException('sitemap.error.duplicate_path');
		}
	}

	/**
	 * Helper method for the main sitemap action
	 * @param Entity\Abstraction\AbstractPage $page
	 * @param string $locale
	 * @return array
	 */
	private function buildTreeArray(Entity\Abstraction\AbstractPage $page, $locale)
	{
		$data = $page->getLocalization($locale);

		if (empty($data)) {
			// try to get any localization if page is global
			if ($page->isGlobal()) {
				// hoping that there is at least one page data instance (naive)
				$data = $page->getLocalizations()->first();
			} else {

				return null;
			}
		}

		$pathPart = null;
		$templateId = null;
		$applicationBasePath = new Path('');

		if ($page instanceof Entity\Page) {
			$templateId = $data->getTemplate()
					->getId();
		}
		
		if ($data instanceof Entity\PageLocalization) {
			$pathPart = $data->getPathPart();
			
			if ( ! $page->isRoot()) {
				$parentPage = $page->getParent();
				$parentLocalization = $parentPage->getLocalization($locale);
				
				if (is_null($parentLocalization)) {
					throw new CmsException(null, "Parent page has no localization in the selected language");
				}
				
				if ($parentPage instanceof Entity\ApplicationPage) {
					$applicationId = $parentPage->getApplicationId();
					$application = PageApplicationCollection::getInstance()
							->createApplication($parentLocalization, $this->entityManager);
					
					$application->showInactivePages(true);
					
					if (empty($application)) {
						throw new CmsException(null, "Application '$applicationId' was not found");
					}
					
					$applicationBasePath = $application->generatePath($data);
				}
			}
		}
		
		$array = array(
			'id' => $data->getId(),
			'title' => $data->getTitle(),
			'template' => $templateId,
			
			// TODO: maybe should send "null" when path is not allowed? Must fix JS then
			'path' => $pathPart,
			// Additional base path received from application
			'basePath' => $applicationBasePath->getFullPath(Path::FORMAT_RIGHT_DELIMITER),
			
			// TODO: hardcoded
			'icon' => 'page',
			'preview' => '/cms/lib/supra/img/sitemap/preview/page-1.jpg'
		);

		$array['children'] = array();

		$children = array();
		
		if ($page instanceof Entity\ApplicationPage) {
			$application = PageApplicationCollection::getInstance()
					->createApplication($data, $this->entityManager);

			$application->showInactivePages(true);
			
			$modes = $application->getAvailableSitemapViewModes();
			
			if (in_array(PageApplicationInterface::SITEMAP_VIEW_COLLAPSED, $modes)) {
				//TODO: children could be a grouped array
				$children = $application->collapsedSitemapView();
			}
			
			// Send sitemap that expanded view is available
			if (in_array(PageApplicationInterface::SITEMAP_VIEW_EXPANDED, $modes)) {
				$array['collapsed'] = true;
			}
			
			//TODO: pass to client if there are any hidden pages
			
		} else {
			$children = $page->getChildren();
		}
		
		foreach ($children as $child) {
			
			// Application responds with localization objects..
			//FIXME: fix inconsistency
			if ($child instanceof Entity\Abstraction\Localization) {
				$child = $child->getMaster();
			}
			
			$childArray = $this->buildTreeArray($child, $locale);

			if ( ! empty($childArray)) {
				$array['children'][] = $childArray;
			}
		}

		if (count($array['children']) == 0) {
			unset($array['children']);
		} else {
			// TODO: hardcoded
			$array['icon'] = 'folder';
		}

		return $array;
	}
	
	/**
	 * Returns Template or Page data
	 * @param string $entity
	 * @return array
	 */
	protected function loadSitemapTree($entity)
	{
		$pages = array();
		$localeId = $this->getLocale()->getId();

		$em = $this->entityManager;

		$response = array();

		$pageRepository = $em->getRepository($entity);

		/* @var $pageRepository \Supra\Controller\Pages\Repository\PageRepository */
		$rootNodes = $pageRepository->getRootNodes();

		foreach ($rootNodes as $rootNode) {
			$tree = $this->buildTreeArray($rootNode, $localeId);
			// TODO: hardcoded
			$tree['icon'] = 'home';

			$response[] = $tree;
		}
		
		return $response;
	}
}
