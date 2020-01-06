<?php
/**
 * Client Parent Controller
 */
class ClientController extends AppController {
	
	/**
	 * Pre action
	 */
	public function preAction() {
		parent::preAction();
		
		$class_name = get_class($this);
		Language::loadLang(Loader::fromCamelCase($class_name));

		// Allow states to be fetched without login
		if ($class_name == "ClientMain" && strtolower($this->action) == "getstates")
			return;

		// Require login
		$this->requireLogin();
		
		// Attempt to set the page title language
		try {
			$language = Language::_($class_name . "." . Loader::fromCamelCase($this->action ? $this->action : "index") . ".page_title", true, $this->client->id_code);
			$this->structure->set("page_title", $language);
		}
		catch(Exception $e) {
			// Attempting to set the page title language has failed, likely due to
			// the language definition requiring multiple parameters.
			// Fallback to index. Assume the specific page will set its own page title otherwise.
			$this->structure->set("page_title", Language::_($class_name . ".index.page_title", true, $this->client->id_code));
		}
	}
	
	/**
	 * Outputs a JSON encoded array of all widgets to be displayed within the requested controller.
	 * Expects $this->get[0] to identify the client ID for which items are to be rendered.
	 */
	public function getWidgets() {
		if (!isset($this->PluginManager))
			$this->uses(array("PluginManager"));
			
		$this->clientWidgets();
		return false;
	}
	
	/**
	 * Checks whether the current user is a staff user and whether the user is
	 * currently logged into the client portal.
	 *
	 * @return boolean True if the user is a staff user logged in as a client, false otherwise
	 */
	protected function isStaffAsClient() {
		return (isset($this->Session) && $this->Session->read("blesta_staff_id") > 0);
	}
	
	/**
	 * Outputs a JSON encoded array of admin widgets for the requested controller
	 */
	protected function clientWidgets() {
		$widgets = array();
		$widget_location = null;
		switch ($this->controller) {
			case "client_main":
				$widget_location = "widget_client_home";
				
				// Set the default widgets to appear
				$widgets = array(
					'client_invoices'=>array('uri'=>$this->base_uri . "invoices/?whole_widget=true"),
					'client_services'=>array('uri'=>$this->base_uri . "services/?whole_widget=true"),
					'client_transactions'=>array('uri'=>$this->base_uri . "transactions/?whole_widget=true")
				);
				break;
		}
		
		// Ensure a section was requested... may be used in the future
		if (!isset($this->get['section']))
			return false;
		$section = $this->get['section'];
		
		// Load widgets configured for display
		$plugin_actions = $this->PluginManager->getActions($this->company_id, $widget_location, true);
		foreach ($plugin_actions as $plugin) {
			$key = str_replace("/", "_", trim($plugin->uri, "/"));
			$widgets[$key] = array(
				'uri'=>$this->base_uri . $plugin->uri
			);
		}
		
		$this->outputAsJson($widgets);
	}
	
	/**
	 * Sets the primary and secondary navigation links. Performs authorization checks on each navigational element.
	 * May cache nav results if possible for better performance.
	 */
	protected function setNav() {
		$nav = array();
		
		$this->uses(array("Navigation"));
		
		$nav = $this->setNavActive($this->Navigation->getPrimaryClient($this->client_uri));

		$this->structure->set("nav", $nav);
	}
	
	/**
	 * {@inheritdoc}
	 */
	protected function requireLogin($redirect_to = null) {
		parent::requireLogin($redirect_to);
		
		$area = $this->plugin ? $this->plugin . ".*" : $this->controller;
		$this->requirePermission($area);
	}
	
	/**
	 * Verifies permissions for the given generic $area
	 *
	 * @param string $area The generic area
	 * @param string $redirect_to The URI or URL to redirect to if permissions do not exist
	 */
	protected function requirePermission($area) {
		$allowed = $this->hasPermission($area);
		
		if (!$allowed) {
			if ($this->isAjax()) {
				// If ajax, send 403 response, user not granted access
				header($this->server_protocol . " 403 Forbidden");
				exit();
			}

			$this->setMessage("error", Language::_("AppController.!error.unauthorized_access", true), false, null, false);
			$this->render("unauthorized", Configure::get("System.default_view"));
			exit();
		}
	}
	
	/**
	 * Verifies if the current user has permission to the given area
	 *
	 * @param string $area The generic area
	 * @return boolean True if user has permission, false otherwise
	 */
	protected function hasPermission($area) {
		if (!isset($this->Contacts))
			$this->uses(array("Contacts"));
		
		if (($contact = $this->Contacts->getByUserId($this->Session->read("blesta_id"), $this->Session->read("blesta_client_id")))) {
			return $this->Contacts->hasPermission($this->company_id, $contact->id, $area);
		}
		return true;
	}
	
	/**
	 * Verifies that the currently logged in user is authorized for the given Controller and Action (or current Controller/Action if none given).
	 * Will first check whether the Controller and Action is a permission value, and if so, checks
	 * to ensure the staff or client group user is authorized to access that resource
	 *
	 * @param string $controller The controller to check authorization on, null will default to the current controller
	 * @param string $action The action to check authorization on, null will default to the current action
	 * @param stdClass $group The staff or client group to check authorization on, null will fetch the group of the current user
	 * @return boolean Returns true if the user is authorized for that resource, false otherwise
	 */
	protected function authorized($controller=null, $action=null, $group=null) {
		
		$prefix = null;
		// Alias for plugin controllers is plugin.controller
		if ($this->plugin && $controller === null)
			$prefix = $this->plugin . ".";
		$controller = $prefix . ($controller === null ? $this->controller : $controller);
		$action = ($action === null ? $this->action : $action);
		
		if ($this->Session->read("blesta_client_id") > 0) {

			if (!isset($this->client)) {
				if (!isset($this->Clients))
					$this->uses(array("Clients"));
				
				// Staff as Client
				if ($this->Session->read("blesta_staff_id")) {
					$client = $this->Clients->get($this->Session->read("blesta_client_id"), true);
				}
				// Contact/Client
				else {
					$client = $this->Clients->getByUserId($this->Session->read("blesta_id"), true);
				}

				if (!$client || $client->status != "active") {
					$this->Session->clear();
					return false;
				}
				$this->client = $client;
			}
		
			return $this->hasPermission($controller);
		}
		return false;
	}
}
?>