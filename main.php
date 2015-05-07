<?php
require_once(APPROOT.'collectors/iTopPersonLDAPCollector.class.inc.php');
require_once(APPROOT.'collectors/iTopUserLDAPCollector.class.inc.php');

if (Utils::GetConfigurationValue('collect_person_only', 'yes') == 'yes')
{
	Orchestrator::AddCollector(1, 'iTopPersonLDAPCollector');
}
else
{
	$iRank = 1;
	Orchestrator::AddCollector($iRank++, 'iTopPersonLDAPCollector');
	Orchestrator::AddCollector($iRank++, 'iTopUserLDAPCollector');
}



