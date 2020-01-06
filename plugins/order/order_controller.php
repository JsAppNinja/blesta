<?php
/**
 * Order System Parent Controller
 * 
 * @package blesta
 * @subpackage blesta.plugins.order
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class OrderController extends AppController {
	
	public function preAction() {
		$this->structure->setDefaultView(APPDIR);
		parent::preAction();
		
		// Auto load language for the controller
		Language::loadLang(array(Loader::fromCamelCase(get_class($this))), null, dirname(__FILE__) . DS . "language" . DS);
		
		// Override default view directory
		$this->view->view = "default";
		$this->orig_structure_view = $this->structure->view;
		$this->structure->view = "default";
	}
}

require_once dirname(__FILE__) . DS . "order_form_controller.php";

?>