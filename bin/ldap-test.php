<?php
/**
 * Command line script to test the connection to LDAP and
 * dump some data from LDAP/AD in order to ease the definition
 * of the mapping.
 */

define('APPROOT', dirname(dirname(dirname(__FILE__))) . '/');

require_once (APPROOT . 'core/parameters.class.inc.php');
require_once (APPROOT . 'core/utils.class.inc.php');

$sLdaphost = Utils::GetConfigurationValue('ldaphost', 'localhost');
$sLdapport = Utils::GetConfigurationValue('ldapport', 389);
$sLdapdn = Utils::GetConfigurationValue('ldapdn', 'DC=company,DC=com');
$sLdapfilter = Utils::GetConfigurationValue('ldappersonfilter', '(&(objectClass=user)(objectCategory=person))');
$sLdaplogin = Utils::GetConfigurationValue('ldaplogin', 'CN=ITOP-LDAP,DC=company,DC=com');
$sLdappassword = Utils::GetConfigurationValue('ldappassword', 'password');
$aPersonFields = Utils::GetConfigurationValue('person_fields', array('primary_key' => 'samaccountname'));

$rLdapconn = @ldap_connect($sLdaphost, $sLdapport);
if ($rLdapconn === false)
{
    echo "ldap_connect failed check the syntax of your parameters !\n";
    exit;
}
ldap_set_option($rLdapconn, LDAP_OPT_REFERRALS, 0);
ldap_set_option($rLdapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
$rBind = @ldap_bind($rLdapconn, $sLdaplogin, $sLdappassword);
if ($rBind === false)
{
    echo "ldap_bind failed: ".ldap_error($rLdapconn)." at $sLdaphost:$sLdapport as '$sLdaplogin' with password '$sLdappassword'\n";
    exit;
}
$rSearch = ldap_search($rLdapconn, $sLdapdn, $sLdapfilter);
$aList = ldap_get_entries($rLdapconn, $rSearch);
ldap_close($rLdapconn);
$iNumberUser = count($aList) - 1;

echo "The LDAP query '" . $sLdapfilter . "' returned " . $iNumberUser . " elements.\n";
echo "------------------------------------------------\n";


foreach ($aList as $aLdapUser)
{
    if (isset($aLdapUser[$aPersonFields['primary_key']][0]) && $aLdapUser[$aPersonFields['primary_key']][0] != "")
    {
        echo "LDAP Structure:\n";
        echo "------------------------------------------------\n";
        $aSampleData = array();
        foreach ($aLdapUser as $sKey => $data)
        {
            if (is_array($data))
            {
                $iKeyLen = strlen($sKey);
                $sFirstField = $sKey . ': ';
                $sPlaceHolder = str_repeat(' ', $iKeyLen + 2);
                
                $iCount = $data['count'];
                $aValues = array();
                for ($i = 0; $i < $iCount; $i ++)
                {
                    $aValues[] = $data[$i];
                    $sFirstField = $sPlaceHolder;
                }
                $aSampleData[$sKey] = $aValues;
            }
        }
        $iMaxKeyLength = 0;
        foreach($aSampleData as $sKey => $void)
        {
            if (strlen($sKey) > $iMaxKeyLength) $iMaxKeyLength = strlen($sKey);
        }
        foreach($aSampleData as $sKey => $aValues)
        {
            $sLabel = $sKey;
            $sSeparator = ':';
            foreach($aValues as $sValue)
            {
                echo sprintf("%-{$iMaxKeyLength}s %s %s\n", $sLabel, $sSeparator, $sValue);
                $sLabel = '';
                $sSeparator = ' ';
            }
        }
        echo "------------------------------------------------\n";
        exit;
    }
}
echo "Found no record containing a non-empty value in {$aPersonFields['primary_key']}\nCheck the LDAP query and the primary_key mapping.\n";


