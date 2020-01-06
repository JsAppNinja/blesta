<?php
/**
 * Admin Company Custom Field Settings
 * 
 * @package blesta
 * @subpackage blesta.app.controllers
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class AdminCompanyCustomfields extends AppController {
	
	/**
	 * Pre-action
	 */
	public function preAction() {
		parent::preAction();		
		
		// Require login
		$this->requireLogin();		
		
		$this->uses(array("Clients", "ClientGroups", "Navigation"));
		
		Language::loadLang("admin_company_customfields");
		
		// Set the left nav for all settings pages to settings_leftnav
		$this->set("left_nav", $this->partial("settings_leftnav", array("nav"=>$this->Navigation->getCompany($this->base_uri))));
	}
	
	/**
	 * Custom Fields page
	 */
	public function index() {
		// Get all client groups and fields
		$client_groups = $this->ClientGroups->getAll($this->company_id);
		$client_fields = $this->Clients->getCustomFields($this->company_id);
		
		// Merge client groups and fields into a nicely formatted array of objects
		$groups = array();		
		if ($client_groups) {
			$num_groups = count($client_groups);
			$num_fields = ($client_fields) ? count($client_fields) : 0;
			
			// Set client group and client fields
			for ($i=0; $i<$num_groups; $i++) {
				$groups[$i] = new stdClass();
				$groups[$i]->name = $client_groups[$i]->name;
				$groups[$i]->color = $client_groups[$i]->color;
				$groups[$i]->fields = array();
				
				// Set any client fields for this group
				for ($j=0; $j<$num_fields; $j++) {
					if ($client_groups[$i]->id == $client_fields[$j]->client_group_id)
						$groups[$i]->fields[] = $client_fields[$j];
				}
			}
		}
		
		$this->set("groups", $groups);
		$this->set("types", $this->Clients->getCustomFieldTypes());
	}
	
	/**
	 * Add custom field
	 */
	public function add() {
		$this->helpers(array("DataStructure"));
		$this->ArrayHelper = $this->DataStructure->create("Array");
		
		$vars = new stdClass();
		
		// Add the custom field
		if (!empty($this->post)) {
			// Set regex if custom field is required
			if (empty($this->post['required']))
				$this->post['regex'] = null;
			elseif ($this->post['required'] == "/.+/")
				$this->post['regex'] = $this->post['required'];
			
			// Set empty checkboxes
			if (empty($this->post['is_lang']))
				$this->post['is_lang'] = "0";
			if (empty($this->post['show_client']))
				$this->post['show_client'] = "0";
			if (empty($this->post['read_only']))
				$this->post['read_only'] = "0";
			if (empty($this->post['encrypted']))
				$this->post['encrypted'] = "0";
			
			$post_data = $this->post;
			
			// Reformat select/checkbox values
			if (!empty($post_data['type'])) {
				switch ($post_data['type']) {
					case "checkbox":
						// Include values specified as checkbox values
						$post_data['values'] = $post_data['checkbox_value'];
						break;
					case "select":
						// Include values specified as select values
						$post_data['values'] = array();
						
						$select_options = $this->ArrayHelper->keyToNumeric($post_data['select']);
						
						// Set option values
						foreach ($select_options as $option)
							$post_data['values'][(isset($option['option']) ? $option['option'] : "")] = (isset($option['value']) ? $option['value'] : "");
						break;
				}
			}
			
			// Add the custom field
			$this->Clients->addCustomField($post_data);
			
			if (($errors = $this->Clients->errors())) {
				// Error, reset vars
				$this->setMessage("error", $errors);
				$vars = (object)$this->post;
			}
			else {
				// Success, redirect
				$this->flashMessage("message", Language::_("AdminCompanyCustomFields.!success.field_created", true));
				$this->redirect($this->base_uri . "settings/company/customfields/");
			}
		}
		
		$this->set("groups", $this->Form->collapseObjectArray($this->ClientGroups->getAll($this->company_id), "name", "id"));
		$this->set("types", $this->Clients->getCustomFieldTypes());
		$this->set("required_types", $this->getRequired());
		$this->set("vars", $vars);
	}
	
	/**
	 * Edit a custom field
	 */
	public function edit() {
		if (isset($this->get[0]) && !($field = $this->Clients->getCustomField((int)$this->get[0], $this->company_id)))
			$this->redirect($this->base_uri . "settings/company/customfields/");
		
		$this->helpers(array("DataStructure"));
		$this->ArrayHelper = $this->DataStructure->create("Array");
		
		$vars = array();
		
		// Edit the custom field
		if (!empty($this->post)) {
			// Set client group ID
			$this->post['client_group_id'] = $field->client_group_id;
			
			// Set regex if custom field is required
			if (empty($this->post['required']))
				$this->post['regex'] = null;
			elseif ($this->post['required'] == "/.+/")
				$this->post['regex'] = $this->post['required'];
			
			// Set empty checkboxes
			if (empty($this->post['is_lang']))
				$this->post['is_lang'] = "0";
			if (empty($this->post['show_client']))
				$this->post['show_client'] = "0";
			if (empty($this->post['read_only']))
				$this->post['read_only'] = "0";
			if (empty($this->post['encrypted']))
				$this->post['encrypted'] = "0";
			
			$post_data = $this->post;
			
			// Reformat select/checkbox values
			if (!empty($post_data['type'])) {
				switch ($post_data['type']) {
					case "checkbox":
						// Include values specified as checkbox values
						$post_data['values'] = $post_data['checkbox_value'];
						break;
					case "select":
						// Include values specified as select values
						$post_data['values'] = array();
						
						$select_options = $this->ArrayHelper->keyToNumeric($post_data['select']);
						
						// Set option values
						foreach ($select_options as $option)
							$post_data['values'][(isset($option['option']) ? $option['option'] : "")] = (isset($option['value']) ? $option['value'] : "");
						break;
				}
			}
			
			// Edit the custom field
			$this->Clients->editCustomField($field->id, $post_data);
			
			if (($errors = $this->Clients->errors())) {
				// Error, reset vars
				$this->setMessage("error", $errors);
				$vars = (object)$this->post;
			}
			else {
				// Success, redirect
				$this->flashMessage("message", Language::_("AdminCompanyCustomFields.!success.field_updated", true));
				$this->redirect($this->base_uri . "settings/company/customfields/");
				
			}
		}
		
		// Set current field
		if (empty($vars)) {
			$vars = $field;
			
			// Set the required status of this custom field
			$vars->required = (empty($vars->regex) ? "" : ($vars->regex == "/.+/" ? $vars->regex : "regex"));
			
			// Format the values
			if ($vars->type != null) {
				switch ($vars->type) {
					case "checkbox":
						// Format the checkbox options for the view
						$vars->checkbox_value = $vars->values;
						break;
					case "select":
						// Format the select options for the view
						$select_values = array(
							'option' => array(),
							'value' => array()
						);
						
						// Set each select option/value
						if (!empty($vars->values)) {
							$i = 0;
							foreach ($vars->values as $option=>$value) {
								$select_values['option'][$i] = $option;
								$select_values['value'][$i] = $value;
								$i++;
							}
						}
						$vars->select = $select_values;
						break;
				}
			}
		}
		
		$this->set("types", $this->Clients->getCustomFieldTypes());
		$this->set("required_types", $this->getRequired());
		$this->set("vars", $vars);
	}
	
	/**
	 * Delete a custom field
	 */
	public function delete() {
		// Ensure a valid custom field was given
		if (!isset($this->post['id']) || !($field = $this->Clients->getCustomField($this->post['id'], $this->company_id)))
			$this->redirect($this->base_uri . "settings/company/customfields/");
		
		$this->Clients->deleteCustomField($field->id);
		$this->flashMessage("message", Language::_("AdminCompanyCustomFields.!success.field_deleted", true));
		$this->redirect($this->base_uri . "settings/company/customfields/");
	}
	
	/**
	 * Returns a list of required custom field types
	 *
	 * @return array A key=>value array of custom field required types
	 */
	private function getRequired() {
		return array(
			""=>Language::_("AdminCompanyCustomFields.getRequired.no", true),
			"/.+/"=>Language::_("AdminCompanyCustomFields.getRequired.yes", true),
			"regex"=>Language::_("AdminCompanyCustomFields.getRequired.regex", true)
		);
	}
}
?>