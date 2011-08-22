<?php

namespace Project\Rss;

// Register namespace
$namespaceConfiguration = new \Supra\Loader\Configuration\NamespaceConfiguration();
$namespaceConfiguration->dir = __DIR__;
$namespaceConfiguration->namespace = __NAMESPACE__;

$routerConfiguration = new \Supra\Router\Configuration\RouterConfiguration();
$routerConfiguration->url = '/rss';
$routerConfiguration->controller = 'Project\Rss\Controller';

$controllerConfiguration = new \Supra\Controller\Configuration\ControllerConfiguration();
$controllerConfiguration->namespace = $namespaceConfiguration;
$controllerConfiguration->router = $routerConfiguration;
$controllerConfiguration->configure();

// Event test
//TODO: make this somehow configurable
$listenerFunction = function($className, $type, $parameters) {
	\Log::info("Event $type for class $className has been fired with parameters ", $parameters);
};
\Supra\Event\Registry::listen('Project\Rss\Controller', 'index', $listenerFunction);
