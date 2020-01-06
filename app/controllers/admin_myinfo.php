<?php
/**
 * Admin My Info
 * 
 * @package blesta
 * @subpackage blesta.app.controllers
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class AdminMyinfo extends AppController {
	
	public function preAction() {
		parent::preAction();
		
		// Require login
		$this->requireLogin();
		
		$this->uses(array("Staff"));
		
		Language::loadLang(array("admin_myinfo"));
	}
	
	/**
	 * Update this staff members information
	 */
	public function index() {
		$this->uses(array("Users"));

		Loader::load(VENDORDIR . "binary-to-text-php" . DS . "Base2n.php");
		$base32 = new Base2n(5, "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567", false, true, true);
		
		// Get staff and user IDs
		$user_id = $this->Session->read("blesta_id");
		$staff_id = $this->Session->read("blesta_staff_id");
		
		$vars = array();
		
		// Update the users' info
		if (!empty($this->post)) {
			
			$errors = array();
			
			// Remove new password if not given
			if (empty($this->post['new_password']))
				unset($this->post['new_password'], $this->post['confirm_password']);
			
			// Begin transaction
			$this->Users->begin();
			
			$this->Users->edit($user_id, $this->post, true);		
			$user_errors = $this->Users->errors();
			
			$this->Staff->edit($staff_id, $this->post);
			$staff_errors = $this->Staff->errors();
			
			$errors = array_merge(($user_errors ? $user_errors : array()), ($staff_errors ? $staff_errors : array()));
			
			if (!empty($errors)) {
				// Error, rollback
				$this->Users->rollBack();
				
				$this->setMessage("error", $errors);
				$vars = (object)$this->post;
			}
			else {
				// Success, commit
				$this->Users->commit();
				
				$this->flashMessage("message", Language::_("AdminMyinfo.!success.updated", true));
				$this->redirect($this->base_uri);
			}
		}
		
		// Set my info
		if (empty($vars)) {
			$user = $this->Users->get($user_id);
			$staff = $this->Staff->get($staff_id, $this->company_id);
			
			$vars = (object)array_merge((array)$user, (array)$staff);
		}
		
		// Generate random two-factor key
		if (!isset($vars->two_factor_key) || $vars->two_factor_key == "")
			$vars->two_factor_key = $this->Users->systemHash(mt_rand() . md5(mt_rand()), null, "sha1");
		
		$vars->two_factor_key_base32 = $base32->encode(pack("H*", $vars->two_factor_key));
		
		
		$this->set("two_factor_modes", $this->Users->getOtpModes());
		$this->set("vars", $vars);
		$this->set("link_tabs", $this->getTabNames());
		
		return $this->renderAjaxWidgetIfAsync();
	}
	
	/**
	 * Updates assigned BCC notices
	 */
	public function notices() {
		$staff_id = $this->Session->read("blesta_staff_id");
		$staff = $this->Staff->get($staff_id, $this->company_id);
		
		if (!empty($this->post)) {
			$notices = (!empty($this->post['notices']) ? $this->post['notices'] : array());
			$this->Staff->addNotices($staff_id, $staff->group->id, $notices);
			
			if (($errors = $this->Staff->errors())) {
				// Error, reset vars
				$this->setMessage("error", $errors);
				$vars = (object)$this->post;
			}
			else {
				// Success
				$this->flashMessage("message", Language::_("AdminMyinfo.!success.notices_updated", true));
				$this->redirect($this->base_uri . "myinfo/");
			}
		}
		
		// Set initial value of notices
		if (empty($vars)) {
			// Set notices
			$staff_notices = $this->Staff->getNotices($staff_id, $staff->group->id);
			$notices = array();
			foreach ($staff_notices as $notice)
				$notices[] = $notice->action;
			
			$vars = (object)array('notices' => $notices);
		}
		
		$this->set("vars", $vars);
		$this->set("link_tabs", $this->getTabNames());
		$this->set("bcc_notices", $this->getGroupNotices($staff->group->id, "bcc"));
		$this->set("subscription_notices", $this->getGroupNotices($staff->group->id, "to"));
		
		return $this->renderAjaxWidgetIfAsync();
	}
	
	/**
	 * Retrieves a list of staff group notices
	 * @see AdminMyinfo::notices()
	 *
	 * @param int $staff_group_id The ID of the staff group this staff member belongs to
	 * @param string $type The notice type of the email groups to fetch
	 * @return array A list of available staff group notices
	 */
	private function getGroupNotices($staff_group_id, $type) {
		$this->uses(array("StaffGroups"));
		// Get staff group notices
		$group_notices = $this->StaffGroups->getNotices($staff_group_id);
		
		if (!empty($group_notices)) {
			// Get all client email groups
			$this->uses(array("EmailGroups"));
			Language::loadLang("admin_company_emails");
			
			$email_groups = $this->EmailGroups->getAllByNoticeType($type);
			
			// Create a list of email groups by action
			$groups = array();
			foreach ($email_groups as &$email_group) {
				// Load plugin language
				if ($email_group->plugin_dir !== null)
					Language::loadLang("admin_company_emails", null, PLUGINDIR . $email_group->plugin_dir . DS . "language" . DS);
				
				$email_group->lang = Language::_("AdminCompanyEmails.templates." . $email_group->action . "_name", true);
				$email_group->lang_description = Language::_("AdminCompanyEmails.templates." . $email_group->action . "_desc", true);
				
				// Set only those notices available to this staff group
				foreach ($group_notices as $notice) {
					if ($notice->action == $email_group->action) {
						$groups[] = $email_group;
						break;
					}
				}
			}
			
			return $groups;
		}
		
		return array();
	}
	
	/**
	 * Retrieves a list of link tabs for use in templates
	 *
	 * @return array A list of tab names
	 */
	private function getTabNames() {
		return array(
			array('name'=>Language::_("AdminMyinfo.gettabnames.text_index", true), 'uri'=>"index"),
			array('name'=>Language::_("AdminMyinfo.gettabnames.text_notices", true), 'uri'=>"notices")
		);
	}
	
}
?>