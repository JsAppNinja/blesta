<?php
/**
 * Interworx Module
 *
 * @package blesta
 * @subpackage blesta.components.modules.interworx
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class Interworx extends Module {
	
	/**
	 * @var string The version of this module
	 */
	private static $version = "2.3.3";
	/**
	 * @var string The authors of this module
	 */
	private static $authors = array(array('name'=>"Phillips Data, Inc.",'url'=>"http://www.blesta.com"));
	
	/**
	 * Initializes the module
	 */
	public function __construct() {
		// Load components required by this module
		Loader::loadComponents($this, array("Input"));
		
		// Load the language required by this module
		Language::loadLang("interworx", null, dirname(__FILE__) . DS . "language" . DS);
	}
	
	/**
	 * Returns the name of this module
	 *
	 * @return string The common name of this module
	 */
	public function getName() {
		return Language::_("Interworx.name", true);
	}
	
	/**
	 * Returns the version of this gateway
	 *
	 * @return string The current version of this gateway
	 */
	public function getVersion() {
		return self::$version;
	}
	
	/**
	 * Returns the name and url of the authors of this module
	 *
	 * @return array The name and url of the authors of this module
	 */
	public function getAuthors() {
		return self::$authors;
	}

	/**
	 * Returns all tabs to display to an admin when managing a service whose
	 * package uses this module
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @return array An array of tabs in the format of method => title. Example: array('methodName' => "Title", 'methodName2' => "Title2")
	 */
	public function getAdminTabs($package) {
		return array(
			'tabStats' => Language::_("Interworx.tab_stats", true)
		);
	}

    /**
	 * Returns all tabs to display to a client when managing a service whose
	 * package uses this module
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @return array An array of tabs in the format of method => title. Example: array('methodName' => "Title", 'methodName2' => "Title2")
	 */
	public function getClientTabs($package) {
		return array(
            'tabClientActions' => Language::_("Interworx.tab_client_actions", true)
		);
	}

	/**
	 * Performs any necessary bootstraping actions. Sets Input errors on
	 * failure, preventing the module from being added.
	 *
	 * @return array A numerically indexed array of meta data containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 */
	public function install() {
		// Ensure the the system has the libxml extension
		$errors = array();
		if (!extension_loaded("libxml"))
			$errors['libxml'] = array('required' => Language::_("Interworx.!error.libxml_required", true));

		// Ensure the SoapClient is available
		if (!class_exists("SoapClient"))
			$errors['soap'] = array('required' => Language::_("Interworx.!error.soap_required", true));
		
		if (!empty($errors)) {
			$this->Input->setErrors($errors);
			return;
		}
	}
	
	/**
	 * Returns a noun used to refer to a module row (e.g. "Server")
	 *
	 * @return string The noun used to refer to a module row
	 */
	public function moduleRowName() {
		return Language::_("Interworx.module_row", true);
	}
	
	/**
	 * Returns a noun used to refer to a module row in plural form (e.g. "Servers", "VPSs", "Reseller Accounts", etc.)
	 *
	 * @return string The noun used to refer to a module row in plural form
	 */
	public function moduleRowNamePlural() {
		return Language::_("Interworx.module_row_plural", true);
	}
	
	/**
	 * Returns a noun used to refer to a module group (e.g. "Server Group")
	 *
	 * @return string The noun used to refer to a module group
	 */
	public function moduleGroupName() {
		return Language::_("Interworx.module_group", true);
	}
	
	/**
	 * Returns the key used to identify the primary field from the set of module row meta fields.
	 *
	 * @return string The key used to identify the primary field from the set of module row meta fields
	 */
	public function moduleRowMetaKey() {
		return "server_name";
	}
	
	/**
	 * Returns an array of available service deligation order methods. The module
	 * will determine how each method is defined. For example, the method "first"
	 * may be implemented such that it returns the module row with the least number
	 * of services assigned to it.
	 *
	 * @return array An array of order methods in key/value paris where the key is the type to be stored for the group and value is the name for that option
	 * @see Module::selectModuleRow()
	 */
	public function getGroupOrderOptions() {
		return array('first'=>Language::_("Interworx.order_options.first", true));
	}
	
	/**
	 * Determines which module row should be attempted when a service is provisioned
	 * for the given group based upon the order method set for that group.
	 *
	 * @return int The module row ID to attempt to add the service with
	 * @see Module::getGroupOrderOptions()
	 */
	public function selectModuleRow($module_group_id) {
		if (!isset($this->ModuleManager))
			Loader::loadModels($this, array("ModuleManager"));
		
		$group = $this->ModuleManager->getGroup($module_group_id);
		
		if ($group) {
			switch ($group->add_order) {
				default:
				case "first":
										
					foreach ($group->rows as $row) {
						if ($row->meta->account_limit > (isset($row->meta->account_count) ? $row->meta->account_count : 0))
							return $row->id;
					}
					
					break;
			}
		}
		return 0;
	}
	
	/**
	 * Returns all fields used when adding/editing a package, including any
	 * javascript to execute when the page is rendered with these fields.
	 *
	 * @param $vars stdClass A stdClass object representing a set of post fields
	 * @return ModuleFields A ModuleFields object, containg the fields to render as well as any additional HTML markup to include
	 */
	public function getPackageFields($vars=null) {
		Loader::loadHelpers($this, array("Html"));
		
		// Fetch all packages available for the given server or server group
		$module_row = null;
		if (isset($vars->module_group) && $vars->module_group == "") {
			
			// Check to see if module_row exists
			$rows = $this->getModuleRows();
			if(!empty($rows) && isset($vars->module_row) && $vars->module_row > 0) {
				foreach($rows as $row)
				{
					if(isset($row->id) && $row->id == $vars->module_row) {
						$module_row = $this->getModuleRow($vars->module_row);
						break;
					}
				}
			}
		}
		if(!$module_row) {
			$rows = $this->getModuleRows(!empty($vars->module_group) ? $vars->module_group : null);
			if (isset($rows[0])) {
				$module_row = $rows[0];
				$vars->meta['type'] = (isset($vars->meta['type']) && in_array($vars->meta['type'], array("standard", "reseller"))) ? $vars->meta['type'] : 'standard';
			}
			unset($rows);
		}
		
		$packages = array();
		$reseller_packages = array();
		
		if ($module_row) {
			$packages = $this->getInterworxPackages($module_row);
			if(!$this->getInterworxIsReseller($module_row)) {
				$reseller_packages = $this->getInterworxPackages($module_row, true);
			}
		}
		
		#
		# TODO: Currently we are only showing the Type if there are Reseller Packages to choose from.
		#		We should check if the User is an Admin then Display a message that there are no Packages configured yet.
		#
		
		$fields = new ModuleFields();
				
		$fields_html = "
			<script type=\"text/javascript\">
				$(document).ready(function() {
					";
					
					if(!empty($reseller_packages)) {
					
					$fields_html.= "
					var interworxStandardPackages = [];
					";
					if(!empty($packages)) {
						foreach($packages as $id => $name) {
						$fields_html.= "interworxStandardPackages[".$id."] = '".$name."';
					";	
						}
					}
					
					$fields_html.= "
					var interworxResellerPackages = [];
					";
					
					
					foreach($reseller_packages as $id => $name) {
						$fields_html.= "interworxResellerPackages[".$id."] = '".$name."';
					";
					}
					
					$fields_html.= "
					var selectPackages = $('#interworx_package');
					if(selectPackages.prop) {
					  var options = selectPackages.prop('options');
					}
					else {
					  var options = selectPackages.attr('options');
					}					
					
					// Store the package options for standard and reseller
					$('#interworx_type_standard').click(function() {
						$('option', selectPackages).remove();
					  	$.each(interworxStandardPackages, function(val, text) {
	    					if(text) options[options.length] = new Option(text, val);
						});
					});
					$('#interworx_type_reseller').click(function() {
						$('option', selectPackages).remove();
					  	$.each(interworxResellerPackages, function(val, text) {
	    					if(text) options[options.length] = new Option(text, val);
						});
					});
					";
					}
					else {
						$fields_html.= "$('#interworx_type_standard').parent().hide();";
					}
					$fields_html.= "
				});
			</script>
		";
        
        $fields->setHtml($fields_html);

		// Set the type of account (standard or reseller)
		$type = $fields->label(Language::_("Interworx.package_fields.type", true), "interworx_type");
		$type_standard = $fields->label(Language::_("Interworx.package_fields.type_standard", true), "interworx_type_standard");
		$type_reseller = $fields->label(Language::_("Interworx.package_fields.type_reseller", true), "interworx_type_reseller");
		$type->attach($fields->fieldRadio("meta[type]", "standard",
			$this->Html->ifSet($vars->meta['type'], "standard") == "standard", array('id'=>"interworx_type_standard"), $type_standard));
		$type->attach($fields->fieldRadio("meta[type]", "reseller",
			$this->Html->ifSet($vars->meta['type']) == "reseller", array('id'=>"interworx_type_reseller"), $type_reseller));
		$fields->setField($type);
		
		// Set the Interworx package as a selectable option
		$package = $fields->label(Language::_("Interworx.package_fields.package", true), "interworx_package");
		$package->attach($fields->fieldSelect("meta[package]", (isset($vars->meta['type']) && $vars->meta['type'] == "reseller" ? $reseller_packages : $packages),
			$this->Html->ifSet($vars->meta['package']), array('id'=>"interworx_package")));
		$fields->setField($package);		
		
		return $fields;
	}
	
	/**
	 * Returns an array of key values for fields stored for a module, package,
	 * and service under this module, used to substitute those keys with their
	 * actual module, package, or service meta values in related emails.
	 *
	 * @return array A multi-dimensional array of key/value pairs where each key is one of 'module', 'package', or 'service' and each value is a numerically indexed array of key values that match meta fields under that category.
	 * @see Modules::addModuleRow()
	 * @see Modules::editModuleRow()
	 * @see Modules::addPackage()
	 * @see Modules::editPackage()
	 * @see Modules::addService()
	 * @see Modules::editService()
	 */
	public function getEmailTags() {
		return array(
			'module' => array('host_name', 'name_servers', 'notes'),
			'package' => array('type', 'package'),
			'service' => array('interworx_username', 'interworx_email', 'interworx_password', 'interworx_domain')
		);
	}
	
	/**
	 * Validates input data when attempting to add a package, returns the meta
	 * data to save when adding a package. Performs any action required to add
	 * the package on the remote server. Sets Input errors on failure,
	 * preventing the package from being added.
	 *
	 * @param array An array of key/value pairs used to add the package
	 * @return array A numerically indexed array of meta fields to be stored for this package containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 * @see Module::getModule()
	 * @see Module::getModuleRow()
	 */
	public function addPackage(array $vars=null) {
		
		// Set rules to validate input data
		$this->Input->setRules($this->getPackageRules($vars));
		
		// Build meta data to return
		$meta = array();
		if ($this->Input->validates($vars)) {
			
			// Return all package meta fields
			foreach ($vars['meta'] as $key => $value) {
				$meta[] = array(
					'key' => $key,
					'value' => $value,
					'encrypted' => 0
				);
			}
		}
		return $meta;
	}
	
	/**
	 * Validates input data when attempting to edit a package, returns the meta
	 * data to save when editing a package. Performs any action required to edit
	 * the package on the remote server. Sets Input errors on failure,
	 * preventing the package from being edited.
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @param array An array of key/value pairs used to edit the package
	 * @return array A numerically indexed array of meta fields to be stored for this package containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 * @see Module::getModule()
	 * @see Module::getModuleRow()
	 */
	public function editPackage($package, array $vars=null) {
		
		// Set rules to validate input data
		$this->Input->setRules($this->getPackageRules($vars));
		
		// Build meta data to return
		$meta = array();
		if ($this->Input->validates($vars)) {
			
			// Return all package meta fields
			foreach ($vars['meta'] as $key => $value) {
				$meta[] = array(
					'key' => $key,
					'value' => $value,
					'encrypted' => 0
				);
			}
		}
		return $meta;
	}
	
	/**
	 * Returns the rendered view of the manage module page
	 *
	 * @param mixed $module A stdClass object representing the module and its rows
	 * @param array $vars An array of post data submitted to or on the manager module page (used to repopulate fields after an error)
	 * @return string HTML content containing information to display when viewing the manager module page
	 */
	public function manageModule($module, array &$vars) {
		// Load the view into this object, so helpers can be automatically added to the view
		$this->view = new View("manage", "default");
		$this->view->base_uri = $this->base_uri;
		$this->view->setDefaultView("components" . DS . "modules" . DS . "interworx" . DS);
		
		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html", "Widget"));
		
		$this->view->set("module", $module);
		
		return $this->view->fetch();
	}
	
	/**
	 * Returns the rendered view of the add module row page
	 *
	 * @param array $vars An array of post data submitted to or on the add module row page (used to repopulate fields after an error)
	 * @return string HTML content containing information to display when viewing the add module row page
	 */
	public function manageAddRow(array &$vars) {
		// Load the view into this object, so helpers can be automatically added to the view
		$this->view = new View("add_row", "default");
		$this->view->base_uri = $this->base_uri;
		$this->view->setDefaultView("components" . DS . "modules" . DS . "interworx" . DS);
		
		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html", "Widget"));
		
		$this->view->set("vars", (object)$vars);
		return $this->view->fetch();
	}

	/**
	 * Returns the rendered view of the edit module row page
	 *
	 * @param stdClass $module_row The stdClass representation of the existing module row
	 * @param array $vars An array of post data submitted to or on the edit module row page (used to repopulate fields after an error)
	 * @return string HTML content containing information to display when viewing the edit module row page
	 */	
	public function manageEditRow($module_row, array &$vars) {
		// Load the view into this object, so helpers can be automatically added to the view
		$this->view = new View("edit_row", "default");
		$this->view->base_uri = $this->base_uri;
		$this->view->setDefaultView("components" . DS . "modules" . DS . "interworx" . DS);
		
		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html", "Widget"));
		
		if (empty($vars))
			$vars = $module_row->meta;
		
		$this->view->set("vars", (object)$vars);
		return $this->view->fetch();
	}
	
	/**
	 * Adds the module row on the remote server. Sets Input errors on failure,
	 * preventing the row from being added. Returns a set of data, which may be
	 * a subset of $vars, that is stored for this module row
	 *
	 * @param array $vars An array of module info to add
	 * @return array A numerically indexed array of meta fields for the module row containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 */
	public function addModuleRow(array &$vars) {
		$meta_fields = array("server_name", "host_name", "key",
			"use_ssl", "port", "account_limit", "account_count", "debug", "name_servers", "notes");
		$encrypted_fields = array("key");
		
		$this->Input->setRules($this->getRowRules($vars));
		
		// Validate module row
		if ($this->Input->validates($vars)) {

			// Build the meta data for this row
			$meta = array();
			foreach ($vars as $key => $value) {
				
				if (in_array($key, $meta_fields)) {
					$meta[] = array(
						'key'=>$key,
						'value'=>$value,
						'encrypted'=>in_array($key, $encrypted_fields) ? 1 : 0
					);
				}
			}
			
			return $meta;
		}
	}
	
	/**
	 * Edits the module row on the remote server. Sets Input errors on failure,
	 * preventing the row from being updated. Returns a set of data, which may be
	 * a subset of $vars, that is stored for this module row
	 *
	 * @param stdClass $module_row The stdClass representation of the existing module row
	 * @param array $vars An array of module info to update
	 * @return array A numerically indexed array of meta fields for the module row containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 */
	public function editModuleRow($module_row, array &$vars) {
		$meta_fields = array("server_name", "host_name", "key",
			"use_ssl", "port", "account_limit", "account_count", "debug", "name_servers", "notes");
		$encrypted_fields = array("key");
		
		$this->Input->setRules($this->getRowRules($vars));
		
		// Validate module row
		if ($this->Input->validates($vars)) {

			// Build the meta data for this row
			$meta = array();
			foreach ($vars as $key => $value) {
				
				if (in_array($key, $meta_fields)) {
					$meta[] = array(
						'key'=>$key,
						'value'=>$value,
						'encrypted'=>in_array($key, $encrypted_fields) ? 1 : 0
					);
				}
			}
			
			return $meta;
		}
	}

	/**
	 * Returns the value used to identify a particular service
	 *
	 * @param stdClass $service A stdClass object representing the service
	 * @return string A value used to identify this service amongst other similar services
	 */
	public function getServiceName($service) {
		foreach ($service->fields as $field) {
			if ($field->key == "interworx_domain")
				return $field->value;
		}
		return null;
	}
	
	/**
	 * Returns the value used to identify a particular package service which has
	 * not yet been made into a service. This may be used to uniquly identify
	 * to uncreated services of the same package (i.e. in an order form checkout)
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @param array $vars An array of user supplied info to satisfy the request
	 * @return string The value used to identify this package service
	 * @see Module::getServiceName()
	 */
	public function getPackageServiceName($package, array $vars=null) {
		if (isset($vars['interworx_domain']))
			return $vars['interworx_domain'];
		return null;
	}
	
	/**
	 * Returns all fields to display to an admin attempting to add a service with the module
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @param $vars stdClass A stdClass object representing a set of post fields
	 * @return ModuleFields A ModuleFields object, containg the fields to render as well as any additional HTML markup to include
	 */
	public function getAdminAddFields($package, $vars=null) {
		Loader::loadHelpers($this, array("Html"));
		
		$fields = new ModuleFields();
		
		if( $package->meta->type != "reseller" ) {
			// Create domain label
			$domain = $fields->label(Language::_("Interworx.service_field.domain", true), "interworx_domain");
			// Create domain field and attach to domain label
			$domain->attach($fields->fieldText("interworx_domain", $this->Html->ifSet($vars->interworx_domain), array('id'=>"interworx_domain")));
			// Set the label as a field
			$fields->setField($domain);
		}
		else {
			// Set a field for reseller ID
			$reseller_id = $fields->label(Language::_("Interworx.service_field.reseller_id", true), "interworx_reseller_id");
			// Create domain field and attach to domain label
			$reseller_id->attach($fields->fieldText("interworx_reseller_id", $this->Html->ifSet($vars->interworx_reseller_id), array('id'=>"interworx_reseller_id")));
			$tooltip = $fields->tooltip(Language::_("Interworx.service_field.tooltip.interworx_reseller_id", true));
			$reseller_id->attach($tooltip);
			// Set the label as a field
			$fields->setField($reseller_id);
		}
		
		// Create email label
		$email = $fields->label(Language::_("Interworx.service_field.email", true), "interworx_email");
		// Create email field and attach to email label
		$email->attach($fields->fieldText("interworx_email", $this->Html->ifSet($vars->interworx_email), array('id'=>"interworx_email")));
		// Add tooltip
		$tooltip = $fields->tooltip(Language::_("Interworx.service_field.tooltip.email", true));
		$email->attach($tooltip);
		// Set the label as a field
		$fields->setField($email);
		
		// Create username label
		$username = $fields->label(Language::_("Interworx.service_field.username", true), "interworx_username");
		// Create username field and attach to username label
		$username->attach($fields->fieldText("interworx_username", $this->Html->ifSet($vars->interworx_username), array('id'=>"interworx_username")));
		// Add tooltip
		$tooltip = $fields->tooltip(Language::_("Interworx.service_field.tooltip.username", true));
		$username->attach($tooltip);
		// Set the label as a field
		$fields->setField($username);
		
		// Create password label
		$password = $fields->label(Language::_("Interworx.service_field.password", true), "interworx_password");
		// Create password field and attach to password label
		$password->attach($fields->fieldPassword("interworx_password", array('id'=>"interworx_password", 'value'=>$this->Html->ifSet($vars->interworx_password))));
		// Add tooltip
		$tooltip = $fields->tooltip(Language::_("Interworx.service_field.tooltip.password", true));
		$password->attach($tooltip);
		// Set the label as a field
		$fields->setField($password);
		
		// Create confirm_password label
		$confirm_password = $fields->label(Language::_("Interworx.service_field.confirm_password", true), "interworx_confirm_password");
		// Create password field and attach to password label
		$confirm_password->attach($fields->fieldPassword("interworx_confirm_password", array('id'=>"interworx_confirm_password", 'value'=>$this->Html->ifSet($vars->interworx_password))));
		// Add tooltip
		$confirm_password->attach($tooltip);
		// Set the label as a field
		$fields->setField($confirm_password);
		
		return $fields;
	}
	
	/**
	 * Returns all fields to display to a client attempting to add a service with the module
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @param $vars stdClass A stdClass object representing a set of post fields
	 * @return ModuleFields A ModuleFields object, containg the fields to render as well as any additional HTML markup to include
	 */	
	public function getClientAddFields($package, $vars=null) {
		Loader::loadHelpers($this, array("Html"));
		
		$fields = new ModuleFields();
		
		// Create domain label
		$domain = $fields->label(Language::_("Interworx.service_field.domain", true), "interworx_domain");
		// Create domain field and attach to domain label
		$domain->attach($fields->fieldText("interworx_domain", $this->Html->ifSet($vars->interworx_domain, $this->Html->ifSet($vars->domain)), array('id'=>"interworx_domain")));
		// Set the label as a field
		$fields->setField($domain);
		
		return $fields;
	}
	
	/**
	 * Returns all fields to display to an admin attempting to edit a service with the module
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @param $vars stdClass A stdClass object representing a set of post fields
	 * @return ModuleFields A ModuleFields object, containg the fields to render as well as any additional HTML markup to include
	 */	
	public function getAdminEditFields($package, $vars=null) {
		Loader::loadHelpers($this, array("Html"));
		
		$fields = new ModuleFields();
		
		// Set a domain field if this is not a reseller
		if ((!isset($vars->interworx_reseller_id) || $vars->interworx_reseller_id == 0) && $package->meta->type != "reseller") {
			// Create domain label
			$domain = $fields->label(Language::_("Interworx.service_field.domain", true), "interworx_domain");
			// Create email field and attach to email label
			$domain->attach($fields->fieldText("interworx_domain", $this->Html->ifSet($vars->interworx_domain), array('id'=>"interworx_domain")));
			// Add tooltip
			$tooltip = $fields->tooltip(Language::_("Interworx.service_field.tooltip.domain", true));
			$domain->attach($tooltip);
			// Set the label as a field
			$fields->setField($domain);
		}
		else {
			// Set a field for reseller ID
			$reseller_id = $fields->label(Language::_("Interworx.service_field.reseller_id", true), "interworx_reseller_id");
			// Create domain field and attach to domain label
			$reseller_id->attach($fields->fieldText("interworx_reseller_id", $this->Html->ifSet($vars->interworx_reseller_id), array('id'=>"interworx_reseller_id")));
			$tooltip = $fields->tooltip(Language::_("Interworx.service_field.tooltip.interworx_reseller_id", true));
			$reseller_id->attach($tooltip);
			// Set the label as a field
			$fields->setField($reseller_id);
		}
		
		// Create email label
		$email = $fields->label(Language::_("Interworx.service_field.email", true), "interworx_email");
		// Create email field and attach to email label
		$email->attach($fields->fieldText("interworx_email", $this->Html->ifSet($vars->interworx_email), array('id'=>"interworx_email")));
		// Set the label as a field
		$fields->setField($email);
		
		// Create username label
		$username = $fields->label(Language::_("Interworx.service_field.username", true), "interworx_username");
		// Create username field and attach to username label
		$username->attach($fields->fieldText("interworx_username", $this->Html->ifSet($vars->interworx_username), array('id'=>"interworx_username")));
		// Set the label as a field
		$fields->setField($username);
		
		// Create password label
		$password = $fields->label(Language::_("Interworx.service_field.password", true), "interworx_password");
		// Create password field and attach to password label
		$password->attach($fields->fieldPassword("interworx_password", array('id'=>"interworx_password", 'value'=>$this->Html->ifSet($vars->interworx_password))));
		// Set the label as a field
		$fields->setField($password);
		
		return $fields;
	}
	
	/**
	 * Adds the service to the remote server. Sets Input errors on failure,
	 * preventing the service from being added.
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @param array $vars An array of user supplied info to satisfy the request
	 * @param stdClass $parent_package A stdClass object representing the parent service's selected package (if the current service is an addon service)
	 * @param stdClass $parent_service A stdClass object representing the parent service of the service being added (if the current service is an addon service service and parent service has already been provisioned)
	 * @param string $status The status of the service being added. These include:
	 * 	- active
	 * 	- canceled
	 * 	- pending
	 * 	- suspended
	 * @return array A numerically indexed array of meta fields to be stored for this service containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 * @see Module::getModule()
	 * @see Module::getModuleRow()
	 */
	public function addService($package, array $vars=null, $parent_package=null, $parent_service=null, $status="pending") {
		$row = $this->getModuleRow();
		$api = $this->getApi($row->meta);
		
		// Set missing username/email address/password
		Loader::loadModels($this, array("Clients"));
		if (isset($vars['client_id']) && ($client = $this->Clients->get($vars['client_id'], false))) {
			// Generate a username
			if (empty($vars['interworx_username'])) {
				$username = "";
				if (isset($vars['interworx_domain']) && $package->meta->type != "reseller")
					$username = $this->generateUsername($vars['interworx_domain']);
				elseif ($package->meta->type == "reseller")
					$username = $client->first_name;
				
				$vars['interworx_username'] = $username;
			}
			// Generate a password
			if (empty($vars['interworx_password'])) {
				$vars['interworx_password'] = $this->generatePassword();
				$vars['interworx_confirm_password'] = $vars['interworx_password'];
			}
			// Use client's email address
			if (empty($vars['interworx_email']))
				$vars['interworx_email'] = $client->email;
		}
		
		$params = $this->getFieldsFromInput((array)$vars, $package);
		
		$this->validateService($package, $vars);
		
		if ($this->Input->errors())
			return;
		
		// Only provision the service if 'use_module' is true
		if ($vars['use_module'] == "true") {
			
			if ($package->meta->type == "reseller") {
				$masked_params = $params;
				$masked_params['password'] = "***";
				$action = $row->meta->host_name . "|createReseller";
				$this->log($action, serialize($masked_params), "input", true);
				unset($masked_params);
				
				$result = $this->parseResponse($api->createReseller($params), $action);
			}
			else {
				$masked_params = $params;
				$masked_params['password'] = "***";
				$action = $row->meta->host_name . "|createAccount";
				$this->log($action, serialize($masked_params), "input", true);
				unset($masked_params);
				
				$result = $this->parseResponse($api->createAccount($params), $action);
			}
			
			if ($this->Input->errors())
				return;
			
			// Update the number of accounts on the server
			$this->updateAccountCount($row);
			
			// Remove any errors set when attempting to update the account count
			if ($this->Input->errors())
				$this->Input->setErrors(array());
		}
		
		// Use the reseller ID given, or the one set if available. Default to 0 otherwise
		$reseller_id = (isset($result->reseller_id) ? $result->reseller_id : (!empty($vars['interworx_reseller_id']) ? $vars['interworx_reseller_id'] : 0));
		
		// Return service fields
		return array(
			array(
				'key' => "interworx_domain",
				'value' => $params['domain'],
				'encrypted' => 0
			),
			array(
				'key' => "interworx_email",
				'value' => $params['email'],
				'encrypted' => 0
			),
			array(
				'key' => "interworx_reseller_id",
				'value' => $reseller_id,
				'encrypted' => 0
			),
			array(
				'key' => "interworx_username",
				'value' => $params['username'],
				'encrypted' => 1
			),
			array(
				'key' => "interworx_password",
				'value' => $params['password'],
				'encrypted' => 1
			)
		);
	}
	
	/**
	 * Edits the service on the remote server. Sets Input errors on failure,
	 * preventing the service from being edited.
	 *
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param array $vars An array of user supplied info to satisfy the request
	 * @param stdClass $parent_package A stdClass object representing the parent service's selected package (if the current service is an addon service)
	 * @param stdClass $parent_service A stdClass object representing the parent service of the service being edited (if the current service is an addon service)
	 * @return array A numerically indexed array of meta fields to be stored for this service containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 * @see Module::getModule()
	 * @see Module::getModuleRow()
	 */
	public function editService($package, $service, array $vars=null, $parent_package=null, $parent_service=null) {
		$row = $this->getModuleRow();
		$api = $this->getApi($row->meta);
		
		$this->validateService($package, $vars, true);
		
		if ($this->Input->errors())
			return;
		
		$service_fields = $this->serviceFieldsToObject($service->fields);

        // Remove password if not being updated
		if (isset($vars['interworx_password']) && $vars['interworx_password'] == "")
			unset($vars['interworx_password']);
		// Set 0 for reseller ID if none set
		if (empty($vars['interworx_reseller_id']))
			$vars['interworx_reseller_id'] = 0;
		
		// Only provision the service if 'use_module' is true
		if ($vars['use_module'] == "true") {
			
			// Check for fields that changed
			$delta = array();
			foreach ($vars as $key => $value) {
				if (!array_key_exists($key, $service_fields) || $vars[$key] != $service_fields->$key)
					$delta[$key] = $value;
			}
			
			$input = array(
				'email' => (isset($delta['interworx_email']) ? $delta['interworx_email'] : $service_fields->interworx_email),
				'nickname' => (isset($delta['interworx_username']) ? $delta['interworx_username'] : $service_fields->interworx_username)
			);

            if (isset($delta['interworx_password'])) {
                $input['password'] = $delta['interworx_password'];
				$input['confirm_password'] = $delta['interworx_password'];
            }
			
			if ($package->meta->type == "reseller") {
				$input['reseller_id'] = $service_fields->interworx_reseller_id;
				
				$action = $row->meta->host_name . "|modifyReseller";
				$this->log($action, serialize($input), "input", true);
				
				$result = $this->parseResponse($api->modifyReseller($input), $action);
			}
			else {
				$input['domain'] = $service_fields->interworx_domain;
				
				$action = $row->meta->host_name . "|modifyAccount";
				$this->log($action, serialize($input), "input", true);
				
				$this->parseResponse($api->modifyAccount($input), $action);
			}
		}
		
		// Set fields to update locally
		$fields = array("interworx_reseller_id", "interworx_domain", "interworx_username", "interworx_email", "interworx_password");
		foreach ($fields as $field) {
			if (property_exists($service_fields, $field) && isset($vars[$field]))
				$service_fields->{$field} = $vars[$field];
		}
		
		// Return all the service fields
		$fields = array();
		$encrypted_fields = array("interworx_username", "interworx_password");
		foreach ($service_fields as $key => $value)
			$fields[] = array('key' => $key, 'value' => $value, 'encrypted' => (in_array($key, $encrypted_fields) ? 1 : 0));
		
		return $fields;
	}
	
	/**
	 * Suspends the service on the remote server. Sets Input errors on failure,
	 * preventing the service from being suspended.
	 *
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param stdClass $parent_package A stdClass object representing the parent service's selected package (if the current service is an addon service)
	 * @param stdClass $parent_service A stdClass object representing the parent service of the service being suspended (if the current service is an addon service)
	 * @return mixed null to maintain the existing meta fields or a numerically indexed array of meta fields to be stored for this service containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 * @see Module::getModule()
	 * @see Module::getModuleRow()
	 */
	public function suspendService($package, $service, $parent_package=null, $parent_service=null) {
		$row = $this->getModuleRow();
		
		if ($row) {
			$api = $this->getApi($row->meta);
			
			$service_fields = $this->serviceFieldsToObject($service->fields);
			
			if ($package->meta->type == "reseller") {
				$action = $row->meta->host_name . "|suspendReseller";
				$this->log($action, serialize($service_fields->interworx_reseller_id), "input", true);
				$this->parseResponse($api->suspendReseller($service_fields->interworx_reseller_id), $action);
			}
			else {
				$action = $row->meta->host_name . "|suspendAccount";
				$this->log($action, serialize($service_fields->interworx_username), "input", true);
				$this->parseResponse($api->suspendAccount($service_fields->interworx_domain), $action);
			}
		}
		
		return null;
	}
	
	/**
	 * Unsuspends the service on the remote server. Sets Input errors on failure,
	 * preventing the service from being unsuspended.
	 *
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param stdClass $parent_package A stdClass object representing the parent service's selected package (if the current service is an addon service)
	 * @param stdClass $parent_service A stdClass object representing the parent service of the service being unsuspended (if the current service is an addon service)
	 * @return mixed null to maintain the existing meta fields or a numerically indexed array of meta fields to be stored for this service containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 * @see Module::getModule()
	 * @see Module::getModuleRow()
	 */
	public function unsuspendService($package, $service, $parent_package=null, $parent_service=null) {
		// unsuspendAccount / unsuspendReseller ($package->meta->type == "reseller")
		if (($row = $this->getModuleRow())) {
			$api = $this->getApi($row->meta);
			
			$service_fields = $this->serviceFieldsToObject($service->fields);
			
			if ($package->meta->type == "reseller") {
				$action = $row->meta->host_name . "|unsuspendReseller";
				$this->log($action, serialize($service_fields->interworx_reseller_id), "input", true);
				$this->parseResponse($api->unsuspendReseller($service_fields->interworx_reseller_id), $action);
			}
			else {
				$action = $row->meta->host_name . "|unsuspendAccount";
				$this->log($action, serialize($service_fields->interworx_domain), "input", true);
				$this->parseResponse($api->unsuspendAccount($service_fields->interworx_domain), $action);
			}
		}
		return null;
	}
	
	/**
	 * Cancels the service on the remote server. Sets Input errors on failure,
	 * preventing the service from being canceled.
	 *
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param stdClass $parent_package A stdClass object representing the parent service's selected package (if the current service is an addon service)
	 * @param stdClass $parent_service A stdClass object representing the parent service of the service being canceled (if the current service is an addon service)
	 * @return mixed null to maintain the existing meta fields or a numerically indexed array of meta fields to be stored for this service containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 * @see Module::getModule()
	 * @see Module::getModuleRow()
	 */
	public function cancelService($package, $service, $parent_package=null, $parent_service=null) {
		
		if (($row = $this->getModuleRow())) {
			$api = $this->getApi($row->meta);
			
			$service_fields = $this->serviceFieldsToObject($service->fields);
			
			if ($package->meta->type == "reseller") {
				$action = $row->meta->host_name . "|removeReseller";
				$this->log($action, serialize($service_fields->interworx_domain), "input", true);
				$this->parseResponse($api->removeReseller($service_fields->interworx_reseller_id), $action);
			}
			else {
				$action = $row->meta->host_name . "|removeAccount";
				$this->log($action, serialize($service_fields->interworx_domain), "input", true);
				$this->parseResponse($api->removeAccount($service_fields->interworx_domain), $action);
			}
			
			if ($this->Input->errors())
				return;
			
			// Update the number of accounts on the server
			$this->updateAccountCount($row);
			
			// Remove any errors set when attempting to update the account count
			if ($this->Input->errors())
				$this->Input->setErrors(array());
		}

		return null;
	}
	
	/**
	 * Updates the package for the service on the remote server. Sets Input
	 * errors on failure, preventing the service's package from being changed.
	 *
	 * @param stdClass $package_from A stdClass object representing the current package
	 * @param stdClass $package_to A stdClass object representing the new package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param stdClass $parent_package A stdClass object representing the parent service's selected package (if the current service is an addon service)
	 * @param stdClass $parent_service A stdClass object representing the parent service of the service being changed (if the current service is an addon service)
	 * @return mixed null to maintain the existing meta fields or a numerically indexed array of meta fields to be stored for this service containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 * @see Module::getModule()
	 * @see Module::getModuleRow()
	 */
	public function changeServicePackage($package_from, $package_to, $service, $parent_package=null, $parent_service=null) {
				
		if(($row = $this->getModuleRow())) {
			$api = $this->getApi($row->meta);
			
			// Only request a package change if it has changed
			if($package_from->meta->package != $package_to->meta->package) {
				
				$service_fields = $this->serviceFieldsToObject($service->fields);
				
				$input = array();
				$input["plan"] = $package_to->meta->package;
				
				if($package_from->meta->type == "reseller" && $package_to->meta->type == "reseller") {
					$input['reseller_id'] = $service_fields->interworx_reseller_id;
					
					$action = $row->meta->host_name . "|modifyReseller";
					$this->log($action, serialize($input), "input", true);
					
					$this->parseResponse($api->modifyReseller($input), $action);
				}
				else if($package_from->meta->type != "reseller" && $package_to->meta->type != "reseller") {
					$input["domain"] = $service_fields->interworx_domain;
					
					$action = $row->meta->host_name . "|modifyAccount";
					$this->log($action, serialize($input), "input", true);
					
					$this->parseResponse($api->modifyAccount($input), $action);
				}
				else {
					$this->Input->setErrors(array('api' => array('result' => Language::_("Interworx.!error.api.package_types", true))));
				}
			}
		}
		return null;
	}
	
	/**
	 * Fetches the HTML content to display when viewing the service info in the
	 * admin interface.
	 *
	 * @param stdClass $service A stdClass object representing the service
	 * @param stdClass $package A stdClass object representing the service's package
	 * @return string HTML content containing information to display when viewing the service info
	 */
	public function getAdminServiceInfo($service, $package) {
		$row = $this->getModuleRow();
		
		// Load the view into this object, so helpers can be automatically added to the view
		$this->view = new View("admin_service_info", "default");
		$this->view->base_uri = $this->base_uri;
		$this->view->setDefaultView("components" . DS . "modules" . DS . "interworx" . DS);
		
		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html"));

		$this->view->set("module_row", $row);
		$this->view->set("package", $package);
		$this->view->set("service", $service);
		$this->view->set("service_fields", $this->serviceFieldsToObject($service->fields));
		
		return $this->view->fetch();
	}
	
	/**
	 * Fetches the HTML content to display when viewing the service info in the
	 * client interface.
	 *
	 * @param stdClass $service A stdClass object representing the service
	 * @param stdClass $package A stdClass object representing the service's package
	 * @return string HTML content containing information to display when viewing the service info
	 */
	public function getClientServiceInfo($service, $package) {
		$row = $this->getModuleRow();
		
		// Load the view into this object, so helpers can be automatically added to the view
		$this->view = new View("client_service_info", "default");
		$this->view->base_uri = $this->base_uri;
		$this->view->setDefaultView("components" . DS . "modules" . DS . "interworx" . DS);
		
		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html"));

		$this->view->set("module_row", $row);
		$this->view->set("package", $package);
		$this->view->set("service", $service);
		$this->view->set("service_fields", $this->serviceFieldsToObject($service->fields));
		
		return $this->view->fetch();
	}
	
	/**
	 * Generates a username from the given host name
	 *
	 * @param string $host_name The host name to use to generate the username
	 * @return string The username generated from the given hostname
	 */
	private function generateUsername($host_name) {
		// Remove everything except letters and numbers from the domain
		// ensure no number appears in the beginning
		$username = ltrim(preg_replace('/[^a-z0-9]/i', '', $host_name), '0123456789');
		
		$length = strlen($username);
		$pool = "abcdefghijklmnopqrstuvwxyz0123456789";
		$pool_size = strlen($pool);
		
		if ($length < 5) {
			for ($i=$length; $i<8; $i++) {
				$username .= substr($pool, mt_rand(0, $pool_size-1), 1);
			}
			$length = strlen($username);
		}
		
		return substr($username, 0, min($length, 8));
	}

    /**
	 * Client Actions (reset password)
	 *
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param array $get Any GET parameters
	 * @param array $post Any POST parameters
	 * @param array $files Any FILES parameters
	 * @return string The string representing the contents of this tab
	 */
	public function tabClientActions($package, $service, array $get=null, array $post=null, array $files=null) {
		$this->view = new View("tab_client_actions", "default");
        $this->view->base_uri = $this->base_uri;
		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html"));

		$service_fields = $this->serviceFieldsToObject($service->fields);

        // Perform the password reset
        if (!empty($post)) {
            Loader::loadModels($this, array("Services"));
            $data = array(
                'interworx_password' => $this->Html->ifSet($post['interworx_password']),
                'interworx_confirm_password' => $this->Html->ifSet($post['interworx_confirm_password'])
            );
            $this->Services->edit($service->id, $data);

            if ($this->Services->errors())
                $this->Input->setErrors($this->Services->errors());

            $vars = (object)$post;
        }

        $this->view->set("service_fields", $service_fields);
        $this->view->set("service_id", $service->id);
        $this->view->set("vars", (isset($vars) ? $vars : new stdClass()));

		$this->view->setDefaultView("components" . DS . "modules" . DS . "interworx" . DS);
		return $this->view->fetch();
	}
	
	/**
	 * Statistics tab (bandwidth/disk usage)
	 *
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param array $get Any GET parameters
	 * @param array $post Any POST parameters
	 * @param array $files Any FILES parameters
	 * @return string The string representing the contents of this tab
	 */
	public function tabStats($package, $service, array $get=null, array $post=null, array $files=null) {
		$row = $this->getModuleRow();
		$api = $this->getApi($row->meta);
		
		$service_fields = $this->serviceFieldsToObject($service->fields);
		
		// Fetch account info
		$account_info = array();
		$account_info["server"] = $row->meta->host_name;
		if($package->meta->type != "reseller") {
			$account_info["domain"] = $service_fields->interworx_domain;
		}
		
		$package_id = null;
		$package_info = array();
		if($package->meta->package) {
			
			$action = $row->meta->host_name . "|" . ($package->meta->type == "reseller" ? "listResellerPackages" : "listPackages");
			$this->log($action, serialize($service_fields->interworx_username), "input", true);
			$packages = $this->parseResponse(($package->meta->type == "reseller" ? $api->listResellerPackages() : $api->listPackages()), $action);
			
			if(!empty($packages->response) && isset($packages->status) && $packages->status == "success") {
				foreach($packages->response as $pack) {
					if($pack['id'] == $package->meta->package) {
						$package_info = $pack;
						foreach($package_info as &$package_detail) {
							if($api->isUnlimited($package_detail)) {
								$package_detail = "opt_unlimited";
							}
						}
						break;
					}
				}
			}
		}
	
		$disk_usage = array(
			'used' => null,
			'limit' => null
		);
		$bandwidth_usage = array(
			'used' => null,
			'limit' => null
		);
		
		$action = $row->meta->host_name . "|listUsage";
		$this->log($action, serialize($service_fields->interworx_domain), "input", true);

		$bw = $this->parseResponse($api->listUsage($package->meta->type == "reseller" ? null : $service_fields->interworx_domain), $action);

        // Set the view
        $view = ($bw ? "tab_stats" : "tab_unavailable");
        $this->view = new View($view, "default");
        $this->view->setDefaultView("components" . DS . "modules" . DS . "interworx" . DS);
		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html"));

        // Stats not available, show unavailable tab
        if (!$bw)
            return $this->view->fetch();

		$all_accounts = $bw->response;
		if($package->meta->type == "reseller") {
			$bw = new stdClass;
			$bw->bandwidth_used = 0;
			$bw->bandwidth = 0;
			$bw->storage_used = 0;
			$bw->storage = 0;
			if(!empty($all)) {
				foreach($all_accounts as $account) {
					$bw->bandwidth_used += $account['bandwidth_used'];
					$bw->bandwidth += $account['bandwidth'];
					$bw->storage_used += $account['storage_used'];
					$bw->storage += $account['storage'];
				}
			}
		}
		else {
			$bw = (object)$bw->response;
		}
				
		if (isset($bw->bandwidth_used)) {
			$bandwidth_usage['used'] = $bw->bandwidth_used;
			$bandwidth_usage['limit'] = ($api->isUnlimited($bw->bandwidth) ? "bandwidth_unlimited" : $bw->bandwidth);
		}
		
		if (isset($bw->storage_used)) {
			$disk_usage['used'] = $bw->storage_used;
			$disk_usage['limit'] = ($api->isUnlimited($bw->storage) ? "disk_unlimited" : $bw->storage);
		}
		

		$this->view->set("bandwidth_usage", $bandwidth_usage);
		$this->view->set("disk_usage", $disk_usage);
		$this->view->set("account_info", $account_info);
		$this->view->set("package_info", $package_info);
		$this->view->set("user_type", $package->meta->type);
		
		return $this->view->fetch();
	}
	
	/**
	 * Retrieves the accounts on the server
	 *
	 * @param stdClass $module_row A stdClass object representing a single server
	 * @return mixed The number of accounts on the server, or false on error
	 */
	private function getAccountCount($module_row) {
		$api = $this->getApi($module_row->meta);
		$accounts = false;
		
		try {
			$action = $module_row->meta->host_name . "|listAccounts";
			$this->log($action, null, "input", true);
			
			$result = $this->parseResponse($api->listAccounts(), $action);
			
			if ($result && isset($result->status) && $result->status == "success" && isset($result->response))
				$accounts = count($result->response);
		}
		catch (Exception $e) {
			// Nothing to do
		}
		return $accounts;
	}
	
	/**
	 * Updates the module row meta number of accounts
	 *
	 * @param stdClass $module_row A stdClass object representing a single server
	 */
	private function updateAccountCount($module_row) {
		$api = $this->getApi($module_row->meta);
		
		// Get the number of accounts on the server
		if (($count = $this->getAccountCount($module_row))) {
			// Update the module row account list
			Loader::loadModels($this, array("ModuleManager"));
			$vars = $this->ModuleManager->getRowMeta($module_row->id);
			
			if ($vars) {
				$vars->account_count = $count;
				$vars = (array)$vars;
				
				$this->ModuleManager->editRow($module_row->id, $vars);
			}
		}
	}
	
	/**
	 * Validates that the given hostname is valid
	 *
	 * @param string $host_name The host name to validate
	 * @return boolean True if the hostname is valid, false otherwise
	 */
	public function validateHostName($host_name) {
		if (strlen($host_name) > 255)
			return false;
		
		return $this->Input->matches($host_name, "/^([a-z0-9]|[a-z0-9][a-z0-9\-]{0,61}[a-z0-9])(\.([a-z0-9]|[a-z0-9][a-z0-9\-]{0,61}[a-z0-9]))*$/");
	}
	
	/**
	 * Validates that at least 2 name servers are set in the given array of name servers
	 *
	 * @param array $name_servers An array of name servers
	 * @return boolean True if the array count is >= 2, false otherwise
	 */
	public function validateNameServerCount($name_servers) {
		if (is_array($name_servers) && count($name_servers) >= 2)
			return true;
		return false;
	}
	
	/**
	 * Validates that the nameservers given are formatted correctly
	 *
	 * @param array $name_servers An array of name servers
	 * @return boolean True if every name server is formatted correctly, false otherwise
	 */
	public function validateNameServers($name_servers) {
		if (is_array($name_servers)) {
			foreach ($name_servers as $name_server) {
				if (!$this->validateHostName($name_server))
					return false;
			}
		}
		return true;
	}
	
	/**
	 * Validates whether or not the connection details are valid by attempting to fetch
	 * the number of accounts that currently reside on the server
	 *
	 * @return boolean True if the connection is valid, false otherwise
	 */
	public function validateConnection($key, $vars) {
		try {
			$api = $this->getApi($vars);
			$connection = $api->canConnect();
			if (!empty($connection->response) && isset($connection->status) && $connection->status == "success") {
				return true;
			}
		}
		catch (Exception $e) {
			// Trap any errors encountered, could not validate connection
		}
		return false;
	}
	
	/**
	 * Attempts to validate service info. This is the top-level error checking method. Sets Input errors on failure.
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @param array $vars An array of user supplied info to satisfy the request
	 * @param boolean $edit True if editing the service, false otherwise
	 * @return boolean True if the service validates, false otherwise. Sets Input errors when false.
	 */
	public function validateService($package, array $vars=null, $edit=false) {
		// Validate the service fields
		$rules = array(
			'interworx_domain' => array(
				'format' => array(
					'rule' => array(array($this, "validateHostName")),
					'message' => Language::_("Interworx.!error.interworx_domain.format", true)
				)
			),
			'interworx_username' => array(
				'format' => array(
					'if_set' => true,
					'rule' => array("matches", "/^[a-z0-9]*$/i"),
					'message' => Language::_("Interworx.!error.interworx_username.format", true)
				),
				'length' => array(
					'if_set' => true,
					'rule' => array("betweenLength", 1, 8),
					'message' => Language::_("Interworx.!error.interworx_username.length", true)
				)
			),
			'interworx_password' => array(
				'format' => array(
					'if_set' => true,
					'rule' => "isEmpty",
					'negate' => true,
					'message' => Language::_("Interworx.!error.interworx_password.format", true)
				),
				'length' => array(
					'if_set' => true,
					'rule' => array("minLength", 6),
					'message' => Language::_("Interworx.!error.interworx_password.length", true)
				)
			),
			'interworx_confirm_password' => array(
				'matches' => array(
					'if_set' => true,
					'rule' => array("compares", "==", (isset($vars['interworx_password']) ? $vars['interworx_password'] : "")),
					'message' => Language::_("Interworx.!error.interworx_confirm_password.matches", true)
				)
			),
			'interworx_email' => array(
				'format' => array(
					'if_set' => true,
					'rule' => "isEmail",
					'message' => Language::_("Interworx.!error.interworx_email.format", true)
				)
			)
		);
		
		// Resellers don't need a username, but they need a nickname, which is created from the username
		if ($package->meta->type == "reseller")
			unset($rules['interworx_username']['format']);
		
		// Set the values that may be empty
		$empty_values = array("interworx_username", "interworx_password", "interworx_confirm_password", "interworx_email");
		if ($edit || $package->meta->type == "reseller") {
			// On edit, domain is optional
			$rules['interworx_domain']['format']['if_set'] = true;
		}
		
		// Remove rules on empty fields
		foreach ($empty_values as $value) {
			// Confirm password must be given if password is too
			if ($value == "interworx_confirm_password" && !empty($vars['interworx_password']))
				continue;
			
			if (empty($vars[$value]))
				unset($rules[$value]);
		}
		
		$this->Input->setRules($rules);
		return $this->Input->validates($vars);
	}
	
	/**
	 * Returns an array of service field to set for the service using the given input
	 *
	 * @param array $vars An array of key/value input pairs
	 * @param stdClass $package A stdClass object representing the package for the service
	 * @return array An array of key/value pairs representing service fields
	 */
	private function getFieldsFromInput(array $vars, $package) {
		$fields = array(
			'domain' => isset($vars['interworx_domain']) ? $vars['interworx_domain'] : null,
			'email' => isset($vars['interworx_email']) ? $vars['interworx_email']: null,
			'username' => isset($vars['interworx_username']) ? $vars['interworx_username']: null,
			'password' => isset($vars['interworx_password']) ? $vars['interworx_password'] : null,
			'plan' => $package->meta->package,
			'reseller' => ($package->meta->type == "reseller" ? 1 : 0)
		);
		
		return $fields;
	}
		
	/**
	 * Parses the response from the API into a stdClass object
	 *
	 * @param string $response The response from the API
	 * @param string $log_action The API action
	 * @return stdClass A stdClass object representing the response, void if the response was an error
	 */
	private function parseResponse($response, $log_action = "") {
		$success = true;
		
		// Set internal error
		if (!isset($response->status)) {
			$this->Input->setErrors(array('api' => array('internal' => Language::_("Interworx.!error.api.internal", true))));
			$success = false;
		}
				
		// Only some API requests return status, so only use it if its available
		if (isset($response->status) && isset($response->response) && $response->status == "error") {
			$msg = (Language::_("Interworx.!error.api." . $response->response, true) ? Language::_("Interworx.!error.api." . $response->response, true) : Language::_("Interworx.!error.api.internal", true));
			$this->Input->setErrors(array('api' => array('result' => $msg)));
			$success = false;
		}
		
		// Log the response
		$this->log($log_action, serialize($response), "output", $success);
		
		// Return if any errors encountered
		if (!$success)
			return;
		
		return $response;
	}
	
	/**
	 * Initializes the InterworxApi and returns an instance of that object with the given $host and $apikey set
	 *
	 * @param mixed $row_meta The Mod Row to the Interworx server
	 * @return InterworxApi The InterworxApi instance
	 */
	private function getApi($row_meta) {
		Loader::load(dirname(__FILE__) . DS . "apis" . DS . "interworx_api.php");
		
		$api = new InterworxApi();
		$api->setHost($row_meta->host_name);
		$api->setApiKey($row_meta->key);
		$api->setPort($row_meta->port);
		$api->setDebug($row_meta->debug);
		
		if(isset($row_meta->use_ssl) && $row_meta->use_ssl == "false") {
			$api->setProtocol("http");
		}
		else {
			$api->setProtocol("https");
		}
		
		return $api;
	}
	
	/**
	 * Fetches a listing of all packages configured in Interworx for the given server
	 *
	 * @param stdClass $module_row A stdClass object representing a single server
	 * @return array An array of packages in key/value pair
	 */
	private function getInterworxPackages($module_row, $reseller = false) {
		if (!isset($this->DataStructure))
			Loader::loadHelpers($this, array("DataStructure"));
		if (!isset($this->ArrayHelper))
			$this->ArrayHelper = $this->DataStructure->create("Array");
				
		$api = $this->getApi($module_row->meta);
				
		if($reseller) {
			$action = $module_row->meta->host_name . "|listResellerPackages";
			$this->log($action, serialize($module_row->meta->host_name), "input", true);
			
			$packages = $this->parseResponse($api->listResellerPackages(), $action);
		}
		else {
			$action = $module_row->meta->host_name . "|listPackages";
			$this->log($action, serialize($module_row->meta->host_name), "input", true);
			
			$packages = $this->parseResponse($api->listPackages(), $action);
		}
			
		if ($this->Input->errors())
			return;
		
		return $this->ArrayHelper->numericToKey($packages->response, 'id', 'name');
	}
	
	/**
	 * Returns if the current API User is a Reseller or not
	 *
	 * @param stdClass $module_row A stdClass object representing a single server
	 * @return boolean
	 */
	private function getInterworxIsReseller($module_row) {
				
		$api = $this->getApi($module_row->meta);
		
		$action = $module_row->meta->host_name . "|isUserReseller";
		$this->log($action, serialize($module_row->meta->host_name), "input", true);
			
		$results = $this->parseResponse($api->isUserReseller(), $action);
			
		if ($this->Input->errors())
			return false;
		
		return $results->response;
	}
	
	/**
	 * Generates a password
	 *
	 * @param int $min_length The minimum character length for the password (5 or larger)
	 * @param int $max_length The maximum character length for the password (14 or fewer)
	 * @return string The generated password
	 */
	private function generatePassword($min_length=10, $max_length=14) {
		$pool = "abcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()";
		$pool_size = strlen($pool);
		$length = mt_rand(max($min_length, 5), min($max_length, 14));
		$password = "";
		
		for ($i=0; $i<$length; $i++) {
			$password .= substr($pool, mt_rand(0, $pool_size-1), 1);
		}
		
		return $password;
	}
	
	/**
	 * Builds and returns the rules requied to add/edit a module row (e.g. server)
	 *
	 * @param array $vars An array of key/value data pairs
	 * @return array An array of Input rules suitable for Input::setRules()
	 */
	private function getRowRules(&$vars) {
		$rules = array(
			'server_name'=>array(
				'valid'=>array(
					'rule'=>"isEmpty",
					'negate'=>true,
					'message'=>Language::_("Interworx.!error.server_name_valid", true)
				)
			),
			'host_name'=>array(
				'valid'=>array(
					'rule'=>array(array($this, "validateHostName")),
					'message'=>Language::_("Interworx.!error.host_name_valid", true)
				)
			),
			'key'=>array(
				'valid'=>array(
					'last'=>true,
					'rule'=>"isEmpty",
					'negate'=>true,
					'message'=>Language::_("Interworx.!error.remote_key_valid", true)
				),
				'valid_connection'=>array(
					'rule'=>array(array($this, "validateConnection"), (object)$vars),
					'message'=>Language::_("Interworx.!error.remote_key_valid_connection", true)
				)
			),
			'account_limit'=>array(
				'valid'=>array(
					'rule'=>array("matches", "/^([0-9]+)?$/"),
					'message'=>Language::_("Interworx.!error.account_limit_valid", true)
				)
			),
			'name_servers'=>array(
				'count'=>array(
					'rule'=>array(array($this, "validateNameServerCount")),
					'message'=>Language::_("Interworx.!error.name_servers_count", true)
				),
				'valid'=>array(
					'rule'=>array(array($this, "validateNameServers")),
					'message'=>Language::_("Interworx.!error.name_servers_valid", true)
				)
			)
		);
		
		return $rules;
	}
	
	/**
	 * Builds and returns rules required to be validating when adding/editing a package
	 *
	 * @param array $vars An array of key/value data pairs
	 * @return array An array of Input rules suitable for Input::setRules()
	 */
	private function getPackageRules($vars) {
		$rules = array(
			'meta[type]' => array(
				'valid' => array(
					'rule' => array("matches", "/^(standard|reseller)$/"),
					'message' => Language::_("Interworx.!error.meta[type].valid", true), // type must be standard or reseller
				)
			),
			'meta[package]' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => Language::_("Interworx.!error.meta[package].empty", true) // package must be given
				)
			)
		);
		
		return $rules;
	}
}
?>