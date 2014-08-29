<?php

namespace Supra\ObjectRepository;

use Doctrine\ORM\EntityManager;
use Supra\FileStorage\FileStorage;
use Supra\User\UserProviderAbstract;
use Supra\Log\Writer\WriterAbstraction;
use Supra\Session\SessionManager;
use Supra\Log\Log;
use Supra\Locale\LocaleManager;
use Supra\Mailer\Mailer;
use Supra\Mailer\MassMail\MassMail;
use Supra\Authorization\AuthorizationProvider;
use Supra\Cms\ApplicationConfiguration;
use Supra\Event\EventManager;
use Solarium_Client;
use Supra\Configuration\Loader\IniConfigurationLoader;
use Supra\Payment\Provider\PaymentProviderCollection;
use Supra\Template\Parser\TemplateParser;
use Supra\BannerMachine\BannerProvider;
use Supra\Configuration\ComponentConfiguration;
use Supra\AuditLog\Writer\AuditLogWriterAbstraction;
use Supra\AuditLog\Writer\NullAuditLogWriter;
use Doctrine\Common\Cache\Cache;
use Supra\Info;
use Supra\Remote\Client\RemoteCommandService;
use Supra\Controller\Layout\Theme\ThemeProviderAbstraction;
use Supra\Controller\Layout\Theme\DefaultThemeProvider;
use Supra\Configuration\Loader\WriteableIniConfigurationLoader;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Translation\Translator;
use Supra\Search;

/**
 * Object repository
 */
class ObjectRepository
{

	const DEFAULT_KEY = '';

	/**
	 * Used when binding the object to controller
	 * TODO: just an idea, not realized because of multiple methods which should be implemented
	 */
	const INTERFACE_LOGGER = 'Supra\Log\Writer\WriterAbstraction';
	const INTERFACE_AUDIT_LOGGER = 'Supra\AuditLog\Writer\AuditLogWriterAbstraction';
	const INTERFACE_FILE_STORAGE = 'Supra\FileStorage\FileStorage';
	const INTERFACE_USER_PROVIDER = 'Supra\User\UserProviderAbstract';
	const INTERFACE_ENTITY_MANAGER = 'Doctrine\ORM\EntityManager';
	const INTERFACE_SESSION_NAMESPACE_MANAGER = 'Supra\Session\SessionManager';
	const INTERFACE_SESSION_NAMESPACE = 'Supra\Session\SessionNamespace';
	const INTERFACE_LOCALE_MANAGER = 'Supra\Locale\LocaleManager';
	const INTERFACE_MAILER = 'Supra\Mailer\Mailer';
	const INTERFACE_AUTHORIZATION_PROVIDER = 'Supra\Authorization\AuthorizationProvider';
	const INTERFACE_APPLICATION_CONFIGURATION = 'Supra\Cms\ApplicationConfiguration';
	const INTERFACE_SOLARIUM_CLIENT = '\Solarium_Client';
	const INTERFACE_EVENT_MANAGER = 'Supra\Event\EventManager';
	const INTERFACE_INI_CONFIGURATION = 'Supra\Configuration\Loader\IniConfigurationLoader';
	const INTERFACE_PAYMENT_PROVIDER_COLLECTION = 'Supra\Payment\Provider\PaymentProviderCollection';
	const INTERFACE_TEMPLATE_PARSER = 'Supra\Template\Parser\TemplateParser';
	const INTERFACE_BANNER_MACHINE = 'Supra\BannerMachine\BannerProvider';
	const INTERFACE_COMPONENT_CONFIGURATION = 'Supra\Configuration\ComponentConfiguration';
	const INTERFACE_MASS_MAIL = 'Supra\Mailer\MassMail\MassMail';
	const INTERFACE_CACHE = 'Doctrine\Common\Cache\Cache';
	const INTERFACE_SYSTEM_INFO = 'Supra\Info';
	const INTERFACE_REMOTE_COMMAND_SERVICE = 'Supra\Remote\Client\RemoteCommandService';
	const INTERFACE_THEME_PROVIDER = 'Supra\Controller\Layout\Theme\ThemeProviderAbstraction';
	const INTERFACE_FORM_FACTORY = 'Symfony\Component\Form\FormFactoryInterface';
	const INTERFACE_TRANSLATOR = 'Symfony\Component\Translation\Translator';
	const INTERFACE_INDEXER_SERVICE = 'Supra\Search\IndexerService';
	const INTERFACE_SEARCH_SERVICE = 'Supra\Search\SearchService';

	/**
	 * Object relation storage
	 *
	 * @var array
	 */
	protected static $objectBindings = array();

	/**
	 * Forced caller hierarchy
	 * @var array
	 */
	protected static $callerHierarchy = array();

	/**
	 * Called controller stack (id list, last added controller first)
	 * @var array
	 */
	protected static $controllerStack = array();

	/**
	 * Variable for checking if the added binding wouldn't change any previous 
	 * repository requests. Can be disabled by setting to null. This also stores
	 * the 5 debug backtrace steps for developer.
	 * FIXME: Should be disabled on production for  performance reasons.
	 * @var array
	 */
	protected static $lateBindingCheckCache = null;
        
