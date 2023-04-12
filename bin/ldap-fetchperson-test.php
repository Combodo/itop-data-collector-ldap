<?php
/**
 * Command line script to test the connection to LDAP
 */

define('APPROOT', dirname(__FILE__, 3). '/'); // correct way

require_once (APPROOT.'collectors/LDAPCliSearchService.class.inc.php');

$aOptionalParams = [
	'help' => 'boolean',
	'config_file' => 'string',
	'console_log_level' => 'int',
];

$bHelp = (Utils::ReadBooleanParameter('help', false) == true);
$aUnknownParameters = Utils::CheckParameters($aOptionalParams);
if ($bHelp || count($aUnknownParameters) > 0) {
	if (!$bHelp) {
		$sErrorMsg = "Unknown parameter(s): ".implode(' ', $aUnknownParameters);
		echo json_encode(['code' => -1, 'msg' => $sErrorMsg]);
		exit(1);
	}

	echo "Usage:\n";
	echo 'php '.basename($argv[0]) . ' ';
	foreach ($aOptionalParams as $sParam => $sType) {
		switch ($sType) {
			case 'boolean':
				echo '[--'.$sParam.']';
				break;

			default:
				echo '[--'.$sParam.'=xxx]';
				break;
		}
	}

	$sExample = file_get_contents(sprintf("%s%sresources%sldap_persons.json",__DIR__, DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR));
	echo "Success output example:\n$sExample\n";

	$sExample = <<<JSON
{
    "code": 34,
    "msg": "Invalid DN syntax"
}
JSON;
	echo "Error output example:\n$sExample\n";
	exit(1);
}

Utils::$iConsoleLogLevel = Utils::ReadParameter('console_log_level', LOG_EMERG); // avoid logs to have json output
$sLdapfilter = Utils::GetConfigurationValue('ldappersonfilter', '(&(objectClass=user)(objectCategory=person))');
$iSizeLimit = Utils::GetConfigurationValue('person_size_limit', 5);

$oLDAPCliSearchSearch = new LDAPCliSearchService();

$sJsonOutput = $oLDAPCliSearchSearch->Search($sLdapfilter, $iSizeLimit, ['*']);
echo $sJsonOutput;
exit($oLDAPCliSearchSearch->GetLastExitCode());
