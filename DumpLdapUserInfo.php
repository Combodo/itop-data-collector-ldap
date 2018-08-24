<?php

define('APPROOT', dirname(dirname(__FILE__)).'/');

require_once(APPROOT.'core/parameters.class.inc.php');
require_once(APPROOT.'core/utils.class.inc.php');

$sLdaphost                   = Utils::GetConfigurationValue('ldaphost', 'localhost');
$sLdapport                   = Utils::GetConfigurationValue('ldapport', 389);
$sLdapdn                     = Utils::GetConfigurationValue('ldapdn', 'DC=company,DC=com');
$sLdapfilter                 = Utils::GetConfigurationValue('ldappersonfilter', '(&(objectClass=user)(objectCategory=person))');
$sLdaplogin                  = Utils::GetConfigurationValue('ldaplogin','CN=ITOP-LDAP,DC=company,DC=com');
$sLdappassword               = Utils::GetConfigurationValue('ldappassword','password');

$rLdapconn = ldap_connect($sLdaphost, $sLdapport);
if (!$rLdapconn) {
      return false;
}
ldap_set_option($rLdapconn, LDAP_OPT_REFERRALS, 0);
ldap_set_option($rLdapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
$rBind     = ldap_bind($rLdapconn, $sLdaplogin, $sLdappassword);
$rSearch   = ldap_search($rLdapconn, $sLdapdn, $sLdapfilter);
$aList     = ldap_get_entries($rLdapconn, $rSearch);
ldap_close($rLdapconn);
$iNumberUser = count($aList) -1;
echo "(PERSON) Number of entries found on LDAP -> ".$iNumberUser." \n";
echo "------------------------------------------------\n";

echo "LDAP Structure:\n";
echo "---------------\n";

foreach($aList as $aLdapUser) {
  if (isset($aLdapUser['samaccountname'] ) && $aLdapUser['samaccountname'] != "" ) {
       print_r($aLdapUser);
        exit;
   }
}

