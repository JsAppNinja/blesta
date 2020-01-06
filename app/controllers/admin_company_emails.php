<?php
/**
 * Admin Company Emails Settings
 * 
 * @package blesta
 * @subpackage blesta.app.controllers
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class AdminCompanyEmails extends AppController {
	
	/**
	 * Pre-action
	 */
	public function preAction() {
		parent::preAction();
		
		// Require login
		$this->requireLogin();		
		
		$this->uses(array("Navigation"));
		$this->components(array("SettingsCollection"));
		
		Language::loadLang("admin_company_emails");
		
		// Set the left nav for all settings pages to settings_leftnav
		$this->set("left_nav", $this->partial("settings_leftnav", array("nav"=>$this->Navigation->getCompany($this->base_uri))));
	}
	
	/**
	 * Taxes page
	 */
	public function index() {
		$this->redirect($this->base_uri . "settings/company/emails/templates/");
	}
	
	/**
	 * Email Mail Settings page
	 */
	public function mail() {
		$this->uses(array("Companies"));
		
		if (!empty($this->post)) {
			// Set checkboxes if not given
			if (empty($this->post['html_email']))
				$this->post['html_email'] = "false";

			$fields = array("html_email", "mail_delivery", "smtp_security",
				"smtp_host", "smtp_port", "smtp_user", "smtp_password");
			$this->Companies->setSettings($this->company_id, $this->post, $fields);
			
			$this->flashMessage("message", Language::_("AdminCompanyEmails.!success.mail_updated", true));
			$this->redirect($this->base_uri . "settings/company/emails/mail/");
		}
		
		$this->set("vars", $this->SettingsCollection->fetchSettings($this->Companies, $this->company_id));
		$this->set("delivery_methods", $this->getDeliveryMethods());
		$this->set("security_options", $this->getSmtpSecurityOptions());
	}
	
	/**
	 * Email Templates
	 */
	public function templates() {
		$this->uses(array("EmailGroups"));
		
		$groups = array();
		// Load core groups
		$groups['client'] = $this->EmailGroups->getAllEmails($this->company_id);
		$groups['staff'] = $this->EmailGroups->getAllEmails($this->company_id, "staff");
		
		// Load plugin groups
		$plugin_groups = array();
		$plugin_groups = $this->EmailGroups->getAllEmails($this->company_id, "client", false);
		$plugin_groups = array_merge($plugin_groups, $this->EmailGroups->getAllEmails($this->company_id, "staff", false));
		$plugin_groups = array_merge($plugin_groups, $this->EmailGroups->getAllEmails($this->company_id, "shared", false));
		
		$groups['plugins'] = $plugin_groups;
		
		// Set language for each group
		foreach ($groups as $type=>&$group_list) {
			foreach ($group_list as &$group) {
				// Set plugin-specific language
				if ($type == "plugins")
					Language::loadLang("admin_company_emails", null, PLUGINDIR . $group->plugin_dir . DS . "language" . DS);
				
				$group->group_name = Language::_("AdminCompanyEmails.templates." . $group->email_group_action . "_name", true);
				$group->group_desc = Language::_("AdminCompanyEmails.templates." . $group->email_group_action . "_desc", true);
			}
		}
		
		$this->set("groups", $groups);
	}
	
	/**
	 * Edit Email Template
	 */
	public function editTemplate() {
		$this->uses(array("EmailGroups", "Emails", "Languages"));
		
		// Set the language of the template to fetch
		$selected_language = Configure::get("Blesta.language");
		
		// Fetch a specific template, if one is given (for another language)
		if (isset($this->get[1]) && ($selected_template = $this->Emails->get($this->get[1])))
			$selected_language = $selected_template->lang;
		unset($selected_template);
		
		// Ensure a valid email group was given
		if (!isset($this->get[0]) || !($template = $this->Emails->getByGroupId($this->get[0], $selected_language)))
			$this->redirect($this->base_uri . "settings/company/emails/templates/");
		
		// Set the selected template as the vars
		$vars = $template;
		$templates = new stdClass();
		$company_id = $this->company_id;
		
		if (!empty($this->post)) {
			
			// Set empty checkboxes for this email template
			if (empty($this->post['status']))
				$this->post['status'] = "inactive";
			if (empty($this->post['include_attachments']))
				$this->post['include_attachments'] = 0;
			
			// Update Email template
			$this->post['email_group_id'] = (int)$this->get[0];
			$this->post['company_id'] = $company_id;
			
			// Remove email signature if set to none
			if (empty($this->post['email_signature_id']))
				$this->post['email_signature_id'] = null;
			
			$this->Emails->edit($template->id, $this->post);
			
			if (($errors = $this->Emails->errors())) {
				// Error
				$this->setMessage("error", $errors);
				$vars = (object)$this->post;
			}
			else {
				// Success
				$this->flashMessage("message", Language::_("AdminCompanyEmails.!success.edittemplate_updated", true));
				$this->redirect($this->base_uri . "settings/company/emails/templates/");
			}
		}
		
		// Set the template name
		if (!empty($template->plugin_dir))
			Language::loadLang("admin_company_emails", null, PLUGINDIR . $template->plugin_dir . DS . "language" . DS);
		$template_name = Language::_("AdminCompanyEmails.templates." . $template->email_group_action . "_name", true);
		
		// All email group templates
		$templates = $this->Emails->getList($company_id, (int)$this->get[0]);		
		$languages = $this->Languages->getAll($company_id);
		
		// Set template language names
		if (is_array($templates) && is_array($languages)) {
			$num_temp = count($templates);
			$num_lang = count($languages);
			
			for ($i=0; $i<$num_temp; $i++) {
				for ($j=0; $j<$num_lang; $j++) {
					if ($templates[$i]->lang == $languages[$j]->code)
						$templates[$i]->lang_name = $languages[$j]->name;
				}
			}
		}
		
		// All email signatures
		$signatures = $this->Form->collapseObjectArray($this->Emails->getSignatureList($company_id), "name", "id");

		$no_sig = array("" => Language::_("AdminCompanyEmails.edittemplate.text_none", true));
		$signatures = $no_sig + $signatures;
		
		// Include WYSIWYG
		$this->Javascript->setFile("ckeditor/ckeditor.js", "head", VENDORWEBDIR);
		$this->Javascript->setFile("ckeditor/adapters/jquery.js", "head", VENDORWEBDIR);
		
		$this->set("template_name", $template_name);
		$this->set("status", $this->Emails->getStatusTypes());
		$this->set("templates", $templates);
		$this->set("signatures", $signatures);
		$this->set("vars", $vars);
		$this->set("template", $template);
	}
	
	/**
	 * Email Signatures
	 */
	public function signatures() {
		$this->uses(array("Emails"));
		
		// Set current page of results
		$page = (isset($this->get[0]) ? (int)$this->get[0] : 1);
		$sort = (isset($this->get['sort']) ? $this->get['sort'] : "name");
		$order = (isset($this->get['order']) ? $this->get['order'] : "asc");
		
		$signatures = $this->Emails->getSignatureList($this->company_id, $page, array($sort=>$order));
		
		$this->set("signatures", $signatures);
		$this->set("sort", $sort);
		$this->set("order", $order);
		$this->set("negate_order", ($order == "asc" ? "desc" : "asc"));
		
		// Overwrite default pagination settings
		$settings = array_merge(Configure::get("Blesta.pagination"), array(
				'total_results' => $this->Emails->getSignatureListCount($this->company_id),
				'uri'=>$this->base_uri . "settings/company/emails/signatures/[p]/",
				'params'=>array('sort'=>$sort,'order'=>$order)
			)
		);
		$this->helpers(array("Pagination"=>array($this->get, $settings)));
		$this->Pagination->setSettings(Configure::get("Blesta.pagination_ajax"));
		
		// Render the request if ajax
		return $this->renderAjaxWidgetIfAsync(isset($this->get[0]) || isset($this->get['sort']));
	}
	
	/**
	 * Add Email Signature
	 */
	public function addSignature() {
		$this->uses(array("Emails"));
		
		$vars = new stdClass();
		
		if (!empty($this->post)) {
			$this->post['company_id'] = $this->company_id;
			$this->Emails->addSignature($this->post);
			
			if (($errors = $this->Emails->errors())) {
				// Error
				$this->setMessage("error", $errors);
				$vars = (object)$this->post;
			}
			else {
				// Success
				$this->flashMessage("message", Language::_("AdminCompanyEmails.!success.addsignature_created", true));
				$this->redirect($this->base_uri . "settings/company/emails/signatures/");
			}
		}
		
		// Include WYSIWYG
		$this->Javascript->setFile("ckeditor/ckeditor.js", "head", VENDORWEBDIR);
		$this->Javascript->setFile("ckeditor/adapters/jquery.js", "head", VENDORWEBDIR);
		
		$this->set("vars", $vars);
	}
	
	/**
	 * Edit Email Signature
	 */
	public function editSignature() {
		$this->uses(array("Emails"));
		
		// Redirect if signature invalid or if it does not belong to this company
		if (!isset($this->get[0]) || !($signature = $this->Emails->getSignature((int)$this->get[0])) ||
			($signature->company_id != $this->company_id))
			$this->redirect($this->base_uri . "settings/company/emails/signatures/");
		
		$vars = array();
		
		if (!empty($this->post)) {
			$this->Emails->editSignature($signature->id, $this->post);
			
			if (($errors = $this->Emails->errors())) {
				// Error
				$this->setMessage("error", $errors);
				$vars = (object)$this->post;
			}
			else {
				// Success
				$this->flashMessage("message", Language::_("AdminCompanyEmails.!success.editsignature_updated", true));
				$this->redirect($this->base_uri . "settings/company/emails/signatures/");
			}
		}
		
		// Set default signature
		if (empty($vars))
			$vars = $signature;
		
		// Include WYSIWYG
		$this->Javascript->setFile("ckeditor/ckeditor.js", "head", VENDORWEBDIR);
		$this->Javascript->setFile("ckeditor/adapters/jquery.js", "head", VENDORWEBDIR);
		
		$this->set("vars", $signature);
	}
	
	/**
	 * Delete Email Signature
	 */
	public function deleteSignature() {
		$this->uses(array("Emails"));
		
		if (!isset($this->post['id']) || !($signature = $this->Emails->getSignature((int)$this->post['id'])) || $signature->company_id != $this->company_id)
			$this->redirect($this->base_uri . "settings/company/emails/signatures/");
		
		$this->Emails->deleteSignature($signature->id);
		
		if (($errors = $this->Emails->errors())) {
			// Error
			$this->flashMessage("error", $errors);
		}
		else {
			// Success
			$this->flashMessage("message", Language::_("AdminCompanyEmails.!success.deletesignature_deleted", true));
		}
		
		$this->redirect($this->base_uri . "settings/company/emails/signatures/");
	}
	
	/**
	 * Retrieves a list of mail delivery methods
	 *
	 * @return array A list of key=>value pairs of delivery methods
	 */
	private function getDeliveryMethods() {
		return array(
			'php'=>Language::_("AdminCompanyEmails.getRequiredMethods.php", true),
			'smtp'=>Language::_("AdminCompanyEmails.getRequiredMethods.smtp", true)
		);
	}
	
	/**
	 * Retrieves a list of SMTP security options
	 *
	 * @return array A list of key=>value pairs of smtp security options
	 */
	private function getSmtpSecurityOptions() {
		return array(
			''=>Language::_("AdminCompanyEmails.getsmtpsecurityoptions.none", true),
			'ssl'=>Language::_("AdminCompanyEmails.getsmtpsecurityoptions.ssl", true),
			'tls'=>Language::_("AdminCompanyEmails.getsmtpsecurityoptions.tls", true)
		);
	}
}
?>