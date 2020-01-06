<?php
/**
 * Download Manager plugin handler
 * 
 * @package blesta
 * @subpackage blesta.plugins.download_manager
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class DownloadManagerPlugin extends Plugin {

	/**
	 * @var string The version of this plugin
	 */
	private static $version = "2.0.2";
	/**
	 * @var string The authors of this plugin
	 */
	private static $authors = array(array('name'=>"Phillips Data, Inc.",'url'=>"http://www.blesta.com"));
	
	public function __construct() {
		Language::loadLang("download_manager_plugin", null, dirname(__FILE__) . DS . "language" . DS);
		
		// Load components required by this plugin
		Loader::loadComponents($this, array("Input", "Record"));
	}
	
	/**
	 * Returns the name of this plugin
	 *
	 * @return string The common name of this plugin
	 */
	public function getName() {
		return Language::_("DownloadManagerPlugin.name", true);	
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
		if (!isset($this->Record))
			Loader::loadComponents($this, array("Record"));
		
		// Add all download tables, *IFF* not already added
		try {
			// download_files
			$this->Record->
				setField("id", array('type'=>"int", 'size'=>10, 'unsigned'=>true, 'auto_increment'=>true))->
				setField("category_id", array('type'=>"int", 'size'=>10, 'unsigned'=>true, 'is_null'=>true, 'default'=>null))->
				setField("company_id", array('type'=>"int", 'size'=>10, 'unsigned'=>true))->
				setField("name", array('type'=>"varchar", 'size'=>255))->
				setField("file_name", array('type'=>"varchar", 'size'=>255))->
				setField("public", array('type'=>"tinyint", 'size'=>1, 'default'=>0))->
				setField("permit_client_groups", array('type'=>"tinyint", 'size'=>1, 'default'=>0))->
				setField("permit_packages", array('type'=>"tinyint", 'size'=>1, 'default'=>0))->
				setKey(array("id"), "primary")->
				setKey(array("category_id"), "index")->
				setKey(array("company_id"), "index")->
				create("download_files", true);
			
			// download_categories
			$this->Record->
				setField("id", array('type'=>"int", 'size'=>10, 'unsigned'=>true, 'auto_increment'=>true))->
				setField("parent_id", array('type'=>"int", 'size'=>10, 'unsigned'=>true, 'is_null'=>true, 'default'=>null))->
				setField("company_id", array('type'=>"int", 'size'=>10, 'unsigned'=>true))->
				setField("name", array('type'=>"varchar", 'size'=>255))->
				setField("description", array('type'=>"text"))->
				setKey(array("id"), "primary")->
				setKey(array("parent_id"), "index")->
				setKey(array("company_id"), "index")->
				create("download_categories", true);
			
			// download_file_groups
			$this->Record->
				setField("file_id", array('type'=>"int", 'size'=>10, 'unsigned'=>true))->
				setField("client_group_id", array('type'=>"int", 'size'=>10, 'unsigned'=>true))->
				setKey(array("file_id", "client_group_id"), "primary")->
				create("download_file_groups", true);
			
			// download_file_packages
			$this->Record->
				setField("file_id", array('type'=>"int", 'size'=>10, 'unsigned'=>true))->
				setField("package_id", array('type'=>"int", 'size'=>10, 'unsigned'=>true))->
				setKey(array("file_id", "package_id"), "primary")->
				create("download_file_packages", true);
			
			// download_logs
			$this->Record->
				setField("id", array('type'=>"int", 'size'=>10, 'unsigned'=>true, 'auto_increment'=>true))->
				setField("file_id", array('type'=>"int", 'size'=>10, 'unsigned'=>true))->
				setField("client_id", array('type'=>"int", 'size'=>10, 'unsigned'=>true, 'is_null'=>true, 'default'=>null))->
				setField("contact_id", array('type'=>"int", 'size'=>10, 'unsigned'=>true, 'is_null'=>true, 'default'=>null))->
				setField("date_added", array('type'=>"datetime"))->
				setKey(array("id"), "primary")->
				setKey(array("file_id"), "index")->
				setKey(array("client_id"), "index")->
				setKey(array("contact_id"), "index")->
				create("download_logs", true);
			
			// Set the uploads directory
			Loader::loadComponents($this, array("SettingsCollection", "Upload"));
			$temp = $this->SettingsCollection->fetchSetting(null, Configure::get("Blesta.company_id"), "uploads_dir");
			$upload_path = $temp['value'] . Configure::get("Blesta.company_id") . DS . "download_files" . DS;
			// Create the upload path if it doesn't already exist
			$this->Upload->createUploadPath($upload_path, 0777);
		}
		catch (Exception $e) {
			// Error adding... no permission?
			$this->Input->setErrors(array('db'=> array('create'=>$e->getMessage())));
			return;
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
		// Remove the tables created by this plugin
		if ($last_instance) {
			try {
				$this->Record->drop("download_categories");
				$this->Record->drop("download_files");
				$this->Record->drop("download_file_groups");
				$this->Record->drop("download_file_packages");
				$this->Record->drop("download_logs");
			}
			catch (Exception $e) {
				// Error dropping... no permission?
				$this->Input->setErrors(array('db'=> array('create'=>$e->getMessage())));
				return;
			}
		}
	}
	
	/**
	 * Returns all actions to be configured for this widget (invoked after install() or upgrade(), overwrites all existing actions)
	 *
	 * @return array A numerically indexed array containing:
	 * 	-action The action to register for
	 * 	-uri The URI to be invoked for the given action
	 * 	-name The name to represent the action (can be language definition)
	 */
	public function getActions() {
		return array(
			array(
				'action'=>"nav_primary_client",
				'uri'=>"plugin/download_manager/client_main/",
				'name'=>Language::_("DownloadManagerPlugin.client_main", true)
			)
		);
	}
}
?>