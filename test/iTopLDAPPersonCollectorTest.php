<?php

namespace UnitTestFiles\Test;

use LdapMockingRessource;
use LDAPSearchService;
use PHPUnit\Framework\TestCase;
use Utils;

if (! defined('APPROOT')){
	define('APPROOT', dirname(__FILE__, 3). '/'); // correct way
}

require_once (__DIR__.'/LdapMockingRessource.php');
require_once (__DIR__.'/AbstractLDAPTest.php');
require_once (APPROOT.'collectors/iTopPersonLDAPCollector.class.inc.php');

/**
 * @runClassInSeparateProcess
 */
class iTopLDAPPersonCollectorTest extends AbstractLDAPTest
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

		$this->oConnexionResource = $this->createMock(LdapMockingRessource::class);
		$this->oResult = $this->createMock(LdapMockingRessource::class);
		$this->oLDAPSearchService = $this->createMock(\LDAPSearchService::class);
	}

	public function tearDown(): void
	{
		parent::tearDown();

		if (is_file($this->sTempConfigFile)){
			unlink($this->sTempConfigFile);
		}
	}

	public function testPreviousCollectFilesAreRemovedDuringPrepare(){
		global $argv;
		$argv[]="--config_file=".$this->sTempConfigFile;
		$this->assertTrue(copy(__DIR__."/resources/connect-via-uri.xml", $this->sTempConfigFile));

		$this->oLDAPSearchService->expects($this->once())
			->method('Search')
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

	public function testPrepareAndFetch(){
		global $argv;
		$argv[]="--config_file=".$this->sTempConfigFile;
		$this->assertTrue(copy(__DIR__."/resources/params.ldapfetch.xml", $this->sTempConfigFile));

		$aSearchResult = json_decode(file_get_contents(__DIR__.'/resources/fetch-person-test.json'), true);
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
		$this->oLDAPSearchService->expects($this->once())
			->method('Search')
			->with('DC=company,DC=com','(&(objectClass=person)(mail=*))', $aExpectedRes)
			->willReturn($aSearchResult['persons']);

		$this->iTopPersonLDAPCollector = new \iTopPersonLDAPCollector();
		$this->iTopPersonLDAPCollector->SetLDAPSearchService($this->oLDAPSearchService);

		$this->iTopPersonLDAPCollector->Prepare();

		$aCollectRes = [];
		while ($aObjFields = $this->iTopPersonLDAPCollector->Fetch()){
			$aCollectRes[]=$aObjFields;
		}

		$aExpectedFirstObject = [
			'primary_key' => 'jli',
		    'org_id' => 'Demo',
		    'status' => 'active',
		    'name' => 'Li',
		    'first_name' => 'jet',
		    'email' => 'jet.li4@combodo.com',
		    'phone' => '',
		    'mobile_phone' => '',
		    'function' => '',
		    'employee_number' => '',
		    'notify' => '',
		    'location_id' => '',
		    'manager_id' => '',
		    'vip' => ''
		];
		$this->assertEquals(5, sizeof($aCollectRes));
		$this->assertEquals($aExpectedFirstObject, $aCollectRes[0]);
	}
}
