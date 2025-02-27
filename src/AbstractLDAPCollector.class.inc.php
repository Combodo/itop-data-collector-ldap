<?php
require_once(APPROOT.'collectors/src/LDAPSearchService.class.inc.php');
require_once(APPROOT.'core/collector.class.inc.php');
require_once(APPROOT.'core/utils.class.inc.php');

class AbstractLDAPCollector extends Collector {
	/** @var LDAPSearchService $oLDAPSearchService*/
	private $oLDAPSearchService;

	public function __construct()
    {
		parent::__construct();
	}

	public function SetLDAPSearchService(LDAPSearchService $oLDAPSearchService)
    {
		$this->oLDAPSearchService = $oLDAPSearchService;
	}

	public function GetLDAPSearchService(): LDAPSearchService
	{
		if (is_null($this->oLDAPSearchService)){
			$this->oLDAPSearchService = new LDAPSearchService();
		}
		return $this->oLDAPSearchService;
	}

}
