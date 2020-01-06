<?php
/**
 * Multicraft Module actions
 *
 * @package blesta
 * @subpackage blesta.components.modules.multicraft.lib
 * @copyright Copyright (c) 2014, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class MulticraftModule {

    /**
     * Initialize
     */
    public function __construct() {
        // Load required components
        Loader::loadComponents($this, array("Input"));
    }

    /**
     * Retrieves a list of Input errors, if any
     */
    public function errors() {
        return $this->Input->errors();
    }

    /**
     * Fetches the module keys usable in email tags
     *
     * @return array A list of module email tags
     */
    public function getEmailTags() {
        return array("panel_url", "panel_api_url", "daemons", "ips", "ips_in_use");
    }

    /**
	 * Performs any necessary bootstraping actions. Sets Input errors on
	 * failure, preventing the module from being added.
	 *
	 * @return array A numerically indexed array of meta data containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 */
	public function install() {
        if (!function_exists("json_encode"))
            $this->Input->setErrors(array('json' => array('unavailable' => Language::_("MulticraftModule.!error.json.unavailable", true))));
	}

    /**
	 * Adds the module row on the remote server. Sets Input errors on failure,
	 * preventing the row from being added.
	 *
	 * @param array $vars An array of module info to add
	 * @return array A numerically indexed array of meta fields for the module row containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 */
    public function addRow(array &$vars) {
        $meta_fields = array("server_name", "panel_url", "panel_api_url", "username", "key", "daemons", "ips", "ips_in_use", "log_all");
		$encrypted_fields = array("username", "key");

        if (!isset($vars['log_all']))
            $vars['log_all'] = "0";

        // Remove dedicated IPs if not set to use any
        if (isset($vars['daemons']) && is_array($vars['daemons']) && count($vars['daemons']) == 1 && empty($vars['daemons'][0]) &&
            isset($vars['ips']) && is_array($vars['ips']) && count($vars['ips']) == 1 && empty($vars['ips'][0]))
            unset($vars['daemons'], $vars['ips']);

		$this->Input->setRules($this->getRowRules($vars));

		// Validate module row
		if ($this->Input->validates($vars)) {

			// Build the meta data for this row
			$meta = array();
			foreach ($vars as $key => $value) {

				if (in_array($key, $meta_fields)) {
					$meta[] = array(
						'key' => $key,
						'value' => $value,
						'encrypted' => in_array($key, $encrypted_fields) ? 1 : 0
					);
				}
			}
			return $meta;
		}
    }

    /**
     * Validates that each of the given IP addresses matches a given daemon
     *
     * @param array $ips A numerically-indexed array of IP addresses, whose index matches the given daemons
     * @param array $daemons A numerically-indexed array of daemons, whose index matches the given IPs
     * @param array $ips_in_use A numerically-indexed array signifying whether this IP is in use, whose index matches IPs
     * @return boolean True if each IP address matches a given daemon; false otherwise
     */
    public function validateIpsMatchDaemons($ips, $daemons, $ips_in_use) {
        // Set rule to validate IP addresses
        $range = "(?:25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9][0-9]|[0-9])";
        $ip_address_rule = "/^(?:" . $range . "\." . $range . "\." . $range . "\." . $range . ")$/";

        if (!empty($ips) && !is_array($ips))
            return false;

        // Validate an IP is set for each daemon
        if (is_array($daemons) && is_array($ips_in_use)) {
            foreach ($daemons as $index => $daemon_id) {
                if (empty($ips[$index]) || !isset($ips_in_use[$index]) || !preg_match($ip_address_rule, $ips[$index]))
                    return false;
            }
        }

        return true;
    }

    /**
     * Validates that each of the given daemons matches a given IP address
     *
     * @param array $daemons A numerically-indexed array of daemons, whose index matches the given IPs
     * @param array $ips A numerically-indexed array of IP addresses, whose index matches the given daemons
     * @param array $ips_in_use A numerically-indexed array signifying whether this IP is in use, whose index matches IPs
     * @return boolean True if each daemon matches a given IP address; false otherwise
     */
    public function validateDaemonsMatchIps($daemons, $ips, $ips_in_use) {
        if (!empty($daemons) && !is_array($daemons))
            return false;

        // Validate a deamon is set for each IP
        if (is_array($ips) && is_array($ips_in_use)) {
            foreach ($ips as $index => $ip) {
                if (empty($daemons[$index]) || !isset($ips_in_use[$index]) || !preg_match("/^[0-9]+$/", $daemons[$index]))
                    return false;
            }
        }

        return true;
    }

    /**
     * Validates that each of the given IPs-in-use fields matches a given IP address
     *
     * @param array $ips_in_use A numerically-indexed array signifying whether this IP is in use, whose index matches IPs
     * @param array $daemons A numerically-indexed array of daemons, whose index matches the given IPs
     * @param array $ips A numerically-indexed array of IP addresses, whose index matches the given daemons
     * @return boolean True if each daemon matches a given IP address; false otherwise
     */
    public function validateIpsInUseMatchIps($ips_in_use, $ips, $daemons) {
        if (!empty($ips_in_use) && !is_array($ips_in_use))
            return false;

        // Validate value is set for each IP in use
        if (is_array($ips) && is_array($daemons)) {
            foreach ($ips_in_use as $index => $value) {
                if (!in_array($value, array("0", "1")))
                    return false;
            }
        }

        return true;
    }

    /**
	 * Builds and returns the rules required to add/edit a module row (e.g. server)
	 *
	 * @param array $vars An array of key/value data pairs
	 * @return array An array of Input rules suitable for Input::setRules()
	 */
	private function getRowRules(&$vars) {
        $ips_required = (isset($vars['ips']) || isset($vars['daemons']));

		return array(
            'server_name' => array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("MulticraftModule.!error.server_name.empty", true)
                )
            ),
            'panel_url' => array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("MulticraftModule.!error.panel_url.empty", true)
                )
            ),
            'panel_api_url' => array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("MulticraftModule.!error.panel_api_url.empty", true)
                )
            ),
            'username' => array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("MulticraftModule.!error.username.empty", true)
                )
            ),
            'key' => array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("MulticraftModule.!error.key.empty", true)
                )
            ),
            'log_all' => array(
                'format' => array(
                    'if_set' => true,
                    'rule' => array("in_array", array("0", "1")),
                    'message' => Language::_("MulticraftModule.!error.log_all.format", true)
                )
            ),
			'ips' => array(
				'match' => array(
                    'if_set' => $ips_required,
                    'rule' => array(array($this, "validateIpsMatchDaemons"), (isset($vars['daemons']) ? $vars['daemons'] : array()), (isset($vars['ips_in_use']) ? $vars['ips_in_use'] : array())),
                    'message' => Language::_("MulticraftModule.!error.ips.match", true)
                )
			),
            'daemons' => array(
				'match' => array(
                    'if_set' => $ips_required,
                    'rule' => array(array($this, "validateDaemonsMatchIps"), (isset($vars['ips']) ? $vars['ips'] : array()), (isset($vars['ips_in_use']) ? $vars['ips_in_use'] : array())),
                    'message' => Language::_("MulticraftModule.!error.daemons.match", true)
                )
			),
            'ips_in_use' => array(
                'match' => array(
                    'rule' => array(array($this, "validateIpsInUseMatchIps"), (isset($vars['ips']) ? $vars['ips'] : array()), (isset($vars['daemons']) ? $vars['daemons'] : array())),
                    'message' => Language::_("MulticraftModule.!error.ips_in_use.match", true)
                )
            )
		);
	}
}
?>