<?php
/**
 * Plugin manager. Handles installing/uninstalling plugins through their respective plugin handlers.
 *
 * @package blesta
 * @subpackage blesta.app.models
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class PluginManager extends AppModel {
	
	/**
	 * Initialize Plugin Manager
	 */
	public function __construct() {
		parent::__construct();
		Language::loadLang(array("plugin_manager"));
	}
	
	/**
	 * Lists all installed plugins
	 *
	 * @param int $company_id The company ID
	 * @param string $sort_by The field to sort by
	 * @param string $order The direction to order results
	 * @return array An array of stdClass objects representing installed plugins
	 */
	public function getAll($company_id, array $order = array('name'=>"asc")) {
		$fields = array("id", "dir", "company_id", "name", "version", "enabled");
		
		$plugins = $this->Record->select($fields)->from("plugins")->where("company_id", "=", $company_id)->
			order($order)->fetchAll();
		
		$num_plugins = count($plugins);
		for ($i=0; $i<$num_plugins; ) {
			try {
				$plugin = $this->loadPlugin($plugins[$i]->dir);
				
				// Set the installed version of the plugin
				$plugins[$i]->installed_version = $plugins[$i]->version;
			}
			catch (Exception $e) {
				// Plugin could not be loaded
				$i++;
				continue;
			}
			
			$info = $this->getPluginInfo($plugin, $company_id);
			foreach ((array)$info as $key=>$value)
				$plugins[$i]->$key = $value;
			$i++;
		}
		
		return $plugins;
	}
	
	/**
	 * Fetches all plugins installed in the system
	 *
	 * @return array An array of stdClass objects, each representing an installed plugin record
	 */
	public function getInstalled() {
		$fields = array("id", "dir", "company_id", "name", "version", "enabled");
		
		return $this->Record->select($fields)->from("plugins")->fetchAll();
	}
	
	/**
	 * Fetches a plugin for a given company, or all plugins installed in the system for the given plugin directory
	 *
	 * @param string $plugin_dir The directory name of the plugin to return results for
	 * @param int $company_id The ID of the company to fetch plugins for
	 * @return array An array of stdClass objects, each representing an installed plugin record
	 */
	public function getByDir($plugin_dir, $company_id = null) {
		$fields = array("id", "dir", "company_id", "name", "version", "enabled");
		
		$this->Record->select($fields)->from("plugins")->
			where("dir", "=", $plugin_dir);
		if ($company_id !== null)
			$this->Record->where("company_id", "=", $company_id);
			
		return $this->Record->fetchAll();
	}
	
	/**
	 * Fetches a single installed plugin.
	 *
	 * @param int $plugin_id The plugin ID to fetch
	 * @param boolean $detailed True to return detailed information about the plugin, false otherwise
	 * @return mixed A stdClass object representing the installed plugin, false if no such plugin exists or is not installed
	 */
	public function get($plugin_id, $detailed=false) {
		$company_id = Configure::get("Blesta.company_id");
		$fields = array("id", "dir", "company_id", "name", "version", "enabled");
		
		$plugin = $this->Record->select($fields)->from("plugins")->
			where("id", "=", $plugin_id)->where("company_id", "=", $company_id)->fetch();
		
		if ($plugin && $detailed) {
			try {
				$loaded_plugin = $this->loadPlugin($plugin->dir);
				
				// Set the installed version of the plugin
				$plugin->installed_version = $plugin->version;
				
				$info = $this->getPluginInfo($loaded_plugin, $company_id);
				foreach ((array)$info as $key=>$value)
					$plugin->$key = $value;
			}
			catch (Exception $e) {
				// Plugin could not be loaded
			}
		}
		
		return $plugin;
	}
	
	/**
	 * Lists all available plugins (those that exist on the file system)
	 *
	 * @param int $company_id The ID of the company to get available plugins for
	 * @return array An array of stdClass objects representing available plugins
	 */
	public function getAvailable($company_id=null) {
		$plugins = array();

		$dir = opendir(PLUGINDIR);
		for ($i=0; false !== ($file = readdir($dir)); ) {
			// If the file is not a hidden file, and is a directory, accept it
			if (substr($file, 0, 1) != "." && is_dir(PLUGINDIR . DS . $file)) {
				// Ensure a plugin handler is available, which is required to install or uninstall this plugin
				if (file_exists(PLUGINDIR . DS . $file . DS . $file . "_plugin.php")) {
					
					try {
						$plugin = $this->loadPlugin($file);
						$plugins[$i] = new stdClass();
					}
					catch (Exception $e) {
						// The plugins could not be loaded, try the next one
						continue;
					}
					
					$info = $this->getPluginInfo($plugin, $company_id);
					foreach ((array)$info as $key=>$value)
						$plugins[$i]->$key = $value;
					$i++;
				}
			}
		}
		
		// Close the directory, we're done now
		closedir($dir);
		
		return $plugins;
	}
	
	
	/**
	 * Checks whether the given plugin is installed for the specified company
	 *
	 * @param string $dir The plugin dir (in file_case)
	 * @param int $company_id The ID of the company to fetch for (null checks if the plugin is installed across any company)
	 * @return boolean True if the plugin is installed, false otherwise
	 */
	public function isInstalled($dir, $company_id=null) {
		$this->Record->select(array("plugins.id"))->from("plugins")->
			where("dir", "=", $dir);
			
		if ($company_id)
			$this->Record->where("company_id", "=", $company_id);
			
		return (boolean)$this->Record->fetch();
	}
	
	/**
	 * Checks whether the given plugin is the last instance installed
	 *
	 * @param string $dir The plugin dir (in file_case)
	 * @return boolean True if the plugin is the last instance, false otherwise
	 */
	public function isLastInstance($dir) {
		$count = $this->Record->select(array("plugins.id"))->from("plugins")->
			where("dir", "=", $dir)->numResults();
		
		return ($count <= 1);
	}
	
	/**
	 * Adds the plugin to the system
	 *
	 * @param array $vars An array of plugin information including:
	 * 	- dir The dir name for the plugin to be installed
	 * 	- company_id The ID of the company the plugin should be installed for
	 * 	- staff_group_id The ID of the staff group to grant access to all permissions created by this plugin (optional)
	 * @return int The ID of the plugin installed, void on error
	 */
	public function add(array $vars) {
		$plugin = $this->loadPlugin($vars['dir']);
		
		$vars['version'] = $plugin->getVersion();
		$vars['name'] = $plugin->getName();
		
		// Begin transaction adding the plugin
		$this->Record->begin();
		$fields = array("company_id", "name", "dir", "version");
		$this->Record->insert("plugins", $vars, $fields);
		$plugin_id = $this->Record->lastInsertId();
		
		// Run the installation
		$plugin->install($plugin_id);
		
		// Check for errors installing
		if (($errors = $plugin->errors())) {
			$this->Input->setErrors($errors);
			
			// Rollback adding the plugin
			$this->Record->rollBack();
			
			// Just in case rollback failed due to 'create' table explicit commit
			// automatically delete the installed plugin
			$this->Record->from("plugins")->where("plugins.id", "=", $plugin_id)->delete();
			return;
		}
		
		$this->Input->setRules($this->getAddRules($vars));
		
		// Install the plugin, and plugin actions and events (if any)
		if ($this->Input->validates($vars)) {
			// Commit adding the plugin
			$this->Record->commit();
			
			$actions = $plugin->getActions();
			$events = $plugin->getEvents();
			
			if ($actions && is_array($actions)) {
				foreach ($actions as $action)
					$this->addAction($plugin_id, $action);
			}
			
			if ($events && is_array($events)) {
				foreach ($events as $event)
					$this->addEvent($plugin_id, $event);
			}
			
			// Gran all permissions to this staff group
			if (isset($vars['staff_group_id'])) {
				Loader::loadModels($this, array("StaffGroups"));
				
				$this->StaffGroups->grantPermission($vars['staff_group_id'], $plugin_id);
			}
			
			return $plugin_id;
		}
		else {
			// Rollback if validation failed
			$this->Record->rollBack();
		}
	}
	
	/**
	 * Runs the plugin's upgrade method to upgrade the plugin to match that of the plugin's file version.
	 * Sets errors in PluginManager::errors() if any errors are set by the plugin's upgrade method.
	 *
	 * @param int $plugin_id The ID of the plugin to upgrade
	 */
	public function upgrade($plugin_id) {
		$installed_plugin = $this->get($plugin_id);
		
		if (!$installed_plugin)
			return;
		
		$plugin = $this->loadPlugin($installed_plugin->dir);
		$file_version = $plugin->getVersion();
		
		// Execute the upgrade if the installed version doesn't match the file version
		if (version_compare($file_version, $installed_plugin->version, "!=")) {
			$plugin->upgrade($installed_plugin->version, $plugin_id);
			
			if (($errors = $plugin->errors()))
				$this->Input->setErrors($errors);
			else {
				$actions = $plugin->getActions();
				$events = $plugin->getEvents();
				
				$this->Record->from("plugin_actions")->where("plugin_id", "=", $plugin_id)->delete();
				$this->Record->from("plugin_events")->where("plugin_id", "=", $plugin_id)->delete();
				
				if ($actions && is_array($actions)) {
					foreach ($actions as $action)
						$this->addAction($plugin_id, $action);
				}
				
				if ($events && is_array($events)) {
					foreach ($events as $event)
						$this->addEvent($plugin_id, $event);
				}
				
				// Update all installed plugins to the given version
				$this->setVersion($installed_plugin->dir, $file_version);
			}
		}
	}
	
	/**
	 * Permanently and completely removes the plugin specified by $plugin_id
	 *
	 * @param int $plugin_id The ID of the plugin to permanently remove
	 */
	public function delete($plugin_id) {
		$plugin = $this->get($plugin_id);
		
		// If the plugin added permissions or permission groups, it's the responsibility
		// of the plugin to remove those items as well as any other tables or entries
		// it has created that are no longer relevant
		$plugin_handler = $this->loadPlugin($plugin->dir);
		$plugin_handler->uninstall($plugin_id, $this->isLastInstance($plugin->dir));

		if (($errors = $plugin_handler->errors())) {
			$this->Input->setErrors($errors);
			return;
		}
		
		$this->Record->from("plugins")->where("id", "=", $plugin_id)->delete();
		$this->Record->from("plugin_actions")->where("plugin_id", "=", $plugin_id)->delete();
		$this->Record->from("plugin_events")->where("plugin_id", "=", $plugin_id)->delete();
	}
	
	/**
	 * Enables a plugin
	 *
	 * @param int $plugin_id
	 */
	public function enable($plugin_id) {
		$plugin = $this->get($plugin_id, false);
		$this->Record->where("id", "=", $plugin_id)->update("plugins", array('enabled' => "1"));
		
		$this->clearNavCache($plugin->company_id);
	}

	/**
	 * Disables a plugin
	 *
	 * @param int $plugin_id
	 */	
	public function disable($plugin_id) {
		$plugin = $this->get($plugin_id, false);
		$this->Record->where("id", "=", $plugin_id)->update("plugins", array('enabled' => "0"));
		
		$this->clearNavCache($plugin->company_id);
	}
	
	/**
	 * Clears the nav cache for the given company ID
	 *
	 * @param int $company_id The ID of the company to clear nav cache for
	 */
	protected function clearNavCache($company_id) {
		Loader::loadModels($this, array("StaffGroups"));
		
		$groups = $this->StaffGroups->getAll($company_id);
		foreach ($groups as $group) {
			Cache::clearCache("nav_staff_group_" . $group->id, $company_id . DS . "nav" . DS);
		}
	}
	
	/**
	 * Adds an event to the system with a callback to be invoked when the event is triggered
	 *
	 * @param array $vars An array of event info including:
	 * 	- plugin_id The ID of the plugin to register the event under
	 * 	- event The event to register the callback under
	 * 	- callback The public static callback to invoke.
	 */
	public function addEvent($plugin_id, array $vars) {
		$vars['plugin_id'] = $plugin_id;
		
		$rules = array(
			'plugin_id' => array(
				'exists' => array(
					'rule' => array(array($this, "validateExists"), "id", "plugins"),
					'message' => $this->_("PluginManager.!error.plugin_id.exists")
				)
			),
			'event' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("PluginManager.!error.event.empty")
				),
				'length' => array(
					'rule' => array("maxLength", 32),
					'message' => $this->_("PluginManager.!error.event.length")
				)
			),
			'callback' => array(
				'exists' => array(
					'rule' => true,
					'post_format' => "serialize",
					'message' => $this->_("PluginManager.!error.callback.empty")
				)
			)
		);
		
		$this->Input->setRules($rules);
		
		if ($this->Input->validates($vars))
			$this->Record->insert("plugin_events", $vars);
	}
	
	/**
	 * Removes the event from the plugin so the event will no longer be triggered
	 *
	 * @param int $plugin_id The ID of the plugin to remove the event from
	 * @param string $event The event to remove from the plugin
	 */
	public function deleteEvent($plugin_id, $event) {
		$this->Record->from("plugin_events")->where("plugin_id", "=", $plugin_id)->where("event", "=", $event)->delete();
	}
	
	/**
	 * Adds an action to the system that may be used to access a particular view
	 *
	 * @param int $plugin_id The ID of the plugin to register the action under
	 * @param array $vars An array of action fields including:
	 * 	- action The action to register the uri under
	 * 	- uri The URI that represents this action
	 * 	- name The language definition naming this action
	 * 	- options An array of key/value pairs to set for the given action (if necessary)
	 */
	public function addAction($plugin_id, array $vars) {
		$vars['plugin_id'] = $plugin_id;
		
		$rules = array(
			'action' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("PluginManager.!error.action.empty")
				),
				'length' => array(
					'rule' => array("maxLength", 32),
					'message' => $this->_("PluginManager.!error.action.length")
				)
			),
			'uri' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("PluginManager.!error.uri.empty")
				)
			),
			'name' => array(
				'action_empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("PluginManager.!error.name.action_empty")
				)
			),
			'options' => array(
				'empty' => array(
					'if_set' => true,
					'rule' => true,
					'post_format' => "serialize"
				)
			)
		);
		
		$this->Input->setRules($rules);
		
		if ($this->Input->validates($vars))
			$this->Record->insert("plugin_actions", $vars);
	}
	
	/**
	 * Removes the action from the plugin
	 *
	 * @param int $plugin_id The ID of the plugin to remove the action from
	 * @param string $action The action to remove from the plugin
	 */
	public function deleteAction($plugin_id, $action) {
		$this->Record->from("plugin_actions")->where("plugin_id", "=", $plugin_id)->where("action", "=", $action)->delete();
	}
	
	/**
	 * Retrieves all callbacks that are registered for a particular event and company
	 *
	 * @param int $company_id The ID of the company the event is registered under
	 * @param string $event The event being requested
	 * @param boolean $enabled True for only enabled plugins, false for disabled, null for both
	 * @return array An array of stdClass objects representing the registered callback events
	 */
	public function getEvents($company_id, $event, $enabled = null) {
		$fields = array(
			"plugin_events.plugin_id", "plugin_events.event",
			"plugin_events.callback", 'plugins.dir' => "plugin_dir"
		);
		$this->Record->select($fields)->from("plugin_events")->
			innerJoin("plugins", "plugins.id", "=", "plugin_events.plugin_id", false)->
			where("plugins.company_id", "=", $company_id)->
			where("plugin_events.event", "=", $event);
		if ($enabled !== null)
			$this->Record->where("plugins.enabled", "=", (int)$enabled);
		
		return $this->Record->fetchAll();
	}
	
	/**
	 * Retrieves the specified event of the given plugin
	 *
	 * @param int $plugin_id The ID of the plugin to fetch the event under
	 * @param string $event The event to fetch
	 * @return mixed A stdClass object representing the plugin event, false if not such plugin event exists.
	 */
	public function getEvent($plugin_id, $event) {
		return $this->Record->select(array("plugin_id", "event", "callback"))->
			from("plugin_events")->where("plugin_id", "=", $plugin_id)->
			where("event", "=", $event)->fetch();
	}

	/**
	 * Retrieves all actions that are registered for a particular action and company
	 *
	 * @param int $company_id The ID of the company the action is registered under
	 * @param string $action The action being requested
	 * @param boolean $enabled True for only enabled plugins, false for disabled, null for both
	 * @return array An array of stdClass objects representing registered actions
	 */	
	public function getActions($company_id, $action, $enabled = null) {
		$fields = array("plugin_actions.plugin_id", "plugin_actions.action",
			"plugin_actions.uri", "plugin_actions.name", "plugin_actions.options",
			'plugins.dir' => "plugin_dir"
		);
		$this->Record->select($fields)->from("plugin_actions")->
			innerJoin("plugins", "plugins.id", "=", "plugin_actions.plugin_id", false)->
			where("plugins.company_id", "=", $company_id)->
			where("plugin_actions.action", "=", $action);
		
		if ($enabled !== null)
			$this->Record->where("plugins.enabled", "=", (int)$enabled);
		
		$actions = $this->Record->fetchAll();
			
		foreach ($actions as &$action) {
			if ($action->options)
				$action->options = unserialize($action->options);
		}
		return $actions;
	}
	
	/**
	 * Retrieves the specified action from the given plugin
	 *
	 * @param int $plugin_id The ID of the plugin to fetch the action under
	 * @param string $action The action to fetch
	 * @return mixed A stdClass object representing the plugin action, false if not such plugin action exists.
	 */
	public function getAction($plugin_id, $action) {
		$action = $this->Record->select()->
			from("plugin_actions")->where("plugin_id", "=", $plugin_id)->
			where("action", "=", $action)->fetch();
			
		if ($action && $action->options)
			$action->options = unserialize($action->options);
		
		return $action;
	}
	
	/**
	 * Invoke a specific event for all plugins
	 *
	 * @param int $company_id The ID of the company to fetch plugin events for
	 * @param EventObject $event An event object
	 * @return EventObject The processed event object
	 */
	public function invokeEvents($company_id, EventObject $event) {
		
		$plugin_events = $this->getEvents($company_id, $event->getName(), true);
		
		if ($plugin_events) {
			foreach ($plugin_events as $plugin_event) {
				try {
					// Load the plugin (so it can initialize the callback)
					$plugin = $this->loadPlugin($plugin_event->plugin_dir);
					
					// Allow the plugin to invoke instance methods
					$callback = unserialize($plugin_event->callback);
					if (is_array($callback) && isset($callback[1]) && $callback[0] == "this")
						$callback[0] = $plugin;

					// Invoke the callback
					call_user_func($callback, $event);

					#
					# TODO: Log this action
					#
				}
				catch (Exception $e) {
					#
					# TODO: Log this action failure
					#
				}				
			}
		}
		
		return $event;
	}
	
	/**
	 * Updates all installed plugins with the version given
	 *
	 * @param string $dir The directory name of the plugin to update
	 * @param string $version The version number to set for each plugin instance
	 */
	private function setVersion($dir, $version) {
		$this->Record->where("dir", "=", $dir)->update("plugins", array('version'=>$version));
	}
	
	/**
	 * Instantiates the given plugin and returns its instance
	 *
	 * @param string $dir The directory name of the plugin to load
	 * @return An instance of the plugin specified
	 */
	private function loadPlugin($dir) {
		// Load the plugin factory if not already loaded
		if (!isset($this->Plugins))
			Loader::loadComponents($this, array("Plugins"));
			
		// Instantiate the plugin and return the instance
		return $this->Plugins->create($dir);
	}
	
	/**
	 * Fetch information about the given plugin object
	 *
	 * @param object $plugin The plugin object to fetch info on
	 * @param int $company_id The ID of the company to fetch the plugin info for
	 */
	private function getPluginInfo($plugin, $company_id) {
		// Fetch supported interfaces
		$reflect = new ReflectionClass($plugin);
		$dir = str_replace("_plugin", "", Loader::fromCamelCase($reflect->getName()));
		$config = new stdClass();
		if (file_exists(PLUGINDIR . $dir . DS . "config.json")) {
			$config = file_get_contents(PLUGINDIR . $dir . DS . "config.json");
			if ($config) {
				if (!isset($this->Json))
					Loader::loadComponents($this, array("Json"));
				$config = $this->Json->decode($config);
			}
		}
		
		$dirname = dirname($_SERVER['SCRIPT_NAME']);
		$info = array(
			'dir'=>$dir,
			'name'=>$plugin->getName(),
			'version'=>$plugin->getVersion(),
			'authors'=>$plugin->getAuthors(),
			'logo'=>Router::makeURI(($dirname == DS ? "" : $dirname) . DS . str_replace(ROOTWEBDIR, "", PLUGINDIR . $dir . DS . $plugin->getLogo())),
			'installed'=>$this->isInstalled($dir, $company_id),
			'manageable'=>file_exists(PLUGINDIR . $dir . DS . "controllers" . DS . "admin_manage_plugin.php"),
			'description'=>isset($config->description) ? $config->description : null
		);
		
		unset($reflect);
		
		return $info;
	}
	
	/**
	 * Returns all common rules for plugins
	 *
	 * @param array $vars The input vars
	 * @return array Common plugin rules
	 */
	private function getAddRules(array $vars) {
		$rules = array(
			'dir' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("PluginManager.!error.dir.empty")
				),
				'length' => array(
					'rule' => array("maxLength", 64),
					'message' => $this->_("PluginManager.!error.dir.length")
				)
			),
			'company_id' => array(
				'exists' => array(
					'rule' => array(array($this, "validateExists"), "id", "companies"),
					'message' => $this->_("PluginManager.!error.company_id.exists")
				)
			),
			'name' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("PluginManager.!error.name.empty")
				)
			),
			'version' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("PluginManager.!error.version.empty")
				),
				'length' => array(
					'rule' => array("maxLength", 16),
					'message' => $this->_("PluginManager.!error.version.length")
				)
			)
		);
		
		return $rules;
	}
}
?>