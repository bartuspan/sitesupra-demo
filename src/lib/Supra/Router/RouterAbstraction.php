<?php

namespace Supra\Router;

use Supra\Controller\ControllerInterface;

/**
 * Description of RouterAbstraction
 */
abstract class RouterAbstraction implements RouterInterface
{
	/**
	 * Default router parameters
	 * @var array
	 */
	static protected $defaultParameters = array();

	/**
	 * Router parameters
	 * @var array
	 */
	protected $parameters = array();

	/**
	 * Controller class name
	 * @var string
	 */
	protected $controllerClass;

	/**
	 * Bound controller
	 * @var ControllerInterface
	 */
	protected $controller;

	/**
	 * Set controller to route
	 * @param string $controller
	 */
	public function setControllerClass($controllerClass)
	{
		if (empty($controllerClass)) {
			throw new Exception\RuntimeException("Controller class name is not provided for the router");
		}
		$this->controllerClass = $controllerClass;
	}

	/**
	 * Get routed controller
	 * @return ControllerInterface
	 */
	public function getController()
	{
		if ( ! is_null($this->controller)) {
			return $this->controller;
		}
		if ( ! class_exists($this->controllerClass)) {
			throw new Exception\RuntimeException("Controller class {$this->controllerClass} cannot be found");
		}
		$this->controller = new $this->controllerClass;
		if ( ! ($this->controller instanceof ControllerInterface)) {
			throw new Exception\RuntimeException("Controller class {$this->controllerClass} does not implement Supra\Controller\ControllerInterface");
		}
		return $this->controller;
	}

	/**
	 * Set parameters
	 * @param array $parameters
	 */
	public function setParameters(array $parameters)
	{
		$this->parameters = static::$defaultParameters + $parameters;
	}

	/**
	 * Gets parameters
	 * @return array
	 */
	public function getParameters()
	{
		return $this->parameters;
	}

	/**
	 * Get parameter value
	 * @param string $key
	 * @param mixed $default
	 * @return mixed
	 */
	public function getParameter($key, $default = null)
	{
		if (array_key_exists($key, $this->parameters)) {
			return $this->parameters[$key];
		}
		return $default;
	}
}