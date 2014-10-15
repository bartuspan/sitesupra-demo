<?php

namespace Supra\Package\Cms\Entity;

use Doctrine\Common\Collections;
use Supra\Package\Cms\Entity\Abstraction\Entity;
use Supra\Package\Cms\Entity\Abstraction\AuditedEntity;
use Supra\Package\Cms\Entity\Abstraction\VersionedEntity;
use Supra\Package\Cms\Editable\EditableInterface;
use Supra\Package\Cms\Entity\Abstraction\Block;
use Supra\Package\Cms\Entity\Abstraction\Localization;

use Supra\Controller\Pages\Exception;

/**
 * Block property class.
 * 
 * @Entity
 * @HasLifecycleCallbacks
 */
class BlockProperty extends VersionedEntity implements
	AuditedEntity
//	OwnedEntityInterface
{
	/**
	 * @ManyToOne(targetEntity="Supra\Package\Cms\Entity\Abstraction\Localization", inversedBy="blockProperties")
	 * @JoinColumn(nullable=false)
	 * @var Localization
	 */
	protected $localization;

	/**
	 * @ManyToOne(targetEntity="Supra\Package\Cms\Entity\Abstraction\Block", inversedBy="blockProperties", cascade={"persist"})
	 * @JoinColumn(name="block_id", referencedColumnName="id", nullable=false)
	 * @var Block
	 */
	protected $block;

	/**
	 * Content type (class name of Supra\Editable\EditableInterface class)
	 * @Column(type="string")
	 * @var string
	 */
	protected $type;
	
	/**
	 * @Column(type="string")
	 * @var string
	 */
	protected $name;

	/**
	 * @Column(type="text", nullable=true)
	 * @var string
	 */
	protected $value;
	
	/**
	 * Value additional data about links, images
	 * @OneToMany(targetEntity="BlockPropertyMetadata", mappedBy="blockProperty", cascade={"all"}, indexBy="name")
	 * @var Collections\Collection
	 */
	protected $metadata;
		
//	/**
//	 * @ManyToOne(targetEntity="Supra\Package\Cms\Entity\BlockPropertyMetadata", inversedBy="metadataProperties", cascade={"persist", "merge"})
//	 * @var BlockPropertyMetadata
//	 */
//	protected $masterMetadata;
	
//	/**
//	 * Master metadata object
//	 *
//	 * @var BlockPropertyMetadata
//	 */
//	protected $masterMetadata;
//
//	/**
//	 * Master metadata Id
//	 *
//	 * @Column(type="supraId20", nullable=true)
//	 * @var string
//	 */
//	protected $masterMetadataId;
//
//	/**
//	 * @Column(type="object")
//	 * @var EditableInterface
//	 */
//	protected $editable;

	/**
	 * Constructor
	 * @param string $name
	 */
	public function __construct($name)
	{
		parent::__construct();
		
		$this->name = $name;
		$this->metadata = new Collections\ArrayCollection();
	}
	
	/**
	 * @PostLoad
	 */
	public function initializeEditable()
	{
		$this->setValue($this->value);
	}

	/**
	 * @return Localization
	 */
	public function getLocalization()
	{
		return $this->localization;
	}

	/**
	 * @return Localization
	 */
	public function getOriginalLocalization()
	{
		return $this->localization;
	}

	/**
	 * @param Localization $data
	 */
	public function setLocalization(Localization $data)
	{
		if ($this->writeOnce($this->localization, $data)) {
			$this->checkScope($this->localization);
		}
	}

	/**
	 * @return Collections\Collection
	 */
	public function getMetadata()
	{
		return $this->metadata;
	}
	
	public function resetBlock()
	{
		$this->block = null;
	}
	
	public function resetLocalization()
	{
		$this->localization = null;
	}
	
	/**
	 * @param BlockPropertyMetadata $metadata
	 */
	public function addMetadata(BlockPropertyMetadata $metadata)
	{
		$name = $metadata->getName();
		$this->metadata->offsetSet($name, $metadata);
	}
	
	/**
	 * @return Block
	 */
	public function getBlock()
	{
		return $this->block;
	}

	/**
	 * @param Block $block
	 */
	public function setBlock(Block $block)
	{
		$this->block = $block;
		//if ($this->writeOnce($this->block, $block)) {
			$this->checkScope($this->block);
		//}
	}
	
	/**
	 * @deprecated use getEditableClass instead.
	 * @return string
	 */
	public function getType()
	{
		return $this->getEditableClass();
	}

	/**
	 * Set content type
	 * @param string $type 
	 */
	public function setType($type)
	{
		throw new \RuntimeException("Should not be used anymore");
		//$this->type = $type;
	}

	/**
	 * @return string
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * @return string
	 */
	public function getValue()
	{
		return $this->value;
	}

	/**
	 * TODO: should we validate the value? should we serialize arrays passed?
	 * @param string $value
	 */
	public function setValue($value)
	{
		$this->value = $value;
	}
	
//	/**
//	 * @return EditableInterface
//	 */
//	public function getEditable()
//	{
//		return $this->editable;
//	}

	/**
	 * @param EditableInterface $editable
	 */
	public function setEditable(EditableInterface $editable)
	{
//		$editable->setContent($this->value);
//		$this->value = $editable->getStorableContent();
//		$this->editable = $editable;

		$this->type = get_class($editable);
	}

	/**
	 * @return string
	 */
	public function getEditableClass()
	{
		return $this->type;
	}

	/**
	 * Checks if associations scopes are matching
	 * @param Entity $object
	 */
	private function checkScope(Entity &$object)
	{
		if ( ! empty($this->localization) && ! empty($this->block)) {
			try {
				// do not-strict match (allows page data with template block)
				$this->localization->matchDiscriminator($this->block);
			} catch (Exception\PagesControllerException $e) {
				$object = null;
				throw $e;
			}
		}
	}
	
	public function overrideMetadataCollection(Collections\ArrayCollection $collection)
	{
		$this->metadata = $collection;
	}
	
	/**
	 * Return owner of current block property.
	 * Could be Localization, Block or BlockPropertyMetadata entity.
	 * @return Entity
	 */
	public function getOwner()
	{
		if ( ! is_null($this->masterMetadata)) {
			return $this->masterMetadata;
		}
		
		// If the owner block belongs to the owner localization, return block,
		// localization otherwise.
		if ($this->localization->equals($this->block->getPlaceHolder()->getMaster())) {
			return $this->block;
		}
		
		return $this->localization;
	}
	
//	/**
//	 * Set metadata entity, that owns this BlockProperty
//	 *
//	 * @param BlockPropertyMetadata $metadata
//	 */
//	public function setMasterMetadata($metadata)
//	{
//		$this->masterMetadata = $metadata;
//		$this->masterMetadataId = $metadata->getId();
//	}
//
//	/**
//	 * Get block property master(owner) metadata entity
//	 *
//	 * @return BlockPropertyMetadata
//	 */
//	public function getMasterMetadata()
//	{
//		return $this->masterMetadata;
//	}
//
//	/**
//	 * @return string
//	 */
//	public function getMasterMetadataId()
//	{
//		return $this->masterMetadataId;
//	}
//
//	public function resetMasterMetadata()
//	{
//		$this->masterMetadata =
//				$this->masterMetadataId = null;
//	}

	public function __clone()
	{
		parent::__clone();
	}
}
