<?php

require_once(APPROOT.'collectors/LDAPService.class.inc.php');
require_once (APPROOT.'core/parameters.class.inc.php');
require_once (APPROOT.'core/utils.class.inc.php');

if (!defined("LDAP_CONTROL_PAGEDRESULTS")) {
	define("LDAP_CONTROL_MANAGEDSAIT", "2.16.840.1.113730.3.4.2");
	define("LDAP_CONTROL_PROXY_AUTHZ", "2.16.840.1.113730.3.4.18");
	define("LDAP_CONTROL_SUBENTRIES", "1.3.6.1.4.1.4203.1.10.1");
	define("LDAP_CONTROL_VALUESRETURNFILTER", "1.2.826.0.1.3344810.2.3");
	define("LDAP_CONTROL_ASSERT", "1.3.6.1.1.12");
	define("LDAP_CONTROL_PRE_READ", "1.3.6.1.1.13.1");
	define("LDAP_CONTROL_POST_READ", "1.3.6.1.1.13.2");
	define("LDAP_CONTROL_SORTREQUEST", "1.2.840.113556.1.4.473");
	define("LDAP_CONTROL_SORTRESPONSE", "1.2.840.113556.1.4.474");
	define("LDAP_CONTROL_PAGEDRESULTS", "1.2.840.113556.1.4.319");
	define("LDAP_CONTROL_SYNC", "1.3.6.1.4.1.4203.1.9.1.1");
	define("LDAP_CONTROL_SYNC_STATE", "1.3.6.1.4.1.4203.1.9.1.2");
	define("LDAP_CONTROL_SYNC_DONE", "1.3.6.1.4.1.4203.1.9.1.3");
	define("LDAP_CONTROL_DONTUSECOPY", "1.3.6.1.1.22");
	define("LDAP_CONTROL_PASSWORDPOLICYREQUEST", "1.3.6.1.4.1.42.2.27.8.5.1");
	define("LDAP_CONTROL_PASSWORDPOLICYRESPONSE", "1.3.6.1.4.1.42.2.27.8.5.1");
	define("LDAP_CONTROL_X_INCREMENTAL_VALUES", "1.2.840.113556.1.4.802");
	define("LDAP_CONTROL_X_DOMAIN_SCOPE", "1.2.840.113556.1.4.1339");
	define("LDAP_CONTROL_X_PERMISSIVE_MODIFY", "1.2.840.113556.1.4.1413");
	define("LDAP_CONTROL_X_SEARCH_OPTIONS", "1.2.840.113556.1.4.1340");
	define("LDAP_CONTROL_X_TREE_DELETE", "1.2.840.113556.1.4.805");
	define("LDAP_CONTROL_X_EXTENDED_DN", "1.2.840.113556.1.4.529");
	define("LDAP_CONTROL_VLVREQUEST", "2.16.840.1.113730.3.4.9");
	define("LDAP_CONTROL_VLVRESPONSE", "2.16.840.1.113730.3.4.10");
}

/**
 * Base class for LDAP collectors, handles the connexion to LDAP (connect & bind)
 * as well as basic searches
 */
class LDAPSearchService
{
	protected $oLDAPService;

	protected $sHost;
	protected $sPort;
	protected $sURI;
    protected $sLogin;
    protected $sPassword;
    protected $rConnection = null;
    protected $bBindSuccess = false;
    protected $bPaginationIsSupported = null;
    protected $iPageSize;

	//limit size of ldap resultat list
    protected $iSizeLimit = -1;

	protected $sLastLdapErrorMessage = null;
	protected $iLastLdapErrorCode = -1;

    public function __construct()
    {
	    $this->oLDAPService = new LDAPService();

        // let's read the configuration parameters
        // No connection method an URI like ldap://<server>:<port> or ldaps://<server>:<port>
        $this->sURI = Utils::GetConfigurationValue('ldapuri', '');
        // Old connection method
        $this->sHost = Utils::GetConfigurationValue('ldaphost', 'localhost');
        $this->sPort = Utils::GetConfigurationValue('ldapport', 389);
        // Bind parameters
        $this->sLogin = Utils::GetConfigurationValue('ldaplogin', 'CN=ITOP-LDAP,DC=company,DC=com');
        $this->sPassword = Utils::GetConfigurationValue('ldappassword', 'password');
        // Pagination
        $this->iPageSize = Utils::GetConfigurationValue('page_size', 0);
    }

