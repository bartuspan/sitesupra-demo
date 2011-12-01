<?php

namespace Supra\Tests\Authentication\Adapter\Algorithm;

use Supra\Authentication\Adapter\Algorithm\BlowfishAlgorithm;

/**
 * Test class for BlowfishAlgorithm.
 * Generated by PHPUnit on 2011-12-01 at 11:46:52.
 */
class BlowfishAlgorithmTest extends \PHPUnit_Framework_TestCase
{

	/**
	 * @var BlowfishAlgorithm
	 */
	protected $object;
	protected $password;
	protected $password2;

	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 */
	protected function setUp()
	{
		$this->object = new BlowfishAlgorithm;
		$this->password = new \Supra\Authentication\AuthenticationPassword('admin123');
		$this->password2 = new \Supra\Authentication\AuthenticationPassword('admin1234');
	}

	/**
	 * Tears down the fixture, for example, closes a network connection.
	 * This method is called after a test is executed.
	 */
	protected function tearDown()
	{
		
	}

	/**
	 * @covers {className}::{origMethodName}
	 * @todo Implement testCrypt().
	 */
	public function testCrypt()
	{
		$hash1 = $this->object->crypt($this->password);
		$hash2 = $this->object->crypt($this->password);
		$hash3 = $this->object->crypt($this->password2);
		
		self::assertEquals(60, strlen($hash1));
		
		self::assertNotEquals($hash1, $hash2);
		self::assertNotEquals($hash1, $hash3);
	}

	/**
	 * @covers {className}::{origMethodName}
	 * @todo Implement testValidate().
	 */
	public function testValidate()
	{
		self::assertTrue($this->object->validate($this->password, '$2a$12$SY5BN2PUdytGZd5bG3RuVuLc9ccAPvitAYn9F1g4o5jBIuqxHjTiO'));
		self::assertFalse($this->object->validate($this->password2, '$2a$12$SY5BN2PUdytGZd5bG3RuVuLc9ccAPvitAYn9F1g4o5jBIuqxHjTiO'));
		self::assertFalse($this->object->validate($this->password, null));
		self::assertTrue($this->object->validate($this->password, '$2a$05$.hisispurlyrandomshitOvuXVvw1F/tQdMVztTBgCp2z0LxOTjJK'));
	}

}
