<?php

namespace Supra\Package\Cms\Controller;

use Doctrine\ORM\EntityManager;
use Supra\Core\Doctrine\Hydrator\ColumnHydrator;
use Supra\Core\HttpFoundation\SupraJsonResponse;
use Supra\Package\Cms\Entity\Abstraction\Entity;
use Supra\Package\Cms\Entity\Abstraction\Localization;
use Supra\Package\Cms\Entity\Abstraction\PlaceHolder;
use Supra\Package\Cms\Entity\Abstraction\Block;
use Supra\Package\Cms\Entity\PageLocalization;
use Supra\Package\Cms\Entity\PageLocalizationPath;
use Supra\Package\Cms\Entity\TemplatePlaceHolder;
use Supra\Package\Cms\Entity\PageBlock;
use Supra\Package\Cms\Entity\TemplateBlock;
use Supra\Package\Cms\Entity\BlockProperty;
use Supra\Package\Cms\Entity\BlockPropertyMetadata;
use Supra\Package\Cms\Exception\CmsException;


class PagesContentController extends AbstractPagesController
{
	public function saveAction()
	{
		$this->isPostRequest();
		
		$this->checkLock();
		
		$input = $this->getRequestInput();

		$block = $this->getEntityManager()
				->find(Block::CN(), $input->get('block_id'));
		/* @var $block Block */

		if ($block === null) {
			throw new CmsException(null, 'The block you are trying to save not found.');
		}

		// Template block advanced options
		if ($block instanceof TemplateBlock) {
			if ($input->has('locked')) {

				$locked = $input->filter('locked', false, false, FILTER_VALIDATE_BOOLEAN);

				$block->setLocked($locked);
			}
		}

		$blockController = $this->getBlockCollection()
				->createController($block);

		$localization = $block instanceof TemplateBlock
				? $block->getPlaceHolder()->getLocalization()
				: $this->getPageLocalization();

		$pageRequest = $this->createPageRequest($localization);

		$this->getPageController()
				->prepareBlockController($blockController, $pageRequest);
		
		$self = $this;

		$closure = function (EntityManager $entityManager) use ($input, $blockController, $self) {

			$configuration = $blockController->getConfiguration();

			$propertyArray = $input->get('properties', array());

			foreach ($propertyArray as $name => $value) {

				$propertyConfiguration = $configuration->getProperty($name);

				$editable = $propertyConfiguration->getEditable();

				$property = $blockController->getProperty($name);

				$self->configureEditableValueTransformers($editable, $property);

				$editable->setEditorValue($value);

				$property->setValue($editable->getRawValue());

				$entityManager->persist($property);
			}
		};

		$this->getEntityManager()
				->transactional($closure);

		// execute, so response is rendered
		$blockController->execute();

		// Respond with block HTML
		return new SupraJsonResponse(array(
				'internal_html' => (string) $blockController->getResponse()
		));
	}