        /**
         * Container implementation
         * 
         * @var \Pimple\Container
         */
        protected static $container;

	/**
	 * Marks beginning of the controller context,
	 * adds the controller ID to the call stack
	 * @param string $controllerId
	 */
	public static function beginControllerContext($controllerId)
	{
		if (is_object($controllerId)) {
			$controllerId = get_class($controllerId);
		}
		array_unshift(self::$controllerStack, $controllerId);
	}

	/**
	 * Marks the end of the controller execution
	 * @param string $expectedControllerId
	 * @throws Exception\LogicException
	 */
	public static function endControllerContext($expectedControllerId)
	{
		if (is_object($expectedControllerId)) {
			$expectedControllerId = get_class($expectedControllerId);
		}

		$actualControllerId = array_shift(self::$controllerStack);

		if ($actualControllerId != $expectedControllerId) {

			$expectationString = null;

			if (empty($actualControllerId)) {
				$expectationString = "No controller";
			} else {
				$expectationString = "Controller '$actualControllerId'";
			}

			throw new Exception\LogicException("$expectationString was expected to be ended, but '$expectedControllerId' was passed");
		}
	}

	/**
	 * Shouldn't be called. Used by tests.
	 */
	public function resetControllerContext()
	{
		self::$controllerStack = array();
	}

	/**
	 * Normalizes caller, object is converted to the class name string
	 * @param mixed $caller
	 * @return string
	 */
	public static function normalizeCallerArgument($caller)
	{
		if (is_object($caller)) {
			$callerClass = get_class($caller);
			$callerHash = self::getObjectHash($caller);
			$caller = $callerClass . '\\' . $callerHash;
		} elseif ( ! is_string($caller)) {
			throw new Exception\RuntimeException('Caller must be class instance or class name, ' . gettype($caller) . ' was given');
		} else {
			$caller = trim($caller, '\\');
		}

		return $caller;
	}

	public static function getObjectHash($object)
	{
		if ( ! isset($object->__oid__)) {
			$object->__oid__ = mt_rand();
		}

		return $object->__oid__;
	}

	// Disabled for performance
//	/**
//	 * Normalizes interface name argument
//	 * @param string $interface
//	 * @return string
//	 * @throws Exception\RuntimeException if argument invalid
//	 */
//	private static function normalizeInterfaceArgument($interface)
//	{
//		if ( ! is_string($interface)) {
//			throw new Exception\RuntimeException('Interface argument must be a string');
//		}
//
//		$interface = trim($interface, '\\');
//
//		return $interface;
//	}

	/**
	 * @return string
	 */
	protected static function generateLateBindingCheckTrace()
	{
		//$debugBacktrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		$debugBacktrace = debug_backtrace(); // php v5.3.6 and higher
		$resultArray = array();
		$i = 1;

		foreach ($debugBacktrace as $trace) {

			$trace = (array) $trace + array(
				'class' => null,
				'type' => null,
				'function' => null,
				'line' => null,
				'file' => null,
			);

			if ($trace['class'] == __CLASS__) {
				continue;
			}

			$resultArray[] = "#$i: {$trace['class']}{$trace['type']}{$trace['function']}() in {$trace['file']}:{$trace['line']}";
			if ($i ++ > 5) {
				break;
			}
		}

		$resultString = implode("\n", $resultArray);

		return $resultString;
	}

	/**
	 * @see self::$lateBindingCheckCache
	 * @param string $caller
	 * @param string $interface
	 */
	protected static function checkLateBinding($caller, $interface = null)
	{
		if (empty(static::$lateBindingCheckCache)) {
			return;
		}

		$interfaces = null;

		if (is_null($interface)) {
			$interfaces = array_keys(static::$lateBindingCheckCache);
		} else {
			$interfaces = (array) $interface;
		}

		foreach ($interfaces as $interface) {

			if ( ! isset(static::$lateBindingCheckCache[$interface])) {
				continue;
			}

			// Ignore the logger
			if ($interface == self::INTERFACE_LOGGER) {
				continue;
			}

			foreach (static::$lateBindingCheckCache[$interface] as $callerTest => $getterTrace) {

				if (self::isParentCaller($callerTest, $caller)) {
					self::getLogger(__CLASS__)
							->warn("Object repository binding of '$interface' was already requested for '$callerTest' before, '$caller' binding changed now.\n"
									. "HINT: Switch debugging on to see more information on this issue.");
					self::getLogger(__CLASS__)
							->debug("Got by trace:\n", $getterTrace);
					self::getLogger(__CLASS__)
							->debug("Checked by trace:\n", self::generateLateBindingCheckTrace());
					break;
				}
			}
		}
	}

