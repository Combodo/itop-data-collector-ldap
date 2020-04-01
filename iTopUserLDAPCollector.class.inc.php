<?php

class iTopUserLDAPCollector extends LDAPCollector
{

    protected $idx;
    protected $sLDAPDN;
    protected $sLDAPFilter;
    
    protected $sDefaultOrganization;
    protected $sDefaultProfile;
    protected $sDefaultLanguage;
    protected $sSynchronizeProfiles;
    protected $sITopGroupPattern;
    protected $sUserId;
    protected $sUserContactId;

    protected $aLogins;
    
    public function __construct()
    {
        parent::__construct();
        $this->sLDAPDN = Utils::GetConfigurationValue('ldapdn', 'DC=company,DC=com');
        $this->sLDAPFilter = Utils::GetConfigurationValue('ldapuserfilter', '(&(objectClass=user)(objectCategory=person))');
        $this->sSynchronizeProfiles = Utils::GetConfigurationValue('synchronize_profiles', 'no');
        $this->sITopGroupPattern = Utils::GetConfigurationValue('itop_group_pattern', '/^CN=itop-(.*),OU=.*/');
        $this->aUserFields = Utils::GetConfigurationValue('user_fields', array('primary_key' => 'samaccountname'));
        $this->aUserDefaults = Utils::GetConfigurationValue('user_defaults', array());
               
        $this->aLogins = array();
        $this->idx = 0;
        
        // Safety check
        if (!array_key_exists('primary_key', $this->aUserFields))
        {
            Utils::Log(LOG_ERROR, "LDAPUsers: You MUST specify a mapping for the field:'primary_key'");
        }
        if (!array_key_exists('login', $this->aUserFields))
        {
            Utils::Log(LOG_ERROR, "LDAPUsers: You MUST specify a mapping for the field:'login'");
        }
        
        // For debugging dump the mapping and default values
        $sMapping = '';
        foreach($this->aUserFields as $sAttCode => $sField)
        {
            if (array_key_exists($sAttCode, $this->aUserDefaults))
            {
                $sDefaultValue = ", default value: '{$this->aUserDefaults[$sAttCode]}'";
            }
            else
            {
                $sDefaultValue = '';
            }
            $sMapping .= "   iTop '$sAttCode' is filled from LDAP '$sField' $sDefaultValue\n";
        }
        if (($this->sSynchronizeProfiles !== 'no'))
        {
            $sMapping .= "   iTop 'profile_list' is filled from LDAP 'memberof' using the regular expression {$this->sITopGroupPattern} to extract the name of the iTop profile\n";
        }
        
        $bDefaultProfilesListProvided = false;
        foreach($this->aUserDefaults as $sAttCode => $sDefaultValue)
        {
            if ($sAttCode == 'profile') continue; // profile is not a real attribute code
            if ($sAttCode == 'profile_list')
            {
                $bDefaultProfilesListProvided = true;
            }
            
            if (!array_key_exists($sAttCode, $this->aUserFields))
            {
                $sMapping .= "   iTop '$sAttCode' is filled with the constant value '$sDefaultValue'\n";
            }
        }
        if ((!$bDefaultProfilesListProvided) && ($this->sSynchronizeProfiles == 'no'))
        {
            $sMapping .= "   iTop 'profile_list' is filled with the constant value 'profileid->name:".$this->aUserDefaults['profile']."'\n";
        }
        Utils::Log(LOG_DEBUG, "LDAPUsers: Mapping of the fields:\n$sMapping");
    }
    
    public function AttributeIsOptional($sAttCode)
    {
        if ($sAttCode == 'status') return true;
        if ($sAttCode == 'reset_pwd_token') return true; // depends on the type of User (UserLDAP vs UserExternal)
        
        return parent::AttributeIsOptional($sAttCode);
    }

    protected function GetData()
    {
        $aList = $this->Search($this->sLDAPDN, $this->sLDAPFilter);
        
        if ($aList !== false)
        {
            $iNumberOfUsers = count($aList) - 1;
            Utils::Log(LOG_INFO,"(Users) Number of entries found on LDAP: ".$iNumberOfUsers);
        }
        return $aList;
    }

    public function Prepare()
    {
        if (! $aData = $this->GetData()) return false;
        
        foreach ($aData as $idx => $aPerson)
        {
            if (isset($aPerson[$this->aUserFields['primary_key']][0]) && $aPerson[$this->aUserFields['primary_key']][0] != "")
            {
                $aValues = array();
                
                // Primary key must be the first column
                $aValues['primary_key'] = $aPerson[$this->aUserFields['primary_key']][0];
                
                // First set the default values (as well as the constant values for fields which are not collected)
                foreach($this->aUserDefaults as $sFieldCode => $sValue)
                {
                    if ($sFieldCode == 'profile') continue; // Not an actual field code, see below for filling the list of profiles
                    
                    $aValues[$sFieldCode] = $sValue;
                }
 
                // Then read the actual values (if any)
                foreach($this->aUserFields as $sFieldCode => $sLDAPAttribute)
                {
                    if ($sFieldCode == 'primary_key') continue; // Aalready processed, must be the first column
                    
                    $sDefaultValue = isset($this->aUserDefaults[$sFieldCode]) ? $this->aUserDefaults[$sFieldCode] : '';
                    $sFieldValue = isset($aPerson[$sLDAPAttribute][0]) ? $aPerson[$sLDAPAttribute][0] : $sDefaultValue;
                    
                    $aValues[$sFieldCode] = $sFieldValue;
                }
                
                // Collecting list of "member of group" starting with the given pattern
                $sProfileList = '';
                if ($this->sSynchronizeProfiles != 'no')
                {
                    $sPattern = $this->sITopGroupPattern;
                    
                    if (isset($aPerson['memberof']) && ($aPerson['memberof']['count'] != 0))
                    {
                        foreach ($aPerson['memberof'] as $sMember)
                        {
                            if (preg_match($sPattern, $sMember, $aProfile))
                            {
                                if ($sProfileList == '')
                                {
                                    $sProfileList .= 'profileid->name:'.$aProfile[1];
                                }
                                else
                                {
                                    $sProfileList .= '|profileid->name:'.$aProfile[1];
                                }
                            }
                        }
                        $aValues['profile_list'] = $sProfileList;
                    }
                }
                
                // Make sure that the list of profiles is never empty since it is mandatory for a user to have at least one profile
                if ((!isset($aValues['profile_list'])) || ($aValues['profile_list'] == ''))
                {
                    $aValues['profile_list'] = "profileid->name:".$this->aUserDefaults['profile'];
                }
                
                $this->aLogins[] = $aValues;
            }
            else
            {
                Utils::Log(LOG_WARNING,"Skipping row #{$idx} because of lack of primary key. Is {$this->aUserFields['primary_key']} the right field to use as a primary key?");
            }
        }
        return true;
    }

    public function Fetch()
    {
        if ($this->idx < count($this->aLogins))
        {
            $aLogin = $this->aLogins[$this->idx];
            $this->idx++;
            
            return $aLogin;
        }
        return false;
    }
}
