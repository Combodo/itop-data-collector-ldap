<?php

// define('APPROOT', dirname(__FILE__, 3) . '/'); // correct way


 class LDAPTest
{
     /**
      * @throws Exception
      */
     public static function LDAPTestConnection(string $sURI = null, string $sLogin = null, string $sPassword = null, string $sLn, int $iNbMaxRecords){

        define("APPROOT", '/var/www/html/itop-community/ldap/ldap-data-collector/'); // for developping with symlinks

        require_once (APPROOT.'core/parameters.class.inc.php');
        require_once (APPROOT.'core/utils.class.inc.php');
        require_once (APPROOT.'core/collector.class.inc.php');
        require_once (APPROOT.'collectors/LDAPCollector.class.inc.php');

         Utils::$iConsoleLogLevel = 0;

         $sLdapfilter = Utils::GetConfigurationValue('ldappersonfilter', '(&(objectClass=user)(objectCategory=person))');
        $aPersonFields = Utils::GetConfigurationValue('person_fields', array('primary_key' => 'samaccountname'));
        $aAttributesToQuery = array_values($aPersonFields);
        $aAttributesToQuery[] = 'memberof';
        $oTestCollector = new LDAPCollector(null, $sURI, null, $sLogin, $sPassword);
        $aList = $oTestCollector->Search($sLn, $sLdapfilter, $aAttributesToQuery);

        if ($aList === false) {
            die(json_encode([
                'message' => "Erreur lors de la connexion au LDAP",
                'code' => 500
            ]));
        } // Something went wrong, exit with error !!

        $iNumberUser = count($aList) - 1;

        $responseArray =  [];
        $ldapPersons = [];


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
    }
}