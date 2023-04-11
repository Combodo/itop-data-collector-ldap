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
require_once (__DIR__.'/AbstractLDAPTest.php');

/**
 * @runClassInSeparateProcess
 * @group beforesetup
 */
class LDAPCollectorTest extends AbstractLDAPTest
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
			'connect via uri' => [ true, true, true, 'connect-via-uri.xml', 'ldap://myldap.fr', '389'],
			'connect with port and host' => [ true, true, true, 'connect-with-port-host.xml', 'myldap2.fr', '666'],
			'connect with host' => [ true, true, true, 'connect-with-host.xml', 'myldap2.fr', '389'],
		];
	}

	/**
	 * @dataProvider ConnectProvider
	 */
	public function testConnect($bSuccessBehaviourConfiguredInMock, $bLdapBindOk=true, $bDefineGetEntriesBehaviour=true, $sFileName='connect-via-uri.xml', $sUri='ldap://myldap.fr', $sPort='389'){
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
			->willReturn($bLdapBindOk);

		if ($bSuccessBehaviourConfiguredInMock){
			$sErrorMsg="Success";
			$iErrorNo=0;

			$this->oLDAPService->expects($this->once())
				->method('ldap_error')
				->with($this->oConnexionResource)
				->willReturn($sErrorMsg);

			$this->oLDAPService->expects($this->once())
				->method('ldap_errno')
				->with($this->oConnexionResource)
				->willReturn($iErrorNo);
		}

		if (!$bLdapBindOk || version_compare(PHP_VERSION, '7.3.0') < 0) {
			$this->oLDAPService->expects($this->never())
				->method('ldap_read');

			if ($bDefineGetEntriesBehaviour) {
				$this->oLDAPService->expects($this->never())
					->method('ldap_get_entries');
			}
		} else {
			$this->oLDAPService->expects($this->once())
				->method('ldap_read')
				->with($this->oConnexionResource, '', '(objectClass=*)', ['supportedControl'])
				->willReturn($this->oResult);

			if ($bDefineGetEntriesBehaviour) {
				$this->oLDAPService->expects($this->once())
					->method('ldap_get_entries')
					->with($this->oConnexionResource, $this->oResult)
					->willReturn([['supportedcontrol' => []]]);
			}
		}

		$this->InvokeNonPublicMethod(LDAPCollector::class, 'Connect', $this->oLDAPCollector, []);

		if ($bSuccessBehaviourConfiguredInMock) {
			$this->assertEquals($iErrorNo, $this->oLDAPCollector->GetLastLdapErrorCode());
			$this->assertEquals($sErrorMsg, $this->oLDAPCollector->GetLastLdapErrorMessage());
		}
	}

	public function ConnectAndDisconnectProvider(){
		return [
			'connect via uri Bind OK' => [ "Success", 0 ],
			'connect via uri Bind KO' => [ "LDAP_OPERATIONS_ERROR", 1, false ],
		];
	}

	/**
	 * @dataProvider ConnectAndDisconnectProvider
	 */
	public function testConnectAndThenDisconnectOK($sErrorMsg, $iErrorNo, $bLdapBindOk=true) {
		$this->oLDAPService->expects($this->once())
			->method('ldap_error')
			->with($this->oConnexionResource)
			->willReturn($sErrorMsg);

		$this->oLDAPService->expects($this->once())
			->method('ldap_errno')
			->with($this->oConnexionResource)
			->willReturn($iErrorNo);

		$this->testConnect(false, $bLdapBindOk);

		$this->oLDAPService->expects($this->once())
			->method('ldap_close')
			->with($this->oConnexionResource)
			->willReturn(true);

		$this->assertEquals($iErrorNo, $this->oLDAPCollector->GetLastLdapErrorCode());
		$this->assertEquals($sErrorMsg, $this->oLDAPCollector->GetLastLdapErrorMessage());

		$this->InvokeNonPublicMethod(LDAPCollector::class, 'Disconnect', $this->oLDAPCollector, []);

		$this->assertEquals($iErrorNo, $this->oLDAPCollector->GetLastLdapErrorCode());
		$this->assertEquals($sErrorMsg, $this->oLDAPCollector->GetLastLdapErrorMessage());
	}

	public function SearchWithoutPaginationProvider(){
		$aPersonFields = [
			'samaccountname',
			'sn',
			'givenname',
			'mail',
			'telephonenumber',
			'mobile',
			'title',
			'employeenumber'
		];

		return [
			'default attributes' => [ 'aAttributes' => null ],
			'person fields' => ['aAttributes' => $aPersonFields ]
		];
	}

	/**
	 * @dataProvider SearchWithoutPaginationProvider
	 */
	public function testSearchWithoutPaginationKO($aAttributes){
		$sErrorMsg = "WTF";
		$iErrorNo=6;

		$this->oLDAPService->expects($this->exactly(2))
			->method('ldap_error')
			->with($this->oConnexionResource)
			->willReturn("Success", $sErrorMsg);

		$this->oLDAPService->expects($this->exactly(2))
			->method('ldap_errno')
			->with($this->oConnexionResource)
			->willReturn(0, $iErrorNo);

		$this->testConnect(false);
		$this->assertEquals(0, $this->oLDAPCollector->GetLastLdapErrorCode());
		$this->assertEquals("Success", $this->oLDAPCollector->GetLastLdapErrorMessage());

		$sFilter = '(objectClass=person)';
		$sDN = 'DC=company,DC=com';

		$aExpecteRes = false;
		if (is_null($aAttributes)){
			$this->oLDAPService->expects($this->once())
				->method('ldap_search')
				->with($this->oConnexionResource, $sDN, $sFilter, ['*'] , 0, -1)
				->willReturn(false);
			$this->assertEquals($aExpecteRes, $this->oLDAPCollector->Search($sDN, $sFilter));
		} else {
			$this->oLDAPService->expects($this->once())
				->method('ldap_search')
				->with($this->oConnexionResource, $sDN, $sFilter, $aAttributes, 0, -1)
				->willReturn(false);
			$this->assertEquals($aExpecteRes, $this->oLDAPCollector->Search($sDN, $sFilter, $aAttributes));
		}

		$this->assertEquals($iErrorNo, $this->oLDAPCollector->GetLastLdapErrorCode());
		$this->assertEquals($sErrorMsg, $this->oLDAPCollector->GetLastLdapErrorMessage());
	}


	/**
	 * @dataProvider SearchWithoutPaginationProvider
	 */
	public function testSearchWithoutPaginationOK($aAttributes){
		$this->oLDAPService->expects($this->exactly(2))
			->method('ldap_error')
			->with($this->oConnexionResource)
			->willReturn("Success");

		$this->oLDAPService->expects($this->exactly(2))
			->method('ldap_errno')
			->with($this->oConnexionResource)
			->willReturn(0);

		$rSearch = $this->createMock(LdapMockingRessource::class);
		$aExpecteRes = ['gabuzomeu' => "shadok"];

		if (version_compare(PHP_VERSION, '7.3.0') < 0) {
			$this->oLDAPService->expects($this->once())
				->method('ldap_get_entries')
				->with($this->oConnexionResource, $rSearch)
				->willReturn($aExpecteRes);
		} else {
			$this->oLDAPService->expects($this->exactly(2))
				->method('ldap_get_entries')
				->withConsecutive(
					[$this->oConnexionResource, $this->oResult],
					[$this->oConnexionResource, $rSearch],
				)
				->willReturnOnConsecutiveCalls(
					[['supportedcontrol' => []]],
					$aExpecteRes
				);
		}

		$this->testConnect(false, true, false);
		$this->assertEquals(0, $this->oLDAPCollector->GetLastLdapErrorCode());
		$this->assertEquals("Success", $this->oLDAPCollector->GetLastLdapErrorMessage());

		$sFilter = '(objectClass=person)';
		$sDN = 'DC=company,DC=com';

		$this->oLDAPService->expects($this->once())
			->method('ldap_close')
			->with($this->oConnexionResource)
			->willReturn(true);

		if (is_null($aAttributes)){
			$this->oLDAPService->expects($this->once())
				->method('ldap_search')
				->with($this->oConnexionResource, $sDN, $sFilter, ['*'] , 0, -1)
				->willReturn($rSearch);
			$this->assertEquals($aExpecteRes, $this->oLDAPCollector->Search($sDN, $sFilter));
		} else {
			$this->oLDAPService->expects($this->once())
				->method('ldap_search')
				->with($this->oConnexionResource, $sDN, $sFilter, $aAttributes, 0, -1)
				->willReturn($rSearch);
			$this->assertEquals($aExpecteRes, $this->oLDAPCollector->Search($sDN, $sFilter, $aAttributes));
		}

		$this->assertEquals(0, $this->oLDAPCollector->GetLastLdapErrorCode());
		$this->assertEquals("Success", $this->oLDAPCollector->GetLastLdapErrorMessage());
	}
}
