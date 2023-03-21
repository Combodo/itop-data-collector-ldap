<?php

namespace UnitTestFiles\Test;

use LdapMockingRessource;
use LDAPCollector;
use PHPUnit\Framework\TestCase;
use Utils;

define('APPROOT', dirname(__FILE__, 3). '/'); // correct way

require_once (__DIR__.'/LdapMockingRessource.php');
require_once (APPROOT.'core/parameters.class.inc.php');
require_once (APPROOT.'core/utils.class.inc.php');
require_once (APPROOT.'core/collector.class.inc.php');
require_once (APPROOT.'collectors/LDAPCollector.class.inc.php');

/**
 * @runClassInSeparateProcess
 */
class LDAPCollectorTest extends TestCase
{
	private $sTempConfigFile;
	private $oConnexionResource;
	private $oResult;
	private $oLDAPCollector;
	private $oLDAPService;

	public function setUp(): void
	{
		parent::setUp();
		$this->sTempConfigFile = tempnam(sys_get_temp_dir(), "paramsxml");

		$this->oConnexionResource = $this->createMock(LdapMockingRessource::class);
		$this->oResult = $this->createMock(LdapMockingRessource::class);
		$this->oLDAPService = $this->createMock(\LDAPService::class);
	}

	public function tearDown(): void
	{
		parent::tearDown();

		if (is_file($this->sTempConfigFile)){
			unlink($this->sTempConfigFile);
		}
	}

	public function ConnectProvider(){
		return [
			'connect via uri' => ['connect-via-uri.xml', 'ldap://myldap.fr', '389'],
			'connect with port and host' => ['connect-with-port-host.xml', 'myldap2.fr', '666'],
			'connect with host' => ['connect-with-host.xml', 'myldap2.fr', '389'],
		];
	}

	/**
	 * @dataProvider ConnectProvider
	 */
	public function testConnect($sFileName, $sUri, $sPort){
		global $argv;
		$argv[]="--config_file=".$this->sTempConfigFile;
		$this->assertTrue(copy(__DIR__."/resources/$sFileName", $this->sTempConfigFile));

		$this->oLDAPCollector = new LDAPCollector();
		$this->oLDAPCollector->SetLDAPService($this->oLDAPService);

		$this->oLDAPService->expects($this->once())
			->method('ldap_connect')
			->with($sUri, $sPort)
			->willReturn($this->oConnexionResource);

		$this->oLDAPService->expects($this->exactly(2))
			->method('ldap_set_option')
			->withConsecutive(
				[$this->oConnexionResource, LDAP_OPT_REFERRALS, 0],
				[$this->oConnexionResource, LDAP_OPT_PROTOCOL_VERSION, 3]
			)
			->willReturn(true);

		$this->oLDAPService->expects($this->once())
			->method('ldap_bind')
			->with($this->oConnexionResource, "ldaplogin123", "ldappassword456")
			->willReturn(true);

		if (version_compare(PHP_VERSION, '7.3.0') < 0) {
			$this->oLDAPService->expects($this->never())
				->method('ldap_read');

			$this->oLDAPService->expects($this->never())
				->method('ldap_get_entries');	
		} else {
			$this->oLDAPService->expects($this->once())
				->method('ldap_read')
				->with($this->oConnexionResource, '', '(objectClass=*)', ['supportedControl'])
				->willReturn($this->oResult);

			$this->oLDAPService->expects($this->once())
				->method('ldap_get_entries')
				->with($this->oConnexionResource, $this->oResult)
				->willReturn([['supportedcontrol'=>[]]]);
		}

		$this->InvokeNonPublicMethod(LDAPCollector::class, 'Connect', $this->oLDAPCollector, []);
	}

	/**
	 * @dataProvider ConnectProvider
	 */
	public function testConnectAndThenDisconnect($sFileName, $sUri, $sPort) {
		$this->testConnect($sFileName, $sUri, $sPort);

		$this->oLDAPService->expects($this->once())
			->method('ldap_close')
			->with($this->oConnexionResource)
			->willReturn(true);

		$this->InvokeNonPublicMethod(LDAPCollector::class, 'Disconnect', $this->oLDAPCollector, []);
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
