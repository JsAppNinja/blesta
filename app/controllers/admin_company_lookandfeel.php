<?php
/**
 * Admin Company Look And Feel Settings
 * 
 * @package blesta
 * @subpackage blesta.app.controllers
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class AdminCompanyLookandfeel extends AppController {
	
	/**
	 * Pre-action
	 */
	public function preAction() {
		parent::preAction();
		
		// Require login
		$this->requireLogin();		
		
		$this->uses(array("Navigation", "Companies"));
		$this->components(array("SettingsCollection"));
		
		Language::loadLang("admin_company_lookandfeel");
		
		// Set the left nav for all settings pages to settings_leftnav
		$this->set("left_nav", $this->partial("settings_leftnav", array("nav"=>$this->Navigation->getCompany($this->base_uri))));
	}

	/**
	 * Index
	 */
	public function index() {
		$this->redirect($this->base_uri . "settings/company/lookandfeel/template/");
	}

	/**
	 * Set template
	 */
	public function template() {

		$client_view_dirs = $this->Companies->getViewDirs("client");
		foreach ($client_view_dirs as $dir => $info) {
			$client_view_dirs[$dir] = $info->name;
		}
		
		if (!empty($this->post)) {
			$this->Companies->setSettings($this->company_id, $this->post, array("client_view_dir"));
			
			$this->setMessage("message", Language::_("AdminCompanyLookandfeel.!success.template_updated", true));
		}
		
		
		$this->set("client_view_dirs", $client_view_dirs);
		$this->set("vars", $this->SettingsCollection->fetchSettings($this->Companies, $this->company_id));
	}
	
	/**
	 *
	 */
	public function customize() {
		#
		# TODO: Set custom content CORE-828
		#
		return false;
	}

}
?>