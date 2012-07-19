<?php

namespace Supra\Controller\Pages\Configuration;

use Supra\Controller\Pages\BlockControllerCollection;
use Supra\Loader\Loader;
use Supra\Configuration\ComponentConfiguration;
use Supra\Controller\Pages\BlockController;
use Supra\Controller\Pages\Configuration\BlockControllerPlugin;
use Supra\Uri\PathConverter;

/**
 * Block configuration class
 * @author Dmitry Polovka <dmitry.polovka@videinfra.com>
 */
class BlockControllerConfiguration extends ComponentConfiguration
{

	/**
	 * Group id 
	 * @var string
	 */
	public $groupId = null;

	/**
	 * @var string
	 */
	public $description;

	/**
	 * Local icon path
	 * @var string
	 */
	public $icon;

	/**
	 * Full icon web path, autogenerated if empty
	 * @var string
	 */
	public $iconWebPath = '/cms/lib/supra/img/blocks/icons-items/default.png';

	/**
	 * CMS classname for the block
	 * @var string
	 */
	public $cmsClassname = 'Editable';

	/**
	 * Block controller class name
	 * @var string
	 */
	public $controllerClass;

	/**
	 * Should be block hidden from block menu or not
	 * @var boolean
	 */
	public $hidden = false;

	/**
	 * Can only one block exist on a page
	 * @var boolean
	 */
	public $unique = false;

	/**
	 * Block HTML description
	 * @var string
	 */
	public $html;

	/**
	 * Cache implementation
	 * @var BlockControllerCacheConfiguration
	 */
	public $cache;

	/**
	 * @var array of block properties
	 */
	public $properties = array();

	/**
	 * @var array of block property groups
	 */
	public $propertyGroups = array();
	
	/**
	 * Array of plugins
	 * @var array
	 */
	public $plugins = array();

	/**
	 * Adds block configuration to block controller collection
	 */
	public function configure()
	{
		if (empty($this->class)) {
			\Log::warn("Configuration property BlockControllerConfiguration::controllerClass deprecated; use class instead.");
			$this->class = $this->controllerClass;
		}

		if (empty($this->id)) {
			$id = $this->prepareClassId($this->class);
			$this->id = $id;
		}

		if ( ! empty($this->icon)) {
			$this->iconWebPath = $this->getIconWebPath($this->icon);
		}

		$this->processProperties();
		$this->processPropertyGroups();

		BlockControllerCollection::getInstance()
				->addBlockConfiguration($this);

		parent::configure();
	}

	protected function processPropertyGroups()
	{
		$propertyGroups = array();

		foreach ($this->propertyGroups as $group) {
			/* @var $group BlockPropertyGroupConfiguration */
			if ($group instanceof BlockPropertyGroupConfiguration) {

				if (isset($propertyGroups[$group->id])) {
					\Log::warn('Property group with id "' . $group->id . '" already exist in property group list. Skipping group. Configuration: ', $group);
					continue;
				}
				if ( ! empty($group->icon)) {
					$group->icon = $this->getIconWebPath($group->icon);
				}

				$propertyGroups[$group->id] = $group;
			} else {
				\Log::warn('Group should be instance of BlockPropertyGroupConfiguration ', $group);
			}
		}

		$this->propertyGroups = array_values($propertyGroups);
	}

	protected function processProperties()
	{
		$class = $this->class;

		// TODO: might be removed later
		if (Loader::classExists($class)) {
			if (method_exists($class, 'getPropertyDefinition')) {
				$editables = (array) $class::getPropertyDefinition();

				foreach ($editables as $name => $editable) {
					/* @var $editable \Supra\Editable\EditableInterface */
					$this->properties[] = $property = new BlockPropertyConfiguration();
					$property->fillFromEditable($editable, $name);
				}
			}
		}

		// generating new icon path for SelectVisual
		if (is_array($this->properties)) {

			foreach ($this->properties as $property) {
				if ( ! $property->editableInstance instanceof \Supra\Editable\SelectVisual) {
					continue;
				}

				$values = array();

				foreach ($property->values as $value) {
					if (empty($value['icon'])) {
						continue;
					}

					$value['icon'] = $this->getIconWebPath($value['icon']);

					$values[] = $value;
				}

				$property->editableInstance->setValues($values);
			}
		}
	}

	/**
	 * Return icon webpath
	 * @return string
	 */
	private function getIconWebPath($icon = null)
	{
		$context = null;
		
		// Relative path
		if ( ! empty($icon) && strpos($icon, '/') !== 0) {
			$context = $this->class;
		} else {
			$icon = SUPRA_WEBROOT_PATH . $icon;
		}
		
		$path = PathConverter::getWebPath($icon, $context);

		return $path;
	}

	/**
	 * @param string $name
	 * @return BlockPropertyConfiguration
	 */
	public function getProperty($name)
	{
		foreach ($this->properties as $property) {
			/* @var $property BlockPropertyConfiguration */
			if ($property->name === $name) {
				return $property;
			}
		}
	}

	protected function prepareClassId($className)
	{
		return trim(str_replace('\\', '_', $className));
	}
	
	public function createBlockController()
	{
		$controllerClass = $this->class;
		$controller = null;

		try {
			/* @var $controller BlockController */
			$controller = Loader::getClassInstance($controllerClass, 'Supra\Controller\Pages\BlockController');
			$controller->setConfiguration($this);
		} catch (\Exception $e) {
			$controllerClass = 'Supra\Controller\Pages\NotInitializedBlockController';
			$controller = Loader::getClassInstance($controllerClass, 'Supra\Controller\Pages\BlockController');
			/* @var $controller BlockController */
			$controller->exception = $e;
			$controller->setConfiguration($this);
		}
		
		foreach ($this->plugins as $plugin) {
			/* @var $plugin BlockControllerPlugin */
			$plugin->bind($controller);
		}
		
		return $controller;
	}

}
