<?php
/**
 * Client maintenance controller
 *
 * @package blesta
 * @subpackage blesta.app.controllers
 * @copyright Copyright (c) 2014, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class ClientMaintenance extends AppController {
	
	public function preAction() {
		parent::preAction();
		
		Language::loadLang(array("client_maintenance"));
	}
	
	public function index() {
		$this->helpers(array("TextParser"));
		$this->components(array("SettingsCollection"));
		
		// Determine maintenance mode
		$system_settings = $this->SettingsCollection->fetchSystemSettings();
		
		// Redirect to client login if maintenance mode is not enabled
		if (!isset($system_settings['maintenance_mode']) || $system_settings['maintenance_mode'] != "true")
			$this->redirect($this->base_uri . "login/");
		
		$this->set("maintenance_reason", $this->TextParser->encode("markdown", $system_settings['maintenance_reason']));
		$this->set("company", Configure::get("Blesta.company"));
	}
}
?>