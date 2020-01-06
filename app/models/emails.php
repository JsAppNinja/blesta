<?php
/**
 * Email management
 *
 * @package blesta
 * @subpackage blesta.app.models
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class Emails extends AppModel {
	
	/**
	 * An array of key/value pairs to be used as default tags for email templates
	 */
	private $default_tags = array();
	
	/**
	 * Initialize Emails
	 */
	public function __construct() {
		parent::__construct();
		Language::loadLang(array("emails"));
		
		Loader::loadHelpers($this, array("CurrencyFormat"));
		
		$company = Configure::get("Blesta.company");
		
		if ($company) {
			$webdir = WEBDIR;
			
			// Set default webdir if running via CLI
			if (empty($_SERVER['REQUEST_URI'])) {
				Loader::loadModels($this, array("Settings"));
				$root_web = $this->Settings->getSetting("root_web_dir");
				if ($root_web) {
					$webdir = str_replace(DS, "/", str_replace(rtrim($root_web->value, DS), "", ROOTWEBDIR));
					
					if (!HTACCESS)
						$webdir .= "index.php/";
				}
			}
			
			// Set the URIs to the admin/client portals
			$this->default_tags['base_uri'] = $company->hostname . $webdir;
			$this->default_tags['admin_uri'] = $company->hostname . $webdir . Configure::get("Route.admin") . "/";
			$this->default_tags['client_uri'] = $company->hostname . $webdir . Configure::get("Route.client") . "/";
		}
	}
	
	/**
	 * Retrieves a single email template by ID, including email group tags
	 *
	 * @param int $id The ID of the email template to fetch
	 * @return mixed A stdClass object representing the email template, false if no such template exists
	 */
	public function get($id) {
		$this->Record = $this->getEmails();
		
		$email = $this->Record->where("emails.id", "=", $id)->fetch();
		
		if ($email) {
			$email->tags = explode(",", $email->tags);
			$email->signature = $this->getSignature($email->email_signature_id);
		}
		return $email;
	}
	
	/**
	 * Retrieves a signle email template by Group ID for the current company
	 *
	 * @param int $group_id The email group ID to use to fetch the email tempalte for the current company
	 * @param string $lang The language in ISO 636-1 2-char + "_" + ISO 3166-1 2-char (e.g. en_us) (optional, defaults to default language)
	 * @return mixed A stdClass object representing the email template, false if no such template exists
	 */
	public function getByGroupId($group_id, $lang = null) {
		// Set default language
		if ($lang == null)
			$lang = Configure::get("Language.default");
		
		$this->Record = $this->getEmails();
		
		$email = $this->Record->where("emails.email_group_id", "=", $group_id)->
			where("emails.company_id", "=", Configure::get("Blesta.company_id"))->
			where("emails.lang", "=", $lang)->fetch();
		
		if ($email) {
			$email->tags = explode(",", $email->tags);
			$email->signature = $this->getSignature($email->email_signature_id);
		}
		return $email;
	}
	
	/**
	 * Constructs a partial Record query used to fetch emails
	 *
	 * @return Record A partial Record query object used to fetch emails
	 */
	private function getEmails() {
		$fields = array("emails.*",
			"email_groups.action"=>"email_group_action", "email_groups.type"=>"email_group_type",
			"email_groups.plugin_dir", "email_groups.tags"
		);
		
		return $this->Record->select($fields)->from("emails")->
			innerJoin("email_groups", "email_groups.id", "=", "emails.email_group_id", false);
	}
	
	/**
	 * Fetches a single email template by company ID, type, and (optionally) language
	 *
	 * @param int $company_id The ID of the company to fetch the email for
	 * @param string $action The email group action to fetch
	 * @param string $lang The language of template to fetch, defaults to default language
	 * @return mixed A stdClass object representing the email template, false if no such template exists
	 */
	public function getByType($company_id, $action, $lang=null) {
		
		if ($lang == null)
			$lang = Configure::get("Language.default");
			
		$fields = array("emails.*", "email_groups.plugin_dir", "email_groups.tags");
		
		$email = $this->Record->select($fields)->from("emails")->
			innerJoin("email_groups", "email_groups.id", "=", "emails.email_group_id", false)->
			where("emails.company_id", "=", $company_id)->
			where("emails.lang", "=", $lang)->
			where("email_groups.action", "=", $action)->fetch();
		
		if ($email) {
			$email->tags = explode(",", $email->tags);
			$email->signature = $this->getSignature($email->email_signature_id);
		}
		elseif (!$email && $lang != "en_us") {
			// Fallback to the English version, if one exists
			$email = $this->getByType($company_id, $action, "en_us");
		}
		return $email;
	}
	
	/**
	 * Fetches a list of all email templates under a company for the given email group
	 * in every available language
	 *
	 * @param int $company_id The company ID to fetch email templates for
	 * @param string $email_group_id The email group ID
	 * @return mixed An array of objects or false if no results.
	 */
	public function getList($company_id, $email_group_id) {
		return $this->Record->select()->from("emails")->			
			where("emails.company_id", "=", $company_id)->where("emails.email_group_id", "=", $email_group_id)->
			fetchAll();
	}
	
	/**
	 * Adds an email with the given data
	 *
	 * @param array $vars An array of email info including:
	 * 	- email_group_id The ID of the group this email belongs to
	 * 	- company_id The company ID this email belongs to
	 * 	- lang The language in ISO 636-1 2-char + "_" + ISO 3166-1 2-char (e.g. en_us) (optional, default en_us)
	 * 	- from The address the message will be sent from
	 * 	- from_name The name belonging to the from address
	 * 	- subject The subject of the message
	 * 	- text The plain-text body of the message (if empty will be created based on the html content) (optional, default null)
	 * 	- html The html body of the message (optional, default null)
	 * 	- email_signature_id The signature to append to the email (optional, default null)
	 * 	- include_attachments 1 to include attachments when the email is sent, 0 to not send attachments with the email (optional, default 1)
	 * 	- status The status of this email 'active', 'inactive' (optional, default active)
	 * @return int The ID of the email, void on error
	 */
	public function add(array $vars) {
		$this->Input->setRules($this->getEmailRules($vars));
		
		if ($this->Input->validates($vars)) {
			// Add an email
			$fields = array("email_group_id", "company_id", "lang", "from", "from_name",
				"subject", "text", "html", "email_signature_id", "include_attachments", "status"
			);
			$this->Record->insert("emails", $vars, $fields);
			return $this->Record->lastInsertId();
		}
		$this->setParseError();
	}
	
	/**
	 * Updates the email with the given data
	 *
	 * @param int $id The ID of the email to update
	 * @param array $vars An array of email info including:
	 * 	- email_group_id The ID of the group this email belongs to
	 * 	- company_id The company ID this email belongs to
	 * 	- lang The language in ISO 636-1 2-char + "_" + ISO 3166-1 2-char (e.g. en_us) (optional, default en_us)
	 * 	- from The address the message will be sent from
	 * 	- from_name The name belonging to the from address
	 * 	- subject The subject of the message
	 * 	- text The plain-text body of the message (if empty will be created based on the html content) (optional, default null)
	 * 	- html The html body of the message (optional, default null)
	 * 	- email_signature_id The signature to append to the email (optional, default null)
	 * 	- include_attachments 1 to include attachments when the email is sent, 0 to not send attachments with the email (optional)
	 * 	- status The status of this email 'active', 'inactive' (optional, default active)
	 */
	public function edit($id, array $vars) {
		$rules = $this->getEmailRules($vars, true);
		$rules['email_id'] = array(
			'exists' => array(
				'rule' => array(array($this, "validateExists"), "id", "emails"),
				'message' => $this->_("Emails.!error.email_id.exists")
			)
		);
		
		$this->Input->setRules($rules);
		// Set email ID to validate
		$vars['email_id'] = $id;
		
		if ($this->Input->validates($vars)) {
			// Update an email
			$fields = array("email_group_id", "company_id", "lang", "from", "from_name",
				"subject", "text", "html", "email_signature_id", "include_attachments", "status"
			);
			$this->Record->where("id", "=", $id)->update("emails", $vars, $fields);
			return;
		}
		$this->setParseError();
	}
	
	/**
	 * Permanently removes the email from the system.
	 *
	 * @param int $id The ID of the email to delete
	 */
	public function delete($id) {
		// Delete from emails
		$this->Record->from("emails")->where("id", "=", $id)->delete();
	}

	/**
	 * Permanently removes all email templates of the given group from the system.
	 *
	 * @param int $email_group_id The ID of the email group to remove all email template from
	 * @param int $company_id The ID of the company to remove all email templates in this group
	 */
	public function deleteAll($email_group_id, $company_id) {
		// Delete from emails
		$this->Record->from("emails")->
			where("company_id", "=", $company_id)->
			where("email_group_id", "=", $email_group_id)->delete();
	}
	
	/**
	 * Updates the domani portion of the from name for every email in the given
	 * company.
	 *
	 * @param string $domain The new domain to use
	 * @param int $company_id The ID of the company to update
	 */
	public function updateFromDomain($domain, $company_id) {
		$this->Record = $this->getEmails();
		$emails = $this->Record->where("emails.company_id", "=", $company_id)->fetchAll();
		
		foreach ($emails as $email) {
			$from = str_replace(strstr($email->from, "@"), "@" . $domain, $email->from);
			$this->Record->set("from", $from)->where("id", "=", $email->id)->update("emails");
		}
	}
	
	/**
	 * Retrieves a list of email status types
	 *
	 * @return array Key=>value pairs of email status types
	 */
	public function getStatusTypes() {
		return array(
			"active"=>$this->_("Emails.getStatusTypes.active"),
			"inactive"=>$this->_("Emails.getStatusTypes.inactive")
		);
	}
	
	/**
	 * Adds the given signature to the system
	 *
	 * @param array $vars An array of signature info including:
	 * 	- company_id The company ID to create the signature under
	 * 	- name The name of the signature
	 * 	- text The plaintext signature
	 * 	- html The HTML signature
	 */
	public function addSignature(array $vars) {		
		$this->Input->setRules($this->getSignatureRules());
		
		if ($this->Input->validates($vars)) {
			// Update email_signatures
			$fields = array("company_id", "name", "text", "html");
			$this->Record->insert("email_signatures", $vars, $fields);
			return $this->Record->lastInsertId();
		}
	}
	
	/**
	 * Updates an existing signature in the system
	 *
	 * @param $email_signature_id The ID of the signature in the system to update
	 * @param array $vars An array of signature info including:
	 * 	- name The name of the signature
	 * 	- text The plaintext signature
	 * 	- html The HTML signature
	 */
	public function editSignature($email_signature_id, array $vars) {
		$rules = $this->getSignatureRules();
		$rules['email_signature_id'] = array(
			'exists' => array(
				'rule' => array(array($this, "validateExists"), "id", "email_signatures"),
				'message' => $this->_("Emails.!error.email_signature_id.exists")
			)
		);
		
		// Remove company_id constraint
		unset($rules['company_id']);
		
		$this->Input->setRules($rules);
		
		$vars['email_signature_id'] = $email_signature_id;
		
		if ($this->Input->validates($vars)) {
			// Update email_signatures (do not update company ID)
			$fields = array("name", "text", "html");
			$this->Record->where("id", "=", $email_signature_id)->update("email_signatures", $vars, $fields);
		}
	}
	
	/**
	 * Permanently removes an email signature from the system
	 *
	 * @param int $email_signature_id The ID of the email signature to delete
	 */
	public function deleteSignature($email_signature_id) {
		$rules = array(
			'email_signature_id' => array(
				'in_use' => array(
					'rule' => array(array($this, "validateSignatureInUse")),
					'negate' => true,
					'message' => $this->_("Emails.!error.email_signature_id.in_use")
				)
			)
		);
		
		$vars = array('email_signature_id'=>$email_signature_id);
		
		$this->Input->setRules($rules);
		
		if ($this->Input->validates($vars))
			$this->Record->from("email_signatures")->where("id", "=", $email_signature_id)->delete();
	}
	
	/**
	 * Fetches an email signature
	 *
	 * @param int $email_signature_id The email signature ID
	 * @return mixed An array of objects or false if no results.
	 */
	public function getSignature($email_signature_id) {
		// If null, no email signature exists, but we can't query on primary key IS NULL
		// due to MySQLs ODBC compatibility, which instead returns the last increment ID if available
		if ($email_signature_id === null)
			return false;
		return $this->Record->select()->from("email_signatures")->where("id", "=", $email_signature_id)->fetch();
	}
	
	/**
	 * Fetches a list of all email signatures for a given company
	 *
	 * @param int $company_id The company ID whose signatures to fetch
	 * @param int $page The page to return results for (optional, default 1)
	 * @param string $order_by The sort and order conditions (e.g. array('sort_field'=>"ASC"), optional)
	 * @return array An array of objects or false if no results.
	 */
	public function getSignatureList($company_id, $page=1, $order_by=array('name'=>"ASC")) {
		$this->Record = $this->getSignatures($company_id);
		
		// Return the results
		return $this->Record->order($order_by)->
			limit($this->getPerPage(), (max(1, $page) - 1)*$this->getPerPage())->fetchAll();
	}
	
	/**
	 * Fetches the total number of signatures returned from Emails::getSignatureList(),
	 * useful in constructing pagination for the getSignatureList() method.
	 *
	 * @param int $company_id The company ID whose signatures to fetch
	 * @return int The total number of signatures
	 * @see Emails::getList()
	 */
	public function getSignatureListCount($company_id) {
		$this->Record = $this->getSignatures($company_id);
		
		// Return the number of results
		return $this->Record->numResults();
	}
	
	/**
	 * Retrieves a list of all signatures for this company
	 *
	 * @param int $company_id The company ID whose signatures to fetch
	 * @return array An array of stdClass objects each representing a signature
	 */
	public function getAllSignatures($company_id) {
		return $this->getSignatures($company_id)->fetchAll();
	}
	
	/**
	 * Partially constructs the query required by both Emails::getSignatureList() and
	 * Emails::getSignatureListCount()
	 *
	 * @param int $company_id The company ID to fetch email for
	 * @return Record The partially constructed query Record object
	 */
	private function getSignatures($company_id) {
		$fields = array("id", "company_id", "name", "text", "html");
		
		return $this->Record->select($fields)->from("email_signatures")->where("company_id", "=", $company_id);
	}
	
	/**
	 * Fetches an email group
	 *
	 * @param int $email_group_id The email group ID
	 * @return mixed An array of objects or false if no results.
	 */
	public function getGroup($email_group_id) {
		return $this->Record->select()->from("email_groups")->where("id", "=", $email_group_id)->fetch();
	}
	
	/**
	 * Fetches all email groups
	 *
	 * @param string $sort_by The field to sort by
	 * @param string $order The direction to order (asc, desc)
	 */
	public function getGroupList($sortby="action", $order="asc") {
		return $this->Record->select()->from("email_groups")->order(array($sortby=>$order))->fetchAll();
	}
	
	/**
	 * Sends the given email using the criteria specified
	 *
	 * @param string $action The action that specifies the email group to use
	 * @param int $company_id The company ID to send this email under
	 * @param string $lang The language in ISO 636-1 2-char + "_" + ISO 3166-1 2-char (e.g. en_us) to send, if no message found for this language will attempt to send using company's default language
	 * @param mixed $to The To address(es) to send to. A string, or an array of email addresses
	 * @param array $tags An array of replacement tags containing the key/value pairs for the replacements where the key is the tag to replace and the value is the value to replace it with
	 * @param mixed $cc The CC address(es) to send to. A string, or an array of email addresses
	 * @param mixed $bcc The BCC address(es) to send to. A string, or an array of email addresses
	 * @param array $attachments A multi-dimensional array of attachments containing:
	 * 	- path The path to the attachment on the file system
	 * 	- name The name of the attachment (optional, default '')
	 * 	- encoding The file encoding (optional, default 'base64')
	 * 	- type The type of attachment (optional, default 'application/octet-stream')
	 * @param array $options An array of options including:
	 * 	- to_client_id The ID of the client the message was sent to
	 * 	- from_staff_id The ID of the staff member the message was sent from
	 * 	- from The from address
	 * 	- from_name The from name
	 * 	- reply_to The reply to address
	 * @return boolean Returns true if the message was successfully sent, false otherwise
	 */
	public function send($action, $company_id, $lang, $to, array $tags=null, $cc=null, $bcc=null, array $attachments=null, array $options=null) {
		if (!isset($this->Staff))
			Loader::loadModels($this, array("Staff"));
		
		// Get all active staff members that should be BCC'd on this email
		$staff = $this->Staff->getAllByEmailAction($action, $company_id, "bcc", "active");
		
		// Merge the BCC addresses
		if (!empty($staff)) {
			$bcc = (array)$bcc;
			foreach ($staff as $staff_member)
				$bcc[] = $staff_member->email;
		}
		
		// Validate this data
		$vars = array(
			'action' => $action,
			'company_id' => $company_id,
			'to_addresses' => $to,
			'cc_addresses' => $cc,
			'bcc_addresses' => $bcc,
			'attachments' => $attachments
		);
		
		// Set additional from, from name, reply to vars
		if (isset($options['from']))
			$vars['from'] = $options['from'];
		if (isset($options['from_name']))
			$vars['from_name'] = $options['from_name'];
		if (isset($options['reply_to']))
			$vars['reply_to'] = $options['reply_to'];
		
		// Get the rules
		$this->Input->setRules($this->getSendEmailRules($vars));

		$result = false;		
		if ($this->Input->validates($vars)) {
			
			$default_options = array('company_id'=>$company_id);
			$options = array_merge($default_options, (array)$options);
			
			// Handle email send event
			$this->Events->register("Emails.send", array("EventsEmailsCallback", "send"));
			$tags = array_merge($tags, (array)$this->Events->trigger(new EventObject("Emails.send", compact("action", "options", "tags")))->getReturnVal());
			
			// Fetch the email based on action company id and lang
			$email = $this->buildEmail($action, $company_id, $lang, $tags);
			
			// If template doesn't exist or is not active, return
			if (!$email || $email->status != "active")
				return false;
			
			$email->to = $to;
			$email->cc = $cc;
			$email->bcc = $bcc;
			$email->options = $options;
			
			// Set attachments if enabled for this email
			$email->attachments = ((isset($email->include_attachments) && $email->include_attachments == 1) ? $attachments : null);
			
			// Set optional from/from name/replyto
			if (isset($options['from']))
				$email->from = $options['from'];
			if (isset($options['from_name']))
				$email->from_name = $options['from_name'];
			if (isset($options['reply_to']))
				$email->reply_to = $options['reply_to'];
			
			$result = $this->sendEmail($email);
			
			// If the email failed to send for some reason it may be a mail configuration issue
			if (!$result)
				$this->Input->setErrors(array(
					'email' => array(
						'failed_to_send' => $this->_("Emails.!error.email.failed_to_send", true)
					)
				));
		}
		
		return $result;
	}
	
	/**
	 * Sends a custom email using the criteria specified
	 *
	 * @param string $from The email address to send from.
	 * @param string $from_name The name to send from.
	 * @param mixed $to The To address(es) to send to. A string, or an array of email addresses
	 * @param string $subject The subject of the message. Tags provided in the subject will be evaluated by the template parser
	 * @param array $body An array containing the body in HTML and text of the email. Tags provided in the body will be evaluated by the template parser:
	 * 	- html The HTML version of the email (optional)
	 * 	- text The text version of the email (optional)
	 * @param array $tags An array of replacement tags containing the key/value pairs for the replacements where the key is the tag to replace and the value is the value to replace it with
	 * @param mixed $cc The CC address(es) to send to. A string, or an array of email addresses
	 * @param mixed $bcc The BCC address(es) to send to. A string, or an array of email addresses
	 * @param array $attachments A multi-dimensional array of attachments containing:
	 * 	- path The path to the attachment on the file system
	 * 	- name The name of the attachment (optional, default '')
	 * 	- encoding The file encoding (optional, default 'base64')
	 * 	- type The type of attachment (optional, default 'application/octet-stream')
	 * @param array $options An array of options including:
	 * 	- to_client_id The ID of the client the message was sent to
	 * 	- from_staff_id The ID of the staff member the message was sent from
	 * 	- reply_to The reply to address
	 * @return boolean Returns true if the message was successfully sent, false otherwise
	 */
	public function sendCustom($from, $from_name, $to, $subject, array $body, array $tags=null, $cc=null, $bcc=null, array $attachments=null, array $options=null)  {
		
		// Validate this data
		$vars = array(
			'from' => $from,
			'from_name' => $from_name,
			'subject' => $subject,
			'to_addresses' => $to,
			'cc_addresses' => $cc,
			'bcc_addresses' => $bcc,
			'attachments' => $attachments
		);
		
		// Set reply to option
		if (isset($options['reply_to']))
			$vars['reply_to'] = $options['reply_to'];
		
		$this->Input->setRules($this->getSendEmailRules($vars, true));

		$result = false;		
		if ($this->Input->validates($vars)) {
			$default_options = array('company_id'=>Configure::get("Blesta.company_id"));
			$options = array_merge($default_options, (array)$options);
			
			// Merge the default tags with those given
			$tags = array_merge($this->default_tags, (array)$tags);
			
			// Handle email send event
			$this->Events->register("Emails.sendCustom", array("EventsEmailsCallback", "sendCustom"));
			$tags = array_merge($tags, (array)$this->Events->trigger(new EventObject("Emails.sendCustom", compact("options", "tags")))->getReturnVal());
			
			$email = new stdClass();
			$email->html = isset($body['html']) ? $body['html'] : null;
			$email->text = isset($body['text']) ? $body['text'] : null;
			$email->subject = $subject;
			$email->from = $from;
			$email->from_name = $from_name;
			
			if (isset($options['reply_to']))
				$email->reply_to = $options['reply_to'];
			
			// Load the template parser
			Loader::load(VENDORDIR . "h2o" . DS . "h2o.php");
			
			$parser = new H2o();
			$this->setFilters($parser, Configure::get("Blesta.company_id"));
			
			$parser_options_html = Configure::get("Blesta.parser_options");
			$parser_options_text = Configure::get("Blesta.parser_options");
			// Don't escape text
			$parser_options_text['autoescape'] = false;
			// Don't escape html
			$parser_options_html['autoescape'] = false;
			
			// Parse email subject and body using template parser		
			$email->text = $parser->parseString($email->text, $parser_options_text)->render($tags);
			if ($email->html)
				$email->html = $parser->parseString($email->html, $parser_options_html)->render($tags);
			$email->subject = $parser->parseString($email->subject, $parser_options_text)->render($tags);
			
			$email->to = $to;
			$email->cc = $cc;
			$email->bcc = $bcc;
			$email->attachments = $attachments;
			$email->options = $options;
			
			$result = $this->sendEmail($email);
			
			// If the email failed to send for some reason it may be a mail configuration issue
			if (!$result)
				$this->Input->setErrors(array(
					'email' => array(
						'failed_to_send' => $this->_("Emails.!error.email.failed_to_send", true)
					)
				));
		}
		
		return $result;
	}
	
	/**
	 * Performs the heavy lifting to send the given email and log it.
	 *
	 * @param stdClass A stdClass object representing an email to send, containing all of the pertinent information including:
	 * 	- to
	 * 	- cc
	 * 	- bcc
	 * 	- subject
	 * 	- text
	 * 	- html
	 * 	- from
	 * 	- from_name
	 * 	- attachments A numerically indexed array containing:
	 * 		- path The full path to the file
	 * 		- name The name of the file
	 * 		- encoding (optional, defaults to base64)
	 * 		- type (optional, defaults to application/octet-stream)
	 * 	- options An array of options including:
	 * 		- to_client_id The ID of the client the message was sent to
	 * 		- from_staff_id The ID of the staff member the message was sent from
	 */
	private function sendEmail($email) {
		if (!isset($this->SettingsCollection)) {
			Loader::loadComponents($this, array("SettingsCollection"));
		}
		
		$company_settings = $this->SettingsCollection->fetchSettings(null, Configure::get("Blesta.company_id"));
		
		if (!isset($this->Email) || !($this->Email instanceof Email)) {
			Loader::loadComponents($this, array("Email"));
			
			// Set how the message is to be delivered
			if ($company_settings['mail_delivery'] == "php") {
				$this->Email->setTransport(Swift_MailTransport::newInstance());
			}
			elseif ($company_settings['mail_delivery'] == "smtp") {
				$transport = Swift_SmtpTransport::newInstance($company_settings['smtp_host'], $company_settings['smtp_port'])->
					setUsername($company_settings['smtp_user'])->
					setPassword($company_settings['smtp_password']);
					
				// Add option to set ssl/tls
				if ($company_settings['smtp_security'] != "")
					$transport->setEncryption($company_settings['smtp_security']);
				
				$this->Email->setTransport($transport);
				
				$this->Email->setFloodResistance(Configure::get("Blesta.email_messages_per_connection"), Configure::get("Blesta.email_reconnect_sleep"));
			}
		}
		
		if (!is_array($email->to))
			$email->to = (array)$email->to;
		if ($email->cc !== null && !is_array($email->cc))
			$email->cc = (array)$email->cc;
		if ($email->bcc !== null && !is_array($email->bcc))
			$email->bcc = (array)$email->bcc;
		
		// Prime the message
		$this->Email->resetAll();
		
		// Set the subject
		$this->Email->setSubject($email->subject);
		
		// Set the body of the message, prefer HTML iff HTML is enabled for the company
		if ($email->html != null && $company_settings['html_email'] == "true") {
			$this->Email->setBody($email->html, true);
			$this->Email->setAltBody($email->text);
		}
		else
			$this->Email->setBody($email->text, false);
		

		// Set the from address
		$this->Email->setFrom($email->from, $email->from_name);
		
		// Set the reply-to address
		if (isset($email->reply_to))
			$this->Email->addReplyTo($email->reply_to);

		// Add To addresses
		for ($i=0; $i<count($email->to); $i++)
			$this->Email->addAddress($email->to[$i]);
		// Add CC addresses
		for ($i=0; $i<count($email->cc); $i++)
			$this->Email->addCc($email->cc[$i]);
		// Add BCC addresses
		for ($i=0; $i<count($email->bcc); $i++)
			$this->Email->addBcc($email->bcc[$i]);

		// Add all attachments to the message
		for ($i=0; $i<count($email->attachments); $i++) {
			if (!isset($email->attachments[$i]['name']))
				$email->attachments[$i]['name'] = '';
			if (!isset($attachments[$i]['encoding']))
				$email->attachments[$i]['encoding'] = 'base64';
			if (!isset($email->attachments[$i]['type']))
				$email->attachments[$i]['type'] = 'application/octet-stream';
				
			$this->Email->addAttachment($email->attachments[$i]['path'], $email->attachments[$i]['name'], $email->attachments[$i]['encoding'], $email->attachments[$i]['type']);
		}
		
		// Send the message
		$this->Email->setLogOptions($email->options);
		return $this->Email->send();
	}
	
	/**
	 * Parses an Email stdClass object using the given data ($tags)
	 *
	 * @param string $action The action that specifies the email group to use
	 * @param int $company_id The company ID to send this email under
	 * @param string $lang The language in ISO 636-1 2-char + "_" + ISO 3166-1 2-char (e.g. en_us) to send, if no message found for this language will attempt to send using company's default language
	 * @param array $tags An array of replacement tags containing the key/value pairs for the replacements where the key is the tag to replace and the value is the value to replace it with
	 * @return mixed A stdClass object representing the parsed email template, false if no such template exists
	 */
	public function buildEmail($action, $company_id, $lang, array $tags=null) {
		// Fetch the email based on action company id and lang
		$email = $this->getByType($company_id, $action, $lang);
		
		// If template doesn't exist, return
		if (!$email)
			return false;
		
		// Merge the default tags with those given
		$tags = array_merge($this->default_tags, (array)$tags);
		
		// Load the template parser
		Loader::load(VENDORDIR . "h2o" . DS . "h2o.php");
		
		$parser = new H2o();
		$this->setFilters($parser, $company_id);
		
		$parser_options_html = Configure::get("Blesta.parser_options");
		$parser_options_text = Configure::get("Blesta.parser_options");
		// Don't escape text
		$parser_options_text['autoescape'] = false;
		// Don't escape html
		$parser_options_html['autoescape'] = false;
		
		// Replace specific tags for the service creation template
		if ($action == "service_creation") {
			$var_html_start = (isset($parser_options_html['VARIABLE_START']) ? $parser_options_html['VARIABLE_START'] : "");
			$var_html_end = (isset($parser_options_html['VARIABLE_END']) ? $parser_options_html['VARIABLE_END'] : "");
			$var_text_start = (isset($parser_options_text['VARIABLE_START']) ? $parser_options_text['VARIABLE_START'] : "");
			$var_text_end = (isset($parser_options_text['VARIABLE_END']) ? $parser_options_text['VARIABLE_END'] : "");
			
			$email->text = str_replace($var_text_start . "package.email_text" . $var_text_end, (isset($tags['package.email_text']) ? $tags['package.email_text'] : ""), $email->text);
			$email->html = str_replace($var_html_start . "package.email_html" . $var_html_end, (isset($tags['package.email_html']) ? $tags['package.email_html'] : ""), $email->html);
			unset($tags['package.email_text'], $tags['package.email_html']);
		}
		
		// Parse email subject and body using template parser		
		$email->text = $parser->parseString($email->text, $parser_options_text)->render($tags);
		if ($email->html)
			$email->html = $parser->parseString($email->html, $parser_options_html)->render($tags);
		$email->subject = $parser->parseString($email->subject, $parser_options_text)->render($tags);
		
		// Set the signatures
		if ($email->signature) {
			$email->text .= $email->signature->text;
			
			if ($email->html)
				$email->html .= $email->signature->html;
		}
		
		return $email;
	}
	
	/**
	 * Returns the rule set for adding/editing signatures
	 *
	 * @return array Signature rules
	 */
	private function getSignatureRules() {
		$rules = array(
			'company_id' => array(
				'exists' => array(
					'rule' => array(array($this, "validateExists"), "id", "companies"),
					'message' => $this->_("Emails.!error.company_id.exists")
				)
			),
			'name' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("Emails.!error.name.empty")
				)
			),
			'text' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("Emails.!error.text.empty")
				)
			)
		);
		return $rules;
	}
	
	/**
	 * Returns the rule set for adding/editing emails
	 *
	 * @param array $vars The key/value pairs of vars
	 * @param boolean $edit True when editing an email, false otherwise
	 * @return array Email rules
	 */
	private function getEmailRules(array $vars, $edit=false) {
		$rules = array(
			'email_group_id' => array(
				'exists' => array(
					'rule' => array(array($this, "validateExists"), "id", "email_groups"),
					'message' => $this->_("Emails.!error.email_group_id.exists")
				)
			),
			'company_id' => array(
				'exists' => array(
					'rule' => array(array($this, "validateExists"), "id", "companies"),
					'message' => $this->_("Emails.!error.company_id.exists")
				),
				'unique' => array(
					'rule' => array(array($this, "validateUnique"), $this->ifSet($vars['email_group_id']), $this->ifSet($vars['lang'])),
					'message' => $this->_("Emails.!error.company_id.unique")
				)
			),
			'lang' => array(
				'empty' => array(
					'if_set' => true,
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("Emails.!error.lang.empty")
				),
				'length' => array(
					'if_set' => true,
					'rule' => array("maxLength", 5),
					'message' => $this->_("Emails.!error.lang.length")
				)
			),
			'from' => array(
				'format' => array(
					'rule' => array("isEmail", false),
					'message' => $this->_("Emails.!error.from.format")
				)
			),
			'from_name' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("Emails.!error.from_name.empty")
				)
			),
			'subject' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("Emails.!error.subject.empty")
				)
			),
			'email_signature_id' => array(
				'exists' => array(
					//'if_set' => true,
					'rule' => array(array($this, "validateSignatureExists"), $this->ifSet($vars['company_id'])),
					'message' => $this->_("Emails.!error.email_signature_id.exists")
				)
			),
			'include_attachments' => array(
				'format' => array(
					'if_set' => true,
					'rule' => array("in_array", array(0, 1)),
					'message' => $this->_("Emails.!error.include_attachments")
				)
			),
			'status' => array(
				'format' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateStatus")),
					'message' => $this->_("Emails.!error.status.format")
				)
			),
			'html' => array(
				'parse' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateParse")),
					'message' => $this->_("Emails.!error.html.parse"),
					'final' => true
				)
			),
			'text' => array(
				'parse' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateParse")),
					'message' => $this->_("Emails.!error.text.parse"),
					'final' => true
				)
			)
		);
		
		// Allows an email to be edited when the email ID is itself
		// by negating the 'unique' constraint
		if ($edit)			
			$rules['company_id']['unique']['negate'] = true;
		
		return $rules;
	}
	
	/**
	 * Returns the rule set for adding/editing emails
	 *
	 * @param array $vars The key/value pairs of vars
	 * @param boolean $custom True to get the send custom email rules, false to get the default send email rules (optional, default false)
	 * @return array The send email rules
	 */
	private function getSendEmailRules(array $vars, $custom=false) {
		$rules = array();
		
		// Set the default rules
		if (!$custom) {
			$rules = array(
				'action' => array(
					'exists' => array(
						'rule' => array(array($this, "validateEmailGroupAction"), $this->ifSet($vars['action'])),
						'message' => $this->_("Emails.!error.action.exists")
					)
				),
				'company_id' => array(
					'exists' => array(
						'rule' => array(array($this, "validateExists"), "id", "companies"),
						'message' => $this->_("Emails.!error.company_id.exists")
					)
				),
				'from' => array(
					'format' => array(
						'if_set' => true,
						'rule' => "isEmail",
						'message' => $this->_("Emails.!error.from.format")
					)
				),
				'from_name' => array(
					'empty' => array(
						'if_set' => true,
						'rule' => "isEmpty",
						'negate' => true,
						'message' => $this->_("Emails.!error.from_name.empty")
					)
				)
			);
		}
		// Set the custom rules
		else {
			$rules = array(
				'from' => array(
					'format' => array(
						'rule' => "isEmail",
						'message' => $this->_("Emails.!error.from.format")
					)
				),
				'from_name' => array(
					'empty' => array(
						'rule' => "isEmpty",
						'negate' => true,
						'message' => $this->_("Emails.!error.from_name.empty")
					)
				),
				'subject' => array(
					'empty' => array(
						'rule' => "isEmpty",
						'negate' => true,
						'message' => $this->_("Emails.!error.subject.empty")
					)
				)
			);
		}
		
		// Set reply-to rule
		$rules['reply_to'] = array(
			'format' => array(
				'if_set' => true,
				'rule' => "isEmail",
				'message' => $this->_("Emails.!error.reply_to.format")
			)
		);
		
		// Set the to addresses
		$rules['to_addresses'] = array(
			'format' => array(
				'rule' => array(array($this, "validateEmailAddresses"), $this->ifSet($vars['to_addresses'])),
				'message' => $this->_("Emails.!error.to_addresses.format")
			)
		);
		
		// Check the CC and BCC addresses if any are given
		if (!empty($vars['cc_addresses'])) {
			$rules['cc_addresses'] = array(
				'format' => array(
					'rule' => array(array($this, "validateEmailAddresses"), $this->ifSet($vars['cc_addresses'])),
					'message' => $this->_("Emails.!error.cc_addresses.format")
				)
			);
		}
		if (!empty($vars['bcc_addresses'])) {
			$rules['bcc_addresses'] = array(
				'format' => array(
					'rule' => array(array($this, "validateEmailAddresses"), $this->ifSet($vars['bcc_addresses'])),
					'message' => $this->_("Emails.!error.bcc_addresses.format")
				)
			);
		}
		// Check the paths to the attachments if any are given
		if (!empty($vars['attachments'])) {
			$rules['attachments'] = array(
				'exist' => array(
					'rule' => array(array($this, "validateAttachmentPaths"), $this->ifSet($vars['attachments'])),
					'message' => $this->_("Emails.!error.attachments.exist")
				)
			);
		}
		
		return $rules;
	}
	
	/**
	 * Validates that the given action is a valid email group action
	 *
	 * @param string $action The email group action
	 * @return boolean True if the action is valid, false otherwise
	 */
	public function validateEmailGroupAction($action) {
		$count = $this->Record->select("action")->from("email_groups")->
			where("action", "=", $action)->numResults();
		
		if ($count > 0)
			return true;
		return false;
	}
	
	/**
	 * Validates that each of the given email addresses provided are valid
	 *
	 * @param mixed A string representing a single email address, or an array of email addresses
	 * @return boolean True if every email address is valid, false otherwise
	 */
	public function validateEmailAddresses($email_addresses) {
		// Check single and multiple addresses
		if (is_array($email_addresses)) {
			foreach ($email_addresses as $email) {
				if (!$this->Input->isEmail($email))
					return false;
			}
		}
		else {
			if (!$this->Input->isEmail($email_addresses))
				return false;
		}
		
		return true;
	}
	
	/**
	 * Validates that the given attachments exist on the file system
	 */
	public function validateAttachmentPaths($attachments) {
		if (is_array($attachments)) {
			foreach ($attachments as $attachment) {
				if (!isset($attachment['path']) || !file_exists($attachment['path']))
					return false;
			}
			
			// All attachment paths exist
			return true;
		}
		
		return false;
	}
	
	/**
	 * Validates the emails 'status' field
	 *
	 * @param string $status The status to check
	 * @return boolean True if status validated, false otherwise
	 */
	public function validateStatus($status) {
		switch ($status) {
			case "active":
			case "inactive":
				return true;
		}
		return false;
	}
	
	/**
	 * Validates the email signature submitted exists and belongs to the
	 * company ID given
	 *
	 * @param int $signature_id The email signature ID
	 * @param int $company_id The company ID (optional)
	 * @return boolean True if the signature exists and belongs to the company ID given, false otherwise
	 */
	public function validateSignatureExists($signature_id, $company_id=null) {
		if ($signature_id == null)
			return true;
		
		$this->Record->select("id")->from("email_signatures")->
			where("id", "=", $signature_id);
		
		if ($company_id != null)
			$this->Record->where("company_id", "=", $company_id);
		
		$count = $this->Record->numResults();
			
		if ($count > 0)
			return true;
		return false;
	}
	
	/**
	 * Checks whether the email signature given is in use by any email
	 *
	 * @param int $signature_id The email signature ID
	 * @return boolean True if signature ID is in use, false otherwise
	 */
	public function validateSignatureInUse($signature_id) {
		$count = $this->Record->select("id")->from("emails")->
			where("email_signature_id", "=", $signature_id)->numResults();
			
		if ($count > 0)
			return true;
		return false;
	}
	
	/**
	 * Validates the given company ID, email group ID, and language type are unique
	 * for this email
	 *
	 * @param int $company_id The company ID
	 * @param int $email_group_id The email group ID
	 * @param string $lang The language in ISO 636-1 2-char + "_" + ISO 3166-1 2-char (e.g. en_us) (optional, default en_us)
	 * @return boolean True if the given info is unique, false otherwise
	 */
	public function validateUnique($company_id, $email_group_id, $lang) {
		$count = $this->Record->select(array("id"))->from("emails")->
			where("company_id", "=", $company_id)->where("email_group_id", "=", $email_group_id)->
			where("lang", "=", $lang)->numResults();
		
		if ($count > 0)
			return false;
		return true;
	}
	
	/**
	 * Validate that the given string parses template parsing
	 *
	 * @param string $str The string to test
	 */
	public function validateParse($str) {
		if (!class_exists("H2o"))
			Loader::load(VENDORDIR . "h2o" . DS . "h2o.php");

		$parser_options_text = Configure::get("Blesta.parser_options");
		try {
			H2o::parseString($str, $parser_options_text)->render();
		}
		catch (H2o_Error $e) {
			$this->parseError = $e->getMessage();
			return false;
		}
		catch (Exception $e) {
			// Don't care about any other exception
		}
		return true;
	}
	
	/**
	 * Sets the parse error in the set of errors
	 */
	private function setParseError() {
		$errors = $this->Input->errors();
		if (isset($errors['text']['parse']) || isset($errors['html']['parse'])) {
			$type = "html";
			if (isset($errors['text']['parse']))
				$type = "text";
			$errors[$type]['parse'] = $this->_("Emails.!error." . $type . ".parse", $this->parseError);
		}
		$this->Input->setErrors($errors);
	}
	
	/**
	 * Sets filters on the parser
	 *
	 * @param object $parser The parser to set filters on
	 * @param int $company_id The company ID to set filters from
	 */
	private function setFilters($parser, $company_id) {
		$this->CurrencyFormat->setCompany($company_id);
		$parser->addFilter("currency_format", array($this->CurrencyFormat, "format"));
	}
}
?>