	/**
	 * Internal relation setter
	 *
	 * @param string $callerClass
	 * @param object $object
	 * @param string $interface 
	 * @throws Exception\RuntimeException
	 */
	protected static function addBinding($caller, $object, $interface)
	{
		$caller = self::normalizeCallerArgument($caller);
//		$interface = self::normalizeInterfaceArgument($interface);
		$selfCaller = self::normalizeCallerArgument($object);

		self::checkLateBinding($caller, $interface);

		if ( ! is_object($object)) {
			throw new Exception\RuntimeException('Object argument must be an object');
		}

		if ( ! is_a($object, $interface)) {
			throw new Exception\RuntimeException('Object must be an instance of interface class or must extend it');
		}

		self::$objectBindings[$interface][$caller] = $object;

		// Bind self if called from self or it's children
		self::$objectBindings[$interface][$selfCaller] = $object;
	}

	/**
	 * Checks if caller is the child of the parent caller
	 * @param mixed $child
	 * @param mixed $parent
	 * @return boolean
	 */
	public static function isParentCaller($child, $parent)
	{
		$child = self::normalizeCallerArgument($child);
		$parent = self::normalizeCallerArgument($parent);

		while ( ! is_null($child)) {
			if ($child === $parent) {
				return true;
			}

			$visited = array();
			$child = self::getParentCaller($child, $visited);
		}

		return false;
	}

	/**
	 * Find object by exact namespace/classname
	 * @param string $caller
	 * @param string $interface
	 * @return object
	 */
	private static function findObject($caller, $interface)
	{
		if (isset(self::$objectBindings[$interface][$caller])) {
			return self::$objectBindings[$interface][$caller];
		}
	}

	/**
	 * Find object by namespace or it's parent namespaces
	 * @param string $caller
	 * @param string $interface 
	 */
	private static function findNearestObject($caller, $interface)
	{
		$object = null;
		$visited = array();

		do {
			$object = self::findObject($caller, $interface);
			$caller = self::getParentCaller($caller, $visited);
		} while (is_null($object) && ! is_null($caller));

		return $object;
	}

//	/**
//	 * Finds all objects matching the caller name trace
//	 * @param mixed $caller
//	 * @param string $interface
//	 * @return array
//	 */
//	private static function findAllObjects($caller, $interface)
//	{
//		$objects = null;
//		$visited = array();
//
//		do {
//			$object = self::findObject($caller, $interface);
//			$caller = self::getParentCaller($caller, $visited);
//
//			if ( ! empty($object) && ! in_array($object, $objects, true)) {
//				$objects[] = $object;
//			}
//		} while ( ! is_null($caller));
//
//		return $objects;
//	}

	/**
	 * Force caller object hierarchy
	 * @param mixed $child
	 * @param mixed $parent
	 * @param boolean $overwrite
	 */
	public static function setCallerParent($child, $parent, $overwrite = false)
	{
		// Shortcut variable
		$ch = &self::$callerHierarchy;

		$child = self::normalizeCallerArgument($child);
		$parent = self::normalizeCallerArgument($parent);

		self::checkLateBinding($child);

		if (( ! $overwrite) && isset($ch[$child]) && ($ch[$child] !== $parent)) {
			throw new Exception\RuntimeException("Caller $child parent already declared");
		}

		$ch[$child] = $parent;
	}

	/**
	 * Loads parent caller name for the current caller
	 * @param string $caller
	 * @param array $visited
	 * @return string
	 * @throws Exception\RuntimeException if infinite cycle defined in the hierarchy definition
	 */
	private static function getParentCaller($caller, array &$visited)
	{
		if ($caller === self::DEFAULT_KEY) {
			return null;
		}

		// Try parent namespace
		$backslashPos = strrpos($caller, "\\");
		$parentCaller = null;

		if (isset(self::$callerHierarchy[$caller])) {

			// Visited nodes are checked and registered only here, 
			// other strategies are safe
			if (in_array($caller, $visited)) {
				throw new Exception\RuntimeException("Loop detected in caller hierarchy");
			}
			$visited[] = $caller;

			$parentCaller = self::$callerHierarchy[$caller];
		} elseif ($backslashPos !== false) {
			$parentCaller = substr($caller, 0, $backslashPos);
		} else {
			$parentCaller = self::DEFAULT_KEY;
		}

		return $parentCaller;
	}

	/**
	 * Get object of specified interface assigned to caller class
	 *
	 * @param mixed $callerClass
	 * @param string $interface
	 * @return object
	 * @throws Exception\RuntimeException
	 */
	public static function getObject($caller, $interface, $throwOnMiss = false)
	{
//		$interface = self::normalizeInterfaceArgument($interface);
		// 1. Try matching any controller from the execution list
		foreach (self::$controllerStack as $controllerId) {

			$object = self::findObject($controllerId, $interface);

			if ( ! is_null($object)) {
				return $object;
			}
		}

		// 2. If not found, try matching nearest defined object by caller
		$caller = self::normalizeCallerArgument($caller);

		// @see self::$lateBindingCheckCache
		if (isset(static::$lateBindingCheckCache)) {
			static::$lateBindingCheckCache[$interface][$caller] =
					self::generateLateBindingCheckTrace();
		}

		$object = self::findNearestObject($caller, $interface);

		if (is_null($object) && $throwOnMiss) {
			throw Exception\RuntimeException::objectNotFound($caller, $interface);
		}

		return $object;
	}

