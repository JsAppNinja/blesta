<?php
/**
 * CMS plugin handler
 * 
 * @package blesta
 * @subpackage blesta.plugins.cms
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class CmsPlugin extends Plugin {
	/**
	 * @var string The version of this plugin
	 */
	private static $version = "2.2.1";
	/**
	 * @var string The authors of this plugin
	 */
	private static $authors = array(array('name'=>"Phillips Data, Inc.",'url'=>"http://www.blesta.com"));
	
	public function __construct() {
		Language::loadLang("cms_plugin", null, dirname(__FILE__) . DS . "language" . DS);
	}
	
	/**
	 * Returns the name of this plugin
	 *
	 * @return string The common name of this plugin
	 */
	public function getName() {
		return Language::_("CmsPlugin.name", true);	
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
			Loader::loadComponents($this, array("Input", "Record"));
		
		Configure::load("cms", dirname(__FILE__) . DS . "config" . DS);
		
		// Add the CMS tables, *IFF* not already added
		try {
			$this->Record->
				setField("uri", array('type'=>"varchar", 'size'=>255))->
				setField("company_id", array('type'=>"int", 'size'=>10, 'unsigned'=>true))->
				setField("title", array('type'=>"varchar", 'size'=>255))->
				setField("content", array('type'=>"text"))->
				setKey(array("uri", "company_id"), "primary")->
				create("cms_pages", true);
			
			// Install default index page
			$vars = array(
				'uri' => "/",
				'company_id' => Configure::get("Blesta.company_id"),
				'title' => Language::_("CmsPlugin.index.title", true),
				'content' => Configure::get("Cms.index.content_install_notice") . Configure::get("Cms.index.content")
			);
			
			// Add the index page
			$fields = array("uri", "company_id", "title", "content");
			try {
				// Attempt to add the page
				$this->Record->insert("cms_pages", $vars, $fields);
			}
			catch (Exception $e) {
				// Do nothing; re-use the existing entry
			}
		}
		catch(Exception $e) {
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
		if (!isset($this->Record))
			Loader::loadComponents($this, array("Record"));
		
		// Upgrade if possible
		if (version_compare($this->getVersion(), $current_version, ">")) {
			Configure::load("cms", dirname(__FILE__) . DS . "config" . DS);
			
			// Upgrade to v1.0.2
			if (version_compare($current_version, "1.0.2", "<")) {
				// Update the index page for all companies
				$index_pages = $this->Record->select()->from("cms_pages")->where("uri", "=", "/")->fetchAll();
				
				// Replace order URL
				foreach ($index_pages as $index_page) {
					$new_content = str_replace("{base_url}order/", "{blesta_url}order/", $index_page->content);
					
					$vars = array('content' => $new_content);
					$this->Record->where("uri", "=", "/")->where("company_id", "=", $index_page->company_id)->
						update("cms_pages", $vars, array("content"));
				}
			}
			
			// Upgrade to v2.0.0
			if (version_compare($current_version, "2.0.0", "<")) {
				// Replace the index page for all companies
				$index_pages = $this->Record->select()->from("cms_pages")->where("uri", "=", "/")->fetchAll();
				$vars = array('content' => Configure::get("Cms.index.content"));
				
				foreach ($index_pages as $index_page) {
                    // Update the default page title
                    $vars['title'] = ($index_page->title == "Portal" ? Language::_("CmsPlugin.index.title", true) : $index_page->title);

					$this->Record->where("uri", "=", "/")->where("company_id", "=", $index_page->company_id)->
						update("cms_pages", $vars, array("content", "title"));
				}
			}
			
			// Upgrade to v2.2.0
			if (version_compare($current_version, "2.2.0", "<")) {
				// Replace the index page for all companies
				$index_pages = $this->Record->select()->from("cms_pages")->where("uri", "=", "/")->fetchAll();
				
				foreach ($index_pages as $index_page) {
                    // Update conditionals in the body to check that each plugin is enabled
					$search_replace = array(
						'{% if plugins.support_manager %}' => "{% if plugins.support_manager.enabled %}",
						'{% if plugins.order %}' => "{% if plugins.order.enabled %}",
						'{% if plugins.download_manager %}' => "{% if plugins.download_manager.enabled %}"
					);
					$vars = array(
						'content' => str_replace(array_keys($search_replace), array_values($search_replace), $index_page->content)
					);
					
					$this->Record->where("uri", "=", "/")->where("company_id", "=", $index_page->company_id)->
						update("cms_pages", $vars, array("content"));
				}
			}
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
		
		// Remove all tables *IFF* no other company in the system is using this plugin
		if ($last_instance) {
			try {
				$this->Record->drop("cms_pages");
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
	 * 	- action The action to register for
	 * 	- uri The URI to be invoked for the given action
	 * 	- name The name to represent the action (can be language definition)
	 */
	public function getActions() {
		return array();
	}
}
?>