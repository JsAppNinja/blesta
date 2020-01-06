<?php
/**
 * Client portal logout controller
 *
 * @package blesta
 * @subpackage blesta.app.controllers
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class ClientLogout extends AppController {
	
	public function index() {
		$this->uses(array("Users"));
		
		// log user out 		
		$this->Users->logout($this->Session);
		
		// Redirect to client login
		$this->redirect("http://google.com");
	}
}
?>