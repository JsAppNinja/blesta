<?php
/**
 * Download Manager parent controller
 * 
 * @package blesta
 * @subpackage blesta.plugins.download_manager
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class DownloadManagerController extends AppController {

	public function preAction() {
		$this->structure->setDefaultView(APPDIR);
		parent::preAction();
		
		// Override default view directory
		$this->view->view = "default";
		$this->orig_structure_view = $this->structure->view;
		$this->structure->view = "default";
	}
}
?>