<?php

namespace UnitTestFiles\Test;

use LdapMockingRessource;
use LDAPCollector;
use PHPUnit\Framework\TestCase;
use Utils;

if (! defined('APPROOT')){
	define('APPROOT', dirname(__FILE__, 3). '/'); // correct way
}

require_once (__DIR__.'/LdapMockingRessource.php');
require_once (APPROOT.'core/parameters.class.inc.php');
require_once (APPROOT.'core/utils.class.inc.php');
require_once (APPROOT.'core/collector.class.inc.php');
require_once (APPROOT.'collectors/LDAPCollector.class.inc.php');
require_once (APPROOT.'collectors/iTopUserLDAPCollector.class.inc.php');
require_once (__DIR__.'/AbstractLDAPTest.php');

/**
 * @runClassInSeparateProcess
 */
class iTopLDAPUserCollectorTest extends AbstractLDAPTest
{
	private $sTempConfigFile;
	private $oConnexionResource;
	private $oResult;
	private $iTopUserLDAPCollector;
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
	public function testPreviousCollectFilesAreRemovedDuringPrepare($bSuccessBehaviourConfiguredInMock, $bLdapBindOk=true, $bDefineGetEntriesBehaviour=true, $sFileName='connect-via-uri.xml', $sUri='ldap://myldap.fr', $sPort='389'){
		global $argv;
		$argv[]="--config_file=".$this->sTempConfigFile;
		$this->assertTrue(copy(__DIR__."/resources/$sFileName", $this->sTempConfigFile));

		$this->iTopUserLDAPCollector = new \iTopUserLDAPCollector();
		$this->iTopUserLDAPCollector->SetLDAPService($this->oLDAPService);

		$sGlobPattern = Utils::GetDataFilePath(\iTopUserLDAPCollector::class.'-*.csv');
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
		$this->iTopUserLDAPCollector->Prepare();
		foreach ($aFilesToRemove as $sFile){
			$this->assertFalse(is_file($sFile), "CSV file should have been removed");
		}
	}
}