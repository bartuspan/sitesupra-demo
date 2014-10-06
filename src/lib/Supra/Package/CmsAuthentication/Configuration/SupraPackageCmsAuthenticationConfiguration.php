<?php

namespace Supra\Package\CmsAuthentication\Configuration;

use Supra\Core\Configuration\AbstractPackageConfiguration;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class SupraPackageCmsAuthenticationConfiguration extends AbstractPackageConfiguration implements ConfigurationInterface
{
	/**
	 * Generates the configuration tree builder.
	 *
	 * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder The tree builder
	 */
	public function getConfigTreeBuilder()
	{
		$treeBuilder = new TreeBuilder();

		$treeBuilder->root('cms_authentication')
			->children()
				->append($this->getPathConfiguration())
				->append($this->getSessionConfiguration())
				->append($this->getUsersConfiguration())
				->append($this->getServicesDefinition())
			->end();

		return $treeBuilder;
	}

	protected function getUsersConfiguration()
	{
		$definition = new ArrayNodeDefinition('users');

		$definition->children()
				->arrayNode('shared_connection')
					->children()
					->scalarNode('host')->isRequired()->end()
					->scalarNode('user')->isRequired()->end()
					->scalarNode('password')->isRequired()->end()
					->scalarNode('dbname')->isRequired()->end()
					->scalarNode('driver')->isRequired()->end()
					->scalarNode('charset')->isRequired()->end()
					->scalarNode('event_manager')->isRequired()->end()
					->end()
				->end()
				->arrayNode('user_providers')
					->children()
						->arrayNode('doctrine')
							->prototype('array')
								->children()
									->scalarNode('em')->isRequired()->end()
									->scalarNode('entity')->isRequired()->end()
								->end()
							->end()
						->end()
					->end()
				->end()
				->arrayNode('provider_chain')
					->requiresAtLeastOneElement()
					->prototype('scalar')
					->end()
				->end()
				->scalarNode('provider_key')->isRequired()->end()
				->arrayNode('password_encoders')
					->requiresAtLeastOneElement()
					->prototype('scalar')->end()
				->end()
				->arrayNode('authentication_providers')
					->requiresAtLeastOneElement()
					->prototype('scalar')->end()
				->end()
				->arrayNode('voters')
					->requiresAtLeastOneElement()
					->prototype('scalar')->end()
				->end()
			->end();

		return $definition;
	}

	protected function getSessionConfiguration()
	{
		$definition = new ArrayNodeDefinition('session');

		$definition->children()
				->scalarNode('storage_key')->isRequired()->end()
			->end();

		return $definition;
	}

	protected function getPathConfiguration()
	{
		$definition = new ArrayNodeDefinition('paths');

		$definition->children()
				->scalarNode('login')->isRequired()->end()
				->scalarNode('logout')->isRequired()->end()
				->scalarNode('login_check')->isRequired()->end()
				->arrayNode('anonymous')
					->prototype('scalar')->end()
				->end()
			->end();

		return $definition;
	}

}