	/**
	 * @param \LDAPService $oLDAPService
	 * used for mock/test only
	 * @return void
	 */
	public function SetLDAPService(LDAPService $oLDAPService){
		$this->oLDAPService = $oLDAPService;
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

        if ($this->InitLDAP())
        {
			return true;
        }
        return false;
    }

    /**
     * Perform just the initialization of the connection parameters (no connection to the LDAP server)
     * @return boolean
     */
    private function InitLDAP()
    {
        if ($this->rConnection !== null) return true;

        $this->bBindSuccess = false;

		// Prepare the connection regarding the parameters
        if ($this->sURI !== '')
        {
            // New syntax for ldapconnect(...)
            Utils::Log(LOG_DEBUG, "ldap_connect('{$this->sURI}')...");
	        $this->rConnection = $this->oLDAPService->ldap_connect($this->sURI);

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
	        $this->rConnection = $this->oLDAPService->ldap_connect($this->sHost, $this->sPort);
        }

		// Test connection with a bind

	    //LDAP debug
	    $sLdapOptDebugLevel = Utils::GetConfigurationValue('ldap_opt_debug_level', null);
	    if (! is_null($sLdapOptDebugLevel) && is_int($sLdapOptDebugLevel)){
		    $this->oLDAPService->ldap_set_option($this->rConnection, LDAP_OPT_DEBUG_LEVEL, $sLdapOptDebugLevel);
	    }
	    $this->oLDAPService->ldap_set_option($this->rConnection, LDAP_OPT_REFERRALS, 0);
	    $this->oLDAPService->ldap_set_option($this->rConnection, LDAP_OPT_PROTOCOL_VERSION, 3);
	    Utils::Log(LOG_DEBUG, "ldap_bind('{$this->sLogin}', '{$this->sPassword}')...");
	    $this->bBindSuccess = $this->oLDAPService->ldap_bind($this->rConnection, $this->sLogin, $this->sPassword);
	    $this->sLastLdapErrorMessage = $this->oLDAPService->ldap_error($this->rConnection);
	    $this->iLastLdapErrorCode = $this->oLDAPService->ldap_errno($this->rConnection);
        if ($this->bBindSuccess === false)
        {
		    Utils::Log(LOG_ERR, "ldap_bind to {$this->sURI} failed, check your LDAP connection parameters (<ldapxxx>)!");
	        Utils::Log(LOG_ERR, "ldap_bind('{$this->sLogin}', '{$this->sPassword}') FAILED (".$this->sLastLdapErrorMessage.").");
            return false;
        }
	    Utils::Log(LOG_DEBUG, "ldap_bind() Ok.");

	    // Check if pagination is supported
        if ($this->PaginationIsSupported(true))
        {
            Utils::Log(LOG_INFO, "Pagination of results is supported by the LDAP server.");
            if ($this->iPageSize > 0)
            {
                Utils::Log(LOG_INFO, "Results will be retrieved by pages of {$this->iPageSize} elements.");
            }
            else
            {
                Utils::Log(LOG_INFO, "Consider setting the parameter <page_size> to a value greater than zero in the configuration file in order to use pagination.");
            }
        }
        else
        {
            if ($this->iPageSize > 0)
            {
                Utils::Log(LOG_WARNING, "The parameter <page_size> will be ignored.");
            }
        }
        return true;
    }

	public function ConnectAndDisconnect() : void
	{
		if ($this->Connect())
		{
			$this->Disconnect();
		}
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
	    $this->oLDAPService->ldap_close($this->rConnection);
        $this->rConnection = null;
        $this->bBindSuccess = false;
    }

