<?php
/**
 * Command line script to test the connection to LDAP and
 * dump some data from LDAP/AD in order to ease the definition
 * of the mapping.
 */

define('APPROOT', dirname(dirname(dirname(__FILE__))) . '/');

require_once (APPROOT.'core/parameters.class.inc.php');
require_once (APPROOT.'core/utils.class.inc.php');
require_once (APPROOT.'core/collector.class.inc.php');
require_once (APPROOT.'collectors/LDAPCollector.class.inc.php');

Utils::$iConsoleLogLevel = 0; // Force debug mode

$sLdapdn = Utils::GetConfigurationValue('ldapdn', 'DC=company,DC=com');
$sLdapfilter = Utils::GetConfigurationValue('ldappersonfilter', '(&(objectClass=user)(objectCategory=person))');
$aPersonFields = Utils::GetConfigurationValue('person_fields', array('primary_key' => 'samaccountname'));
$iNbMaxRecords = (int)Utils::ReadParameter('max-records', 10);
$sAttributes = Utils::ReadParameter('attributes', null);
$sURI = Utils::ReadParameter('ldapuri', null);
$sLogin = Utils::ReadParameter('ldaplogin', null);
$sPassword = Utils::ReadParameter('ldappassword', null);
$sLdapdn = Utils::ReadParameter('ldapdn', 'DC=company,DC=com');


if ($sAttributes === null)
{
    $aAttributesToQuery = array_values($aPersonFields);
    $aAttributesToQuery[] = 'memberof';
    //echo "List of the attributes to retrieve (taken from the mapping):\n".implode(',', $aAttributesToQuery)."\n";
    //echo "Use --attributes=x,y,z to retrieve x, y and z instead. Use --attributes=* to retrieve all fields.\n";
}
else
{
    $aAttributesToQuery = explode(',', $sAttributes);
    //echo "List of the attributes to retrieve (taken from the command line):\n".implode(',', $aAttributesToQuery)."\n";
}

// Uncomment the line below if you really want to debug what's happening at the ldap level...
//ldap_set_option(NULL, LDAP_OPT_DEBUG_LEVEL, 7);

$oTestCollector = new LDAPCollector(null, $sURI, null, $sLogin, $sPassword);
$aList = $oTestCollector->Search($sLdapdn, $sLdapfilter, $aAttributesToQuery);

if ($aList === false) {
    die(json_encode([
        'message' => "Erreur lors de la connexion au LDAP",
        'code' => 500
    ]));
} // Something went wrong, exit with error !!

$iNumberUser = count($aList) - 1;

$responseArray =  [];
$ldapPersons = [];


//echo "The LDAP query '" . $sLdapfilter . "' returned " . $iNumberUser . " elements.\n";
if ($iNumberUser > $iNbMaxRecords)
{
    //echo "Displaying only $iNbMaxRecords elements (use --max-records=xx to change this limit).\n";
}
//echo "------------------------------------------------\n";


$idx = 0;
foreach ($aList as $aLdapUser)
{
    $person = [];

    if (isset($aLdapUser[$aPersonFields['primary_key']][0]) && $aLdapUser[$aPersonFields['primary_key']][0] != "")
    {
        if ($idx == 0)
        {
            //echo "LDAP Structure:\n";
            //echo "Info: when a field is empty on a given record, it is not returned by LDAP.\n";
            //echo "------------------------------------------------\n";
        }
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
                //echo sprintf("%-{$iMaxKeyLength}s %s %s\n", $sLabel, $sSeparator, $sValue);
                $sLabel = '';
                $sSeparator = ' ';
            }
            $person[$sKey] = $aValues[0];

        }
        //echo "------------------------------------------------\n";
        $ldapPersons[] = $person;
        $idx++;
        if ($idx == $iNbMaxRecords) break;
    }

}
if ($idx ==0)
{
    //echo "Found no record containing a non-empty value in {$aPersonFields['primary_key']}\nCheck the LDAP query and the primary_key mapping.\n";
    //echo "The returned data is:\n";
    print_r($aList);
}

$responseArray["numberOfUsers"] = $iNumberUser;
$responseArray["persons"] = $ldapPersons;
$responseArray['code'] = 200;
$responseArray['message'] = 'Connexion effectuée avec succès; vous pouvez désormais synchroniser vos données dans les onglets ci-dessus.';

echo json_encode($responseArray);