	public function publishAction()
	{
		$publicEm = $this->container->getDoctrine()
				->getManager('public');
		/* @var $publicEm \Doctrine\ORM\EntityManager */

		$draftEm = $this->container->getDoctrine()
				->getManager('cms');
		/* @var $draftEm \Doctrine\ORM\EntityManager */

		$pageRequest = $this->createPageRequest();

		$draftEm->beginTransaction();
		$publicEm->beginTransaction();

		try {

			$localization = $this->getPageLocalization();

			if ($localization instanceof PageLocalization) {

				// *) Checks for path duplicates in Public schema
				$pathEntity = $localization->getPathEntity();

				$duplicatePath = $publicEm->createQueryBuilder()
						->select('p.path')
						->from(PageLocalizationPath::CN(), 'p')
						->where('p.locale = ?0 AND p.path = ?1 AND p.id <> ?2')
						->setParameters(array(
							$pathEntity->getLocale(),
							$pathEntity->getPath()->getFullPath(),
							$pathEntity->getId()
						))
						->getQuery()
						->getArrayResult();

				if ($duplicatePath) {
					throw new Exception\RuntimeException(sprintf(
							'Another page with path [%s] already exists',
							$duplicatePath['path']
					));
				}

				// *) Sets the first publication time if empty
				if ( ! $localization->isPublishTimeSet()) {
					$localization->setCreationTime();
					// Flush right now, to recalculate revision id and have
					// actual version in public entity
					$draftEm->flush($localization);
				}
			}

			// Initialize, because not initialized proxy objects are not merged
			$localization->initializeProxyAssociations();

			/**
			 * 1. Merge
			 */
			// 1.1. Localization entity
			$publicLocalization = $publicEm->merge($localization);
			/* @var $publicLocalization Supra\Package\Cms\Entity\Abstraction\Localization */

			// Reset Lock object since it is not needed in Public schema
			$publicLocalization->setLock(null);

			// 1.2. Localization tags
			$tagCollection = $localization->getTagCollection();

			// 1.2.1. remove deleted tags
			$publicTagCollection = $publicLocalization->getTagCollection();
			foreach ($publicTagCollection as $tag) {
				if ( ! $tagCollection->offsetExists($tag->getName())) {
					$publicEm->remove($tag);
				}
			}

			// 1.2.2. merge all tags existing in the Draft
			foreach ($tagCollection as $tag) {
				$publicEm->merge($tag);
			}

			// 1.3. PageLocalization redirect target
			if ($localization instanceof PageLocalization) {

				$publicRedirect = $publicLocalization->getRedirectTarget();

				if ($publicRedirect !== null
						&& ! $publicRedirect->equals($localization->getRedirect())) {

					// Remove already published redirect target object if it differs from new one.
					// New one will be merged thanks to Doctrine's cascade persisting.
					$publicEm->remove($publicRedirect);
				}
			}

			/**
			 * Page contents.
			 */
			// 1.4 Blocks
			//		1. get all blocks in Draft version
			$draftBlocks = $this->getBlocksInLocalization($draftEm, $localization);

			//		2. get all blocks in Public version
			$publicBlocks = $this->getBlocksInLocalization($publicEm, $publicLocalization);

			//		3. remove blocks from 2. if they don't exists in 1.
			$draftBlockIds = Entity::collectIds($draftBlocks);
			$publicBlockIds = Entity::collectIds($publicBlocks);

			foreach (array_diff($publicBlockIds, $draftBlockIds) as $removedBlockId) {
				foreach ($publicBlocks as $block) {
					if ($block->getId() === $removedBlockId) {
						$publicEm->remove($block);
						break;
					}
				}
			}

			// not needed anymore.
			unset ($publicBlocks, $publicBlockIds);

			//		4. merge all the Draft version blocks.
			foreach ($draftBlocks as $block) {
				$publicEm->merge($block);
			}

			// 1.5 Placeholders
			$placeHolderIds = array();
			$placeHolderNames = array();

			//		- doing merge + collecting the IDs and names to cleanup not used placeholders
			foreach ($draftBlocks as $block) {
				$placeHolder = $block->getPlaceHolder();

				$publicEm->merge($placeHolder);

				$id = $placeHolder->getId();
				$name = $placeHolder->getName();

				if (! array_key_exists($id, $placeHolderIds)) {
					$placeHolderIds[] = $id;
				}

				if (! array_key_exists($name, $placeHolderNames)) {
					$placeHolderNames[] = $name;
				}
			}

			// not needed anymore.
			unset ($draftBlocks);

			if (! empty($placeHolderIds)
					&& ! empty($placeHolderNames)) {

				$queryString = 'SELECT p FROM %s p WHERE p.localization = ?0 AND p.id NOT IN (?1) AND p.name IN (?2)';

				$query = $publicEm->createQuery(sprintf($queryString, PlaceHolder::CN()))
						->setParameters(array(
							$localization->getId(),
							$placeHolderIds,
							$placeHolderNames,
						));

				// @TODO: it's not performance-friendly to load placeholders just to remove them
				foreach ($query->getResult() as $placeHolder) {
					$publicEm->remove($placeHolder);
				}
			}

			// not needed anymore.
			unset($placeHolderIds, $placeHolderNames);

			// 1.6 Block properties
			$draftProperties = $pageRequest->getBlockPropertySet();
			$draftPropertyIds = $draftProperties->collectIds();

			$queryString = 'SELECT bp FROM %s bp WHERE bp.localization = ?0';
			$publicProperties = $publicEm->createQuery(sprintf($queryString, BlockProperty::CN()))
					->setParameters(array($localization->getId()))
					->getResult();

			$publicPropertyIds = Entity::collectIds($publicProperties);

			foreach (array_diff($publicPropertyIds, $draftPropertyIds) as $removedPropertyId) {
				foreach ($publicProperties as $property) {
					if ($property->getId() === $removedPropertyId) {
						$publicEm->remove($property);
						break;
					}
				}
			}

			// 7. For properties 5b get block, placeholder IDs, check their existance in public, get not existant
			$missingParentTemplateBlockIds = array();

			// Searching for missing parent template blocks IDs in the public schema
			$draftParentTemplateBlockIds = array_values(
					array_diff($draftProperties->getBlockIdList(), $draftBlockIds)
			);

			if (! empty($draftParentTemplateBlockIds)) {

				$queryString = 'SELECT b.id FROM %s b WHERE b.id IN (?0)';

				$publicParentTemplateBlockIds = $publicEm->createQuery(sprintf($queryString, Block::CN()))
						->setParameters(array($draftParentTemplateBlockIds))
						->getResult(ColumnHydrator::HYDRATOR_ID);

				$missingParentTemplateBlockIds = array_values(array_diff(
						$draftParentTemplateBlockIds,
						$publicParentTemplateBlockIds
				));

				if (! empty($missingParentTemplateBlockIds)) {

					$queryString = 'SELECT p.id FROM %s p JOIN p.blocks b WITH b.id IN (?0)';

					$publicPlaceHolderIds = $publicEm->createQuery(sprintf($queryString, PlaceHolder::CN()))
							->setParameters(array($missingParentTemplateBlockIds))
							->getResult(ColumnHydrator::HYDRATOR_ID);

					// @FIXME: will fail if $publicPlaceHolderIds will be empty!
					$queryString = 'SELECT p FROM %s p JOIN p.blocks b WITH b.id IN (?0) WHERE p.id NOT IN (?1)';

					$queryBuilder = $publicEm->createQueryBuilder()
							->select('p')
							->from(PlaceHolder::CN(), 'p')
							->join('p.blocks', 'b', 'WITH', 'b.id IN (:block_ids)')
							->setParameter('block_ids', $missingParentTemplateBlockIds);


					if (! empty($publicPlaceHolderIds)) {
						$queryBuilder->andWhere('p.id NOT IN (:ids)')
								->setParameter('ids', $publicPlaceHolderIds);
					}

					foreach ($queryBuilder->getQuery()
							->getResult() as $placeHolder) {

						$placeHolder = $publicEm->merge($placeHolder);

						// Reset locked property
						if ($placeHolder instanceof TemplatePlaceHolder) {
							$placeHolder->setLocked(false);
						}
					}

					// merge missing blocks, mark them as temporary.
					$missingBlocks = $draftEm->createQuery(sprintf('SELECT b FROM %s b WHERE b.id IN (?0)', TemplateBlock::CN()))
							->setParameters(array($missingParentTemplateBlockIds))
							->getResult();

					foreach ($missingBlocks as $block) {

						$block->getPlaceHolder();

						$block = $publicEm->merge($block);
						$block->setTemporary(true);
					}

					// not needed anymore.
					unset($publicPlaceHolderIds, $missingBlocks);
				}
			}

			// Clear all property metadata in public schema that belongs to properties going to be merged
			if (! empty($draftPropertyIds)) {
				$queryString = 'DELETE FROM %s m WHERE m.blockProperty IN (?0)';

				$publicEm->createQuery(sprintf($queryString, BlockPropertyMetadata::CN()))
						->setParameters(array($draftPropertyIds))
						->execute();
			}

			foreach ($draftProperties as $property) {
				/* @var $property BlockProperty */
				// Initialize the property metadata so it is merged as well
				$property->initializeProxyAssociations();

				$publicEm->merge($property);
			}

			// not needed anymore.
			unset($draftPropertyIds, $draftProperties);

			// flushing only Public,
			// expecting that there are no changes in Draft schema.
			$publicEm->flush();

		} catch (\Exception $e) {

			$draftEm->rollback();
			$publicEm->rollback();

			throw $e;
		}

		$draftEm->commit();
		$publicEm->commit();

		return new SupraJsonResponse();
	}

	/**
	 * @param \Doctrine\ORM\EntityManager $entityManager
	 * @param Localization $localization
	 */
	private function getBlocksInLocalization($entityManager, Localization $localization)
	{
		$queryString = 'SELECT b FROM %s b JOIN b.placeHolder p WHERE p.localization = ?0';

		$blocks = $entityManager->createQuery(sprintf($queryString, Block::CN()))
				->setParameters(array($localization->getId()))
				->getResult();

		foreach ($blocks as $key => $block) {
			if ($block instanceof PageBlock
					&& $block->isInactive()) {

				unset($blocks[$key]);
			}
		}

		return $blocks;
	}
}