	/**
	 * Get array of objects of specified interface
	 * 
	 * @param string $interface
	 * @return array
	 */
	public static function getAllObjects($interface)
	{
		if (isset(self::$objectBindings[$interface])) {
			return self::$objectBindings[$interface];
		}
	}

	/**
	 * Assign object of its own class to caller class
	 *
	 * @param mixed $caller
	 * @param object $object 
	 * @param string $interface
	 */
	public static function setObject($caller, $object, $interface)
	{
		self::addBinding($caller, $object, $interface);
	}

	/**
	 * Set default assigned object of its class
	 *
	 * @param mixed $object 
	 * @param string $interface
	 */
	public static function setDefaultObject($object, $interface)
	{
		self::addBinding(self::DEFAULT_KEY, $object, $interface);
	}

	/**
	 * Get assigned logger
	 *
	 * @param mixed $caller
	 * @return WriterAbstraction
	 */
	public static function getLogger($caller)
	{
		$logger = self::getObject($caller, self::INTERFACE_LOGGER);

		// Create bootstrap logger in case of missing logger
		if (empty($logger)) {
			$logger = Log::getBootstrapLogger();
		}

		return $logger;
	}

	/**
	 * Assign logger instance to caller class
	 *
	 * @param mixed $caller
	 * @param WriterAbstraction $object 
	 */
	public static function setLogger($caller, WriterAbstraction $object)
	{
		self::addBinding($caller, $object, self::INTERFACE_LOGGER);
	}

	/**
	 * Set default logger
	 *
	 * @param WriterAbstraction $object
	 */
	public static function setDefaultLogger(WriterAbstraction $object)
	{
		self::addBinding(self::DEFAULT_KEY, $object, self::INTERFACE_LOGGER);
	}

	/**
	 * Get entity manager assigned to caller class
	 *
	 * @param mixed $caller
	 * @return EntityManager
	 */
	public static function getEntityManager($caller)
	{
		return self::getObject($caller, self::INTERFACE_ENTITY_MANAGER, true);
	}

	/**
	 * Assign entity manager instance to caller class
	 *
	 * @param mixed $caller
	 * @param EntityManager $object 
	 */
	public static function setEntityManager($caller, EntityManager $object)
	{
		self::addBinding($caller, $object, self::INTERFACE_ENTITY_MANAGER);
	}

	/**
	 * Set default entity manager
	 *
	 * @param EntityManager $object 
	 */
	public static function setDefaultEntityManager(EntityManager $object)
	{
		self::addBinding(self::DEFAULT_KEY, $object, self::INTERFACE_ENTITY_MANAGER);
	}

	/**
	 * Get assigned file storage
	 *
	 * @param mixed $caller
	 * @return FileStorage
	 */
	public static function getFileStorage($caller)
	{
		return self::getObject($caller, self::INTERFACE_FILE_STORAGE, true);
	}

	/**
	 * Assign file storage instance to caller class
	 *
	 * @param mixed $caller
	 * @param FileStorage $object 
	 */
	public static function setFileStorage($caller, FileStorage $object)
	{
		self::addBinding($caller, $object, self::INTERFACE_FILE_STORAGE);
	}

	/**
	 * Set default file storage
	 *
	 * @param FileStorage $object 
	 */
	public static function setDefaultFileStorage(FileStorage $object)
	{
		self::addBinding(self::DEFAULT_KEY, $object, self::INTERFACE_FILE_STORAGE);
	}

	/**
	 * Get assigned session namespace
	 *
	 * @param mixed $caller
	 * @return SessionManager
	 */
	public static function getSessionManager($caller)
	{
		return self::getObject($caller, self::INTERFACE_SESSION_NAMESPACE_MANAGER, true);
	}

	public static function getAllSessionManagers()
	{
		return self::getAllObjects(self::INTERFACE_SESSION_NAMESPACE_MANAGER);
	}

	/**
	 * Assign session manager to caller class
	 *
	 * @param mixed $caller
	 * @param SessionManager $object 
	 */
	public static function setSessionManager($caller, SessionManager $object)
	{
		self::addBinding($caller, $object, self::INTERFACE_SESSION_NAMESPACE_MANAGER);
	}

	/**
	 * Set default session manager
	 *
	 * @param SessionManager $object 
	 */
	public static function setDefaultSessionManager(SessionManager $object)
	{
		self::addBinding(self::DEFAULT_KEY, $object, self::INTERFACE_SESSION_NAMESPACE_MANAGER);
	}

	/**
	 * Get assigned user provider
	 *
	 * @param mixed $caller
	 * @param boolean $throwOnMiss
	 * @return UserProviderAbstract
	 */
	public static function getUserProvider($caller, $throwOnMiss = true)
	{
		return self::getObject($caller, self::INTERFACE_USER_PROVIDER, $throwOnMiss);
	}

	/**
	 * Assign user provider instance to caller class
	 *
	 * @param mixed $caller
	 * @param UserProviderAbstract $object 
	 */
	public static function setUserProvider($caller, UserProviderAbstract $object)
	{
		self::addBinding($caller, $object, self::INTERFACE_USER_PROVIDER);
	}

