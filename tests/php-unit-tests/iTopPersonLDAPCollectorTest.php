<?php

namespace UnitTestFiles\Test;

use LDAPMockingRessource;
use LDAPSearchServiceTest;
use PHPUnit\Framework\Attributes\DataProvider;
use Utils;

require_once (__DIR__.'/AbstractLDAPTestCase.php');
require_once (__DIR__.'/LDAPMockingRessource.php');
require_once (APPROOT.'collectors/src/iTopPersonLDAPCollector.class.inc.php');

/**
 * @runClassInSeparateProcess
 */
class iTopPersonLDAPCollectorTest extends AbstractLDAPTestCase
{
	private $sTempConfigFile;
	private $oConnexionResource;
	private $oResult;
	private $iTopPersonLDAPCollector;
	private $oLDAPSearchService;

	public function setUp(): void
	{
		parent::setUp();
		$this->sTempConfigFile = tempnam(sys_get_temp_dir(), "paramsxml");

		$this->oConnexionResource = $this->createMock(LDAPMockingRessource::class);
		$this->oResult = $this->createMock(LDAPMockingRessource::class);
		$this->oLDAPSearchService = $this->createMock(\LDAPSearchService::class);
	}

	public function tearDown(): void
	{
		parent::tearDown();

		if (is_file($this->sTempConfigFile)){
			unlink($this->sTempConfigFile);
		}
	}

	public static function ConnectProvider()
    {
		return [
			'connect via uri' => [ true, true, true, 'connect-via-uri.xml', 'ldap://myldap.fr', '389'],
			'connect with port and host' => [ true, true, true, 'connect-with-port-host.xml', 'myldap2.fr', '666'],
			'connect with host' => [ true, true, true, 'connect-with-host.xml', 'myldap2.fr', '389'],
		];
	}

    #[DataProvider('ConnectProvider')]
	public function testPreviousCollectFilesAreRemovedDuringPrepare($bSuccessBehaviourConfiguredInMock, $bLdapBindOk=true, $bDefineGetEntriesBehaviour=true, $sFileName='connect-via-uri.xml', $sUri='ldap://myldap.fr', $sPort='389'){
		global $argv;
		$argv[]="--config_file=".$this->sTempConfigFile;
		$this->assertTrue(copy(__DIR__."/resources/$sFileName", $this->sTempConfigFile));

		$this->oLDAPSearchService->expects($this->once())
			->method('Search')
			//->with($sUri, $sPort)
			->willReturn(false);

		$this->iTopPersonLDAPCollector = new \iTopPersonLDAPCollector();
		$this->iTopPersonLDAPCollector->SetLDAPSearchService($this->oLDAPSearchService);

		$sGlobPattern = Utils::GetDataFilePath(\iTopPersonLDAPCollector::class.'-*.csv');
		$sSprintfPattern = str_replace('*', '%s', $sGlobPattern);
		$aFilesToRemove=[];
		for($i=0;$i<5;$i++){
			$sCsvFilePath = sprintf($sSprintfPattern, $i);
			touch($sCsvFilePath);
			$aFilesToRemove[]=$sCsvFilePath;
		}

		foreach ($aFilesToRemove as $sFile){
			$this->assertTrue(is_file($sFile), "CSV file exist before prepare as if previous collect created it");
		}
		$this->iTopPersonLDAPCollector->Prepare();
		foreach ($aFilesToRemove as $sFile){
			$this->assertFalse(is_file($sFile), "CSV file should have been removed");
		}
	}
}
