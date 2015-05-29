<?php

class iTopPersonLDAPCollector extends Collector
{
   protected $idx;
   protected $ldaphost;
   protected $ldapport;
   protected $ldapdn;
   protected $ldapfilter;
   protected $default_organisation;
   protected $person_tab;
   protected $ldaplogin;
   protected $ldappassword;
   protected $synchronize_organization;

   public function __construct()
   {
      parent::__construct();
      $this->ldaphost                   = Utils::GetConfigurationValue('ldaphost', 'localhost');
      $this->ldapport                   = Utils::GetConfigurationValue('ldapport', 389);
      $this->ldapdn                     = Utils::GetConfigurationValue('ldapdn', 'DC=company,DC=com');
      $this->ldapfilter                 = Utils::GetConfigurationValue('ldappersonfilter', '(&(objectClass=user)(objectCategory=person))');
      $this->ldaplogin                  = Utils::GetConfigurationValue('ldaplogin','CN=ITOP-LDAP,DC=company,DC=com');
      $this->ldappassword               = Utils::GetConfigurationValue('ldappassword','password');
      $this->synchronize_organization   = Utils::GetConfigurationValue('synchronize_organization','no');
      $this->default_organisation       = Utils::GetConfigurationValue('person_default_organisation_id', 'Demo');
      $this->default_status             = Utils::GetConfigurationValue('person_default_status','active');
      $this->person_id                  = Utils::GetConfigurationValue('person_id','samaccountname');
      $this->person_name                = Utils::GetConfigurationValue('person_name','sn');
      $this->person_first_name          = Utils::GetConfigurationValue('person_first_name','givenname');
      $this->person_email               = Utils::GetConfigurationValue('person_email','mail');
      $this->person_phone               = Utils::GetConfigurationValue('person_phone','telephonenumber');
      $this->person_mobile_phone        = Utils::GetConfigurationValue('person_mobile_phone','mobile');
      $this->person_function            = Utils::GetConfigurationValue('person_function','title');
      $this->person_employee_number     = Utils::GetConfigurationValue('person_employee_number','employeenumber');
      $this->person_ou                  = Utils::GetConfigurationValue('person_ou','ou');
      $this->person_tab                 = array();
      $this->idx                        = 0;

   }


   protected function getDatas()
   {
      $rLdapconn = ldap_connect($this->ldaphost, $this->ldapport);
      if (!$rLdapconn) {
            return false;
      }
      ldap_set_option($rLdapconn, LDAP_OPT_REFERRALS, 0);
      ldap_set_option($rLdapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
      $rBind     = ldap_bind($rLdapconn, $this->ldaplogin, $this->ldappassword);
      $rSearch   = ldap_search($rLdapconn, $this->ldapdn, $this->ldapfilter);
      $aList     = ldap_get_entries($rLdapconn, $rSearch);
      ldap_close($rLdapconn);
      $iNumberUser = count($alist) -1;
      echo "(PERSON) Number of entries found on LDAP -> ".$iNumberUser." \n";
      return $aList;
   }

   public function prepare()
   {
      if (!$aDatas = $this->getDatas()) return false;
      foreach($aDatas as $aPerson) {
        if (isset($aPerson[$this->person_id][0] ) && $aPerson[$this->person_id][0] != "" ) {
             if ($this->synchronize_organization == 'yes')
             {
                $sOrg = isset($aPerson[$this->person_ou][0]) ? $aPerson[$this->person_ou][0] : '';
             }
             else
             {
                $sOrg =  $this->default_organisation ;
             }
             $aValues = array(
               'primary_key'     => isset($aPerson[$this->person_id][0]) ? $aPerson[$this->person_id][0] : '',
               'first_name'      => isset($aPerson[$this->person_first_name][0]) ? $aPerson[$this->person_first_name][0] : '' ,
               'name'            => isset($aPerson[$this->person_name][0]) ? $aPerson[$this->person_name][0] : '',
               'email'           => isset($aPerson[$this->person_email][0]) ? $aPerson[$this->person_email][0] : '',
               'org_id'          => $sOrg,
               'status'          => $this->default_status,
               'phone'           => isset($aPerson[$this->person_phone][0]) ? $aPerson[$this->person_phone][0] : '',
               'mobile_phone'    => isset($aPerson[$this->person_mobile_phone][0]) ? $aPerson[$this->person_mobile_phone][0] : '',
               'function'        => isset($aPerson[$this->person_function][0]) ? $aPerson[$this->person_function][0] : '',
               'employee_number' => isset($aPerson[$this->person_employee_number][0]) ? $aPerson[$this->person_employee_number][0] : '',
            );
            $this->person_tab[] = $aValues;
         }
      }
      return true;
   }

   public function fetch()
   {
      if ($this->idx < count($this->person_tab)) {
         $aDatas = $this->person_tab[$this->idx];
         $this->idx++;

         return $aDatas;
      }
      return false;
   }

}
