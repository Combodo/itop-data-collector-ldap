<?php

namespace UnitTestFiles\Test;

use PHPUnit\Framework\TestCase;

function SearchFile(string $sDir, string $sModuleFileToFind) : ?string {
	$sPath = $sDir.DIRECTORY_SEPARATOR.$sModuleFileToFind;
	if (is_file($sPath)){
		return $sPath;
	}

	$aFiles = scandir($sDir); // Warning glob('.*') does not seem to return the broken symbolic links, thus leaving a non-empty directory
	if ($aFiles !== false) {
		foreach ($aFiles as $sFile) {
			if (($sFile != '.') && ($sFile != '..')) {
				$sSubDir = $sDir.'/'.$sFile;
				if (is_dir($sSubDir)) {
					$sPath = SearchFile($sSubDir, $sModuleFileToFind);
					if (! is_null($sPath)){
						return $sPath;
					}
				}
			}
		}
	}

	return null;
}

if (! defined('APPROOT')){
	$sLdapModuleFileName = 'module.itop-data-collector-ldap.php';
	$sItopApprootFileName = 'approot.inc.php';

	$sDir = dirname(__DIR__);
	$siTopDir=null;
	while(is_readable($sDir) && $sDir != '/') {
		$sPath = $sDir.DIRECTORY_SEPARATOR.$sLdapModuleFileName;
		echo "check file $sPath" . PHP_EOL;
		if (file_exists($sPath)) {
			define('APPROOT', dirname($sPath, 2) . DIRECTORY_SEPARATOR);
			echo "APPROOT: " . APPROOT . '\n';
			break;
		} else if (file_exists($sDir.DIRECTORY_SEPARATOR.$sItopApprootFileName)) {
			//iTop root dir
			$siTopDir = $sDir;
			break;
		}

		$sDir = dirname($sDir);
	}

	if (! defined('APPROOT') && ! is_null($siTopDir)) {
		$aFolders = [
			"collectors",
			"datamodels" . DIRECTORY_SEPARATOR . "2.x",
			"extensions",
			"data" . DIRECTORY_SEPARATOR . "production-modules",
		];

		foreach ($aFolders as $sDir) {
			$sSubDir = $siTopDir.DIRECTORY_SEPARATOR.$sDir;
			if (! is_dir($sSubDir)){
				continue;
			}

			echo "check file (iTop dir) in $sSubDir" . PHP_EOL;

			$sPath = SearchFile($sSubDir, $sLdapModuleFileName);
			if (! is_null($sPath)) {
				define('APPROOT', dirname($sPath, 2) . DIRECTORY_SEPARATOR);
				echo "APPROOT: " . APPROOT . '\n';
				break;
			}
	}

		if (! defined('APPROOT')) {
			throw new \Exception("cannot find $sLdapModuleFileName in iTop dir $sDir");
		}
	}
}

abstract class AbstractLDAPTestCase extends TestCase
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
