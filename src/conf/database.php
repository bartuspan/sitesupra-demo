<?php

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Configuration;
use Supra\Database\Doctrine;
use Doctrine\Common\Cache\ArrayCache;
use Supra\ObjectRepository\ObjectRepository;
use Doctrine\ORM\Events;
use Doctrine\Common\EventManager;
use Supra\NestedSet\Listener\NestedSetListener;
use Supra\Database\Doctrine\Listener\TableNamePrefixer;
use Supra\Database\Doctrine\Hydrator\ColumnHydrator;
use Supra\Controller\Pages\Listener;
use Doctrine\DBAL\Types\Type;
use Supra\Database\Doctrine\Type\Sha1HashType;
use Supra\Database\Doctrine\Type\PathType;
use Supra\Database\Doctrine\Type\TemplateType;
use Supra\Database\Doctrine\Type\BlockType;
use Supra\Database\Doctrine\Listener\TimestampableListener;
use Supra\Controller\Pages\PageController;

Type::addType(Sha1HashType::NAME, 'Supra\Database\Doctrine\Type\Sha1HashType');
Type::addType(PathType::NAME, 'Supra\Database\Doctrine\Type\PathType');
Type::addType(TemplateType::NAME, 'Supra\Database\Doctrine\Type\TemplateType');
Type::addType(BlockType::NAME, 'Supra\Database\Doctrine\Type\BlockType');

// TODO: use configuration classes maybe?
$managerNames = array(
		'PublicSchema' => '',
		'Draft' => 'Supra\Cms',
		//'Trash' => 'Supra\Cms\Abstraction\Trash',
		//'History' => 'Supra\Cms\Abstraction\History',
		// EXPERIMENTAL
		'Audit' => 'Supra\Cms\Abstraction\Audit',
);

