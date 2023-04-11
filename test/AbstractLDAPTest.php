<?php

namespace UnitTestFiles\Test;

use PHPUnit\Framework\TestCase;

abstract class AbstractLDAPTest extends TestCase
{
	public function setUp(): void
	{
		parent::setUp();
	}

	public function tearDown(): void
	{
		parent::tearDown();
	}

	/**
	 * @param string $sObjectClass for example DBObject::class
	 * @param string $sMethodName
	 * @param ?object $oObject
	 * @param array $aArgs
	 *
	 * @return mixed method result
	 *
	 * @throws \ReflectionException
	 *
	 * @since 2.7.4 3.0.0
	 */
	public function InvokeNonPublicMethod($sObjectClass, $sMethodName, $oObject, $aArgs)
	{
		$class = new \ReflectionClass($sObjectClass);
		$method = $class->getMethod($sMethodName);
		$method->setAccessible(true);

		return $method->invokeArgs($oObject, $aArgs);
	}
}
