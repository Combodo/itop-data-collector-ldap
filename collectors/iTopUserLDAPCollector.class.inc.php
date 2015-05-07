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
      $ldapconn = ldap_connect($this->ldaphost, $this->ldapport);
      if (!$ldapconn) {
            return false;
      }
      ldap_set_option($ldapconn, LDAP_OPT_REFERRALS, 0);
      ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
      $bind     = ldap_bind($ldapconn,$this->ldaplogin,$this->ldappassword);
      $search   = ldap_search($ldapconn, $this->ldapdn, $this->ldapfilter);
      $list     = ldap_get_entries($ldapconn, $search);
      ldap_close($ldapconn);

      $iNumberUser = count($list) -1;
      echo "(USER)Number of entries found on LDAP -> ".$iNumberUser." \n";

      return $list;
   }

   public function prepare()
   {
      if (!$datas = $this->getDatas()) return false;

      foreach($datas as $person) {
         if (isset($person[$this->user_id][0] ) && $person[$this->user_id][0] != "" ) {
            // Collecting list of member of group starting with itop-
               $pattern = $this->itop_group_pattern;
               $profile_list = '';
               if ( $this->synchronize_profils != 'no' ) {
                if (isset($person['memberof']) && ($person['memberof']['count'] != 0))
                {
                        foreach(  $person['memberof'] as $sMember )
                        {
                                 if (preg_match($pattern, $sMember, $aProfile))
                                 {
                                         if ($profile_list == '')
                                         {
                                                 $profile_list.='profileid->name:'.$aProfile[1];
                                         }
                                         else
                                         {
                                                 $profile_list.='|profileid->name:'.$aProfile[1];
                                         }
                                 }
                        }
                }
               }
               if ( $profile_list == '')
               {
                        $profile_list="profileid->name:".$this->default_profile;
               }
               $this->login_tab[] = array(
               'primary_key'     => isset($person[$this->user_id][0]) ? utf8_encode($person[$this->user_id][0]) : '',
               'contactid'       => isset($person[$this->user_contactid][0]) ? utf8_encode($person[$this->user_contactid][0]) : '',
               'login'           => isset($person[$this->user_id][0]) ? utf8_encode($person[$this->user_id][0]) : '',
               'language'        => isset($this->default_language) ? utf8_encode($this->default_language) : '',
               'profile_list'    => utf8_encode($profile_list),
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