foreach ($managerNames as $managerName => $namespace) {
	$config = new Configuration();

	// Doctrine cache (array cache for development)
	$cache = new ArrayCache();

	// Memcache cache configuration sample
	//$cache = new \Doctrine\Common\Cache\MemcacheCache();
	//$memcache = new \Memcache();
	//$memcache->addserver('127.0.0.1');
	//$cache->setMemcache($memcache);
	//NB! Must have different namespace for draft connection
	$cache->setNamespace($managerName);
	$config->setMetadataCacheImpl($cache);
	$config->setQueryCacheImpl($cache);

	// Metadata driver
	$entityPaths = array(
			SUPRA_LIBRARY_PATH . 'Supra/Controller/Pages/Entity/',
			SUPRA_LIBRARY_PATH . 'Supra/FileStorage/Entity/',
			SUPRA_LIBRARY_PATH . 'Supra/User/Entity/',
			SUPRA_LIBRARY_PATH . 'Supra/Console/Cron/Entity/',
			SUPRA_LIBRARY_PATH . 'Supra/Search/Entity',
	);
	$driverImpl = $config->newDefaultAnnotationDriver($entityPaths);
	//$driverImpl = new \Doctrine\ORM\Mapping\Driver\YamlDriver(SUPRA_LIBRARY_PATH . 'Supra/yaml/');
	$config->setMetadataDriverImpl($driverImpl);

	// Proxy configuration
	//$config->setProxyDir(SUPRA_LIBRARY_PATH . 'Supra/Proxy/' . $managerName);
	//$config->setProxyNamespace('Supra\\Proxy\\' . $managerName);
	$config->setProxyDir(SUPRA_LIBRARY_PATH . 'Supra/Proxy/');
	$config->setProxyNamespace('Supra\\Proxy');

	$config->setAutoGenerateProxyClasses(true);

	// SQL logger
	$sqlLogger = new \Supra\Log\Logger\SqlLogger();
	$config->setSQLLogger($sqlLogger);

	$connectionOptions = $ini['database'];

	// TODO: Let's see if it is still required with MySQL PDO charset updates in PHP 5.3.6
	$connectionOptions['driverOptions'] = array(
			PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'
	);

	// TODO: move to some other configuration
	$config->addCustomNumericFunction('IF', 'Supra\Database\Doctrine\Functions\IfFunction');

	$eventManager = new EventManager();
	$eventManager->addEventSubscriber(new TableNamePrefixer('su_'));
	$eventManager->addEventSubscriber(new TimestampableListener());

	$eventManager->addEventSubscriber(new Listener\VersionedAnnotationListener());
	$eventManager->addEventSubscriber(new Listener\CreateSchemaListener());

	$eventManager->addEventListener(array(Events::loadClassMetadata), new Listener\EntityRevisionListener());

	switch ($managerName) {
		case 'PublicSchema':
			$eventManager->addEventSubscriber(new Listener\PagePathGenerator());
			$eventManager->addEventSubscriber(new NestedSetListener());
			break;

		case 'Draft':
			$eventManager->addEventSubscriber(new Listener\PagePathGenerator());
			$eventManager->addEventSubscriber(new NestedSetListener());
			$eventManager->addEventSubscriber(new Listener\ImageSizeCreatorListener());
			$eventManager->addEventSubscriber(new Listener\TableDraftSuffixAppender());
			
			// NB! ORDER DOES MATTER!
			// Revision id must be filled before entity goes to audit listener
			// Manage entity revision values
			$eventManager->addEventSubscriber(new Listener\EntityRevisionListener());
			// Audit entity changes in Draft schema
			//$eventManager->addEventSubscriber(new Listener\EntityAuditListener());
			//$eventManager->addEventSubscriber(new Listener\DraftVersionListener());
			break;
		/*
		  case 'Trash':
		  $eventManager->addEventListener(array(Events::loadClassMetadata), new Listener\TableTrashPrefixAppender());
		  break;

		  case 'History':
		  $eventManager->addEventListener(array(Events::loadClassMetadata, Events::onFlush), new Listener\HistorySchemaModifier());
		  $eventManager->addEventSubscriber(new Listener\HistoryRevisionListener());
		  break;
		 */
		// EXPERIMENTAL
		case 'Audit':
			$eventManager->addEventSubscriber(new Listener\AuditManagerListener());
			// TODO: should assign this to PublicSchema instead?
			$eventManager->addEventSubscriber(new Listener\AuditCreateSchemaListener());
			break;
	}

	$em = EntityManager::create($connectionOptions, $config, $eventManager);
	$em->getConfiguration()->addCustomHydrationMode(ColumnHydrator::HYDRATOR_ID, new ColumnHydrator($em));
	$em->getConnection()->getDatabasePlatform()->markDoctrineTypeCommented(Type::getType(Sha1HashType::NAME));
	$em->getConnection()->getDatabasePlatform()->markDoctrineTypeCommented(Type::getType(PathType::NAME));
	$em->getConnection()->getDatabasePlatform()->markDoctrineTypeCommented(Type::getType(TemplateType::NAME));
	$em->getConnection()->getDatabasePlatform()->markDoctrineTypeCommented(Type::getType(BlockType::NAME));
	$em->_mode = $managerName;

	ObjectRepository::setEntityManager($namespace, $em);

	// Experimental: sets entity manager by ID
	switch ($managerName) {

		case 'Draft':
			ObjectRepository::setEntityManager(PageController::SCHEMA_DRAFT, $em);
			break;

//		case 'Trash':
//			ObjectRepository::setEntityManager('#trash', $em); break;
//		case 'History':
//			ObjectRepository::setEntityManager('#history', $em); break;
		case 'PublicSchema':
			ObjectRepository::setEntityManager(PageController::SCHEMA_PUBLIC, $em);
			break;
		// EXPERIMENTAL
		case 'Audit':
			ObjectRepository::setEntityManager(PageController::SCHEMA_AUDIT, $em);
			break;
	}
}
