<?php
/**
 * Download Manager Client Main controller
 * 
 * @package blesta
 * @subpackage blesta.plugins.download_manager
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class ClientMain extends DownloadManagerController {

	/**
	 * Pre-action
	 */
	public function preAction() {
		parent::preAction();
		
		$this->uses(array("Clients"));
		
		// Fetch the client
		$this->client = $this->Clients->get($this->Session->read("blesta_client_id"));
		$this->client_id = (isset($this->client->id) ? $this->client->id : null);
		$this->company_id = (isset($this->client->company_id) ? $this->client->company_id : Configure::get("Blesta.company_id"));
		
		$this->uses(array("DownloadManager.DownloadManagerCategories", "DownloadManager.DownloadManagerFiles"));
		
		// Restore structure view location of the client portal
		$this->structure->setDefaultView(APPDIR);
		$this->structure->setView(null, $this->orig_structure_view);
		
		Language::loadLang("client_main", null, PLUGINDIR . "download_manager" . DS . "language" . DS);
	}


	/**
	 * List categories/files
	 */
	public function index() {
		// Get the current category
		$parent_category_id = (isset($this->get[0]) ? $this->get[0] : null);
		$category = null;
		if ($parent_category_id !== null)
			$category = $this->DownloadManagerCategories->get($parent_category_id);
		
		// Include the TextParser
		$this->helpers(array("TextParser"));
		
		$this->set("categories", $this->DownloadManagerCategories->getAll($this->company_id, $parent_category_id));
		$this->set("files", $this->DownloadManagerFiles->getAllAvailable($this->company_id, $this->client_id, $parent_category_id));
		$this->set("category", $category);
		$this->set("parent_category", ($category ? $this->DownloadManagerCategories->get($category->parent_id) : null));
		
		if ($category)
			$this->set("category_hierarchy", $this->DownloadManagerCategories->getAllParents($category->id));
	}
	
	/**
	 * Download a file
	 */
	public function download() {
		// Ensure a file ID was provided
		if (!isset($this->get[0]) || !($file = $this->DownloadManagerFiles->get($this->get[0])) ||
			($file->company_id != $this->company_id) ||
			!$this->DownloadManagerFiles->hasAccessToFile($file->id, $this->company_id, $this->client_id))
			$this->redirect($this->base_uri . "plugin/download_manager/client_main/");
		
		$this->components(array("Download"));
		
		$this->uses(array("DownloadManager.DownloadManagerLogs"));
		$log = array(
			'client_id' => $this->client_id,
			'contact_id' => (isset($this->client->contact_id) ? $this->client->contact_id : null),
			'file_id' => $file->id
		);
		$this->DownloadManagerLogs->add($log);
		
		// Set the file extension
		$extension = explode(".", $file->file_name);
		$extension = end($extension);
		
		$this->Download->downloadFile($file->file_name, $file->name . (!empty($extension) ? "." . $extension : ""));
		return false;
	}
}
?>