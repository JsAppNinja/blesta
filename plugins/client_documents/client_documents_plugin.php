<?php
/**
 * Client Documents plugin handler
 * 
 * @package blesta
 * @subpackage blesta.plugins.client_documents
 * @copyright Copyright (c) 2014, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class ClientDocumentsPlugin extends Plugin {
	
	public function __construct() {
		Language::loadLang("client_documents_plugin", null, dirname(__FILE__) . DS . "language" . DS);
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
		
		// Add tables, *IFF* not already added
		try {
			// download_files
			$this->Record->
				setField("id", array('type'=>"int", 'size'=>10, 'unsigned'=>true, 'auto_increment'=>true))->
				setField("client_id", array('type'=>"int", 'size'=>10, 'unsigned'=>true))->
				setField("name", array('type'=>"varchar", 'size'=>255))->
				setField("file_name", array('type'=>"varchar", 'size'=>255))->
				setField("description", array('type'=>"text", 'is_null'=>true, 'default'=>null))->
				setField("date_added", array('type'=>"datetime"))->
				setKey(array("id"), "primary")->
				setKey(array("client_id"), "index")->
				create("client_documents", true);
				
			// Set the uploads directory
			Loader::loadComponents($this, array("SettingsCollection", "Upload"));
			$temp = $this->SettingsCollection->fetchSetting(null, Configure::get("Blesta.company_id"), "uploads_dir");
			$upload_path = $temp['value'] . Configure::get("Blesta.company_id") . DS . "client_documents" . DS;
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
	 * Performs any necessary cleanup actions
	 *
	 * @param int $plugin_id The ID of the plugin being uninstalled
	 * @param boolean $last_instance True if $plugin_id is the last instance across all companies for this plugin, false otherwise
	 */
	public function uninstall($plugin_id, $last_instance) {
		
		if ($last_instance) {
			if (!isset($this->Record))
				Loader::loadComponents($this, array("Record"));

			$this->Record->drop("client_documents");
		}
	}
	
	/**
	 * Returns all actions to be configured for this widget (invoked after install() or upgrade(), overwrites all existing actions)
	 *
	 * @return array A numerically indexed array containing:
	 * 	- action The action to register for
	 * 	- uri The URI to be invoked for the given action
	 * 	- name The name to represent the action (can be language definition)
	 * 	- options An array of key/value pair options for the given action
	 */
	public function getActions() {
		return array(
			// Client Nav
			array(
				'action' => "nav_primary_client",
				'uri' => "plugin/client_documents/client_main/index/",
				'name' => Language::_("ClientDocumentsPlugin.nav_primary_client.main", true)
			),
			// Client Profile Action Link
			array(
				'action' => "action_staff_client",
				'uri' => "plugin/client_documents/admin_main/index/",
				'name' => Language::_("ClientDocumentsPlugin.action_staff_client.index", true),
				'options' => array(
					'class' => "invoice"
				)
			)
		);
	}
}
?>