	/**
	 * Set default user provider
	 *
	 * @param UserProviderAbstract $object 
	 */
	public static function setDefaultUserProvider(UserProviderAbstract $object)
	{
		self::addBinding(self::DEFAULT_KEY, $object, self::INTERFACE_USER_PROVIDER);
	}

	/**
	 * Get assigned locale manager
	 *
	 * @param mixed $caller
	 * @return LocaleManager
	 */
	public static function getLocaleManager($caller)
	{
		return self::getObject($caller, self::INTERFACE_LOCALE_MANAGER, true);
	}

	/**
	 * Assign locale manager instance to caller class
	 *
	 * @param mixed $caller
	 * @param LocaleManager $object 
	 */
	public static function setLocaleManager($caller, LocaleManager $object)
	{
		self::addBinding($caller, $object, self::INTERFACE_LOCALE_MANAGER);
	}

	/**
	 * Get assigned cache adapter
	 *
	 * @param mixed $caller
	 * @return Cache
	 */
	public static function getCacheAdapter($caller)
	{
		return self::getObject($caller, self::INTERFACE_CACHE, true);
	}

	/**
	 * Assign cache adapter
	 *
	 * @param mixed $caller
	 * @param Cache $object 
	 */
	public static function setCacheAdapter($caller, Cache $object)
	{
		self::addBinding($caller, $object, self::INTERFACE_CACHE);
	}

	/**
	 * Sets default cache adapter
	 * @param Cache $object 
	 */
	public static function setDefaultCacheAdapter(Cache $object)
	{
		self::addBinding(self::DEFAULT_KEY, $object, self::INTERFACE_CACHE);
	}

	/**
	 * Set default locale manager
	 *
	 * @param LocaleManager $object 
	 */
	public static function setDefaultLocaleManager(LocaleManager $object)
	{
		self::addBinding(self::DEFAULT_KEY, $object, self::INTERFACE_LOCALE_MANAGER);
	}

	/**
	 * Assign mailer instance to caller class
	 *
	 * @param mixed $caller
	 * @param Mailer $object
	 */
	public static function setMailer($caller, Mailer $object)
	{
		self::addBinding($caller, $object, self::INTERFACE_MAILER);
	}

	/**
	 * Set default mailer
	 *
	 * @param Mailer $object 
	 */
	public static function setDefaultMailer(Mailer $object)
	{
		self::addBinding(self::DEFAULT_KEY, $object, self::INTERFACE_MAILER);
	}

	/**
	 * Get assigned mailer
	 *
	 * @param mixed $caller
	 * @return Mailer
	 */
	public static function getMailer($caller)
	{
		return self::getObject($caller, self::INTERFACE_MAILER, true);
	}

	/**
	 * Get assigned authorization provider.
	 *
	 * @param mixed $caller
	 * @return AuthorizationProvider
	 */
	public static function getAuthorizationProvider($caller)
	{
		return self::getObject($caller, self::INTERFACE_AUTHORIZATION_PROVIDER, true);
	}

	/**
	 * Assign autorization provider to class.
	 *
	 * @param mixed $caller
	 * @param AuthorizationProvider $object 
	 */
	public static function setAuthorizationProvider($caller, AuthorizationProvider $object)
	{
		self::addBinding($caller, $object, self::INTERFACE_AUTHORIZATION_PROVIDER);
	}

	/**
	 * Set default authorization provider.
	 *
	 * @param AuthorizationProvider $object 
	 */
	public static function setDefaultAuthorizationProvider(AuthorizationProvider $object)
	{
		self::addBinding(self::DEFAULT_KEY, $object, self::INTERFACE_AUTHORIZATION_PROVIDER);
	}

	/**
	 * Get assigned application configuration.
	 *
	 * @param mixed $caller
	 * @return ApplicationConfiguration
	 */
	public static function getApplicationConfiguration($caller)
	{
		return self::getObject($caller, self::INTERFACE_APPLICATION_CONFIGURATION);
	}

	/**
	 * Assign application configuration to namespace
	 *
	 * @param mixed $caller
	 * @param ApplicationConfiguration $object 
	 */
	public static function setApplicationConfiguration($caller, ApplicationConfiguration $object)
	{
		self::addBinding($caller, $object, self::INTERFACE_APPLICATION_CONFIGURATION);
	}

	/**
	 * Set application configuration.
	 *
	 * @param ApplicationConfiguration $object 
	 */
	public static function setDefaultApplicationConfiguration(ApplicationConfiguration $object)
	{
		self::addBinding(self::DEFAULT_KEY, $object, self::INTERFACE_APPLICATION_CONFIGURATION);
	}

	/**
	 * Get assigned audit logger
	 *
	 * @param mixed $caller
	 * @return AuditLogWriterAbstraction
	 */
	public static function getAuditLogger($caller)
	{
		$logger = self::getObject($caller, self::INTERFACE_AUDIT_LOGGER);

		if (empty($logger)) {
			$logger = new NullAuditLogWriter();
			self::setDefaultAuditLogger($logger);
		}

		return $logger;
	}

