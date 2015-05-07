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
      $this->person_tab                 = array();
      $this->idx                        = 0;

   }


   protected function getDatas()
   {
      $ldapconn = ldap_connect($this->ldaphost, $this->ldapport);
      if (!$ldapconn) {
            return false;
      }
      ldap_set_option($ldapconn, LDAP_OPT_REFERRALS, 0);
      ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
      $bind     = ldap_bind($ldapconn, $this->ldaplogin, $this->ldappassword);
      $search   = ldap_search($ldapconn, $this->ldapdn, $this->ldapfilter);
      $list     = ldap_get_entries($ldapconn, $search);
      ldap_close($ldapconn);

      $iNumberUser = count($list) -1;
      echo "(PERSON) Number of entries found on LDAP -> ".$iNumberUser." \n";
      return $list;
   }

   public function prepare()
   {

      if (!$datas = $this->getDatas()) return false;

      foreach($datas as $person) {
        if (isset($person[$this->person_id][0] ) && $person[$this->person_id][0] != "" ) {
             if ($this->synchronize_organization == 'yes')
             {
                $org = isset($person['ou'][0]) ? $person['ou'][0] : '';
             }
             else
             {
                $org =  $this->default_organisation ;
             }
             $values = array(
               'primary_key'     => isset($person[$this->person_id][0]) ? utf8_encode($person[$this->person_id][0]) : '',
               'first_name'      => isset($person[$this->person_first_name][0]) ? utf8_encode($person[$this->person_first_name][0]) : '' ,
               'name'            => isset($person[$this->person_name][0]) ? utf8_encode($person[$this->person_name][0]) : '',
               'email'           => isset($person[$this->person_email][0]) ? utf8_encode($person[$this->person_email][0]) : '',
               'org_id'          => utf8_encode($org),
               'status'          => utf8_encode($this->default_status),
               'phone'           => isset($person[$this->person_phone][0]) ? utf8_encode($person[$this->person_phone][0]) : '',
               'mobile_phone'    => isset($person[$this->person_mobile_phone][0]) ? utf8_encode($person[$this->person_mobile_phone][0]) : '',
               'function'        => isset($person[$this->person_function][0]) ? utf8_encode($person[$this->person_function][0]) : '',
               'employee_number' => isset($person[$this->person_employee_number][0]) ? utf8_encode($person[$this->person_employee_number][0]) : '',
            );

            $this->person_tab[] = $values;

         }
      }

      return true;
   }


   public function fetch()
   {
      if ($this->idx < count($this->person_tab)) {
         $datas = $this->person_tab[$this->idx];
         $this->idx++;

         return $datas;
      }

      return false;
   }


}
