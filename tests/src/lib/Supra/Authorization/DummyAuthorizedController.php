<?php

namespace Supra\Tests\Authorization;

use Supra\Authorization\AuthorizedControllerInterface;
use Supra\Authorization\AuthorizedAction;
use Supra\Controller\EmptyController;
use Symfony\Component\Security\Acl\Permission\MaskBuilder;
use Supra\Authorization\AuthorizedControllerExecuteAction;
use Supra\User\Entity\AbstractUser;

class DummyAuthorizedController extends EmptyController implements AuthorizedControllerInterface
{
	function getAuthorizationId() 
	{
		return __CLASS__;
	}
	
	function authorize(AbstractUser $user, $permission = null) 
	{
		return true;
	}
}
