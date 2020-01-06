<?php
/**
 * Order System signup controller
 *
 * @package blesta
 * @subpackage blesta.plugins.order.controllers
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class Signup extends OrderFormController {
	
	/**
	 * Signup
	 */
	public function index() {
		$vars = new stdClass();
		
		$this->uses(array("Users", "Contacts", "Countries", "States"));
		$this->components(array("SettingsCollection"));
		$this->ArrayHelper = $this->DataStructure->create("Array");
		
		$order_settings = $this->ArrayHelper->numericToKey($this->OrderSettings->getSettings($this->company_id), "key", "value");

		// Check if captcha is required for signups
		if ($this->order_form->require_captcha == "1") {
			if (isset($order_settings['captcha'])) {
				if ($order_settings['captcha'] == "recaptcha") {
					$this->helpers(array('Recaptcha' => array($order_settings['recaptcha_pri_key'], $order_settings['recaptcha_pub_key'])));
					$this->set("captcha", $this->Recaptcha->getHtml("clean"));
				}
				elseif ($order_settings['captcha'] == "ayah") {
					$this->helpers(array('Areyouahuman' => array($order_settings['ayah_pub_key'], $order_settings['ayah_score_key'])));
					$this->set("captcha", $this->Areyouahuman->getPublisherHTML());
				}
			}
		}
		
		// Get company settings
		$company_settings = $this->SettingsCollection->fetchSettings($this->Companies, $this->company_id);
		
		// Fetch client group tax ID setting
		$show_client_tax_id = $this->SettingsCollection->fetchClientGroupSetting($this->order_form->client_group_id, null, "show_client_tax_id");
		$show_client_tax_id = (isset($show_client_tax_id['value']) ? $show_client_tax_id['value'] : "");
		
		// Set default currency, country, and language settings from this company
		$vars = new stdClass();
		$vars->country = $company_settings['country'];
		
		if (!empty($this->post)) {
			$errors = false;
			if (isset($this->Recaptcha)) {
				if (!$this->Recaptcha->verify($this->post['recaptcha_challenge_field'], $this->post['recaptcha_response_field']))
					$errors = array('captcha' => array('invalid' => Language::_("Signup.!error.captcha.invalid", true)));
			}
			elseif (isset($this->Areyouahuman)) {
				if (!$this->Areyouahuman->scoreResult())
					$errors = array('captcha' => array('invalid' => Language::_("Signup.!error.captcha.invalid", true)));
			}
			
			if (!$errors) {
				// Set mandatory defaults
				$this->post['client_group_id'] = $this->order_form->client_group_id;
				
				$client_info = $this->post;
				$client_info['settings'] = array(
					'username_type' => $this->post['username_type'],
					'tax_id' => ($show_client_tax_id == "true" ? $this->post['tax_id'] : ""),
					'default_currency' => $this->SessionCart->getData("currency"),
					'language' => $company_settings['language']
				);
				$client_info['numbers'] = $this->ArrayHelper->keyToNumeric($client_info['numbers']);
				
				foreach ($this->post as $key => $value) {
					if (substr($key, 0, strlen($this->custom_field_prefix)) == $this->custom_field_prefix)
						$client_info['custom'][str_replace($this->custom_field_prefix, "", $key)] = $value;
				}
				
				// Fraud detection
				if (isset($order_settings['antifraud']) && $order_settings['antifraud'] != "") {
					$this->components(array("Order.Antifraud"));
					$fraud_detect = $this->Antifraud->create($order_settings['antifraud'], array($order_settings));
					$status = $fraud_detect->verify(array(
						'ip' => $_SERVER['REMOTE_ADDR'],
						'email' => $this->Html->ifSet($client_info['email']),
						'address1' => $this->Html->ifSet($client_info['address1']),
						'address2' => $this->Html->ifSet($client_info['address2']),
						'city' => $this->Html->ifSet($client_info['city']),
						'state' => $this->Html->ifSet($client_info['state']),
						'country' => $this->Html->ifSet($client_info['country']),
						'zip' => $this->Html->ifSet($client_info['zip']),
						'phone' => $this->Contacts->intlNumber($this->Html->ifSet($client_info['numbers'][0]['number']), $client_info['country'])
					));
					
					if (isset($fraud_detect->Input))
						$errors = $fraud_detect->Input->errors();
					
					$this->SessionCart->setData("fraud_report", $fraud_detect->fraudDetails());
					$this->SessionCart->setData("fraud_status", $status);
					
					if ($status == "review")
						$errors = false; // remove errors (if any)
				}
				
				if (!$errors) {
					// Create the client
					$this->client = $this->Clients->create($client_info);
					
					$errors = $this->Clients->errors();
				}
			}
			
			if ($errors) {
				$this->setMessage("error", $errors, false, null, false);
			}
			else {

				// Log the user into the newly created client account
				$login = array(
					'username' => $this->client->username,
					'password' => $client_info['new_password']
				);
				$user_id = $this->Users->login($this->Session, $login);
				
				if ($user_id) {
					$this->Session->write("blesta_company_id", $this->company_id);
					$this->Session->write("blesta_client_id", $this->client->id);
				}
				
				if (!$this->isAjax())
					$this->redirect($this->base_uri . "order/checkout/index/" . $this->order_form->label);
			}
			$vars = (object)$this->post;
		}
		
		
		// Set custom fields to display
		$custom_fields = $this->Clients->getCustomFields($this->company_id, $this->order_form->client_group_id, array('show_client' => 1));
		
		// Swap key/value pairs for "Select" option custom fields (to display)
		foreach ($custom_fields as &$field) {
			if ($field->type == "select" && is_array($field->values))
				$field->values = array_flip($field->values);
		}
		
		$this->set("custom_field_prefix", $this->custom_field_prefix);
		$this->set("custom_fields", $custom_fields);
		
		$this->set("countries", $this->Form->collapseObjectArray($this->Countries->getList(), array("name", "alt_name"), "alpha2", " - "));
		$this->set("states", $this->Form->collapseObjectArray($this->States->getList($vars->country), "name", "code"));
		$this->set("currencies", $this->Currencies->getAll($this->company_id));
		
		$this->set("vars", $vars);
		
		$this->set("client", $this->client);
		if (!$this->isClientOwner($this->client, $this->Session)) {
			$this->setMessage("error", Language::_("Signup.!error.not_client_owner", true), false, null, false);
			$this->set("client", false);
		}
		$this->set("show_client_tax_id", ($show_client_tax_id == "true"));
		
		return $this->renderView();
	}
	
	/**
	 * Outputs clients info
	 */
	public function clientinfo() {
		$this->set("client", $this->Clients->get($this->Session->read("blesta_client_id")));
		$this->outputAsJson($this->view->fetch());
		return false;
	}
	
	/**
	 * AJAX Fetch all states belonging to a given country (json encoded ajax request)
	 */
	public function getStates() {
		$this->uses(array("States"));
		// Prepend "all" option to state listing
		$states = array();
		if (isset($this->get[1]))
			$states = array_merge($states, (array)$this->Form->collapseObjectArray($this->States->getList($this->get[1]), "name", "code"));
		
		$this->outputAsJson($states);
		return false;
	}
}
?>