<?php
/**
 * Billing Overview settings
 * 
 * @package blesta
 * @subpackage blesta.plugins.billing_overview.models
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class BillingOverviewSettings extends BillingOverviewModel {
	
	/**
	 * @var array A list of default billing overview settings
	 */
	private static $default_settings = array(
		array('key'=>"revenue_today", 'value'=>1, 'order'=>1),
		array('key'=>"revenue_month", 'value'=>1, 'order'=>2),
		array('key'=>"revenue_year", 'value'=>1, 'order'=>3),
		array('key'=>"credits_today", 'value'=>0, 'order'=>4),
		array('key'=>"credits_month", 'value'=>0, 'order'=>5),
		array('key'=>"credits_year", 'value'=>0, 'order'=>6),
		array('key'=>"invoiced_today", 'value'=>1, 'order'=>7),
		array('key'=>"invoiced_month", 'value'=>1, 'order'=>8),
		array('key'=>"balance_outstanding", 'value'=>1, 'order'=>9),
		array('key'=>"balance_overdue", 'value'=>1, 'order'=>10),
		array('key'=>"scheduled_cancelation", 'value'=>1, 'order'=>11),
		array('key'=>"services_active", 'value'=>1, 'order'=>12),
		array('key'=>"services_added_today", 'value'=>1, 'order'=>13),
		array('key'=>"services_canceled_today", 'value'=>1, 'order'=>14),
		array('key'=>"graph_revenue", 'value'=>1, 'order'=>15),
		array('key'=>"graph_revenue_year", 'value'=>1, 'order'=>16),
		array('key'=>"graph_invoiced", 'value'=>1, 'order'=>17),
		array('key'=>"show_legend", 'value'=>1, 'order'=>18),
		array('key'=>"date_range", 'value'=>7, 'order'=>19)
	);
	
	/**
	 * Initialize
	 */
	public function __construct() {
		parent::__construct();
		Language::loadLang("billing_overview_settings", null, PLUGINDIR . "billing_overview" . DS . "language" . DS);
	}
	
	/**
	 * Adds new staff settings for the Billing Overview settings
	 *
	 * @param int $staff_id The ID of the staff member whose settings to update
	 * @param int $company_id The ID of the company to which this staff member belongs
	 * @param array $vars A numerically-indexed list of overview setting key/value pairs
	 */
	public function add($staff_id, $company_id, array $vars) {
		$rules = array(
			'staff_id'=>array(
				'exists'=>array(
					'rule'=>array(array($this, "validateExists"), "id", "staff"),
					'message'=>$this->_("BillingOverviewSettings.!error.staff_id.exists")
				)
			),
			'company_id'=>array(
				'exists'=>array(
					'rule'=>array(array($this, "validateExists"), "id", "companies"),
					'message'=>$this->_("BillingOverviewSettings.!error.company_id.exists")
				)
			),
			'settings[][key]'=>array(
				'empty'=>array(
					'rule'=>"isEmpty",
					'negate'=>true,
					'message'=>$this->_("BillingOverviewSettings.!error.settings[][key].empty")
				)
			),
			'settings[][value]'=>array(
				'length'=>array(
					'rule'=>array("maxLength", 255),
					'message'=>$this->_("BillingOverviewSettings.!error.settings[][value].length")
				)
			)
		);
		
		$input = array(
			'staff_id'=>$staff_id,
			'company_id'=>$company_id,
			'settings'=>$vars
		);
		
		$this->Input->setRules($rules);
		
		if ($this->Input->validates($input)) {
			// Save each setting
			foreach ($input['settings'] as $setting) {
				$value = isset($setting['value']) ? $setting['value'] : "";
				$order = isset($setting['order']) ? $setting['order'] : null;
				
				// Set input settings
				$settings = array('staff_id'=>$staff_id, 'company_id'=>$company_id, 'key'=>$setting['key'], 'value'=>$value);
				
				// Set order if given
				if ($order != null)
					$settings['order'] = $order;
				
				$this->Record->duplicate("value", "=", $value)->
					insert("billing_overview_settings", $settings);
			}
		}
	}
	
	/**
	 * Saves the default settings for the given staff member
	 *
	 * @param int $staff_id The ID of the staff member whose settings to update
	 * @param int $company_id The ID of the company to which this staff member belongs
	 */
	public function addDefault($staff_id, $company_id) {
		$this->add($staff_id, $company_id, self::$default_settings);
	}
	
	/**
	 * Retrieves a list of all billing overview settings for a given staff member
	 *
	 * @param int $staff_id The staff ID of the staff member to get settings for
	 * @param int $company_id The company ID to which the staff member belongs
	 * @return array A list of billing overview settings for the given staff member
	 */
	public function getSettings($staff_id, $company_id, $order_by=array('order'=>"asc")) {
		return $this->Record->select()->from("billing_overview_settings")->
			where("staff_id", "=", $staff_id)->where("company_id", "=", $company_id)->
			order($order_by)->
			fetchAll();
	}
}
?>