<?php

namespace UnitTestFiles\Test;

use PHPUnit\Framework\TestCase;
use Utils;

if (! defined('APPROOT')){
	define('APPROOT', dirname(__FILE__, 3). '/'); // correct way
}

require_once (__DIR__.'/LdapMockingRessource.php');
require_once (__DIR__.'/AbstractLDAPTest.php');
require_once (APPROOT.'collectors/iTopUserLDAPCollector.class.inc.php');

/**
 * @runClassInSeparateProcess
 */
class AbstractLDAPCollectorTest extends AbstractLDAPTest
{
	private $sTempConfigFile;
	private $iTopUserLDAPCollector;

	public function setUp(): void
	{
		parent::setUp();
		$this->sTempConfigFile = tempnam(sys_get_temp_dir(), "paramsxml");
	}

	public function tearDown(): void
	{
		parent::tearDown();

		if (is_file($this->sTempConfigFile)){
			unlink($this->sTempConfigFile);
		}
	}

	public function testGetFieldKeysToSearchOnLDAPSide(){
		global $argv;
		$argv[]="--config_file=".$this->sTempConfigFile;
		$this->assertTrue(copy(__DIR__."/resources/params.ldapfetch.xml", $this->sTempConfigFile));

		$this->iTopUserLDAPCollector = new \iTopPersonLDAPCollector();
		$aExpectedRes = [
			'uid',
			'sn',
			'givenname',
			'mail',
			'telephonenumber',
			'mobile',
			'title',
			'employeenumber',
		];
		$aLDAPFields = $this->InvokeNonPublicMethod(\iTopPersonLDAPCollector::class, 'GetFieldsToFetch', $this->iTopUserLDAPCollector, []);
		$this->assertEquals($aExpectedRes,$aLDAPFields);
	}
}