	/**
	 * Assign audit logger instance to caller class
	 *
	 * @param mixed $caller
	 * @param AuditLogWriterAbstraction $object 
	 */
	public static function setAuditLogger($caller, AuditLogWriterAbstraction $object)
	{
		self::addBinding($caller, $object, self::INTERFACE_AUDIT_LOGGER);
	}

	/**
	 * Set default audit logger
	 *
	 * @param AuditLogWriterAbstraction $object 
	 */
	public static function setDefaultAuditLogger(AuditLogWriterAbstraction $object)
	{
		self::addBinding(self::DEFAULT_KEY, $object, self::INTERFACE_AUDIT_LOGGER);
	}

	/**
	 * @return boolean
	 */
	public static function isSolariumConfigured($caller)
	{
		$object = self::getObject($caller, self::INTERFACE_SOLARIUM_CLIENT, false);

		return ! empty($object);
	}

	/**
	 * Get Solarium client assigned for caller.
	 *
	 * @param mixed $caller
	 * @return Solarium_Client
	 */
	public static function getSolariumClient($caller)
	{
		return self::getObject($caller, self::INTERFACE_SOLARIUM_CLIENT, true);
	}
	
	/**
	 * Gets searcher service
	 * 
	 * @param mixed $caller
	 * @return \Supra\Search\SearchService
	 */
	public static function getSearchService($caller)
	{
		$service = self::getObject($caller, self::INTERFACE_SEARCH_SERVICE, false);
		
		// @TODO: configuration via dependency injection
		if ($service === null) {
			
			$service = new Search\SearchService(new Search\NullSearcher);
			
			self::setDefaultSearchService($service);
		}
		
		return $service;
	}

	/**
	 * @param mixed $caller
	 * @param \Supra\Search\SearchService $object
	 */
	public static function setSearchService($caller, Search\SearchService $object)
	{
		self::addBinding($caller, $object, self::INTERFACE_SEARCH_SERVICE);
	}

	/**
	 * @param \Supra\Search\SearchService $object
	 */
	public static function setDefaultSearchService(Search\SearchService $object)
	{
		self::addBinding(self::DEFAULT_KEY, $object, self::INTERFACE_SEARCH_SERVICE);
	}
	
	/**
	 * Gets search indexing service
	 * 
	 * @param mixed $caller
	 * @return \Supra\Search\IndexerService
	 */
	public static function getIndexerService($caller)
	{
		$service = self::getObject($caller, self::INTERFACE_INDEXER_SERVICE, false);
		
		// @TODO: configuration via dependency injection
		if ($service === null) {
			
			$service = new Search\IndexerService(new Search\NullIndexer);
			
			self::setDefaultIndexerService($service);
		}
		
		return $service;
	}

	/**
	 * @param mixed $caller
	 * @param \Supra\Search\IndexerService $object
	 */
	public static function setIndexerService($caller, Search\IndexerService $object)
	{
		self::addBinding($caller, $object, self::INTERFACE_INDEXER_SERVICE);
	}

	/**
	 * @param \Supra\Search\IndexerService $object
	 */
	public static function setDefaultIndexerService(Search\IndexerService $object)
	{
		self::addBinding(self::DEFAULT_KEY, $object, self::INTERFACE_INDEXER_SERVICE);
	}

	/**
	 * Get assigned event manager.
	 *
	 * @param mixed $caller
	 * @return EventManager
	 */
	public static function getEventManager($caller = self::DEFAULT_KEY)
	{
		$eventManager = self::getObject($caller, self::INTERFACE_EVENT_MANAGER);

		if (is_null($eventManager)) {
			$eventManager = new EventManager();
			self::setObject(self::DEFAULT_KEY, $eventManager, self::INTERFACE_EVENT_MANAGER);
		}

		return $eventManager;
	}

	/**
	 * Assign event manager to namespace.
	 *
	 * @param mixed $caller
	 * @param EventManager $object 
	 */
	public static function setEventManager($caller, EventManager $object)
	{
		self::addBinding($caller, $object, self::INTERFACE_EVENT_MANAGER);
	}

	/**
	 * Set default event manager.
	 *
	 * @param EventManager $object 
	 */
	public static function setDefaultEventManager(EventManager $object)
	{
		self::addBinding(self::DEFAULT_KEY, $object, self::INTERFACE_EVENT_MANAGER);
	}

	/**
	 * Get assigned ini configuration loader.
	 *
	 * @param mixed $caller
	 * @return IniConfigurationLoader
	 */
	public static function getIniConfigurationLoader($caller)
	{
		$iniConfigurationLoader = self::getObject($caller, self::INTERFACE_INI_CONFIGURATION, true);

		return $iniConfigurationLoader;
	}

	/**
	 * Assign ini configuration loader to namespace.
	 *
	 * @param mixed $caller
	 * @param IniConfigurationLoader $object 
	 */
	public static function setIniConfigurationLoader($caller, IniConfigurationLoader $object)
	{
		self::addBinding($caller, $object, self::INTERFACE_INI_CONFIGURATION);
	}