    private function PaginationIsSupported($bLogStatus = false)
    {
        if ($this->bPaginationIsSupported === null)
        {
	        if (version_compare(PHP_VERSION, '7.3.0') < 0) {
		        $this->bPaginationIsSupported = false;
		        if ($bLogStatus && ($this->iPageSize > 0)) {
			        Utils::Log(LOG_WARNING, "PHP 7.3.0 or above is needed to support pagination");
		        }
	        } else {
		        $result = $this->oLDAPService->ldap_read($this->rConnection, '', '(objectClass=*)', ['supportedControl']);
		        $aData = $this->oLDAPService->ldap_get_entries($this->rConnection, $result);
		        $aControls = $this->LdapControlsToLabels($aData[0]['supportedcontrol']);

		        Utils::Log(LOG_DEBUG, "Supported controls: ".implode(', ', $aControls).".");

		        $this->bPaginationIsSupported = in_array(LDAP_CONTROL_PAGEDRESULTS, $aData[0]['supportedcontrol']);
		        if ($bLogStatus && !$this->bPaginationIsSupported && ($this->iPageSize > 0)) {
			        Utils::Log(LOG_WARNING, "Pagination is NOT supported by the server");
		        }
	        }
        }
        return $this->bPaginationIsSupported;
    }

    /**
     * Replace the well-known OIDs with human readable labels
     * @param string[] $aControls
     * @return string[]
     */
    private function LdapControlsToLabels($aControls)
    {
        $aHumanReadableControls = array();
        $aWellKnownControls = array(
	        LDAP_CONTROL_MANAGEDSAIT => 'LDAP_CONTROL_MANAGEDSAIT',
	        LDAP_CONTROL_PROXY_AUTHZ => 'LDAP_CONTROL_PROXY_AUTHZ',
	        LDAP_CONTROL_SUBENTRIES => 'LDAP_CONTROL_SUBENTRIES',
	        LDAP_CONTROL_VALUESRETURNFILTER => 'LDAP_CONTROL_VALUESRETURNFILTER',
	        LDAP_CONTROL_ASSERT => 'LDAP_CONTROL_ASSERT',
	        LDAP_CONTROL_PRE_READ => 'LDAP_CONTROL_PRE_READ',
	        LDAP_CONTROL_POST_READ => 'LDAP_CONTROL_POST_READ',
	        LDAP_CONTROL_SORTREQUEST => 'LDAP_CONTROL_SORTREQUEST',
	        LDAP_CONTROL_SORTRESPONSE => 'LDAP_CONTROL_SORTRESPONSE',
	        LDAP_CONTROL_PAGEDRESULTS => 'LDAP_CONTROL_PAGEDRESULTS',
	        LDAP_CONTROL_SYNC => 'LDAP_CONTROL_SYNC',
	        LDAP_CONTROL_SYNC_STATE => 'LDAP_CONTROL_SYNC_STATE',
	        LDAP_CONTROL_SYNC_DONE => 'LDAP_CONTROL_SYNC_DONE',
	        LDAP_CONTROL_DONTUSECOPY => 'LDAP_CONTROL_DONTUSECOPY',
	        //LDAP_CONTROL_PASSWORDPOLICYREQUEST => 'LDAP_CONTROL_PASSWORDPOLICYREQUEST',
	        LDAP_CONTROL_PASSWORDPOLICYRESPONSE => 'LDAP_CONTROL_PASSWORDPOLICYRESPONSE',
	        LDAP_CONTROL_X_INCREMENTAL_VALUES => 'LDAP_CONTROL_X_INCREMENTAL_VALUES',
	        LDAP_CONTROL_X_DOMAIN_SCOPE => 'LDAP_CONTROL_X_DOMAIN_SCOPE',
	        LDAP_CONTROL_X_PERMISSIVE_MODIFY => 'LDAP_CONTROL_X_PERMISSIVE_MODIFY',
	        LDAP_CONTROL_X_SEARCH_OPTIONS => 'LDAP_CONTROL_X_SEARCH_OPTIONS',
	        LDAP_CONTROL_X_TREE_DELETE => 'LDAP_CONTROL_X_TREE_DELETE',
	        LDAP_CONTROL_X_EXTENDED_DN => 'LDAP_CONTROL_X_EXTENDED_DN',
	        LDAP_CONTROL_VLVREQUEST => 'LDAP_CONTROL_VLVREQUEST',
	        LDAP_CONTROL_VLVRESPONSE => 'LDAP_CONTROL_VLVRESPONSE',
        );
        foreach($aControls as $key => $sControl)
        {
            if ($key == 'count') continue;
            if (array_key_exists($sControl, $aWellKnownControls))
            {
                $aHumanReadableControls[] = $aWellKnownControls[$sControl];
            }
            else
            {
                $aHumanReadableControls[] = $sControl;
            }
        }
        return $aHumanReadableControls;
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
            if ($this->PaginationIsSupported() && ($this->iPageSize > 0))
            {
                return $this->PaginatedSearch($sDN, $sFilter, $aAttributes);
            }
            else
            {
                Utils::Log(LOG_DEBUG, "ldap_search('$sDN', '$sFilter', ['".implode("', '", $aAttributes)."'])...");
                $rSearch = $this->oLDAPService->ldap_search($this->rConnection, $sDN, $sFilter, $aAttributes, 0, $this->iSizeLimit);

	            $this->oLDAPService->ldap_count_entries($this->rConnection, $rSearch);
	            $this->sLastLdapErrorMessage = $this->oLDAPService->ldap_error($this->rConnection);
	            $this->iLastLdapErrorCode = $this->oLDAPService->ldap_errno($this->rConnection);
                if ($rSearch === false)
                {
                    Utils::Log(LOG_ERR, "ldap_search('$sDN', '$sFilter') FAILED (".$this->sLastLdapErrorMessage.").");
                    return false;
                }
                Utils::Log(LOG_DEBUG, "ldap_search() Ok.");

                $aList = $this->oLDAPService->ldap_get_entries($this->rConnection, $rSearch);
                $this->Disconnect();
                return $aList;
            }
        }
        return false;
    }

    private function PaginatedSearch($sDN, $sFilter, $aAttributes = array('*'))
    {
        $cookie = '';
        $aData = array('count' => 0);

        do
        {
            Utils::Log(LOG_DEBUG, "ldap_search('$sDN', '$sFilter', ['".implode("', '", $aAttributes)."'])...");
            $rSearch = $this->oLDAPService->ldap_search($this->rConnection, $sDN, $sFilter, $aAttributes, 0, $this->iSizeLimit, 0, LDAP_DEREF_NEVER, [['oid' => LDAP_CONTROL_PAGEDRESULTS, 'value' => ['size' => $this->iPageSize, 'cookie' => $cookie]]]);

            $errcode = $matcheddn = $sErrmsg = $referrals = $aControls = null;
	        $this->oLDAPService->ldap_parse_result($this->rConnection, $rSearch, $errcode , $matcheddn , $sErrmsg , $referrals, $aControls);
	        $this->sLastLdapErrorMessage = $sErrmsg;
	        $this->iLastLdapErrorCode = $errcode;
            if ($errcode !== 0)
            {
                Utils::Log(LOG_ERR, "ldap_search('$sDN', '$sFilter') FAILED (".$this->sLastLdapErrorMessage.").");
                return false;
            }

            $aList = $this->oLDAPService->ldap_get_entries($this->rConnection, $rSearch);
            foreach($aList as $values)
            {
                if (is_array($values)) // ignore the first element of the results: 'count' => <number>
                {
                    $aData[] = $values;
                    $aData['count']++;
                }
            }

            if (isset($aControls[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie']))
            {
                // You need to pass the cookie from the last call to the next one
                $cookie = $aControls[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie'];
            }
            else
            {
                $cookie = '';
            }
            // Empty cookie means last page
        }
        while (!empty($cookie));

        return $aData;
    }

	/**
	 * @return string | null
	 */
	public function GetLastLdapErrorMessage() {
		return $this->sLastLdapErrorMessage;
	}

	public function GetLastLdapErrorCode(): int {
		return $this->iLastLdapErrorCode;
	}

	public function SetSizeLimit(int $iSizeLimit){
		$this->iSizeLimit = $iSizeLimit;
	}
}
