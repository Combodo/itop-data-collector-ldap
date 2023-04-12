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
require_once (APPROOT.'collectors/LDAPCliSearchService.class.inc.php');

/**
 * @runClassInSeparateProcess
 */
class LDAPCliSearchServiceTest extends AbstractLDAPTest
{
	private $LDAPSearchService;

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

	public function SearchOKProvider(){
		$sOkJsonOuput = <<<JSON
{"count":2,"code":%s,"titi":["XXX","YYY"],"msg":"GetLastLdapErrorMessageOutput"}
JSON;

		$sErrorJsonOuput = <<<JSON
{"code":8,"msg":"GetLastLdapErrorMessageOutput"}
JSON;

		return [
			'exit 0' => [ 'iExitCode' => 0, 'sExpectedJson' => sprintf($sOkJsonOuput, 0) ],
			'exit 4' => [ 'iExitCode' => 4, 'sExpectedJson' => sprintf($sOkJsonOuput, 4) ],
			'exit 8' => [ 'iExitCode' => 8, 'sExpectedJson' => $sErrorJsonOuput ],
		];
	}

	/**
	 * @dataProvider SearchOKProvider
	 */
	public function testSearch(int $iExitCode, string $sExpectedJson){
		global $argv;
		$argv[]="--config_file=".$this->sTempConfigFile;
		$this->assertTrue(copy(__DIR__."/resources/connect-via-uri.xml", $this->sTempConfigFile));

		$sLdapfilter = '(&amp;(objectClass=person)(mail=*))';
		$aAttributes = ['*'];

		$this->oLDAPSearchService->expects($this->once())
			->method('Search')
			->with('DC=company,DC=com', $sLdapfilter, $aAttributes)
			->willReturn(['count' => 2, 0 => 'XXX', 1 => 'YYY']);

		$this->oLDAPSearchService->expects($this->once())
			->method('GetLastLdapErrorCode')
			->willReturn($iExitCode);

		$this->oLDAPSearchService->expects($this->once())
			->method('GetLastLdapErrorMessage')
			->willReturn("GetLastLdapErrorMessageOutput");

		$oLDAPCliSearchService = new \LDAPCliSearchService('titi');
		$oLDAPCliSearchService->SetLDAPSearchService($this->oLDAPSearchService);


		$sOutput = $oLDAPCliSearchService->Search($sLdapfilter, 5, $aAttributes);

		$this->assertEquals($sExpectedJson, $sOutput);
		$this->assertEquals($iExitCode, $oLDAPCliSearchService->GetLastExitCode());
	}

	public function testFetchWithBinary(){
		global $argv;
		$argv[]="--config_file=".$this->sTempConfigFile;
		$this->assertTrue(copy(__DIR__."/resources/connect-via-uri.xml", $this->sTempConfigFile));

		$iExitCode = 0;
		$sExpectedJson = file_get_contents(__DIR__."/resources/expected_search_withbinary.json");

		//$aData loaded below
		require_once __DIR__."/resources/ldap_activedirectory_fetchwithbinary.php";
		$aData = GetBinaryData();
		//var_dump($aData);
		$sLdapfilter = '(&amp;(objectClass=person)(mail=*))';
		$aAttributes = ['*'];

		$this->oLDAPSearchService->expects($this->once())
			->method('Search')
			->with('DC=company,DC=com', $sLdapfilter, $aAttributes)
			->willReturn($aData);

		$this->oLDAPSearchService->expects($this->once())
			->method('GetLastLdapErrorCode')
			->willReturn($iExitCode);

		$this->oLDAPSearchService->expects($this->once())
			->method('GetLastLdapErrorMessage')
			->willReturn("GetLastLdapErrorMessageOutput");

		$oLDAPCliSearchService = new \LDAPCliSearchService('titi');
		$oLDAPCliSearchService->SetLDAPSearchService($this->oLDAPSearchService);

		$sOutput = $oLDAPCliSearchService->Search($sLdapfilter, 5, $aAttributes);

		$this->assertEquals($sExpectedJson, $sOutput);
		$this->assertEquals($iExitCode, $oLDAPCliSearchService->GetLastExitCode());
	}
}
