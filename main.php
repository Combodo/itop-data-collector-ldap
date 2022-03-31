<?php
require_once(APPROOT.'collectors/LDAPCollector.class.inc.php');
require_once(APPROOT.'collectors/iTopPersonLDAPCollector.class.inc.php');
require_once(APPROOT.'collectors/iTopUserLDAPCollector.class.inc.php');

Orchestrator::AddRequirement('1.0.0', 'ldap'); // LDAP support is required to run this collector

$iRank = 1;
Orchestrator::AddCollector($iRank++, iTopPersonLDAPCollector::class);

if (Utils::GetConfigurationValue('collect_person_only', 'yes') !== 'yes')
{
	Orchestrator::AddCollector($iRank++, iTopUserLDAPCollector::class);
}
