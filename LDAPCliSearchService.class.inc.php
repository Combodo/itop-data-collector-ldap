<?php

require_once(APPROOT.'collectors/LDAPSearchService.class.inc.php');
require_once (APPROOT.'core/utils.class.inc.php');

class LDAPCliSearchService {
	/** @var LDAPSearchService $oLDAPSearchService*/
	private $oLDAPSearchService;

	/** @var string $sObjetName */
	private $sObjetName;

	/** @var int $iExitCode */
	private $iExitCode;

	public function __construct($sObjetName='persons') {
		$this->sObjetName = $sObjetName;
	}

	public function SetLDAPSearchService(LDAPSearchService $oLDAPSearchService){
		$this->oLDAPSearchService = $oLDAPSearchService;
	}

	public function GetLDAPSearchService() : LDAPSearchService
	{
		if (is_null($this->oLDAPSearchService)){
			$this->oLDAPSearchService = new LDAPSearchService();
		}
		return $this->oLDAPSearchService;
	}

	public function Search(string $sLdapfilter, int $iSizeLimit, $aAttributes) : string {
		$sLdapdn = Utils::GetConfigurationValue('ldapdn', 'DC=company,DC=com');

		$aLdapResults = $this->GetLDAPSearchService()->Search($sLdapdn, $sLdapfilter, $aAttributes);
		$iCount = count($aLdapResults) - 1;
		if ("$iSizeLimit" === "-1"){
			$aRes = $aLdapResults;
		} else {
			$aRes = [];
			for($i=0;$i<$iCount;$i++){
				$aRes[]=$aLdapResults[$i];
			}
		}

		$this->iExitCode = $this->GetLDAPSearchService()->GetLastLdapErrorCode();
		if (LDAP_SUCCESS === $this->iExitCode||LDAP_SIZELIMIT_EXCEEDED === $this->iExitCode){
			$aOutput = [
				'count' => $iCount,
				'code' => $this->iExitCode,
				$this->sObjetName => $aRes,
				'msg' => $this->GetLDAPSearchService()->GetLastLdapErrorMessage(),
			];
		} else {
			$aOutput = [
				'code' => $this->iExitCode,
				'msg' => $this->GetLDAPSearchService()->GetLastLdapErrorMessage(),
			];
		}
		return json_encode($aOutput, JSON_PARTIAL_OUTPUT_ON_ERROR);
	}

	public function GetLastExitCode() : int {
		return $this->iExitCode;
	}
}
