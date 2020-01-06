<?php
/**
 * Admin Parent Controller
 */
class AdminController extends AppController {
	
	/**
	 * Admin pre-action
	 */
	public function preAction() {
		parent::preAction();
		
		// Require login
		$this->requireLogin();

		Language::loadLang(array(Loader::fromCamelCase(get_class($this))));
	}
}
