<?php
/**
 * Base class for LDAP collectors, handles the connexion to LDAP (connect & bind)
 * as well as basic searches
 */
class LDAPCollector extends Collector
{
    protected $sHost;
    protected $sPort;
    protected $sURI;
    protected $sLogin;
    protected $sPassword;
    protected $rConnection = null;
    protected $bBindSuccess = false;
    
    public function __construct()
    {
        parent::__construct();
        // let's read the configuration parameters
        // No connection method an URI like ldap://<server>:<port> or ldaps://<server>:<port>
        $this->sURI = Utils::GetConfigurationValue('ldapuri', '');
        // Old connection method
        $this->sHost = Utils::GetConfigurationValue('ldaphost', 'localhost');
        $this->sPort = Utils::GetConfigurationValue('ldapport', 389);
        // Bind parameters
        $this->sLogin = Utils::GetConfigurationValue('ldaplogin', 'CN=ITOP-LDAP,DC=company,DC=com');
        $this->sPassword = Utils::GetConfigurationValue('ldappassword', 'password');
    }
    
    /**
     * Tells if the connexion is already established
     * @return boolean
     */
    private function IsConnected()
    {
        return $this->bBindSuccess;    
    }
    
    /**
     * Perform the actual connection to the LDAP server (connect AND bind) 
     * @return boolean
     */
    private function Connect()
    {
        if ($this->IsConnected()) return true;
        
        if ($this->Init())
        {
            ldap_set_option($this->rConnection, LDAP_OPT_REFERRALS, 0);
            ldap_set_option($this->rConnection, LDAP_OPT_PROTOCOL_VERSION, 3);
            
            Utils::Log(LOG_DEBUG, "ldap_bind('{$this->sLogin}', '{$this->sPassword}')...");
            $this->bBindSuccess = @ldap_bind($this->rConnection, $this->sLogin, $this->sPassword);
            if ($this->bBindSuccess === false)
            {
                Utils::Log(LOG_ERR, "ldap_bind('{$this->sLogin}', '{$this->sPassword}') FAILED (".ldap_error($this->rConnection).").");
                return false;
            }
            Utils::Log(LOG_DEBUG, "ldap_bind() Ok.");
        }
        return true;
    }
    
    /**
     * Perform just the initialization of the connection parameters (no connection to the LDAP server)
     * @return boolean
     */
    private function Init()
    {
        if ($this->rConnection !== null) return true;
        
        $this->bBindSuccess = false;
        
        if ($this->sURI !== '')
        {
            // New syntax for ldapconnect(...)
            Utils::Log(LOG_DEBUG, "ldap_connect('{$this->sURI}')...");
            $this->rConnection = ldap_connect($this->sURI);
            if ($this->rConnection === false)
            {
                echo "ldap_connect to {$this->sURI} failed, check the syntax of the <ldapuri> parameter !\n";
                return false;
            }
        }
        else
        {
            // Old syntax for ldapconnect(...)
            $sURI = $this->MakeURI($this->sHost, $this->sPort);
            Utils::Log(LOG_WARNING,
<<<TXT
Using the old syntax with two parameters 'ldaphost' and 'ldapport' to call ldapconnect.
Consider upgrading your configuration file to use the parameter 'ldapuri' instead.
The value should be something like:
    <ldapuri>$sURI</ldapuri>
TXT
            );
            Utils::Log(LOG_DEBUG, "ldap_connect('{$this->sHost}', '{$this->sPort}')...");
            $this->rConnection = ldap_connect($this->sHost, $this->sPort);
            if ($this->rConnection === false)
            {
                echo "ldap_connect to {$this->sHost}:{$this->sPort} failed, check the syntax of your parameters !\n";
                return false;
            }
        }
        return true;
    }
    
    /**
     * Try to build a meaningful LDAP URI from the 2 parameters given
     * @param string $sHost
     * @param string $sPort
     * @return string
     */
    private function MakeURI($sHost, $sPort)
    {
        if (preg_match('@^(ldap://|ldaps://)@', $sHost))
        {
            if ($sPort != '')
            {
                return "$sHost:$sPort";
            }
            return $sHost;
        }
        else
        {
            if ($sPort != '389')
            {
                return "ldaps://$sHost:$sPort";
            }
            return "ldap://$sHost";
        }
    }
    
    /**
     * Closes the connexion to the LDAP server
     * @return void
     */
    private function Disconnect()
    {
        ldap_close($this->rConnection);
        $this->rConnection = null;
        $this->bBindSuccess = false;
    }
   
    /**
     * Perform a search with the given parameters, also manages the connexion to the server
     * @param string $sDN The DN of the base object to search under
     * @param string $sFilter The filter criteria
     * @param string[] $aAttributes The attributes to retrieve '*' means all attributes... BEWARE: sometimes memberof must be explicitely requested
     * @return false|string[]
     */
    public function Search($sDN, $sFilter, $aAttributes = array('*'))
    {
        if ($this->Connect())
        {
            Utils::Log(LOG_DEBUG, "ldap_search('$sDN', '$sFilter', ['".implode("', '", $aAttributes)."'])...");

	$cookie = '';
	$pageSize = 500;
	$firstPass = true;
	do {
	    ldap_control_paged_result($this->rConnection, $pageSize, true, $cookie);

            $rSearch = @ldap_search($this->rConnection, $sDN, $sFilter, $aAttributes);
            if ($rSearch === false)
            {
                Utils::Log(LOG_ERR, "ldap_search('$sDN', '$sFilter') FAILED (".ldap_error($this->rConnection).").");
                return false;
            }
            Utils::Log(LOG_DEBUG, "ldap_search() Ok.");

	    $bList = ldap_get_entries($this->rConnection, $rSearch);
	    if($firstPass)
	    {
		$aList = $bList;
	    }
	    else
	    {
		$aList = array_merge($aList, $bList);
	    }

	    ldap_control_paged_result_response($this->rConnection, $rSearch, $cookie);
	    $iNumberUser = count($aList) - 1;
	    Utils::Log(LOG_INFO,"(Persons) Number of entries found on LDAP so far: ".$iNumberUser);
	    $firstPass = false;
	} while($cookie !== null && $cookie != ''); 		


            
	    //$aList = ldap_get_entries($this->rConnection, $rSearch);
            $this->Disconnect();
            return $aList;
        }
        return false;
    }
}
