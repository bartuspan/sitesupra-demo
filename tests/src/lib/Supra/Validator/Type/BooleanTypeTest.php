<?php

namespace Supra\Tests\Validator\Type;

use Supra\Validator\Type\BooleanType;

/**
 * Test class for BooleanType.
 * Generated by PHPUnit on 2011-11-24 at 19:13:25.
 */
class BooleanTypeTest extends \PHPUnit_Framework_TestCase
{

	/**
	 * @var BooleanType
	 */
	protected $object;

	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 */
	protected function setUp()
	{
		$this->object = new BooleanType;
	}

	/**
	 * Tears down the fixture, for example, closes a network connection.
	 * This method is called after a test is executed.
	 */
	protected function tearDown()
	{
		
	}

	/**
	 */
	public function testValidate()
	{
		$a = 'on';
		$this->object->validate($a);
		self::assertSame(true, $a);
		$a = null;
		$this->object->validate($a);
		self::assertSame(false, $a);
		$a = '';
		$this->object->validate($a);
		self::assertSame(false, $a);
		$a = '0';
		$this->object->validate($a);
		self::assertSame(false, $a);
		$a = 'off';
		$this->object->validate($a);
		self::assertSame(false, $a);
	}
	
	/**
	 * @expectedException \Supra\Validator\Exception\ValidationFailure
	 */
	public function testInvalid()
	{
		$a = 'x';
		$this->object->validate($a);
	}

}
