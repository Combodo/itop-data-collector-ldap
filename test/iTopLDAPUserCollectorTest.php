<?php

namespace UnitTestFiles\Test;

use LdapMockingRessource;
use LDAPSearchService;
use PHPUnit\Framework\TestCase;
use Utils;

if (! defined('APPROOT')){
	define('APPROOT', dirname(__FILE__, 3). '/'); // correct way
}

require_once (__DIR__.'/AbstractLDAPTest.php');
require_once (APPROOT.'collectors/iTopUserLDAPCollector.class.inc.php');

/**
 * @runClassInSeparateProcess
 */
class iTopLDAPUserCollectorTest extends AbstractLDAPTest
{
	private $sTempConfigFile;
	private $iTopUserLDAPCollector;
	private $oLDAPSearchService;

	public function setUp(): void
	{
		parent::setUp();
		$this->sTempConfigFile = tempnam(sys_get_temp_dir(), "paramsxml");
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

		$this->iTopUserLDAPCollector = new \iTopUserLDAPCollector();
		$this->iTopUserLDAPCollector->SetLDAPSearchService($this->oLDAPSearchService);

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


	public function testPrepareAndFetch(){
		global $argv;
		$argv[]="--config_file=".$this->sTempConfigFile;
		$this->assertTrue(copy(__DIR__."/resources/params.ldapfetch.xml", $this->sTempConfigFile));

		$aSearchResult = json_decode(file_get_contents(__DIR__.'/resources/fetch-user-test.json'), true);
		$aExpectedRes = [
			'mail',
		];
		$this->oLDAPSearchService->expects($this->once())
			->method('Search')
			->with('DC=company,DC=com','(&(objectClass=person)(mail=*))', $aExpectedRes)
			->willReturn($aSearchResult['users']);

		$this->iTopUserLDAPCollector = new \iTopUserLDAPCollector();
		$this->iTopUserLDAPCollector->SetLDAPSearchService($this->oLDAPSearchService);

		$this->iTopUserLDAPCollector->Prepare();

		$aCollectRes = [];
		while ($aObjFields = $this->iTopUserLDAPCollector->Fetch()){
			$aCollectRes[]=$aObjFields;
		}

		$aExpectedFirstObject = [
			'primary_key' => 'jet.li4@combodo.com',
		    'status' => '',
		    'language' => 'EN US',
		    'login' => 'jet.li4@combodo.com',
		    'contactid' => 'jet.li4@combodo.com',
		    'profile_list' => 'profileid->name:Portal user'
		];
		$this->assertEquals(5, sizeof($aCollectRes));
		$this->assertEquals($aExpectedFirstObject, $aCollectRes[0]);
	}
}
