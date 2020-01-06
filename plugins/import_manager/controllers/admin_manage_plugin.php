<?php
/**
 * Import Manager manage plugin controller
 * 
 * @package blesta
 * @subpackage blesta.plugins.import_manager
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class AdminManagePlugin extends AppController {
	
	/**
	 * Performs necessary initialization
	 */
	private function init() {
		// Require login
		$this->parent->requireLogin();
		
		Language::loadLang("import_manager_manage_plugin", null, PLUGINDIR . "import_manager" . DS . "language" . DS);

		$this->uses(array("ImportManager.ImportManagerImporter"));
		// Use the parent date helper, it's already configured properly
		$this->Date = $this->parent->Date;
		
		// Set the company ID
		$this->company_id = Configure::get("Blesta.company_id");
		
		// Set the plugin ID
		$this->plugin_id = (isset($this->get[0]) ? $this->get[0] : null);
		
		// Set the page title
		$this->parent->structure->set("page_title", Language::_("ImportManagerManagePlugin." . Loader::fromCamelCase($this->action ? $this->action : "index") . ".page_title", true));
		
		// Set the view to render for all actions under this controller
		$this->view->setView(null, "ImportManager.default");
	}
	
	/**
	 * Returns the view to be rendered when managing this plugin
	 */
	public function index() {
		$this->init();
		
		$vars = array('migrators' => $this->ImportManagerImporter->getMigrators(), 'plugin_id' => $this->plugin_id);
		
		// Set the view to render
		return $this->partial("admin_manage_plugin", $vars);
	}
	
	/**
	 * Import from a migrator
	 */
	public function import() {
		$this->init();
		
		$this->components(array("ImportManager.Migrators"));
		
		$type = isset($this->get[1]) ? $this->get[1] : null;
		$version = isset($this->get[2]) ? $this->get[2] : null;
		$migrator = $this->Migrators->create($type, $version, array($this->ImportManagerImporter->Record));
		
		if (!$migrator)
			$this->redirect($this->base_uri . "settings/company/plugins/manage/" . $this->plugin_id . "/");

		$vars = array('content' => $migrator->getSettings($this->post));
		$vars['continue'] = $migrator->getConfiguration($this->post) != null;
		
		$migrate = false;
		if (!empty($this->post)) {
			if (!isset($this->post['step']))
				$this->post['step'] = "settings";
			
			switch ($this->post['step']) {
				default:
				case "settings":
					$migrator->processSettings($this->post);

					if (($errors = $migrator->errors())) {
						$vars['message'] = $this->setMessage("error", $errors, true, null, false);
						$vars['content'] = $migrator->getSettings($this->post);
					}
					// Request configuration options
					elseif (($content = $migrator->getConfiguration($this->post)) != null) {
						$vars['continue'] = false;
						$vars['content'] = $content;
					}
					// Process migration
					else
						$migrate = true;
					
					break;
				case "configuration":
					$vars['continue'] = false;
					$migrator->processSettings($this->post);
					$migrator->processConfiguration($this->post);
					
					if (($errors = $migrator->errors())) {
						$vars['message'] = $this->setMessage("error", $errors, true, null, false);
						$vars['content'] = $migrator->getConfiguration($this->post);
					}
					// Process migration
					else
						$migrate = true;
					
					break;
			}
			
			if ($migrate) {
				$this->ImportManagerImporter->runMigrator($type, $version, $this->post);
	
				if (($errors = $this->ImportManagerImporter->errors())) {
					$vars['message'] = $this->setMessage("error", $errors, true, null, false);
				}
				else {
					$this->parent->flashMessage("message", Language::_("ImportManagerManagePlugin.!success.imported", true), null, false);
					$this->redirect($this->base_uri . "settings/company/plugins/manage/" . $this->plugin_id . "/");
				}				
			}
		}

		$vars['type'] = $type;
		$vars['info'] = $this->ImportManagerImporter->getMigrator($type);
		$vars['version'] = $version;
		$vars['plugin_id'] = $this->plugin_id;
		
		// Set the view to render
		return $this->partial("admin_manage_plugin_import", $vars);
		
		/*
		$this->init();
		
		$this->components(array("ImportManager.Migrators"));
		
		$type = isset($this->get[1]) ? $this->get[1] : null;
		$version = isset($this->get[2]) ? $this->get[2] : null;
		$migrator = $this->Migrators->create($type, $version);
		
		if (!$migrator)
			$this->redirect($this->base_uri . "settings/company/plugins/manage/" . $this->plugin_id . "/");

		$vars = array();
		
		if (!empty($this->post)) {
			$this->ImportManagerImporter->runMigrator($type, $version, $this->post);

			if (($errors = $this->ImportManagerImporter->errors())) {
				$vars['message'] = $this->setMessage("error", $errors, true, null, false);
			}
			else {
				$this->parent->flashMessage("message", Language::_("ImportManagerManagePlugin.!success.imported", true), null, false);
				$this->redirect($this->base_uri . "settings/company/plugins/manage/" . $this->plugin_id . "/");
			}
		}

		$vars['type'] = $type;
		$vars['info'] = $this->ImportManagerImporter->getMigrator($type);
		$vars['version'] = $version;
		$vars['content'] = $migrator->getSettings($this->post);
		$vars['plugin_id'] = $this->plugin_id;
		
		// Set the view to render
		return $this->partial("admin_manage_plugin_import", $vars);
		*/
	}
}
?>