	/**
	 * Set default ini configuration loader.
	 *
	 * @param IniConfigurationLoader $object 
	 */
	public static function setDefaultIniConfigurationLoader(IniConfigurationLoader $object)
	{
		self::addBinding(self::DEFAULT_KEY, $object, self::INTERFACE_INI_CONFIGURATION);
	}

	/**
	 * Get assigned payment provider collection.
	 *
	 * @param mixed $caller
	 * @return PaymentProviderCollection
	 */
	public static function getPaymentProviderCollection($caller)
	{
		$paymentProviderCollection = self::getObject($caller, self::INTERFACE_PAYMENT_PROVIDER_COLLECTION, true);

		return $paymentProviderCollection;
	}

	/**
	 * Assign payment provider collection to namespace.
	 *
	 * @param mixed $caller
	 * @param PaymentProviderCollection $object 
	 */
	public static function setPaymentProviderCollection($caller, PaymentProviderCollection $object)
	{
		self::addBinding($caller, $object, self::INTERFACE_PAYMENT_PROVIDER_COLLECTION);
	}

	/**
	 * Set default payment provider collection.
	 *
	 * @param PaymentProviderCollection $object 
	 */
	public static function setDefaultPaymentProviderCollection(PaymentProviderCollection $object)
	{
		self::addBinding(self::DEFAULT_KEY, $object, self::INTERFACE_PAYMENT_PROVIDER_COLLECTION);
	}

	/**
	 * Get assigned template parser.
	 * @param mixed $caller
	 * @return TemplateParser
	 */
	public static function getTemplateParser($caller)
	{
		$templateParser = self::getObject($caller, self::INTERFACE_TEMPLATE_PARSER, true);

		return $templateParser;
	}

	/**
	 * Assign template parser to namespace.
	 * @param mixed $caller
	 * @param TemplateParser $object
	 */
	public static function setTemplateParser($caller, TemplateParser $object)
	{
		self::addBinding($caller, $object, self::INTERFACE_TEMPLATE_PARSER);
	}

	/**
	 * Set default template parser.
	 * @param TemplateParser $object
	 */
	public static function setDefaultTemplateParser(TemplateParser $object)
	{
		self::addBinding(self::DEFAULT_KEY, $object, self::INTERFACE_TEMPLATE_PARSER);
	}

	/**
	 * Get assigned banner provider.
	 * @param mixed $caller
	 * @return BannerProvider
	 */
	public static function getBannerProvider($caller)
	{
		$bannerProvider = self::getObject($caller, self::INTERFACE_BANNER_MACHINE, true);

		return $bannerProvider;
	}

	/**
	 * Assign banner provider to namespace.
	 * @param mixed $caller
	 * @param BannerProvider $object
	 */
	public static function setBannerProvider($caller, BannerProvider $object)
	{
		self::addBinding($caller, $object, self::INTERFACE_BANNER_MACHINE);
	}

	/**
	 * Set default banner provider.
	 * @param BannerProvider $object
	 */
	public static function setDefaultBannerProvider(BannerProvider $object)
	{
		self::addBinding(self::DEFAULT_KEY, $object, self::INTERFACE_BANNER_MACHINE);
	}

	/**
	 * Get assigned component configuration.
	 *
	 * @param mixed $caller
	 * @return ComponentConfiguration
	 */
	public static function getComponentConfiguration($caller)
	{
		return self::getObject($caller, self::INTERFACE_COMPONENT_CONFIGURATION);
	}

	/**
	 * Assign component configuration to namespace
	 *
	 * @param mixed $caller
	 * @param ComponentConfiguration $object 
	 */
	public static function setComponentConfiguration($caller, ComponentConfiguration $object)
	{
		self::addBinding($caller, $object, self::INTERFACE_COMPONENT_CONFIGURATION);
	}

	/**
	 * Set default component configuration
	 *
	 * @param ComponentConfiguration $object 
	 */
	public static function setDefaultComponentConfiguration(ComponentConfiguration $object)
	{
		self::addBinding(self::DEFAULT_KEY, $object, self::INTERFACE_COMPONENT_CONFIGURATION);
	}

	/**
	 * Get assigned system info object.
	 * @param mixed $caller
	 * @return Info
	 */
	public static function getSystemInfo($caller)
	{
		$systemInfo = self::getObject($caller, self::INTERFACE_SYSTEM_INFO, true);

		return $systemInfo;
	}

	/**
	 * Assign system info to namespace.
	 * @param mixed $caller
	 * @param Info $object
	 */
	public static function setSystemInfo($caller, Info $object)
	{
		self::addBinding($caller, $object, self::INTERFACE_SYSTEM_INFO);
	}

	/**
	 * Set default system info
	 * @param Info $object
	 */
	public static function setDefaultSystemInfo(Info $object)
	{
		self::addBinding(self::DEFAULT_KEY, $object, self::INTERFACE_SYSTEM_INFO);
	}

	/**
	 * Get assigned MassMail instance.
	 *
	 * @param mixed $caller
	 * @return MassMail
	 */
	public static function getMassMail($caller)
	{
		$massMail = self::getObject($caller, self::INTERFACE_MASS_MAIL, true);

		return $massMail;
	}

