<?php
/**
 * Download Manager manage plugin controller
 * 
 * @package blesta
 * @subpackage blesta.plugins.download_manager
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
		
		Language::loadLang("download_manager_manage_plugin", null, PLUGINDIR . "download_manager" . DS . "language" . DS);

		$this->uses(array("DownloadManager.DownloadManagerCategories", "DownloadManager.DownloadManagerFiles"));
		// Use the parent data helper, it's already configured properly
		$this->Date = $this->parent->Date;
		
		// Set the Data Structure Array
		$this->helpers(array("DataStructure"));
		$this->ArrayHelper = $this->DataStructure->create("Array");
		
		// Set the company ID
		$this->company_id = Configure::get("Blesta.company_id");
		
		// Set the plugin ID
		$this->plugin_id = (isset($this->get[0]) ? $this->get[0] : null);
		
		// Set the page title
		$this->parent->structure->set("page_title", Language::_("DownloadManagerManagePlugin." . Loader::fromCamelCase($this->action ? $this->action : "index") . ".page_title", true));
		
		// Set the view to render for all actions under this controller
		$this->view->setView(null, "DownloadManager.default");
	}
	
	/**
	 * Returns the view to be rendered when managing this plugin
	 */
	public function index() {
		$this->init();
		
		// Get the current category
		$parent_category_id = (isset($this->get[1]) ? $this->get[1] : null);
		$category = null;
		if ($parent_category_id !== null)
			$category = $this->DownloadManagerCategories->get($parent_category_id);
		
		$vars = array(
			'plugin_id' => $this->plugin_id,
			'categories' => $this->DownloadManagerCategories->getAll($this->company_id, $parent_category_id),
			'files' => $this->DownloadManagerFiles->getAll($this->company_id, $parent_category_id),
			'category' => $category, // current category
			'parent_category' => ($category ? $this->DownloadManagerCategories->get($category->parent_id) : null)
		);
		
		// Set the view to render
		return $this->partial("admin_manage_plugin", $vars);
	}
	
	/**
	 * Add a download
	 */
	public function add() {
		$this->init();
		
		$this->uses(array("ClientGroups", "Packages"));
		
		// Set category if given, otherwise default to the root category
		$category = (isset($this->get[1]) ? $this->DownloadManagerCategories->get($this->get[1]) : null);
		
		// Ensure the parent category is in the same company too
		if ($category && $category->company_id != $this->company_id)
			$this->redirect($this->base_uri . "settings/company/plugins/manage/" . $this->plugin_id . "/");
		
		// Get all client groups and packages for selection
		$client_groups = $this->ArrayHelper->numericToKey($this->ClientGroups->getAll($this->company_id), "id", "name");
		$packages = $this->ArrayHelper->numericToKey($this->Packages->getAll($this->company_id, array('name' => "ASC"), "active"), "id", "name");
		
		// Set vars
		$vars = array(
			'plugin_id' => $this->plugin_id,
			'client_groups' => $client_groups,
			'packages' => $packages,
			'category' => $category // current category
		);
		unset($client_groups, $packages);
		
		if (!empty($this->post)) {
			// Set the category this file is to be added in
			$data = array(
				'category_id' => (isset($category->id) ? $category->id : null),
				'company_id' => $this->company_id
			);
			
			// Set vars according to selected items
			if (isset($this->post['type']) && $this->post['type'] == "public")
				$data['public'] = "1";
			else {
				// Set availability to groups/packages
				if (isset($this->post['available_to_client_groups']) && $this->post['available_to_client_groups'] == "1")
					$data['permit_client_groups'] = "1";
				if (isset($this->post['available_to_packages']) && $this->post['available_to_packages'] == "1")
					$data['permit_packages'] = "1";
			}
			
			// Set any client groups/packages
			if (isset($data['permit_client_groups']))
				$data['file_groups'] = isset($this->post['file_groups']) ? (array)$this->post['file_groups'] : array();
			if (isset($data['permit_packages']))
				$data['file_packages'] = isset($this->post['file_packages']) ? (array)$this->post['file_packages'] : array();
			
			// Remove file name if path not selected
			// This indicates that the file is expected to be uploaded by post
			if (isset($this->post['file_type']) && $this->post['file_type'] == "upload")
				unset($this->post['file_name']);
			
			$data = array_merge($this->post, $data);
			
			// Add the download
			$this->DownloadManagerFiles->add($data, $this->files);
			
			if (($errors = $this->DownloadManagerFiles->errors())) {
				// Error, reset vars
				$vars['vars'] = (object)$this->post;
				$this->parent->setMessage("error", $errors);
			}
			else {
				// Success
				$this->parent->flashMessage("message", Language::_("DownloadManagerManagePlugin.!success.file_added", true));
				$this->redirect($this->base_uri . "settings/company/plugins/manage/" . $this->plugin_id . "/" . (isset($category->id) ? $category->id : ""));
			}
		}
		
		// Set all selected client groups in assigned and unset all selected client groups from available
		if (isset($vars['vars']->file_groups) && is_array($vars['vars']->file_groups)) {
			$selected = array();
			
			foreach ($vars['client_groups'] as $id => $name) {
				if (in_array($id, $vars['vars']->file_groups)) {
					$selected[$id] = $name;
					unset($vars['client_groups'][$id]);
				}
			}
			
			$vars['vars']->file_groups = $selected;
		}
		
		// Set all selected packages in assigned and unset all selected packages from available
		if (isset($vars['vars']->file_packages) && is_array($vars['vars']->file_packages)) {
			$selected = array();
			
			foreach ($vars['packages'] as $id => $name) {
				if (in_array($id, $vars['vars']->file_packages)) {
					$selected[$id] = $name;
					unset($vars['packages'][$id]);
				}
			}
			
			$vars['vars']->file_packages = $selected;
		}
		
		// Set the view to render
		return $this->partial("admin_manage_plugin_add", $vars);
	}
	
	/**
	 * Edit a download
	 */
	public function edit() {
		$this->init();
		
		// Ensure a file was given
		if (!isset($this->get[1]) || !($file = $this->DownloadManagerFiles->get($this->get[1])) ||
			($file->company_id != $this->company_id))
			$this->redirect($this->base_uri . "settings/company/plugins/manage/" . $this->plugin_id . "/");
		
		$this->uses(array("ClientGroups", "Packages"));
		
		// Get all client groups and packages for selection
		$client_groups = $this->ArrayHelper->numericToKey($this->ClientGroups->getAll($this->company_id), "id", "name");
		$packages = $this->ArrayHelper->numericToKey($this->Packages->getAll($this->company_id, array('name' => "ASC"), "active"), "id", "name");
		
		// Set vars
		$vars = array(
			'plugin_id' => $this->plugin_id,
			'client_groups' => $client_groups,
			'packages' => $packages,
			'category' => ($file->category_id ? $this->DownloadManagerCategories->get($file->category_id) : null) // current category
		);
		unset($client_groups, $packages);
		
		
		if (!empty($this->post)) {
			// Set the category this file belongs to
			$data = array(
				'category_id' => $file->category_id,
				'company_id' => $this->company_id
			);
			
			// Set vars according to selected items
			if (isset($this->post['type']) && $this->post['type'] == "public") {
				$data['public'] = "1";
				$data['permit_client_groups'] = "0";
				$data['permit_packages'] = "0";
			}
			else {
				$data['public'] = "0";
				
				// Set availability to groups/packages
				if (isset($this->post['available_to_client_groups']) && $this->post['available_to_client_groups'] == "1")
					$data['permit_client_groups'] = "1";
				if (isset($this->post['available_to_packages']) && $this->post['available_to_packages'] == "1")
					$data['permit_packages'] = "1";
			}
			
			// Set any client groups/packages
			if (isset($data['permit_client_groups']))
				$data['file_groups'] = isset($this->post['file_groups']) ? (array)$this->post['file_groups'] : array();
			if (isset($data['permit_packages']))
				$data['file_packages'] = isset($this->post['file_packages']) ? (array)$this->post['file_packages'] : array();
			
			// Remove file name if path not selected
			// This indicates that the file is expected to be uploaded by post
			if (isset($this->post['file_type']) && $this->post['file_type'] == "upload")
				unset($this->post['file_name']);
			
			$data = array_merge($this->post, $data);
			
			// Update the download
			$this->DownloadManagerFiles->edit($file->id, $data, $this->files);
			
			if (($errors = $this->DownloadManagerFiles->errors())) {
				// Error, reset vars
				$vars['vars'] = (object)$this->post;
				
				// Set the original path to the file if it was removed
				if (empty($this->post['file_name']))
					$vars['vars']->file_name = $file->file_name;
				
				$this->parent->setMessage("error", $errors);
			}
			else {
				// Success
				$this->parent->flashMessage("message", Language::_("DownloadManagerManagePlugin.!success.file_updated", true));
				$this->redirect($this->base_uri . "settings/company/plugins/manage/" . $this->plugin_id . "/" . $file->category_id);
			}
		}
		
		// Set initial packages/client groups
		if (empty($vars['vars'])) {
			$vars['vars'] = $file;
			$vars['vars']->file_groups = $this->ArrayHelper->numericToKey($file->client_groups, "client_group_id", "client_group_id");
			$vars['vars']->file_packages = $this->ArrayHelper->numericToKey($file->packages, "package_id", "package_id");
			
			// Default to 'path' since a file has already been uploaded
			$vars['vars']->file_type = "path";
			
			// Set default radio/checkboxes
			if ($file->permit_client_groups == "1" || $file->permit_packages == "1") {
				$vars['vars']->type = "logged_in";
				$vars['vars']->available_to_client_groups = ($file->permit_client_groups == "1" ? $file->permit_client_groups : "0");
				$vars['vars']->available_to_packages = ($file->permit_packages == "1" ? $file->permit_packages : "0");
			}
		}
		
		// Set all selected client groups in assigned and unset all selected client groups from available
		if (isset($vars['vars']->file_groups) && is_array($vars['vars']->file_groups)) {
			$selected = array();
			
			foreach ($vars['client_groups'] as $id => $name) {
				if (in_array($id, $vars['vars']->file_groups)) {
					$selected[$id] = $name;
					unset($vars['client_groups'][$id]);
				}
			}
			
			$vars['vars']->file_groups = $selected;
		}
		
		// Set all selected packages in assigned and unset all selected packages from available
		if (isset($vars['vars']->file_packages) && is_array($vars['vars']->file_packages)) {
			$selected = array();
			
			foreach ($vars['packages'] as $id => $name) {
				if (in_array($id, $vars['vars']->file_packages)) {
					$selected[$id] = $name;
					unset($vars['packages'][$id]);
				}
			}
			
			$vars['vars']->file_packages = $selected;
		}
		
		// Set the view to render
		return $this->partial("admin_manage_plugin_edit", $vars);
	}
	
	/**
	 * Deletes a file
	 */
	public function delete() {
		$this->init();
		
		// Ensure the file ID was provided
		if (!isset($this->post['id']) || !($file = $this->DownloadManagerFiles->get($this->post['id'])) ||
			($file->company_id != $this->company_id))
			$this->redirect($this->base_uri . "settings/company/plugins/manage/" . $this->plugin_id . "/");
		
		// Get the current category
		$category_id = null;

		if ($file->category_id !== null) {
			$category = $this->DownloadManagerCategories->get($file->category_id);
			$category_id = $category->id;
		}
		
		// Delete the file
		$this->DownloadManagerFiles->delete($file->id);
		
		$this->parent->flashMessage("message", Language::_("DownloadManagerManagePlugin.!success.file_deleted", true));
		$this->redirect($this->base_uri . "settings/company/plugins/manage/" . $this->plugin_id . "/" . $category_id);
	}
	
	/**
	 * Downloads a file
	 */
	public function download() {
		$this->init();
		
		// Ensure a file ID was provided
		if (!isset($this->get[1]) || !($file = $this->DownloadManagerFiles->get($this->get[1])) ||
			($file->company_id != $this->company_id))
			$this->redirect($this->base_uri . "settings/company/plugins/manage/" . $this->plugin_id . "/");
		
		$this->components(array("Download"));
		
		// Set the file extension
		$extension = explode(".", $file->file_name);
		$extension = end($extension);
		
		$this->Download->downloadFile($file->file_name, $file->name . (!empty($extension) ? "." . $extension : ""));
		die;
	}
	
	/**
	 * Add a category
	 */
	public function addCategory() {
		$this->init();
		
		// Set category if given, otherwise default to the root category
		$current_category = (isset($this->get[1]) ? $this->DownloadManagerCategories->get($this->get[1]) : null);
		
		// Ensure the parent category is in the same company too
		if ($current_category && $current_category->company_id != $this->company_id)
			$this->redirect($this->base_uri . "settings/company/plugins/manage/" . $this->plugin_id . "/");
		
		$vars = array(
			'plugin_id' => $this->plugin_id,
			'category' => $current_category,
			'vars' => (object)array('parent_id' => (isset($current_category->id) ? $current_category->id : null))
		);
		
		if (!empty($this->post)) {
			// Create the category
			$data = array_merge($this->post, (array)$vars['vars']);
			$data['company_id'] = $this->company_id;
			$category = $this->DownloadManagerCategories->add($data);
			
			if (($errors = $this->DownloadManagerCategories->errors())) {
				// Error, reset vars
				$vars['vars'] = (object)$this->post;
				$this->parent->setMessage("error", $errors);
			}
			else {
				// Success
				$this->parent->flashMessage("message", Language::_("DownloadManagerManagePlugin.!success.category_added", true));
				$this->redirect($this->base_uri . "settings/company/plugins/manage/" . $this->plugin_id . "/" . (isset($current_category->id) ? $current_category->id : null));
			}
		}
		
		// Set the view to render
		return $this->partial("admin_manage_plugin_addcategory", $vars);
	}
	
	/**
	 * Edit a category
	 */
	public function editCategory() {
		$this->init();
		
		if (!isset($this->get[1]) || !($category = $this->DownloadManagerCategories->get($this->get[1])) ||
			($category->company_id != $this->company_id))
			$this->redirect($this->base_uri . "settings/company/plugins/manage/" . $this->plugin_id . "/");
		
		$vars = array(
			'plugin_id' => $this->plugin_id,
			'category' => $category
		);
		
		if (!empty($this->post)) {
			// Update the category
			$data = $this->post;
			$data['company_id'] = $this->company_id;
			$category = $this->DownloadManagerCategories->edit($category->id, $data);
			
			if (($errors = $this->DownloadManagerCategories->errors())) {
				// Error, reset vars
				$vars['vars'] = (object)$this->post;
				$this->parent->setMessage("error", $errors);
			}
			else {
				// Success
				$this->parent->flashMessage("message", Language::_("DownloadManagerManagePlugin.!success.category_updated", true));
				$this->redirect($this->base_uri . "settings/company/plugins/manage/" . $this->plugin_id . "/" . $category->parent_id);
			}
		}
		
		// Set initial vars
		if (empty($vars['vars']))
			$vars['vars'] = $category;
		
		// Set the view to render
		return $this->partial("admin_manage_plugin_editcategory", $vars);
	}
	
	/**
	 * Deletes a category
	 */
	public function deleteCategory() {
		$this->init();
		
		// Ensure the category ID was provided
		if (!isset($this->post['id']) || !($category = $this->DownloadManagerCategories->get($this->post['id'])) ||
			($category->company_id != $this->company_id))
			$this->redirect($this->base_uri . "settings/company/plugins/manage/" . $this->plugin_id . "/");
		
		// Delete the file
		$this->DownloadManagerCategories->delete($category->id);
		
		$this->parent->flashMessage("message", Language::_("DownloadManagerManagePlugin.!success.category_deleted", true));
		$this->redirect($this->base_uri . "settings/company/plugins/manage/" . $this->plugin_id . "/" . $category->parent_id);
	}
}
?>