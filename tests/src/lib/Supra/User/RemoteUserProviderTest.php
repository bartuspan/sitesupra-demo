<?php

namespace Supra\Tests\User;

use Supra\Tests\TestCase;
use Supra\User;
use Supra\User\Entity;
use Supra\User\Exception;
use Supra\ObjectRepository\ObjectRepository;

require_once 'PHPUnit/Extensions/OutputTestCase.php';

/**
 * Test class for EmptyController
 */
class RemoteUserProviderTest extends \PHPUnit_Extensions_OutputTestCase
{

	/**
	 * @var User\UserProvider
	 */
	private $userProvider;

	/**
	 * @var \Doctrine\ORM\EntityManager
	 */
	private $em;

	public function __construct()
	{
		$this->em = ObjectRepository::getEntityManager($this);
		$this->userProvider = ObjectRepository::getUserProvider($this);
	}

	public function testAuthenticate()
	{
		
	}

	public function testCreateGroup()
	{
		
	}

	public function testCreateUser()
	{
		
	}

	public function testDoDeleteUser()
	{
		
	}

	public function testFindAllGroups()
	{
		
	}

	public function testFindAllUsers()
	{
		$users = $this->userProvider->findAllUsers();
		if (empty($users)) {
			self::fail('Get empty $users array');
			return;
		}

		foreach ($users as $user) {
			$this->validateUser($user);
			$this->validateGroup($user->getGroup());
		}
	}

	public function testFindGroupById()
	{
		
	}

	public function testFindGroupByName()
	{
		
	}

	/**
	 * Validates user
	 * @param Entity\User $user
	 * @param boolean $success If success true will assert true else false
	 */
	private function validateUser($user, $success = true)
	{
		$instance = self::isInstanceOf('Supra\User\Entity\User');
		$result = $instance->evaluate($user, '$user should be instance of User entity', true);
		if ($success) {
			self::assertTrue($result);
		} else {
			self::assertFalse($result);
		}
	}

	private function validateGroup($group, $success = true)
	{
		$instance = self::isInstanceOf('Supra\User\Entity\Group');
		$result = $instance->evaluate($group, '$group should be instance of Group entity', true);
		if ($success) {
			self::assertTrue($result);
		} else {
			self::assertFalse($result);
		}
	}

	public function testFindUserByEmail()
	{
		$user = $this->userProvider->findUserByLogin('vasya');
		$this->validateUser($user, false);

		$user = $this->userProvider->findUserByLogin('vasya@google.lv');
		$this->validateUser($user);
		$this->validateGroup($user->getGroup());
	}

	public function testFindUserById()
	{
		$user = $this->userProvider->findUserById('002s42jkpcmyu6d9ihdw');
		$this->validateUser($user);
		$this->validateGroup($user->getGroup());

		$user = $this->userProvider->findUserById('badId');
		$this->validateUser($user, false);
	}

	public function testFindUserByLogin()
	{
		$user = $this->userProvider->findUserByLogin('vasya');
		$this->validateUser($user, false);

		$user = $this->userProvider->findUserByLogin('vasya@google.lv');
		$this->validateUser($user);
		$this->validateGroup($user->getGroup());
	}

	public function testFindUserByName()
	{
		$user = $this->userProvider->findUserByName('vasya');
		$this->validateUser($user, false);

		$user = $this->userProvider->findUserByName('VASYA');
		$this->validateUser($user, false);

		$user = $this->userProvider->findUserByName('VASYA VORONEZHSKY');
		$this->validateUser($user);
		$this->validateGroup($user->getGroup());

		$user = $this->userProvider->findUserByName('VASYA Voronezhsky');
		$this->validateUser($user);
		$this->validateGroup($user->getGroup());

		$user = $this->userProvider->findUserByName('vasya voronezhsky');
		$this->validateUser($user);
		$this->validateGroup($user->getGroup());
	}

	public function testGetAllUsersInGroup()
	{
		
	}

	public function testLoadUserByUsername()
	{
	}

	public function testRefreshUser()
	{
		
	}

	public function testSupportsClass()
	{
		
	}

	public function testUpdateGroup()
	{
		
	}

	public function testUpdateUser()
	{
		
	}

}