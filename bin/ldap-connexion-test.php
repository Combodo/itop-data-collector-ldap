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
		echo json_encode(['code' => -1, 'msg' => $sErrorMsg], JSON_PRETTY_PRINT);
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
	echo "\n";
	echo "output example: " . json_encode(['code' => 0, 'msg' => "Success"], JSON_PRETTY_PRINT);
	exit(1);
}

Utils::$iConsoleLogLevel = Utils::ReadParameter('console_log_level', LOG_EMERG); // avoid logs to have json output

$oTestCollector = new LDAPCollector();
$aLdapErrorInfo = $oTestCollector->ConnectAndGetErrorInfo();
$iExitCode = $aLdapErrorInfo['ldap_errno'] ?? 1;

$aOutput = [
	'code' => $iExitCode,
	'msg' => $aLdapErrorInfo['ldap_error'] ?? ""
];
echo json_encode($aOutput, JSON_PRETTY_PRINT);
exit($iExitCode);
