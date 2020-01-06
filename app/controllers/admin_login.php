<?php
/**
 * Administrative Login
 *
 * @package blesta
 * @subpackage blesta.app.controllers
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class AdminLogin extends AppController {
	
	public function preAction() {
		parent::preAction();
		
		$this->uses(array("Users"));
		Language::loadLang(array("admin_login"));
		
		// If company specified as a parameter, set that as the desired log in company
		if (isset($this->get[0])) {
			$this->Session->write("blesta_company_id", $this->get[0]);
			
			// If already logged in, saved URI so we can load up the same URI under the other company
			if (isset($this->get['uri']) && $this->Session->read("blesta_id") > 0 && $this->Session->read("blesta_staff_id") > 0) 
				$this->redirect($this->get['uri']);
		}
		else
			$this->Session->write("blesta_company_id", $this->company_id);
		
		// If logged in, redirect to admin main
		if ($this->Session->read("blesta_id") > 0 && $this->Session->read("blesta_staff_id") > 0)
			$this->redirect($this->base_uri);
		
		// Use the special admin login structure
		$this->structure_view = "structure_admin_login";
	}
	
	/**
	 * Configure the first account in the system, sets/fetches/verifies license key
	 */
	public function setup() {
		
		// Ensure not fully installed
		if ($this->fullyInstalled())
			$this->redirect($this->base_uri . "login/");
		
		if (!empty($this->post)) {
			
			$this->uses(array("Companies", "Emails", "License"));
			
			// Handle requesting free trial
			if ($this->post['enter_key'] == "false") {
				$this->post['license_key'] = $this->requestTrial($this->post);
			}
			
			// Set license key
			$this->License->updateLicenseKey($this->post['license_key']);
			
			$trans_began = false;
			if (!($errors = $this->License->errors())) {
			
				// Create the user and staff account
				$trans_began = true;
				$this->Users->begin();
				$user = $this->post;
				$user['new_password'] = $user['password'];
				$user_id = $this->Users->add($user);
	
				if (!($errors = $this->Users->errors())) {
	
					$staff = array(
						'user_id' => $user_id,
						'first_name' => $user['first_name'],
						'last_name' => $user['last_name'],
						'email' => $user['email'],
						'groups' => array(1) // assign to the intial Administrators group
					);
					
					$staff_id = $this->Staff->add($staff);
					
					if (!($errors = $this->Staff->errors())) {
						$this->Users->commit();
						
						// Set hostname
						if (isset($_SERVER['SERVER_NAME'])) {
							// Update all email from addresses using the current host name
							$this->Emails->updateFromDomain($_SERVER['SERVER_NAME'], Configure::get("Blesta.company_id"));
							
							$this->Companies->edit(Configure::get("Blesta.company_id"), array('hostname' => $_SERVER['SERVER_NAME']));
						}
						
						// Set default widget displays
						$home_widgets = array(
							'widget_system_overview_admin_main' => array('section' => "section1", 'open' => true),
							'widget_system_status_admin_main' => array('section' => "section3", 'open' => true),
							'widget_feed_reader_admin_main' => array('section' => "section2", 'open' => true)
						);
						$this->Staff->saveHomeWidgetsState($staff_id, Configure::get("Blesta.company_id"), $home_widgets);
						
						$billing_widgets = array(
							'widget_billing_overview_admin_main' => array('open' => true, 'section' => "section1"),
							'widget_order_admin_main' => array('open' => true, 'section' => "section1")
						);
						$this->Staff->saveBillingWidgetsState($staff_id, Configure::get("Blesta.company_id"), $billing_widgets);
						
						$this->loadRemoteConfig($this->post['license_key']);
						
						// Auto-login (not really, but we already have the username and password in $this->post)
						$this->index();
					}
				}
			}
			
			if ($errors) {
				if ($trans_began)
					$this->Users->rollback();
				
				$this->setMessage("error", $errors);
				$this->set("vars", $this->post);
			}
		}
	}
	
	/**
	 * Handle login attempts
	 */
	public function index() {

		// Check if fully installed, if not fully installed, complete installation
		if (!$this->fullyInstalled())
			$this->redirect($this->base_uri . "login/setup");
		
		if ($this->Session->read("blesta_auth") != "")
			$this->forwardPostAuth();
		
		if (!empty($this->post)) {

			// Attempt to log user in
			$this->post['ip_address'] = (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : "");
			$this->Users->login($this->Session, $this->post);
			
			if (($errors = $this->Users->errors())) {
				$this->setMessage("error", $errors);
				$this->set("vars", (object)$this->post);
			}
			else
				$this->forwardPostAuth();
		}
	}
	
	/**
	 * Handle otp requests
	 */
	public function otp() {
		
		if ($this->Session->read("blesta_auth") == "")
			$this->redirect($this->base_uri . "login/");
			
		if (!empty($this->post)) {

			// Attempt to log user in
			$this->post['ip_address'] = (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : "");
			$this->Users->login($this->Session, $this->post);
			
			if (($errors = $this->Users->errors())) {
				$this->setMessage("error", $errors);
				$this->set("vars", (object)$this->post);
			}
			else
				$this->forwardPostAuth();
		}
	}
	
	/**
	 * Reset password
	 */
	public function reset() {
		
		$this->uses(array("Staff", "Emails"));
		
		if (!empty($this->post)) {
			$sent = Configure::get("Blesta.default_password_reset_value");
			
			if (isset($this->post['username']) && ($user = $this->Users->getByUsername($this->post['username']))) {
				
				// Send reset password email
				$staff = $this->Staff->getByUserId($user->id);
				if ($staff && $staff->status == "active") {
					// Get the company hostname
					$hostname = isset(Configure::get("Blesta.company")->hostname) ? Configure::get("Blesta.company")->hostname : "";
					
					$time = time();
					$hash = $this->Staff->systemHash('u=' . $user->id . '|t=' . $time);
					$tags = array(
						'staff'=>$staff,
						'ip_address'=>isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null,
						'password_reset_url'=>$this->Html->safe($hostname . $this->base_uri . "login/confirmreset/?sid=" . rawurlencode($this->Staff->systemEncrypt('u=' . $user->id . '|t=' . $time . '|h=' . substr($hash, -16))))
					);
					$this->Emails->send("staff_reset_password", $this->company_id, Configure::get("Blesta.language"),  $staff->email, $tags);
					$sent = true;
				}
			}
			
			if ($sent)
				$this->setMessage("message", Language::_("AdminLogin.!success.reset_sent", true));
			else
				$this->setMessage("error", Language::_("AdminLogin.!error.unknown_user", true));
		}		
	}
	
	/**
	 * Confirm password reset
	 */
	public function confirmReset() {

		$this->uses(array("Staff"));

		// Verify parameters
		if (!isset($this->get['sid']))
			$this->redirect($this->base_uri . "login/");
		
		$params = array();
		$temp = explode("|", $this->Staff->systemDecrypt($this->get['sid']));
		
		if (count($temp) <= 1)
			$this->redirect($this->base_uri . "login/");
		
		foreach ($temp as $field) {
			$field = explode("=", $field, 2);
			$params[$field[0]] = $field[1];
		}
		
		// Verify reset has not expired
		if ($params['t'] < strtotime("-" . Configure::get("Blesta.reset_password_ttl")))
			$this->redirect($this->base_uri . "login/");
			
		// Verify hash matches
		if ($params['h'] != substr($this->Staff->systemHash('u=' . $params['u'] . '|t=' . $params['t']), -16))
			$this->redirect($this->base_uri . "login/");
		
		// Attempt to update the user's password and log in
		if (!empty($this->post)) {
			
			$staff = $this->Staff->getByUserId($params['u']);
			
			if ($staff && $staff->status == "active") {
				// Update the user's password
				$this->Users->edit($params['u'], $this->post);
				
				if (!($errors = $this->Users->errors())) {
					$this->post['username'] = $staff->username;
					$this->post['password'] = $this->post['new_password'];
					$this->post['ip_address'] = (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : "");
					
					// Attempt to log user in
					$this->Users->login($this->Session, $this->post);
					
					$this->forwardPostAuth();
				}
				else
					$this->setMessage("error", $errors);
			}
		}
	}
	
	/**
	 * Finishes logging in the staff member and forwards the user off to the desired location
	 */
	private function forwardPostAuth() {
		if ($this->Session->read("blesta_id")) {
			
			$this->uses(array("Staff", "StaffGroups"));

			$staff = $this->Staff->getByUserId($this->Session->read("blesta_id"));
			
			if (!$staff) {
				$this->Session->clear();
				$this->flashMessage("error", Language::_("Users.!error.username.auth", true));
				$this->redirect($this->base_uri . "login");
			}

			// Set the appropriate Company ID and Staff ID values for this session
			$groups = $this->StaffGroups->getUsersGroups($this->Session->read("blesta_id"));
			$num_groups = count($groups);
			
			// Ensure that the desired company ID is available to this staff member
			$staff_id = null;
			for ($i=0; $i<$num_groups; $i++) {
				if ($this->Session->read("blesta_company_id") == $groups[$i]->company_id) {
					$staff_id = $staff->id;
					break;
				}
			}
			
			// Company ID wasn't available so assign to the 1st available company if possible
			// else the user can not log in because they are not assigned to any companies
			if (!$staff_id) {
				if ($num_groups > 0) {
					$this->Session->write("blesta_company_id", $groups[0]->company_id);
					$staff_id = $staff->id;
				}
				else {
					$this->Session->clear();
					$this->flashMessage("error", Language::_("StaffGroups.!error.no_company_id.exists", true));
					$this->redirect($this->base_uri . "login");
				}
			}
			$this->Session->write("blesta_staff_id", $staff_id);
			
			// Detect if we should forward after logging in and do so
			if (isset($this->post['forward_to']))
				$forward_to = $this->post['forward_to'];
			else
				$forward_to = $this->Session->read("blesta_forward_to");
				
			$this->Session->clear("blesta_forward_to");
			if (!$forward_to)
				$forward_to = $this->base_uri;
			
			$this->redirect($forward_to);
		}
		else {
			// Requires OTP auth
			$this->redirect($this->base_uri . "login/otp");
		}
	}
	
	/**
	 * Checks whether the system is fully installed
	 *
	 * @return boolean True if the system is fully installed, false otherwise
	 */
	private function fullyInstalled() {
		$this->uses(array("Staff", "Settings"));
		
		$license_key = $this->Settings->getSetting("license_key");
		
		if ($this->Staff->getListCount() > 0 && ($license_key && $license_key->value != ""))
			return true;
		return false;	
	}
	
	/**
	 * Request a trial from the license server
	 *
	 * @param array $vars An array of input including:
	 * 	- first_name
	 * 	- last_name
	 * 	- email
	 * @return string The license key created for this trial (if possible)
	 */
	private function requestTrial(array $vars) {
		$license_key = null;
		$vars['domain'] = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : null;
		
		$this->components(array("Net"));
		$this->Http = $this->Net->create("Http");
		
		return $this->Http->post("https://account.blesta.com/plugin/license_manager/trial/", $vars);
	}
	
	/**
	 * Loads the remote config for this license if one exists
	 *
	 * @param string $license_key The license key
	 */
	private function loadRemoteConfig($license_key) {
		if (strtolower(substr($license_key, 0, 3)) == "var") {
			if (!isset($this->Http)) {
				$this->components(array("Net"));
				$this->Http = $this->Net->create("Http");
				
				if (!isset($this->Json))
					$this->components(array("Json"));
				
				$this->Http->setTimeout(5);				
				$data = $this->Http->get("https://account.blesta.com/plugin/license_kickstarter/config/index/" . $license_key);
				
				if ($data) {
					$config = $this->Json->decode($data, true);
					
					// Set themes
					if (isset($config['themes']))
						$this->setThemes($config['themes']);
					
					if (isset($config['feeds']))
						$this->setFeeds($config['feeds']);
						
					if (isset($config['plugins']))
						$this->installExtensions($config['plugins'], "plugins");
						
					if (isset($config['modules']))
						$this->installExtensions($config['modules'], "modules");
						
					if (isset($config['gateways']))
						$this->installExtensions($config['gateways'], "gateways");
				}
			}
		}
	}
	
	/**
	 * Adds themes
	 *
	 * @param array An array of themes to add
	 */
	private function setThemes($themes) {
		$this->uses(array("Companies", "Themes"));
		foreach ($themes as $theme) {
			$theme_id = $this->Themes->add($theme);
			$this->Companies->setSetting($this->company_id, "theme_" . $theme['type'], $theme_id);
		}
	}
	
	/**
	 * Adds feeds
	 *
	 * @param array An array of feeds to add
	 */
	private function setFeeds($feeds) {
		$this->uses(array("FeedReader.FeedReaderFeeds"));
		foreach ($feeds as $feed) {
			$this->FeedReaderFeeds->addFeed(array('url' => $feed, 'company_id' => $this->company_id));
		}
	}
	
	/**
	 * Installs the given extensions
	 *
	 * @param array $extensions An array of extensions to install
	 * @param string $type The type of extensions (plugins, modules, gateways)
	 */
	private function installExtensions($extensions, $type) {
		if ($type == "plugins")
			$this->uses(array("PluginManager"));
		elseif ($type == "modules")
			$this->uses(array("ModuleManager"));
		elseif ($type == "gateways")
			$this->uses(array("GatewayManager"));
		
		foreach ($extensions as $extension) {
			switch ($type) {
				case "plugins":
					if (!$this->PluginManager->isInstalled($extension, $this->company_id)) {
						$vars = array(
							'dir' => $extension,
							'company_id' => $this->company_id,
							'staff_group_id' => 1
						);
						$this->PluginManager->add($vars);
					}
					break;
				case "modules":
					if (!$this->ModuleManager->isInstalled($extension, $this->company_id)) {
						$vars = array(
							'class' => $extension,
							'company_id' => $this->company_id
						);
						$this->ModuleManager->add($vars);
					}
					break;
				case "gateways":
					$type = is_dir(COMPONENTDIR . "gateways" . DS . "merchant" . DS. $extension) ? "merchant" : "nonmerchant";
					
					if (!$this->GatewayManager->isInstalled($extension, $type, $this->company_id)) {
						$vars = array(
							'class' => $extension,
							'company_id' => $this->company_id,
							'type' => $type
						);
						$this->GatewayManager->add($vars);
					}
					break;
			}
		}
	}
}
?>