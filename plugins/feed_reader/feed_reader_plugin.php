<?php
/**
 * Feed Reader plugin handler
 * 
 * @package blesta
 * @subpackage blesta.plugins.feed_reader
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class FeedReaderPlugin extends Plugin {

	/**
	 * @var array An array of default feeds to add when the plugin is installed for a given company
	 */
	private static $default_feeds = array("http://www.blesta.com/feed/");
	
	public function __construct() {
		// Load components required by this plugin
		Loader::loadComponents($this, array("Input"));
		
		Language::loadLang("feed_reader_plugin", null, dirname(__FILE__) . DS . "language" . DS);
		$this->loadConfig(dirname(__FILE__) . DS . "config.json");
	}
	
	/**
	 * Performs any necessary bootstraping actions
	 *
	 * @param int $plugin_id The ID of the plugin being installed
	 */
	public function install($plugin_id) {
		if (!isset($this->Record))
			Loader::loadComponents($this, array("Record"));
		
		$errors = array();
		// Ensure the the system meets the requirements for this plugin
		if (!extension_loaded("libxml"))
			$errors['libxml']['required'] = Language::_("FeedReaderPlugin.!error.libxml_required", true);
		if (!extension_loaded("dom"))
			$errors['dom']['required'] = Language::_("FeedReaderPlugin.!error.dom_required", true);
		
		if (!empty($errors)) {
			$this->Input->setErrors($errors);
			return;
		}

		Loader::loadModels($this, array("FeedReader.FeedReaderFeeds"));
		
		// Add all feed_reader tables, *IFF* not already added
		try {
			// feed_reader_feeds
			$this->Record->
				setField("id", array('type'=>"int",'size'=>10,'unsigned'=>true,'auto_increment'=>true))->
				setField("url", array('type'=>"varchar", 'size'=>255))->
				setField("updated", array('type'=>"datetime", 'is_null'=>true, 'default'=>null))->
				setKey(array("id"), "primary")->
				setKey(array("url"), "unique")->
				create("feed_reader_feeds", true);
			
			// feed_reader_defaults
			$this->Record->
				setField("feed_id", array('type'=>"int",'size'=>10,'unsigned'=>true))->
				setField("company_id", array('type'=>"int",'size'=>10,'unsigned'=>true))->
				setKey(array("feed_id", "company_id"), "primary")->
				create("feed_reader_defaults", true);
				
			// feed_reader_articles
			$this->Record->
				setField("id", array('type'=>"int",'size'=>10,'unsigned'=>true,'auto_increment'=>true))->
				setField("feed_id", array('type'=>"int",'size'=>10,'unsigned'=>true))->
				setField("guid", array('type'=>"varchar",'size'=>255))->
				setField("data", array('type'=>"text", 'is_null'=>true, 'default'=>null))->
				setField("date", array('type'=>"datetime"))->
				setKey(array("id"), "primary")->
				setKey(array("feed_id","guid"), "unique")->
				create("feed_reader_articles", true);
				
			// feed_reader_subscribers
			$this->Record->
				setField("feed_id", array('type'=>"int",'size'=>10,'unsigned'=>true))->
				setField("company_id", array('type'=>"int",'size'=>10,'unsigned'=>true))->
				setField("staff_id", array('type'=>"int",'size'=>10,'unsigned'=>true))->
				setKey(array("feed_id", "company_id", "staff_id"), "primary")->
				create("feed_reader_subscribers", true);
		}
		catch (Exception $e) {
			// Error adding... no permission?
			$this->Input->setErrors(array('db'=> array('create'=>$e->getMessage())));
			return;
		}
		
		// Add all default feeds for this company
		if (is_array(self::$default_feeds)) {
			for ($i=0; $i<count(self::$default_feeds); $i++)
				$this->FeedReaderFeeds->addFeed(array('url'=>self::$default_feeds[$i], 'company_id'=>Configure::get("Blesta.company_id")));
		}
	}
	
	/**
	 * Performs migration of data from $current_version (the current installed version)
	 * to the given file set version
	 *
	 * @param string $current_version The current installed version of this plugin
	 * @param int $plugin_id The ID of the plugin being upgraded
	 */
	public function upgrade($current_version, $plugin_id) {
		
		// Upgrade if possible
		if (version_compare($this->getVersion(), $current_version, ">")) {
			// Handle the upgrade, set errors using $this->Input->setErrors() if any errors encountered
		}
	}
	
	/**
	 * Performs any necessary cleanup actions
	 *
	 * @param int $plugin_id The ID of the plugin being uninstalled
	 * @param boolean $last_instance True if $plugin_id is the last instance across all companies for this plugin, false otherwise
	 */
	public function uninstall($plugin_id, $last_instance) {
		if (!isset($this->Record))
			Loader::loadComponents($this, array("Record"));
		
		// Remove all feed_reader tables *IFF* no other company in the system is using this plugin
		if ($last_instance) {
			$this->Record->drop("feed_reader_feeds");
			$this->Record->drop("feed_reader_defaults");
			$this->Record->drop("feed_reader_articles");
			$this->Record->drop("feed_reader_subscribers");
		}
	}
	
	/**
	 * Returns all actions to be configured for this widget (invoked after install() or upgrade(), overwrites all existing actions)
	 *
	 * @return array A numerically indexed array containing:
	 * 	- action The action to register for
	 * 	- uri The URI to be invoked for the given action
	 * 	- name The name to represent the action (can be language definition)
	 */
	public function getActions() {
		return array(
			array(
				'action'=>"widget_staff_home",
				'uri'=>"widget/feed_reader/admin_main/",
				'name'=>Language::_("FeedReaderPlugin.name", true)
			)
		);
	}
}
?>