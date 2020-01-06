<?php
/**
 * Shared Login plugin handler
 * 
 * @package blesta
 * @subpackage blesta.plugins.shared_login
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class SharedLoginPlugin extends Plugin {

	/**
	 * @var string The version of this plugin
	 */
	private static $version = "1.0.1";
	/**
	 * @var string The authors of this plugin
	 */
	private static $authors = array(array('name' => "Phillips Data, Inc.", 'url' => "http://www.blesta.com"));
	
	public function __construct() {
		Language::loadLang("shared_login_plugin", null, dirname(__FILE__) . DS . "language" . DS);
	}
	
	/**
	 * Returns the name of this plugin
	 *
	 * @return string The common name of this plugin
	 */
	public function getName() {
		return Language::_("SharedLoginPlugin.name", true);	
	}
	
	/**
	 * Returns the version of this plugin
	 *
	 * @return string The current version of this plugin
	 */
	public function getVersion() {
		return self::$version;
	}

	/**
	 * Returns the name and URL for the authors of this plugin
	 *
	 * @return array The name and URL of the authors of this plugin
	 */
	public function getAuthors() {
		return self::$authors;
	}
	
	/**
	 * Performs any necessary bootstraping actions
	 *
	 * @param int $plugin_id The ID of the plugin being installed
	 */
	public function install($plugin_id) {
		Loader::loadModels($this, array("Companies", "PluginManager"));

		$plugin = $this->PluginManager->get($plugin_id);
		
		if (!$plugin)
			return;
		
		if (function_exists("openssl_random_pseudo_bytes"))
			$key = bin2hex(openssl_random_pseudo_bytes(16));
		else
			$key = md5(uniqid($plugin_id, true) . mt_rand() . md5($plugin_id . mt_rand()));
		
		$this->Companies->setSetting($plugin->company_id, "shared_login.key", $key, true);
	}
	
	/**
	 * Performs any necessary cleanup actions
	 *
	 * @param int $plugin_id The ID of the plugin being uninstalled
	 * @param boolean $last_instance True if $plugin_id is the last instance across all companies for this plugin, false otherwise
	 */
	public function uninstall($plugin_id, $last_instance) {
		Loader::loadModels($this, array("Companies", "PluginManager"));
		
		$plugin = $this->PluginManager->get($plugin_id);
		
		if (!$plugin)
			return;
		
		$this->Companies->unsetSetting($plugin->company_id, "shared_login.key");
	}
}
?>