	/**
	 * Assign MassMail to namespace.
	 *
	 * @param mixed $caller
	 * @param MassMail $object 
	 */
	public static function setMassMail($caller, MassMail $object)
	{
		self::addBinding($caller, $object, self::INTERFACE_MASS_MAIL);
	}

	/**
	 * Set default MassMail object.
	 *
	 * @param MassMail $object 
	 */
	public static function setDefaultMassMail(MassMail $object)
	{
		self::addBinding(self::DEFAULT_KEY, $object, self::INTERFACE_MASS_MAIL);
	}

	/**
	 * Get assigned RemoteCommandService instance.
	 *
	 * @param mixed $caller
	 * @return RemoteCommandService
	 */
	public static function getRemoteCommandService($caller)
	{
		$remoteCommandService = self::getObject($caller, self::INTERFACE_REMOTE_COMMAND_SERVICE, true);

		return $remoteCommandService;
	}

	/**
	 * Assign RemoteCommandService to namespace.
	 *
	 * @param mixed $caller
	 * @param RemoteCommandService $object 
	 */
	public static function setRemoteCommandService($caller, RemoteCommandService $object)
	{
		self::addBinding($caller, $object, self::INTERFACE_REMOTE_COMMAND_SERVICE);
	}

	/**
	 * Set default RemoteCommandService object.
	 *
	 * @param RemoteCommandService $object 
	 */
	public static function setDefaultRemoteCommandService(RemoteCommandService $object)
	{
		self::addBinding(self::DEFAULT_KEY, $object, self::INTERFACE_REMOTE_COMMAND_SERVICE);
	}

	/**
	 * Get assigned ThemeProviderAbstraction instance.
	 *
	 * @param mixed $caller
	 * @return ThemeProviderAbstraction
	 */
	public static function getThemeProvider($caller)
	{
		$themeProvider = self::getObject($caller, self::INTERFACE_THEME_PROVIDER, false);

		if (empty($themeProvider)) {

			$themeProvider = new DefaultThemeProvider();
			$themeProvider->setUrlBase('/');
			$themeProvider->setRootDir(SUPRA_TEMPLATE_PATH);

			$writeableIniLoader = new WriteableIniConfigurationLoader('theme.ini');
			self::setIniConfigurationLoader('Supra\Controller\Layout\Theme', $writeableIniLoader);

			self::setDefaultThemeProvider($themeProvider);
		}

		return $themeProvider;
	}

	/**
	 * Assign ThemeProviderAbstraction to namespace.
	 *
	 * @param mixed $caller
	 * @param ThemeProviderAbstraction $object 
	 */
	public static function setThemeProvider($caller, ThemeProviderAbstraction $object)
	{
		self::addBinding($caller, $object, self::INTERFACE_THEME_PROVIDER);
	}

	/**
	 * Set default ThemeProviderAbstraction object.
	 *
	 * @param ThemeProviderAbstraction $object 
	 */
	public static function setDefaultThemeProvider(ThemeProviderAbstraction $object)
	{
		self::addBinding(self::DEFAULT_KEY, $object, self::INTERFACE_THEME_PROVIDER);
	}

	/**
	 * @param mixed $caller
	 * @return FormFactoryInterface
	 */
	public static function getFormFactory($caller)
	{
		$formFactory = self::getObject($caller, self::INTERFACE_FORM_FACTORY, false);

		// Generate the default one
		if (is_null($formFactory)) {
			$configuration = new \Supra\Form\Configuration\FormFactoryConfiguration();
			$configuration->caller = self::DEFAULT_KEY;
			$configuration->configure();

			$formFactory = self::getObject($caller, self::INTERFACE_FORM_FACTORY, true);
		}

		return $formFactory;
	}

	/**
	 * @param mixed $caller
	 * @param \Symfony\Component\Form\FormFactoryInterface $object
	 */
	public static function setFormFactory($caller, \Symfony\Component\Form\FormFactoryInterface $object)
	{
		self::addBinding($caller, $object, self::INTERFACE_FORM_FACTORY);
	}

	/**
	 * @param \Symfony\Component\Form\FormFactoryInterface $object
	 */
	public static function setDefaultFormFactory(\Symfony\Component\Form\FormFactoryInterface $object)
	{
		self::addBinding(self::DEFAULT_KEY, $object, self::INTERFACE_FORM_FACTORY);
	}

	/**
	 * Get assigned Translator instance.
	 *
	 * @param mixed $caller
	 * @return Translator
	 */
	public static function getTranslator($caller)
	{
		$translator = self::getObject($caller, self::INTERFACE_TRANSLATOR, true);

		return $translator;
	}

	/**
	 * Assign Translator to namespace.
	 *
	 * @param mixed $caller
	 * @param Translator $object 
	 */
	public static function setTranslator($caller, Translator $object)
	{
		self::addBinding($caller, $object, self::INTERFACE_TRANSLATOR);
	}

	/**
	 * Set default Translator object.
	 *
	 * @param Translator $object 
	 */
	public static function setDefaultTranslator(Translator $object)
	{
		self::addBinding(self::DEFAULT_KEY, $object, self::INTERFACE_TRANSLATOR);
	}

}
