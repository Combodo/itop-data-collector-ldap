<?php

class LDAPService {
	/**
	 * @param string|null $uri
	 * @param int $port
	 *
	 * @return false|resource
	 */
	public function ldap_connect(?string $uri, int $port = 389) {
		return ldap_connect($uri, $port);
	}

	/**
	 * @param false|resource $ldap
	 * @param int $option
	 * @param $value
	 *
	 * @return bool
	 */
	public function ldap_set_option(
		$ldap,
		int $option,
		$value
	): bool {
		return ldap_set_option($ldap, $option, $value);
	}

	/**
	 * @param false|resource $ldap
	 * @param string|null $dn
	 * @param string|null $password
	 *
	 * @return bool
	 */
	public function ldap_bind($ldap, ?string $dn, ?string $password): bool {
		return @ldap_bind($ldap, $dn, $password);
	}

	/**
	 * @param false|resource $ldap
	 *
	 * @return string
	 */
	public function ldap_error($ldap): string {
		return ldap_error($ldap);
	}

	/**
	 * @param false|resource $ldap
	 *
	 * @return int
	 */
	public function ldap_errno($ldap): int {
		return ldap_errno($ldap);
	}

	/**
	 * @param false|resource $ldap
	 *
	 * @return bool
	 */
	public function ldap_close($ldap): bool {
		return ldap_close($ldap);
	}

	/**
	 * @param false|resource $ldap
	 * @param array|string $base
	 * @param array|string $filter
	 *
	 * @return false|resource
	 */
	public function ldap_read(
		$ldap,
		$base,
		$filter,
        array $attributes = []
	) {
		return ldap_read($ldap, $base, $filter, $attributes);
	}

	/**
	 * @param false|resource $ldap
	 * @param false|resource $result
	 *
	 * @return array|false
	 */
	public function ldap_get_entries(
		$ldap,
		$result
	){
		return ldap_get_entries($ldap, $result);
	}

	/**
	 * @param false|resource $ldap
	 * @param array|string $base
	 * @param array|string $filter
	 * @param array $attributes
	 * @param int $attributes_only
	 * @param int $sizelimit
	 * @param int $timelimit
	 * @param int $deref
	 * @param array|null $controls
	 *
	 * @return false|resource
	 */
	public function ldap_search(
		$ldap,
		$base,
		$filter,
		array $attributes = [],
		int $attributes_only = 0,
		int $sizelimit = -1,
		int $timelimit = -1,
		int $deref = 0,
		?array $controls = null
	) {
		if (is_null($controls)){
			return ldap_search($ldap, $base, $filter, $attributes, $attributes_only, $sizelimit, $timelimit, $deref);
		} else {
			return ldap_search($ldap, $base, $filter, $attributes, $attributes_only, $sizelimit, $timelimit, $deref, $controls);
		}
 }

	/**
	 * @param false|resource $ldap
	 * @param false|resource $result
	 *
	 * @return array|false
	 */
	public function ldap_count_entries(
		$ldap,
		$result
	) {
		return @ldap_count_entries($ldap, $result);
	}

	public function ldap_parse_result(
		$ldap,
		$result,
		&$error_code,
		&$matched_dn,
		&$error_message,
		&$referrals,
		&$controls = null
	): bool {
		return @ldap_parse_result($ldap, $result, $error_code, $matched_dn, $error_message, $referrals, $controls);
	}
}
