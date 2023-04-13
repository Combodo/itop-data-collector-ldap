<?php

require_once(APPROOT.'collectors/LDAPSearchService.class.inc.php');
require_once (APPROOT.'core/collector.class.inc.php');
require_once (APPROOT.'core/utils.class.inc.php');

class AbstractLdapCollector extends Collector {
	/** @var LDAPSearchService $oLDAPSearchService*/
	private $oLDAPSearchService;

	public function __construct() {
		parent::__construct();
	}

	public function SetLDAPSearchService(LDAPSearchService $oLDAPSearchService){
		$this->oLDAPSearchService = $oLDAPSearchService;
	}

	public function GetLDAPSearchService() : LDAPSearchService
	{
		if (is_null($this->oLDAPSearchService)){
			$this->oLDAPSearchService = new LDAPCollectorService();
		}
		return $this->oLDAPSearchService;
	}

	public function GetFieldKeysToSearchOnLDAPSide(array $aFields) : array {
		$aAllFields = array_values($aFields);
		$aCleanFields = [];

		foreach (array_values($aFields) as $sField){
			if (empty($sField)){
				//itop field must be declared but has no matching with any ldap field
				continue;
			}

			if (! in_array($sField, $aCleanFields)){
				$aCleanFields[]=$sField;
			}
		}

		return $aCleanFields;
	}

}
