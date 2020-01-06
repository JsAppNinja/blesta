<?php
/**
 * CMS manage plugin controller
 * 
 * @package blesta
 * @subpackage blesta.plugins.cms.controllers
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
		
		Language::loadLang("cms_manage_plugin", null, PLUGINDIR . "cms" . DS . "language" . DS);
		
		// Set the company ID
		$this->company_id = Configure::get("Blesta.company_id");
		
		// Set the plugin ID
		$this->plugin_id = (isset($this->get[0]) ? $this->get[0] : null);
		
		// Set the Javascript helper
		$this->Javascript = $this->parent->Javascript;
		
		// Set the page title
		$this->parent->structure->set("page_title", Language::_("CmsManagePlugin." . Loader::fromCamelCase($this->action ? $this->action : "index") . ".page_title", true));
		
		// Set the view to render for all actions under this controller
		$this->view->setView(null, "Cms.default");
	}
	
	/**
	 * Returns the view to be rendered when managing this plugin
	 */
	public function index() {
		$this->init();
		
		$this->uses(array("Cms.CmsPages"));
		
		if (!empty($this->post)) {
			$data = $this->post;
			$data['company_id'] = $this->company_id;
			$data['uri'] = "/";
			
			// Add the page
			$this->CmsPages->add($data);
			
			if (($errors = $this->CmsPages->errors())) {
				// Error, reset vars
				$vars = (object)$this->post;
				$this->parent->setMessage("error", $errors);
			}
			else {
				// Success
				$this->parent->flashMessage("message", Language::_("CmsManagePlugin.!success.plugin_updated", true));
				$this->redirect($this->base_uri . "settings/company/plugins/installed/");
			}
		}
		
		if (!isset($vars))
			$vars = $this->CmsPages->get("/", $this->company_id);
		
		// Set default tags for index page
		$tags = array("{base_url}", "{blesta_url}", "{admin_url}", "{client_url}", "{plugins}");
		
		// Include WYSIWYG
		$this->Javascript->setFile("ckeditor/ckeditor.js", "head", VENDORWEBDIR);
		$this->Javascript->setFile("ckeditor/adapters/jquery.js", "head", VENDORWEBDIR);
		
		// Set the view to render
		return $this->partial("admin_manage_plugin", array('vars' => $vars, 'tags' => $tags));
	}
}
?>