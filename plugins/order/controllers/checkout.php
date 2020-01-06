<?php
/**
 * Order System checkout controller
 *
 * @package blesta
 * @subpackage blesta.plugins.order.controllers
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class Checkout extends OrderFormController {
	
	/**
	 * Setup
	 */
	public function preAction() {
		if ($this->action == "complete") {
			// Disable CSRF for this request
			Configure::set("Blesta.verify_csrf_token", false);
		}
		parent::preAction();
		
		$this->components(array("Input"));
	}
	
	/**
	 * Collect payment/create order
	 */
	public function index() {
		
		$this->uses(array("Accounts", "Contacts", "Transactions", "Payments", "Invoices", "Order.OrderOrders"));
		$vars = new stdClass();

		$invoice = false;
		$order = false;
		
		// Require login to proceed
		if (!$this->client)
			$this->redirect($this->base_uri . "order/signup/index/" . $this->order_form->label);
			
		// Can't proceed unless this is the account owner
		if (!$this->isClientOwner($this->client, $this->Session)) {
			$this->setMessage("error", Language::_("Checkout.!error.not_client_owner", true), false, null, false);
			$this->post = array();
		}
		
		// If order number given, verify it belongs to this client
		if (isset($this->get[1]) && (!($order = $this->OrderOrders->getByNumber($this->get[1])) ||
			!isset($this->client) || $order->client_id != $this->client->id)) {
			$this->redirect($this->base_uri . "order/main/index/" . $this->order_form->label);
		}
		
		// Require order or non-empty cart
		if (!$order && $this->SessionCart->isEmptyCart())
			$this->redirect($this->base_uri . "order/main/index/" . $this->order_form->label);
		
		// If we have an order already, fetch the invoice
		if ($order)
			$invoice = $this->Invoices->get($order->invoice_id);
		
		// Update the cart to the currency from the invoice if one exists
		if ($invoice && $invoice->currency)
			$this->SessionCart->setData("currency", $invoice->currency);
		
		extract($this->getPaymentOptions());
		$summary = $this->getSummary();
		
		// Create the order now if no amount is due. (Skip payment step)
		if (!$order && $summary['totals']['total_w_tax']['amount'] <= 0) {
			// Create the order
			$order = $this->createOrder($summary['cart']['items'], $currency);
			
			// Set errors if add order failed
			if (($errors = $this->OrderOrders->errors())) {
				$this->flashMessage("error", $errors, null, false);
				$this->redirect($this->base_uri . "order/checkout/cart/" . $this->order_form->label . "/");
			}
			else {
				// Order recorded, empty the cart
				$this->SessionCart->emptyCart();
				
				$this->redirect($this->base_uri . "order/checkout/complete/" . $this->order_form->label . "/" . $order->order_number . "/");
			}
		}
		
		if (!empty($this->post)) {
			// If no order yet exists, verify terms and attempt to create the order
			if (!$order) {
				// Verify agreement of terms and conditions
				if ($this->order_form->require_tos && (!isset($this->post['agree_tos']) || $this->post['agree_tos'] != "true")) {
					$this->setMessage("error", Language::_("Checkout.!error.invalid_agree_tos", true), false, null, false);
				}
				else {
					// Create the order
					$order = $this->createOrder($summary['cart']['items'], $currency);
					
					// Set errors if add order failed
					if (($errors = $this->OrderOrders->errors())) {
						$this->setMessage("error", $errors, false, null, false);
					}
					else {
						// Order recorded, empty the cart
						$this->SessionCart->emptyCart();
						
						// Fetch the invoice created for the order
						$invoice = $this->Invoices->get($order->invoice_id);
					}
				}
			}
			
			if ($order && $invoice) {
                // Apply any credits to the invoice
                if (empty($errors) && isset($this->post['apply_credit']) && $this->post['apply_credit'] == "true") {
                    $amount_applied = $this->applyCredit($invoice);
                    
                    // Refetch the invoice
                    if ($amount_applied !== false) {
                        $invoice = $this->Invoices->get($invoice->id);
                        
                        // Redirect straight to the complete page if the credits took care of the entire invoice
                        if ($invoice->due <= 0 && $invoice->date_closed !== null) {
                            $this->redirect($this->base_uri . "order/checkout/complete/" . $this->order_form->label . "/" . $order->order_number . "/");
                        }
                    }
                }
                
				// Process payment
				if (!isset($this->post['set_vars']) && (!empty($this->post['payment_account']) || !empty($this->post['payment_type']))) {
					$this->processPayment($order, $invoice);
					
					// If payment error occurred display error and allow repayment
					if (($errors = $this->Input->errors()))
						$this->setMessage("error", $errors, false, null, false);
					else
						$this->redirect($this->base_uri . "order/checkout/complete/" . $this->order_form->label . "/" . $order->order_number . "/");
				}
				// Redirect to order complete page and render gateway button
				elseif (isset($this->post['gateway'])) {
					$this->redirect($this->base_uri . "order/checkout/complete/" . $this->order_form->label . "/" . $order->order_number . "/?gateway=" . $this->post['gateway']);
				}
				elseif (!isset($this->post['set_vars'])) {
					$this->setMessage("error", Language::_("Checkout.!error.no_payment_info", true), false, null, false);
				}
			}
			
			$vars = (object)$this->post;
		}
		
		$payment_accounts = $this->getPaymentAccounts($merchant_gateway, $currency, $payment_types);
		$require_passphrase = !empty($this->client->settings['private_key_passphrase']);
		
		$vars->country = (!empty($this->client->settings['country']) ? $this->client->settings['country'] : "");
		
		// Set the contact info partial to the view
		$this->setContactView($vars);
		// Set the CC info partial to the view
		$this->setCcView($vars, false, true);
		// Set the ACH info partial to the view
		$this->setAchView($vars, false, true);
		
        // Set the total available credit that can be applied to the invoices
        $total_credit = $this->Transactions->getTotalCredit($this->client->id, $currency);
        $credits = array('currency' => $currency, 'amount' => $total_credit);
        
		$cart = isset($summary['cart']) ? $summary['cart'] : false;
		$totals = isset($summary['totals']) ? $summary['totals'] : false;
        $totals_section = (isset($order->order_number) ? $this->getTotals($order->order_number, true) : "");

		$this->set(compact("vars", "cart", "totals", "payment_accounts", "require_passphrase", "payment_types", "nonmerchant_gateways", "order", "invoice", "credits", "totals_section"));
	}
    
    /**
     * AJAX Retrieves the partial that displays totals
     * @see Checkout::index()
     *
     * @param int $order_number The order number whose totals to fetch (optional, default null)
     * @param boolean $return True to return the partial for totals, or false to output as JSON (optional, default false)
     * @return string A string representing the totals partial
     */
    public function getTotals($order_number = null, $return = false) {
        $this->uses(array("Invoices", "Order.OrderOrders", "Transactions"));
        
        // Require login to proceed
		if (!$this->client)
			$this->redirect($this->base_uri . "order/signup/index/" . $this->order_form->label);
        
        if (!$return && !$this->isAjax())
            $this->redirect($this->base_uri . "order/main/index/" . $this->order_form->label);
        
        $order_number = ($order_number !== null ? $order_number : (isset($this->get[1]) ? $this->get[1] : null));
        
        // If order number given, verify it belongs to this client
		if ($order_number === null || !($order = $this->OrderOrders->getByNumber($order_number)) ||
			!isset($this->client) || $order->client_id != $this->client->id) {
			$this->redirect($this->base_uri . "order/main/index/" . $this->order_form->label);
		}
        
        $invoice = $this->Invoices->get($order->invoice_id);
        
        if ($invoice) {
            // Format taxes
            $taxes = array();
            if (!empty($invoice->taxes)) {
                foreach ($invoice->taxes as $tax) {
                    $taxes[] = array(
                        'id' => $tax->id,
                        'name' => $tax->name,
                        'percentage' => $tax->amount,
                        'amount' => $tax->tax_total,
                        'amount_formatted' => $this->CurrencyFormat->format($tax->tax_total, $invoice->currency)
                    );
                }
            }
            
            // Set a credit, if any
            $total_credit = 0;
            if (isset($this->post['apply_credit']) && $this->post['apply_credit'] == "true")
                $total_credit = $this->Transactions->getTotalCredit($this->client->id, $invoice->currency);
            
            // Set totals, with any credits
            $total = max(0, ($invoice->due - $total_credit));
            $usable_credit = ($total_credit >= $invoice->due ? $invoice->due : $total_credit);
            $totals = array(
				'subtotal' => array('amount' => $invoice->subtotal, 'amount_formatted' => $this->CurrencyFormat->format($invoice->subtotal, $invoice->currency)),
                'credit' => array('amount' => -$usable_credit, 'amount_formatted' => $this->CurrencyFormat->format(-$usable_credit, $invoice->currency)),
				'total' => array('amount' => $invoice->subtotal, 'amount_formatted' => $this->CurrencyFormat->format($invoice->subtotal, $invoice->currency)),
				'total_w_tax' => array('amount' => $total, 'amount_formatted' => $this->CurrencyFormat->format($total, $invoice->currency)),
                'paid' => array('amount' => -$invoice->paid, 'amount_formatted' => $this->CurrencyFormat->format(-$invoice->paid, $invoice->currency)),
				'tax' => $taxes
			);
            
            $partial = $this->partial("checkout_total_info", array('totals' => $totals));
            
            if ($return)
                return $partial;
            echo $this->Json->encode($partial);
        }
        
        return false;
    }
	
	/**
	 * Display order complete/nonmerchant pay page
	 */
	public function complete() {
		
		$this->uses(array("Order.OrderOrders", "Invoices"));
		
		if (!isset($this->get[1]) || !($order = $this->OrderOrders->getByNumber($this->get[1])) ||
			!isset($this->client) || $order->client_id != $this->client->id) {
			$this->redirect($this->base_uri . "order/main/index/" . $this->order_form->label);
		}
		
		$invoice = $this->Invoices->get($order->invoice_id);
		
		if (isset($this->get['gateway']))
			$this->setNonmerchantDetails($order, $invoice, $this->get['gateway']);
		
		$this->set("order", $order);
		$this->set("invoice", $invoice);
	}
	
	/**
	 * Sets nonmerchant gateway payment details within the current view
	 *
	 * @param stdClass $order The order
	 * @param stdClass $invoice The invoice
	 * @param stdClass $gateway_id The ID of the gateway to render
	 */
	private function setNonmerchantDetails($order, $invoice, $gateway_id) {
		extract($this->getPaymentOptions($invoice->currency));
		
		// Non-merchant gateway
		$this->uses(array("Contacts", "Countries", "Payments", "States"));
		
		// Fetch this contact
		$contact = $this->Contacts->get($this->client->contact_id);
		
		$contact_info = array(
			'id' => $this->client->contact_id,
			'client_id' => $this->client->id,
			'user_id' => $this->client->user_id,
			'contact_type' => $contact->contact_type_name,
			'contact_type_id' => $contact->contact_type_id,
			'first_name' => $this->client->first_name,
			'last_name' => $this->client->last_name,
			'title' => $contact->title,
			'company' => $this->client->company,
			'address1' => $this->client->address1,
			'address2' => $this->client->address2,
			'city' => $this->client->city,
			'zip' => $this->client->zip,
			'country' => (array)$this->Countries->get($this->client->country),
			'state' => (array)$this->States->get($this->client->country, $this->client->state)
		);
		
        $options = array();
		$apply_amounts = array();
		// Set payment be applied to the invoice that was created
		$apply_amounts[$invoice->id] = $invoice->due;
		
        // Check for recurring info
        if (($recur = $this->Invoices->getRecurringInfo($invoice->id)))
            $options['recur'] = $recur;
        
		$options['description'] = Language::_("Checkout.index.description_invoice", true, $invoice->id_code);
		$options['return_url'] = rtrim($this->base_url, "/");
		
		$order_complete_uri = $this->base_uri . "order/checkout/complete/" . $this->order_form->label . "/" . $order->order_number;
		
		foreach ($nonmerchant_gateways as $gateway) {
			if ($gateway->id == $gateway_id) {
				$this->set("gateway_name", $gateway->name);
				$options['return_url'] .= $order_complete_uri;
				
				$this->set("gateway_buttons", $this->Payments->getBuildProcess($contact_info, $invoice->due, $currency, $apply_amounts, $options, $gateway->id));
				break;
			}
		}
		
		$this->set("client", $this->client);
	}
	
	/**
	 * Creates an order from the cart's items
	 * @see Checkout::index
	 *
	 * @param array An array of cart items
	 * @param string $currency The ISO 4217 currency code set for the order
	 * @return mixed An stdClass object representing the order, or void on error
	 */
	private function createOrder(array $items, $currency) {
		// Set order details
		$details = array(
			'client_id' => $this->client->id,
			'order_form_id' => $this->order_form->id,
			'currency' => $currency,
			'fraud_report' => $this->SessionCart->getData("fraud_report") ? serialize($this->SessionCart->getData("fraud_report")) : null,
			'fraud_status' => $this->SessionCart->getData("fraud_status"),
			'status' => ($this->order_form->manual_review || $this->SessionCart->getData("fraud_status") == "review" ? "pending" : "accepted"),
			'coupon' => $this->SessionCart->getData("coupon")
		);
		
		// Attempt to add the order
		return $this->OrderOrders->add($details, $items);
	}
    
    /**
     * Applies any existing credits from this client to the given invoice
     * @see Checkout::index()
     *
     * @param stdClass $invoice An stdClass object representing the invoice to be paid via credit
     * @return mixed A float value representing the amount that was applied to the invoice, otherwise boolean false
     */
    private function applyCredit($invoice) {
        if (!isset($this->Transactions))
            $this->uses(array("Transactions"));
        
        // Fetch the credits we have available
        $total_credit = $this->Transactions->getTotalCredit($this->client->id, $invoice->currency);
        
        // Apply as much credit as possible toward this invoice
        if ($total_credit > 0) {
            $apply_amount = ($invoice->due - $total_credit > 0 ? $total_credit : $invoice->due);
            $amounts = array(
                array('invoice_id' => $invoice->id, 'amount' => $apply_amount)
            );
            
            $amounts_applied = $this->Transactions->applyFromCredits($this->client->id, $invoice->currency, $amounts);
            $errors = $this->Transactions->errors();
            
            if (!empty($amounts_applied) && empty($errors))
                return $apply_amount;
        }
        
        return false;
    }
	
	/**
	 * Attempt merchant payment for the given order and invoice.
	 * Sets Input errors on error.
	 *
	 * @param stdClass $order The order to process payment for
	 * @param stdClass $invoice The invoice to process payment for
	 */
	private function processPayment($order, $invoice) {
		extract($this->getPaymentOptions($invoice->currency));
		
		$this->uses(array("Contacts", "Accounts", "Payments"));
		
		$options = array();
		$pay_with = !empty($this->post['payment_account']) ? "account" : (!empty($this->post['payment_type']) ? "details" : null);
	
		if ($pay_with == "account" || $pay_with == "details") {
	
			$account_info = null;
			$account_id =  null;
			$type = null;
			
			// Set payment account details
			if ($pay_with == "account") {
				$temp = explode("_", $this->post['payment_account']);
				$type = $temp[0];
				$account_id = $temp[1];
			}
			// Set the new payment account details
			else {
				// Fetch the contact we're about to set the payment account for
				$this->post['contact_id'] = (isset($this->post['contact_id']) ? $this->post['contact_id'] : 0);
				$contact = $this->Contacts->get($this->post['contact_id']);
				
				if ($this->post['contact_id'] == "none" || !$contact || ($contact->client_id != $this->client->id))
					$this->post['contact_id'] = $this->client->contact_id;
				
				$type = $this->post['payment_type'];
				
				// Attempt to save the account, then set it as the account to use
				if (isset($this->post['save_details']) && $this->post['save_details'] == "true") {
					if ($type == "ach")
						$account_id = $this->Accounts->addAch($this->post);
					elseif ($type == "cc") {
						$this->post['expiration'] = (isset($this->post['expiration_year']) ? $this->post['expiration_year'] : "") . (isset($this->post['expiration_month']) ? $this->post['expiration_month'] : "");
						// Remove type, it will be automatically determined
						unset($this->post['type']);
						$account_id = $this->Accounts->addCc($this->post);
					}
				}
				else {
					$account_info = $this->post;
					
					if ($type == "ach") {
						$account_info['account_number'] = $account_info['account'];
						$account_info['routing_number'] = $account_info['routing'];
						$account_info['type'] = $account_info['type'];
					}
					elseif ($type == "cc") {
						$account_info['card_number'] = $account_info['number'];
						$account_info['card_exp'] = $account_info['expiration_year'] . $account_info['expiration_month'];
						$account_info['card_security_code'] = $account_info['security_code'];
					}
				}
				
			}
			
			// Set payment to be applied to the invoice that was created
			$options['invoices'] = array($invoice->id => $invoice->due);
				
			$transaction = $this->Payments->processPayment($this->client->id, $type, $invoice->due, $currency, $account_info, $account_id, $options);
			
			// If payment error occurred, send client to pay invoice page (they're already logged in)
			if (($errors = $this->Payments->errors())) {
				$this->Input->setErrors($errors);
				return;
			}
		}
	}
	
	/**
	 * Sets the contact partial view
	 *
	 * @param stdClass $vars The input vars object for use in the view
	 * @param boolean $edit True if this is an edit, false otherwise
	 */
	private function setContactView(stdClass $vars, $edit=false) {
		$this->uses(array("Contacts", "Countries", "States"));
		
		$contacts = array();
		
		if (!$edit) {
			// Set an option for no contact
			$no_contact = array(
				(object)array(
					'id'=>"none",
					'first_name'=>Language::_("Checkout.setcontactview.text_none", true),
					'last_name'=>""
				)
			);
			
			// Set all contacts whose info can be prepopulated (primary or billing only)
			$primary_contact = $this->Contacts->getAll($this->client->id, "primary");
			if (!isset($vars->contact_id) && isset($primary_contact[0]))
				$vars->contact_id = $primary_contact[0]->id;
				
			$contacts = array_merge($primary_contact, $this->Contacts->getAll($this->client->id, "billing"));
			$contacts = array_merge($no_contact, $contacts);
		}
		
		// Set partial for contact info
		$contact_info = array(
			'js_contacts' => $this->Json->encode($contacts),
			'contacts' => $this->Form->collapseObjectArray($contacts, array("first_name", "last_name"), "id", " "),
			'countries' => $this->Form->collapseObjectArray($this->Countries->getList(), array("name", "alt_name"), "alpha2", " - "),
			'states' => $this->Form->collapseObjectArray($this->States->getList($vars->country), "name", "code"),
			'vars' => $vars,
			'edit' => $edit,
			'order_form' => $this->order_form
		);
		
		$this->set("contact_info", $this->partial("checkout_contact_info", $contact_info));
	}
	
	/**
	 * Sets the ACH partial view
	 * @see ClientPay::index()
	 *
	 * @param stdClass $vars The input vars object for use in the view
	 * @param boolean $edit True if this is an edit, false otherwise
	 * @param boolean $save_account True to offer an option to save these payment details, false otherwise
	 */
	private function setAchView(stdClass $vars, $edit=false, $save_account=false) {
		// Set partial for ACH info
		$ach_info = array(
			'types' => $this->Accounts->getAchTypes(),
			'vars' => $vars,
			'edit' => $edit,
			'client' => $this->client,
			'save_account' => $save_account
		);
		
		$this->set("ach_info", $this->partial("checkout_ach_info", $ach_info));
	}
	
	/**
	 * Sets the CC partial view
	 * @see ClientPay::index()
	 *
	 * @param stdClass $vars The input vars object for use in the view
	 * @param boolean $edit True if this is an edit, false otherwise
	 * @param boolean $save_account True to offer an option to save these payment details, false otherwise
	 */
	private function setCcView(stdClass $vars, $edit=false, $save_account=false) {
		// Set available credit card expiration dates
		$expiration = array(
			// Get months with full name (e.g. "January")
			'months' => $this->Date->getMonths(1, 12, "m", "F"),
			// Sets years from the current year to 10 years in the future
			'years' => $this->Date->getYears(date("Y"), date("Y") + 10, "Y", "Y")
		);
		
		// Set partial for CC info
		$cc_info = array(
			'expiration' => $expiration,
			'vars' => $vars,
			'edit' => $edit,
			'client' => $this->client,
			'save_account' => $save_account
		);
		
		$this->set("cc_info", $this->partial("checkout_cc_info", $cc_info));
	}
	
	/**
	 * Gets all payments the client can choose from
	 *
	 * @param stdClass $merchant_gateway A stdClass object representin the merchant gateway, false if no merchant gateway set
	 * @param string $currency The ISO 4217 currency code to pay in
	 * @param array $payment_types An array of allowed key/value payment types, where each key is the payment type and each value is the payment type name
	 */
	private function getPaymentAccounts($merchant_gateway, $currency, array $payment_types) {
		
		$this->uses(array("Accounts", "GatewayManager"));
		
		// Get ACH payment types
		$ach_types = $this->Accounts->getAchTypes();
		// Get CC payment types
		$cc_types = $this->Accounts->getCcTypes();
		
		// Set available payment accounts
		$payment_accounts = array();
		
		// Only allow CC payment accounts if enabled
		if (isset($payment_types['cc'])) {
			$cc = $this->Accounts->getAllCcByClient($this->client->id);
			
			$temp_cc_accounts = array();
			foreach ($cc as $account) {
				// Skip this payment account if it is expecting a different
				// merchant gateway, one is not available, or the payment
				// method is not supported by the gateway
				if (!$merchant_gateway ||
					($merchant_gateway &&
						(
							($account->gateway_id && $account->gateway_id != $merchant_gateway->id) ||
							($account->reference_id && !in_array("MerchantCcOffsite", $merchant_gateway->info['interfaces'])) ||
							(!$account->reference_id && !in_array("MerchantCc", $merchant_gateway->info['interfaces']))
						)
					))
					continue;

				$temp_cc_accounts["cc_" . $account->id] = Language::_("Checkout.getpaymentaccounts.account_name", true, $account->first_name, $account->last_name, $cc_types[$account->type], $account->last4);
			}
			
			// Add the CC payment accounts that can be used for this payment
			if (!empty($temp_cc_accounts)) {
				$payment_accounts[] = array('value'=>"optgroup", 'name'=>Language::_("Checkout.getpaymentaccounts.paymentaccount_cc", true));
				$payment_accounts = array_merge($payment_accounts, $temp_cc_accounts);
			}
			unset($temp_cc_accounts);
		}
		
		// Only allow ACH payment accounts if enabled
		if (isset($payment_types['ach'])) {
			$ach = $this->Accounts->getAllAchByClient($this->client->id);
			
			$temp_ach_accounts = array();
			foreach ($ach as $account) {
				// Skip this payment account if it is expecting a different
				// merchant gateway, one is not available, or the payment
				// method is not supported by the gateway
				if (!$merchant_gateway ||
					($merchant_gateway &&
						(
							($account->gateway_id && $account->gateway_id != $merchant_gateway->id) ||
							($account->reference_id && !in_array("MerchantAchOffsite", $merchant_gateway->info['interfaces'])) ||
							(!$account->reference_id && !in_array("MerchantAch", $merchant_gateway->info['interfaces']))
						)
					))
					continue;

				$temp_ach_accounts["ach_" . $account->id] = Language::_("Checkout.getpaymentaccounts.account_name", true, $account->first_name, $account->last_name, $ach_types[$account->type], $account->last4);
			}
			
			// Add the ACH payment accounts that can be used for this payment
			if (!empty($temp_ach_accounts)) {
				$payment_accounts[] = array('value'=>"optgroup", 'name'=>Language::_("Checkout.getpaymentaccounts.paymentaccount_ach", true));
				$payment_accounts = array_merge($payment_accounts, $temp_ach_accounts);
			}
			unset($temp_ach_accounts);
		}
		
		return $payment_accounts;
	}
}
?>