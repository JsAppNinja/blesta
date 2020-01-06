<?php
/**
 * Admin Packages Management
 * 
 * @package blesta
 * @subpackage blesta.app.controllers
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class AdminPackages extends AppController {
	
	/**
	 * Packages pre-action
	 */
	public function preAction() {
		parent::preAction();
		
		// Require login
		$this->requireLogin();		
		
		$this->uses(array("Packages", "PackageGroups"));
		$this->helpers(array("DataStructure"));
		
		// Create Array Helper
		$this->ArrayHelper = $this->DataStructure->create("Array");
		
		Language::loadLang(array("admin_packages"));
	}
	
	/**
	 * List packages
	 */
	public function index() {
		// Set current page of results
		$status = (isset($this->get[0]) ? $this->get[0] : "active");
		$page = (isset($this->get[1]) ? (int)$this->get[1] : 1);
		$sort = (isset($this->get['sort']) ? $this->get['sort'] : "id_code");
		$order = (isset($this->get['order']) ? $this->get['order'] : "desc");
		
		// Set the number of packages of each type
		$status_count = array(
			'active' => $this->Packages->getListCount("active"),
			'inactive' => $this->Packages->getListCount("inactive"),
			'restricted' => $this->Packages->getListCount("restricted")
		);
		
		$this->set("status", $status);
		$this->set("status_count", $status_count);
		$this->set("packages", $this->Packages->getList($page, array($sort => $order), $status));
		$this->set("sort", $sort);
		$this->set("order", $order);
		$this->set("negate_order", ($order == "asc" ? "desc" : "asc"));
		$total_results = $this->Packages->getListCount($status);
		
		// Overwrite default pagination settings
		$settings = array_merge(Configure::get("Blesta.pagination"), array(
				'total_results' => $total_results,
				'uri'=>$this->base_uri . "packages/index/" . $status . "/[p]/",
				'params'=>array('sort'=>$sort,'order'=>$order),
			)
		);
		$this->helpers(array("Pagination"=>array($this->get, $settings)));
		$this->Pagination->setSettings(Configure::get("Blesta.pagination_ajax"));
		
		// Render the request if ajax
		return $this->renderAjaxWidgetIfAsync(isset($this->get[1]) || isset($this->get['sort']));
	}
	
	/**
	 * AJAX request for all pricing details for a package
	 */
	public function packagePricing() {
		if (!isset($this->get[0]))
			exit();
		
		$package = $this->Packages->get((int)$this->get[0]);
		
		// Ensure the package exists and this is an ajax request
		if (!$this->isAjax() || !$package) {
			header($this->server_protocol . " 403 Forbidden");
			exit();
		}
		
		// Set language for periods
		$periods = $this->Packages->getPricingPeriods();
		foreach ($this->Packages->getPricingPeriods(true) as $period=>$lang)
			$periods[$period . "_plural"] = $lang;
		
		$vars = array(
			'pricing'=>$package->pricing,
			'periods'=>$periods
		);
		
		// Send the template
		echo $this->partial("admin_packages_packagepricing", $vars);
		
		// Render without layout
		return false;
	}
	
	/**
	 * List module options for ajax requests
	 */
	public function moduleOptions() {
		
		if (!$this->isAjax() || !isset($this->post['module_id']))
			return false;
		
		$this->uses(array("ModuleManager"));
		$this->components(array("Modules"));

		if (!isset($this->post['module_row']))
			$this->post['module_row'] = 0;
		if (!isset($this->post['module_group']))
			$this->post['module_group'] = "";

		$module = $this->ModuleManager->initModule($this->post['module_id'], $this->company_id);
		
		if (!$module)
			return false;
		
		// Fetch all package fields this module requires
		$package_fields = $module->getPackageFields((object)$this->post);
		$fields = $package_fields->getFields();
		$html = $package_fields->getHtml();
		$tags = $module->getEmailTags();
		
		// Fetch the parser options to determine the start and end characters for template variables
		$parser_options = Configure::get("Blesta.parser_options");
		
		$module_email_tags = "";
		if (!empty($tags)) {
			$i=0;
			foreach ($tags as $group => $group_tags) {
				foreach ($group_tags as $tag) {
					$module_email_tags .= ($i++ > 0 ? " " : "") .
						$parser_options['VARIABLE_START'] . $group . "." . $tag . $parser_options['VARIABLE_END'];
				}
			}
		}
		
		$groups = $this->ArrayHelper->numericToKey((array)$this->ModuleManager->getGroups($this->post['module_id']), 'id', 'name');
		$rows = $this->ArrayHelper->numericToKey((array)$this->ModuleManager->getRows($this->post['module_id']), 'id', 'meta');
		
		$row_key = $module->moduleRowMetaKey();
		foreach ($rows as $key => &$value)
			$value = $value->$row_key;
		
		$data = array(
			'module_options'=>$this->partial('admin_packages_moduleoptions',
				array(
					'fields'=>$fields,
					'html'=>$html,
					'group_name'=>$module->moduleGroupName(),
					'groups'=>$groups,
					'row_name'=>$module->moduleRowName(),
					'rows'=>$rows,
					'vars'=>(object)$this->post
				)
			),
			'module_email_tags'=>$module_email_tags
		);
		
		$this->outputAsJson($data);
		return false;
	}
	
	/**
	 * Add package
	 */
	public function add() {
		
		$this->uses(array("Companies", "Currencies", "Languages", "ModuleManager", "PackageOptionGroups"));
		$this->components(array("SettingsCollection"));
		
        $vars = new stdClass();
        
        // Copy a package
        if (isset($this->get[0]) && ($package = $this->Packages->get((int)$this->get[0])) && $package->company_id == $this->company_id) {
            $vars = $package;
            // Set pricing to the correct format
            $vars->pricing = $this->ArrayHelper->numericToKey($vars->pricing);

            // Set package groups and option groups to the correct format
            $vars->groups = $this->Form->collapseObjectArray($vars->groups, "id");
            $vars->option_groups = $this->Form->collapseObjectArray($vars->option_groups, "id");
        }
		
		// Fetch all available package groups
		$package_groups = $this->Form->collapseObjectArray($this->Packages->getAllGroups($this->company_id), "name", "id");
		
		if (!empty($this->post)) {
			// Set company ID for this package
			$this->post['company_id'] = $this->company_id;
			// Set empty checkboxes
			if (!isset($this->post['taxable']))
				$this->post['taxable'] = 0;
			if (!isset($this->post['single_term']))
				$this->post['single_term'] = 0;
			
			// Begin transaction
			$this->Packages->begin();
			
			$data = $this->post;
			
			// Remove pro rata options
			if (!isset($data['prorata']) || $data['prorata'] != "1")
				unset($data['prorata_day'], $data['prorata_cutoff']);
			if (isset($data['prorata_cutoff']) && $data['prorata_cutoff'] == "")
				unset($data['prorata_cutoff']);
			
			// Attempt to add a package group if none are available
			$group_errors = array();
			if ((isset($data['select_group_type']) && $data['select_group_type'] == "new")) {
				$this->uses(array("PackageGroups"));
				$package_group_data = array(
					'name' => (isset($data['group_name']) ? $data['group_name'] : ""),
					'type' => "standard",
					'company_id' => $data['company_id']
				);
				$new_package_group_id = $this->PackageGroups->add($package_group_data);
				
				$group_errors = $this->PackageGroups->errors();
			}
			
			// Attempt to add a package
			$package_errors = array();
			if (empty($group_errors)) {
				if (isset($data['qty_unlimited']) && $data['qty_unlimited'] == "true")
					$data['qty'] = null;
				
				// Add the new package group if created
				if (!empty($new_package_group_id))
					$data['groups'] = array($new_package_group_id);
				
				if (isset($data['module_group']) && $data['module_group'] == "")
					unset($data['module_group']);
				
				$data['pricing'] = $this->ArrayHelper->keyToNumeric($this->post['pricing']);
				$this->Packages->add($data);
				$package_errors = $this->Packages->errors();
			}
			
			$errors = array_merge((!empty($group_errors) ? $group_errors : array()), (!empty($package_errors) ? $package_errors : array()));
			
			if ($errors) {
				// Error
				$this->Packages->rollBack();
				$this->setMessage("error", $errors);
				$vars = (object)$this->post;

				// Set each email to an object
				if (isset($vars->email_content)) {
					foreach ($vars->email_content as &$email_data)
						$email_data = (object)$email_data;
				}
			}
			else {
				// Success
				$this->Packages->commit();
				$this->flashMessage("message", Language::_("AdminPackages.!success.package_added", true));
				$this->redirect($this->base_uri . "packages/");
			}
		}
		
		// Get all settings
		$default_currency = $this->SettingsCollection->fetchSetting($this->Companies, $this->company_id, "default_currency");
		$default_currency = $default_currency['value'];

		// Get all currencies
		$currencies = $this->Form->collapseObjectArray($this->Currencies->getAll($this->company_id), "code", "code");
		
		// Set default currency as the selected currency
		if (empty($this->post) && empty($vars->pricing['currency']))
			$vars->pricing['currency'] = array($default_currency);
		
		// Set all selected package groups
		$vars->groups = (isset($vars->groups) && is_array($vars->groups) ? $vars->groups : array());
		$vars->groups = $this->getSelectedSwappableOptions($package_groups, $vars->groups);
		
		// Fetch all available package option groups
		$package_option_groups = $this->Form->collapseObjectArray($this->PackageOptionGroups->getAll($this->company_id), "name", "id");
		$vars->option_groups = (isset($vars->option_groups) && is_array($vars->option_groups) ? $vars->option_groups : array());
		$vars->option_groups = $this->getSelectedSwappableOptions($package_option_groups, $vars->option_groups);
		
		$this->set("prorata_days", $this->Packages->getProrataDays());
		$this->set("currencies", $currencies);
		$this->set("default_currency", $default_currency);
		$this->set("modules", $this->Form->collapseObjectArray($this->ModuleManager->getAll($this->company_id), "name", "id"));
		$this->set("status", $this->Packages->getStatusTypes());
		$this->set("periods", $this->Packages->getPricingPeriods());
		$this->set("languages", $this->Languages->getAll($this->company_id));
		$this->set("package_groups", $package_groups);
		$this->set("package_option_groups", $package_option_groups);
		$this->set("vars", $vars);
		
		$this->set("module_email_tags", $this->getWelcomeTags());
		
		// Include WYSIWYG
		$this->Javascript->setFile("ckeditor/ckeditor.js", "head", VENDORWEBDIR);
		$this->Javascript->setFile("ckeditor/adapters/jquery.js", "head", VENDORWEBDIR);
	}
	
	/**
	 * Edit package
	 */
	public function edit() {
		$this->uses(array("Companies", "Currencies", "Languages", "ModuleManager", "PackageOptionGroups"));
		$this->components(array("SettingsCollection"));
		
		// Redirect if invalid package ID given
		if (empty($this->get[0]) || !($package = $this->Packages->get((int)$this->get[0])) || $package->company_id != $this->company_id)
			$this->redirect($this->base_uri . "packages/");
		
		$vars = $package;
		// Set pricing to the correct format
		$vars->pricing = $this->ArrayHelper->numericToKey($vars->pricing);
		
		// Set package groups and option groups to the correct format
		$vars->groups = $this->Form->collapseObjectArray($vars->groups, "id");
		$vars->option_groups = $this->Form->collapseObjectArray($vars->option_groups, "id");
		
		// Update the package
		if (!empty($this->post)) {
			// Set empty checkboxes
			if (!isset($this->post['taxable']))
				$this->post['taxable'] = 0;
			if (!isset($this->post['single_term']))
				$this->post['single_term'] = 0;
			// Remove pro rata options
			if (!isset($this->post['prorata']) || $this->post['prorata'] != "1") {
				$this->post['prorata_day'] = null;
				$this->post['prorata_cutoff'] = null;
			}
			
			// Remove blank pricing IDs
			$this->post['pricing'] = (isset($this->post['pricing']) ? (array)$this->post['pricing'] : array());
			if (isset($this->post['pricing']['id'])) {
				foreach ($this->post['pricing']['id'] as $key => $id) {
					if (empty($id)) {
						unset($this->post['pricing']['id'][$key]);
					}
				}
			}
			
			$data = $this->post;
			
			// Remove pro rata options
			if (isset($data['prorata_cutoff']) && $data['prorata_cutoff'] == "")
				$data['prorata_cutoff'] = null;
			
			if (isset($data['qty_unlimited']) && $data['qty_unlimited'] == "true")
				$data['qty'] = null;
				
			if (isset($data['module_group']) && $data['module_group'] == "")
				$data['module_group'] = null;
			
			// Set to remove all package groups if none given
			if (empty($data['groups']))
				$data['groups'] = array();
			
			// Convert pricing back to the desired format
			$data['pricing'] = $this->ArrayHelper->keyToNumeric($this->post['pricing']);
			
			// Remove blank IDs. These will be added as new prices
			foreach ($data['pricing'] as &$prices) {
				if (array_key_exists("id", $prices) && empty($prices['id'])) {
					unset($prices['id']);
				}
			}
			
			$this->Packages->edit($package->id, $data);
			
			if (($errors = $this->Packages->errors())) {
				// Error
				$this->setMessage("error", $errors);
				$vars = (object)$this->post;
				
				// Set each email to an object
				if (isset($vars->email_content)) {
					foreach ($vars->email_content as &$email_data)
						$email_data = (object)$email_data;
				}
			}
			else {
				// Success
				$this->flashMessage("message", Language::_("AdminPackages.!success.package_updated", true));
				$this->redirect($this->base_uri . "packages/");
			}
		}
		
		// Get all settings
		$default_currency = $this->SettingsCollection->fetchSetting($this->Companies, $this->company_id, "default_currency");
		$default_currency = $default_currency['value'];
		
		// Get all currencies
		$currencies = $this->Form->collapseObjectArray($this->Currencies->getAll($this->company_id), "code", "code");
		
		// Fetch all available package groups
		$package_groups = $this->Form->collapseObjectArray($this->Packages->getAllGroups($this->company_id), "name", "id");
		$vars->groups = (isset($vars->groups) && is_array($vars->groups) ? $vars->groups : array());
		$vars->groups = $this->getSelectedSwappableOptions($package_groups, $vars->groups);
		
		// Fetch all available package option groups
		$package_option_groups = $this->Form->collapseObjectArray($this->PackageOptionGroups->getAll($this->company_id), "name", "id");
		$vars->option_groups = (isset($vars->option_groups) && is_array($vars->option_groups) ? $vars->option_groups : array());
		$vars->option_groups = $this->getSelectedSwappableOptions($package_option_groups, $vars->option_groups);
		
		$this->set("prorata_days", $this->Packages->getProrataDays());
		$this->set("currencies", $currencies);
		$this->set("default_currency", $default_currency);
		$this->set("modules", $this->Form->collapseObjectArray($this->ModuleManager->getAll($this->company_id), "name", "id"));
		$this->set("status", $this->Packages->getStatusTypes());
		$this->set("periods", $this->Packages->getPricingPeriods());
		$this->set("languages", $this->Languages->getAll($this->company_id));
		$this->set("package_groups", $package_groups);
		$this->set("package_option_groups", $package_option_groups);
		$this->set("vars", $vars);
		
		$this->set("module_email_tags", $this->getWelcomeTags());
		
		// Include WYSIWYG
		$this->Javascript->setFile("ckeditor/ckeditor.js", "head", VENDORWEBDIR);
		$this->Javascript->setFile("ckeditor/adapters/jquery.js", "head", VENDORWEBDIR);
	}
	
	/**
	 * Delete package
	 */
	public function delete() {
		// Redirect if invalid package ID given
		if (!isset($this->post['id']) || !($package = $this->Packages->get((int)$this->post['id'])) ||
			($package->company_id != $this->company_id))
			$this->redirect($this->base_uri . "packages/");
		
		// Attempt to delete the package
		$this->Packages->delete($package->id);
		
		if (($errors = $this->Packages->errors())) {
			// Error
			$this->flashMessage("error", $errors);
		}
		else {
			// Success
			$this->flashMessage("message", Language::_("AdminPackages.!success.package_deleted", true));
		}
		
		$this->redirect($this->base_uri . "packages/");
	}
	
	/**
	 * Package groups
	 */
	public function groups() {
		// Set current page of results
		$type = (isset($this->get[0]) ? $this->get[0] : "standard");
		$page = (isset($this->get[1]) ? (int)$this->get[1] : 1);
		$sort = (isset($this->get['sort']) ? $this->get['sort'] : "name");
		$order = (isset($this->get['order']) ? $this->get['order'] : "desc");
		
		// Get the count of each package group type
		$type_count = array(
			'standard' => $this->PackageGroups->getTypeCount($this->company_id, "standard"),
			'addon' => $this->PackageGroups->getTypeCount($this->company_id, "addon")
		);
		
		// Fetch package groups
		$package_groups = $this->PackageGroups->getList($this->company_id, $page, $type, array($sort => $order));
		
		// Fetch packages belonging to each group
		$packages = array();
		foreach ($package_groups as &$group)
			$group->packages = $this->Packages->getAllPackagesByGroup($group->id);
		
		$this->set("type", $type);
		$this->set("types", $this->PackageGroups->getTypes());
		$this->set("package_groups", $package_groups);
		$this->set("type_count", $type_count);
		$this->set("sort", $sort);
		$this->set("order", $order);
		$this->set("negate_order", ($order == "asc" ? "desc" : "asc"));
		
		// Overwrite default pagination settings
		$settings = array_merge(Configure::get("Blesta.pagination"), array(
				'total_results' => $this->PackageGroups->getListCount($this->company_id, $type),
				'uri'=>$this->base_uri . "packages/groups/" . $type . "/[p]/",
				'params'=>array('sort'=>$sort,'order'=>$order),
			)
		);
		$this->helpers(array("Pagination"=>array($this->get, $settings)));
		$this->Pagination->setSettings(Configure::get("Blesta.pagination_ajax"));
		
		// Render the request if ajax
		return $this->renderAjaxWidgetIfAsync(isset($this->get[1]) || isset($this->get['sort']));
	}
	
	/**
	 * Add a package group
	 */
	public function addGroup() {
		$vars = new stdClass();
		
		// Get all standard groups (available groups)
		$standard_groups = $this->PackageGroups->getAll($this->company_id, "standard");
		
		if (!empty($this->post)) {
			// Set the currenty company ID
			$this->post['company_id'] = $this->company_id;
			
			// Set checkboxes if not given
			if (!isset($this->post['allow_upgrades'])) {
				$this->post['allow_upgrades'] = "0";
			}
			
			// Add the package group
			$package_group_id = $this->PackageGroups->add($this->post);
			
			if (($errors = $this->PackageGroups->errors())) {
				// Error, reset vars
				unset($this->post['company_id']);
				$vars = (object)$this->post;
				$this->setMessage("error", $errors);
				
				// Format the parent groups
				if (!empty($vars->parents)) {
					$parents = array();
					
					foreach ($vars->parents as $parent_group_id) {
						foreach ($standard_groups as $standard_group) {
							if ($parent_group_id == $standard_group->id)
								$parents[] = (object)array('id'=>$parent_group_id, 'name'=>$standard_group->name);
						}
					}
					$vars->parents = $parents;
				}
			}
			else {
				// Success
				$package_group = $this->PackageGroups->get($package_group_id);
				$this->flashMessage("message", Language::_("AdminPackages.!success.group_added", true, $package_group->name));
				$this->redirect($this->base_uri . "packages/groups/");
			}
		}
		
		// Format the list of selected parent groups and available groups
		if (isset($vars->parents)) {
			foreach ($vars->parents as $parent_group) {
				// Remove standard groups that are currently selected as parent groups
				foreach ($standard_groups as $key=>$standard_group) {
					if ($parent_group->id == $standard_group->id)
						unset($standard_groups[$key]);
				}
			}
			
			$vars->parents = $this->Form->collapseObjectArray($vars->parents, "name", "id");
		}
		
		$this->set("vars", $vars);
		$this->set("group_types", $this->PackageGroups->getTypes());
		$this->set("standard_groups", $this->Form->collapseObjectArray($standard_groups, "name", "id"));
	}
	
	/**
	 * Edit a package group
	 */
	public function editGroup() {
		// Ensure a group has been given
		if (!isset($this->get[0]) || !($group = $this->PackageGroups->get((int)$this->get[0])) ||
			($this->company_id != $group->company_id))
			$this->redirect($this->base_uri . "packages/groups/");
		
		// Get all standard groups (available groups)
		$standard_groups = $this->PackageGroups->getAll($this->company_id, "standard");
		
		// Remove this group itself from the list of available standard groups
		foreach ($standard_groups as $key=>$standard_group) {
			if ($standard_group->id == $group->id) {
				unset($standard_groups[$key]);
				break;
			}
		}
		
		if (!empty($this->post)) {
			$this->post['company_id'] = $this->company_id;
			
			// Set checkboxes if not given
			if (!isset($this->post['allow_upgrades'])) {
				$this->post['allow_upgrades'] = "0";
			}
			
			$this->PackageGroups->edit($group->id, $this->post);
			
			if (($errors = $this->PackageGroups->errors())) {
				// Error
				$vars = (object)$this->post;
				$this->setMessage("error", $errors);
				
				// Format the parent groups
				if (!empty($vars->parents)) {
					$parents = array();
					
					foreach ($vars->parents as $parent_group_id) {
						foreach ($standard_groups as $standard_group) {
							if ($parent_group_id == $standard_group->id)
								$parents[] = (object)array('id'=>$parent_group_id, 'name'=>$standard_group->name);
						}
					}
					$vars->parents = $parents;
				}
			}
			else {
				// Success
				$package_group = $this->PackageGroups->get($group->id);
				$this->flashMessage("message", Language::_("AdminPackages.!success.group_updated", true, $package_group->name));
				$this->redirect($this->base_uri . "packages/groups/");
			}
		}
		else {
			// Set the initial group
			$vars = $group;
		}
		
		// Format the list of selected parent groups and available groups
		if (isset($vars->parents)) {
			foreach ($vars->parents as $parent_group) {
				// Remove standard groups that are currently selected as parent groups
				foreach ($standard_groups as $key=>$standard_group) {
					if ($parent_group->id == $standard_group->id)
						unset($standard_groups[$key]);
				}
			}
			
			$vars->parents = $this->Form->collapseObjectArray($vars->parents, "name", "id");
		}
		
		$this->set("vars", $vars);
		$this->set("standard_groups", $this->Form->collapseObjectArray($standard_groups, "name", "id"));
		$this->set("group_types", $this->PackageGroups->getTypes());
	}
	
	/**
	 * Deletes a package group
	 */
	public function deleteGroup() {
		// Ensure a group has been given
		if (!isset($this->post['id']) || !($group = $this->PackageGroups->get((int)$this->post['id'])) ||
			($this->company_id != $group->company_id))
			$this->redirect($this->base_uri . "packages/groups/");
		
		$this->PackageGroups->delete($group->id);
		
		$this->flashMessage("message", Language::_("AdminPackages.!success.group_deleted", true, $group->name));
		$this->redirect($this->base_uri . "packages/groups/");
	}
	
	/**
	 * Order packages within a package group
	 */
	public function orderPackages() {
		if (!$this->isAjax())
			$this->redirect($this->base_uri . "packages/groups/");
		
		if (!empty($this->post))
			$this->Packages->orderPackages($this->post['group_id'], $this->post['packages']);
		return false;
	}
	
	/**
	 * Returns a string containing all welcome tags available by default to package welcome emails
	 *
	 * @return string A string containing all welcome tags available by default to package welcome emails
	 */
	private function getWelcomeTags() {
		$this->uses(array("Services"));
		
		// Fetch the parser options to determine the start and end characters for template variables
		$parser_options = Configure::get("Blesta.parser_options");
		
		// Build all tags available by default in the welcome email
		$module_email_tags = "";
		$tags = $this->Services->getWelcomeEmailTags();
		if (!empty($tags)) {
			$i=0;
			foreach ($tags as $group => $group_tags) {
				foreach ($group_tags as $tag) {
					$module_email_tags .= ($i++ > 0 ? " " : "") .
						$parser_options['VARIABLE_START'] . $group . "." . $tag . $parser_options['VARIABLE_END'];
				}
			}
		}
		return $module_email_tags;
	}
	
	/**
	 * Retrieves a list of selected options for the swappable multi-select, and removes them from the available groups
	 *
	 * @param array &$available_groups A reference to a key/value array of all the available groups to choose from
	 * @param array $selected_groups A key/value array of all the selected groups
	 * @return array A key/value indexed array subset of the $available_groups that have been selected
	 */
	private function getSelectedSwappableOptions(array &$available_groups, array $selected_groups) {
		$selected = array();
		
		foreach ($available_groups as $id => $name) {
			if (in_array($id, $selected_groups)) {
				$selected[$id] = $name;
				unset($available_groups[$id]);
			}
		}
		
		return $selected;
	}
}
?>