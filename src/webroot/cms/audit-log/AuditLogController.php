<?php

namespace Supra\Cms\AuditLog;

use Supra\Controller\DistributedController;

/**
 * Settings application controller
 */
class AuditLogController extends DistributedController
{
	/**
	 * Default action when no action is provided
	 * @var string
	 */
	protected $defaultAction = 'root';
}