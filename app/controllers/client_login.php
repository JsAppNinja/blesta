<?php
/**
 * Client portal login controller
 *
 * @package blesta
 * @subpackage blesta.app.controllers
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class ClientLogin extends AppController {
	
	public function preAction() {
		parent::preAction();
		
		$this->uses(array("Users","Clients"));
		Language::loadLang(array("client_login"));
		
		// If logged in, redirect to client main
		if ($this->Session->read("blesta_id") > 0 && $this->Session->read("blesta_client_id") > 0)
			$this->redirect($this->base_uri);
		
		$this->structure->set("show_header", false);
		
		$this->set("company", $this->Companies->get(Configure::get("Blesta.company_id")));
	}
	
	/**
	 * Login
	 */
	public function index() {
		$this->uses(array("Companies"));
		$this->structure->set("page_title", Language::_("ClientLogin.index.page_title", true));
		
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
		
		$this->structure->set("page_title", Language::_("ClientLogin.otp.page_title", true));
		
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
		$this->uses(array("Clients", "Contacts", "Emails"));

		$this->structure->set("page_title", Language::_("ClientLogin.reset.page_title", true));
		
		if (!empty($this->post)) {
			$sent = Configure::get("Blesta.default_password_reset_value");
			
			if (isset($this->post['username']) && ($user = $this->Users->getByUsername($this->post['username']))) {
				
				// Send reset password email
				$client = $this->Clients->getByUserId($user->id);
				$contact = null;
				
				if (!($contact = $this->Contacts->getByUserId($user->id, $client->id)))
					$contact = $client;

				if ($client && $client->status == "active") {
					// Get the company hostname
					$hostname = isset(Configure::get("Blesta.company")->hostname) ? Configure::get("Blesta.company")->hostname : "";
					
					$time = time();
					$hash = $this->Clients->systemHash('u=' . $user->id . '|t=' . $time);
					$tags = array(
						'client' => $client,
						'contact' => $contact,
						'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null,
						'password_reset_url' => $this->Html->safe($hostname . $this->base_uri . "login/confirmreset/?sid=" . rawurlencode($this->Clients->systemEncrypt('u=' . $user->id . '|t=' . $time . '|h=' . substr($hash, -16))))
					);
					$this->Emails->send("reset_password", $this->company_id, Configure::get("Blesta.language"),  $contact->email, $tags, null, null, null, array('to_client_id' => $client->id));
					$sent = true;
				}
			}
			
			if ($sent)
				$this->setMessage("message", Language::_("ClientLogin.!success.reset_sent", true));
			else
				$this->setMessage("error", Language::_("ClientLogin.!error.unknown_user", true));
		}
	}
	
	/**
	 * Confirm password reset
	 */
	public function confirmReset()  {

		$this->uses(array("Clients"));

		// Verify parameters
		if (!isset($this->get['sid']))
			$this->redirect($this->base_uri . "login/");
		
		$params = array();
		$temp = explode("|", $this->Clients->systemDecrypt($this->get['sid']));
		
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
		if ($params['h'] != substr($this->Clients->systemHash('u=' . $params['u'] . '|t=' . $params['t']), -16))
			$this->redirect($this->base_uri . "login/");
		
		// Attempt to update the user's password and log in
		if (!empty($this->post)) {
			
			$client = $this->Clients->getByUserId($params['u']);
			$user = $this->Users->get($params['u']);
			
			if ($user && $client && $client->status == "active") {
				// Update the user's password
				$this->Users->edit($params['u'], $this->post);
				
				if (!($errors = $this->Users->errors())) {
					$this->post['username'] = $user->username;
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
	 * Finishes logging in the client and forwards the user off to the desired location
	 */
	private function forwardPostAuth() {

		// Verify client can log in to this company and log
		if ($this->Session->read("blesta_id")) {
			$client = $this->Clients->getByUserId($this->Session->read("blesta_id"));
			
			if (!$client) {
				$this->Session->clear();
				$this->flashMessage("error", Language::_("Users.!error.username.auth", true));
				$this->redirect($this->base_uri . "login");
			}
			
			$this->Session->write("blesta_company_id", Configure::get("Blesta.company_id"));
			$this->Session->write("blesta_client_id", $client->id);
			
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
}
?>