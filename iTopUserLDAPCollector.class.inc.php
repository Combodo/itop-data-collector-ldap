<?php

class iTopUserLDAPCollector extends Collector
{
   protected $idx;
   protected $ldaphost;
   protected $ldapport;
   protected $ldapdn;
   protected $ldapfilter;
   protected $default_organisation;
   protected $default_profile;
   protected $default_language;
   protected $login_tab;
   protected $ldaplogin;
   protected $ldappassword;
   protected $synchronize_profils;
   protected $itop_group_pattern;

   public function __construct()
   {
      parent::__construct();
      $this->ldaphost             = Utils::GetConfigurationValue('ldaphost', 'localhost');
      $this->ldapport             = Utils::GetConfigurationValue('ldapport', 389);
      $this->ldapdn               = Utils::GetConfigurationValue('ldapdn', 'DC=company,DC=com');
      $this->ldapfilter           = Utils::GetConfigurationValue('ldapuserfilter', '(&(objectClass=user)(objectCategory=person))');
      $this->ldaplogin            = Utils::GetConfigurationValue('ldaplogin','CN=ITOP-LDAP,DC=company,DC=com');
      $this->ldappassword         = Utils::GetConfigurationValue('ldappassword','password');
      $this->synchronize_profils  = Utils::GetConfigurationValue('synchronize_profils','no');
      $this->itop_group_pattern   = Utils::GetConfigurationValue('itop_group_pattern','/^CN=itop-(.*),OU=.*/');
      $this->user_id              = Utils::GetConfigurationValue('user_id','samaccountname');
      $this->user_contactid       = Utils::GetConfigurationValue('user_contactid','email');
      $this->default_profile      = Utils::GetConfigurationValue('user_default_profile', 'Portal user');
      $this->default_language     = Utils::GetConfigurationValue('user_default_language', 'FR FR');
      $this->login_tab            = array();
      $this->idx                  = 0;
   }


   protected function getDatas()
   {
      $rLdapconn = ldap_connect($this->ldaphost, $this->ldapport);
      if (!$rLdapconn) {
            return false;
      }
      ldap_set_option($rLdapconn, LDAP_OPT_REFERRALS, 0);
      ldap_set_option($rLdapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
      $rBind     = ldap_bind($rLdapconn,$this->ldaplogin,$this->ldappassword);
      $rSearch   = ldap_search($rLdapconn, $this->ldapdn, $this->ldapfilter);
      $rList     = ldap_get_entries($rLdapconn, $rSearch);
      ldap_close($rLdapconn);

      $iNumberUser = count($rList) -1;
      echo "(USER)Number of entries found on LDAP -> ".$iNumberUser." \n";
      return $rList;
   }

   public function prepare()
   {
      if (!$aDatas = $this->getDatas()) return false;

      foreach($aDatas as $aPerson) {
         if (isset($aPerson[$this->user_id][0] ) && $aPerson[$this->user_id][0] != "" ) {
            // Collecting list of member of group starting with itop-
               $sPattern = $this->itop_group_pattern;
               $sProfile_list = '';
               if ( $this->synchronize_profils != 'no' ) {
                if (isset($aPerson['memberof']) && ($aPerson['memberof']['count'] != 0))
                {
                        foreach( $aPerson['memberof'] as $sMember )
                        {
                                 if (preg_match($sPattern, $sMember, $aProfile))
                                 {
                                         if ($sProfile_list == '')
                                         {
                                                 $sProfile_list.='profileid->name:'.$aProfile[1];
                                         }
                                         else
                                         {
                                                 $sProfile_list.='|profileid->name:'.$aProfile[1];
                                         }
                                 }
                        }
                }
               }
               if ( $sProfile_list == '')
               {
                        $sProfile_list="profileid->name:".$this->default_profile;
               }
               $this->login_tab[] = array(
                 'primary_key'     => isset($aPerson[$this->user_id][0]) ? $aPerson[$this->user_id][0] : '',
                 'contactid'       => isset($aPerson[$this->user_contactid][0]) ? $aPerson[$this->user_contactid][0] : '',
                 'login'           => isset($aPerson[$this->user_id][0]) ? $aPerson[$this->user_id][0] : '',
                 'language'        => isset($this->default_language) ? $this->default_language : '',
                 'profile_list'    => $sProfile_list,
              );
         }
      }
      return true;
   }


   public function fetch()
   {
      if ($this->idx < count($this->login_tab)) {
         $datas = $this->login_tab[$this->idx];
         $this->idx++;

         return $datas;
      }
      return false;
   }
}
