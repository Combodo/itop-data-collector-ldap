<?php
/**
 * Command line script to test the connection to LDAP
 */

define('APPROOT', dirname(__FILE__, 3). '/'); // correct way

require_once (APPROOT.'core/parameters.class.inc.php');
require_once (APPROOT.'core/utils.class.inc.php');
require_once (APPROOT.'core/collector.class.inc.php');
require_once (APPROOT.'collectors/LDAPCollector.class.inc.php');

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

	echo "\n\nsuccess output example:\n";
	$sExample = file_get_contents(sprintf("%s%sresources%sldap_users.json",__DIR__, DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR));
	echo "success output example:\n$sExample\n";

	$sExample = <<<JSON
{
    "code": 34,
    "msg": "Invalid DN syntax"
}
JSON;
	echo "error output example:\n$sExample\n";
	exit(1);
}

Utils::$iConsoleLogLevel = Utils::ReadParameter('console_log_level', LOG_EMERG); // avoid logs to have json output

$sLdapdn = Utils::GetConfigurationValue('ldapdn', 'DC=company,DC=com');
$sLdapfilter = Utils::GetConfigurationValue('ldapuserfilter', '(&amp;(objectClass=person)(mail=*))');
$aFields = Utils::GetConfigurationValue('user_fields', ['primary_key' => 'samaccountname']);
//var_dump($aFields);
$oTestCollector = new LDAPCollector();
$iSizeLimit = Utils::GetConfigurationValue('user_size_limit', 1);
$oTestCollector->SetSizeLimit($iSizeLimit);

$aLdapResults = $oTestCollector->Search($sLdapdn, $sLdapfilter, array_values($aFields));
$iExitCode = $oTestCollector->getLastLdapErrorCode();
if (0 === $iExitCode||4 === $iExitCode){
	$aOutput = [
		'code' => $iExitCode,
		'users' => $aLdapResults,
		'msg' => $oTestCollector->GetLastLdapErrorMessage(),
	];
} else {
	$aOutput = [
		'code' => $iExitCode,
		'msg' => $oTestCollector->GetLastLdapErrorMessage(),
	];
}
echo json_encode($aOutput);
exit($iExitCode);
