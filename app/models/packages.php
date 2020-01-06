<?php
/**
 * Package management
 * 
 * @package blesta
 * @subpackage blesta.app.models
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class Packages extends AppModel {
	
	/**
	 * Initialize Packages
	 */
	public function __construct() {
		parent::__construct();
		Language::loadLang(array("packages"));
	}
	
	/**
	 * Adds a new package to the system
	 *
	 * @param array $vars An array of package information including:
	 * 	- module_id The ID of the module this package belongs to (optional, default NULL)
	 * 	- name The name of the package
	 * 	- description The description of the package (optional, default NULL)
	 * 	- description_html The HTML description of the package (optional, default NULL)
	 * 	- qty The maximum quantity available in this package, if any (optional, default NULL)
	 * 	- module_row The module row this package belongs to (optional, default 0)
	 * 	- module_group The module group this package belongs to (optional, default NULL)
	 * 	- taxable Whether or not this package is taxable (optional, default 0)
	 * 	- single_term Whether or not services derived from this package should be canceled at the end of term (optional, default 0)
	 * 	- status The status of this package, 'active', 'inactive', 'restricted' (optional, default 'active')
	 * 	- company_id The ID of the company this package belongs to
	 * 	- prorata_day The prorated day of the month (optional, default NULL)
	 * 	- prorata_cutoff The day of the month pro rata should cut off (optional, default NULL)
	 * 	- email_content A numerically indexed array of email content including:
	 * 		- lang The language of the email content
	 * 		- html The html content for the email (optional)
	 * 		- text The text content for the email, will be created automatically from html if not given (optional)
	 * 	- pricing A numerically indexed array of pricing info including:
	 * 		- term The term as an integer 1-65535 (period should be given if this is set; optional, default 1)
	 * 		- period The period, 'day', 'week', 'month', 'year', 'onetime' (optional, default 'month')
	 * 		- price The price of this term (optional, default 0.00)
	 * 		- setup_fee The setup fee for this package (optional, default 0.00)
	 * 		- cancel_fee The cancelation fee for this package (optional, default 0.00)
	 * 		- currency The ISO 4217 currency code for this pricing
	 * 	- groups A numerically indexed array of package group assignments (optional)
	 * 	- option_groups A numerically indexed array of package option group assignments (optional)
	 * 	- * A set of miscellaneous fields to pass, in addition to the above fields, to the module when adding the package (optional)
	 * @return int The package ID created, void on error
	 */
	public function add(array $vars) {
		
		if (isset($vars['module_group']) && $vars['module_group'] != "")
			$vars['module_row'] = 0;
		if (!isset($vars['company_id']))
			$vars['company_id'] = Configure::get("Blesta.company_id");
		
		// Attempt to validate $vars with the module, set any meta fields returned by the module
		if (isset($vars['module_id']) && $vars['module_id'] != "") {
			
			if (!isset($this->ModuleManager))
				Loader::loadModels($this, array("ModuleManager"));
				
			$module = $this->ModuleManager->initModule($vars['module_id']);
			
			if ($module) {
				$vars['meta'] = $module->addPackage($vars);
				
				// If any errors encountered through the module, set errors and return
				if (($errors = $module->errors())) {
					$this->Input->setErrors($errors);
					return;
				}
			}
		}
		
		$rules = $this->getRules($vars);
		
		// The required currency rule can be optional iff no pricing is given
		if (!array_key_exists("pricing", $vars))
			$rules['pricing[][currency]']['format']['if_set'] = true;
		
		$this->Input->setRules($rules);
		
		if ($this->Input->validates($vars)) {
			// Fetch company settings on clients
			Loader::loadComponents($this, array("SettingsCollection"));
			$company_settings = $this->SettingsCollection->fetchSettings(null, $vars['company_id']);
			
			// Creates subquery to calculate the next package ID value on the fly
			$sub_query = new Record();
			/*
			$values = array($company_settings['packages_start'], $company_settings['packages_increment'],
				$company_settings['packages_start'], $company_settings['packages_increment'],
				$company_settings['packages_start'], $company_settings['packages_pad_size'],
				$company_settings['packages_pad_str']);
			*/
			$values = array($company_settings['packages_start'], $company_settings['packages_increment'],
				$company_settings['packages_start']);
			
			/*
			$sub_query->select(array("LPAD(IFNULL(GREATEST(MAX(t1.id_value),?)+?,?), " .
				"GREATEST(CHAR_LENGTH(IFNULL(MAX(t1.id_value)+?,?)),?),?)"), false)->
			*/
			$sub_query->select(array("IFNULL(GREATEST(MAX(t1.id_value),?)+?,?)"), false)->
				appendValues($values)->
				from(array("packages"=>"t1"))->
				where("t1.company_id", "=", $vars['company_id'])->
				where("t1.id_format", "=", $company_settings['packages_format']);
			// run get on the query so $sub_query->values are built
			$sub_query->get();
			
			$vars['id_format'] = $company_settings['packages_format'];
			// id_value will be calculated on the fly using a subquery
			$vars['id_value'] = $sub_query;
			
			// Assign subquery values to this record component
			$this->Record->appendValues($sub_query->values);
			// Ensure the subquery value is set first because its the first value
			$vars = array_merge(array('id_value'=>null), $vars);
			
			// Add package
			$fields = array("id_format", "id_value", "module_id", "name", "description", "description_html",
				"qty", "module_row", "module_group", "taxable", "single_term", "status", "company_id",
				"prorata_day", "prorata_cutoff"
			);
			$this->Record->insert("packages", $vars, $fields);
			
			$package_id = $this->Record->lastInsertId();
			
			// Add package email contents
			if (!empty($vars['email_content']) && is_array($vars['email_content'])) {
				for ($i=0; $i<count($vars['email_content']); $i++) {
					$vars['email_content'][$i]['package_id'] = $package_id;
					$fields = array("package_id", "lang", "html", "text");
					$this->Record->insert("package_emails", $vars['email_content'][$i], $fields);
				}
			}
			
			// Add package pricing			
			if (!empty($vars['pricing']) && is_array($vars['pricing'])) {
				
				for ($i=0; $i<count($vars['pricing']); $i++) {
					$vars['pricing'][$i]['package_id'] = $package_id;
					
					// Default one-time package to a term of 0 (never renews)
					if (isset($vars['pricing'][$i]['period']) && $vars['pricing'][$i]['period'] == "onetime")
						$vars['pricing'][$i]['term'] = 0;
						
					$vars['pricing'][$i]['company_id'] = $vars['company_id'];
					$this->addPackagePricing($package_id, $vars['pricing'][$i]);
				}
			}
			
			// Add package meta data
			if (isset($vars['meta']) && !empty($vars['meta']) && is_array($vars['meta']))
				$this->setMeta($package_id, $vars['meta']);
			
			// Set package option groups, if given
			$this->removeOptionGroups($package_id);
			if (!empty($vars['option_groups']))
				$this->setOptionGroups($package_id, $vars['option_groups']);
			
			// Add all package groups given
			if (isset($vars['groups']))
				$this->setGroups($package_id, $vars['groups']);
			
			return $package_id;
		}
		
		// Set any email content parse error
		$this->setParseError();
	}
	
	/**
	 * Update an existing package ID with the data given
	 *
	 * @param int $package_id The ID of the package to update
	 * @param array $vars An array of package information including:
	 * 	- module_id The ID of the module this package belongs to (optional, default NULL)
	 * 	- name The name of the package
	 * 	- description The description of the package (optional, default NULL)
	 * 	- description_html The HTML description of the package (optional, default NULL)
	 * 	- qty The maximum quantity available in this package, if any (optional, default NULL)
	 * 	- module_row The module row this package belongs to (optional, default 0)
	 * 	- module_group The module group this package belongs to (optional, default NULL)
	 * 	- taxable Whether or not this package is taxable (optional, default 0)
	 * 	- single_term Whether or not services derived from this package should be canceled at the end of term (optional, default 0)
	 * 	- status The status of this package, 'active', 'inactive', 'restricted' (optional, default 'active')
	 * 	- company_id The ID of the company this package belongs to (optional)
	 * 	- prorata_day The prorated day of the month (optional)
	 * 	- prorata_cutoff The day of the month pro rata should cut off (optional)
	 * 	- email_content A numerically indexed array of email content including:
	 * 		- lang The language of the email content
	 * 		- html The html content for the email (optional)
	 * 		- text The text content for the email, will be created automatically from html if not given (optional)
	 * 	- pricing A numerically indexed array of pricing info including (required):
	 * 		- id The pricing ID (optional, required if an edit else will add as new)
	 * 		- term The term as an integer 1-65535 (period should be given if this is set; optional, default 1), if term is empty will remove this pricing option
	 * 		- period The period, 'day', 'week', 'month', 'year', 'onetime' (optional, default 'month')
	 * 		- price The price of this term (optional, default 0.00)
	 * 		- setup_fee The setup fee for this package (optional, default 0.00)
	 * 		- cancel_fee The cancelation fee for this package (optional, default 0.00)
	 * 		- currency The ISO 4217 currency code for this pricing
	 * 	- groups A numerically indexed array of package group assignments (optional), if given will replace all package group assignments with those given
	 * 	- option_groups A numerically indexed array of package option group assignments (optional)
	 * 	- * A set of miscellaneous fields to pass, in addition to the above fields, to the module when adding the package (optional)
	 */
	public function edit($package_id, array $vars) {
		
		if (isset($vars['module_group']) && $vars['module_group'] != "")
			$vars['module_row'] = 0;
		
		$package = $this->get($package_id);
		
		// Attempt to validate $vars with the module, set any meta fields returned by the module
		if (isset($vars['module_id']) && $vars['module_id'] != "") {
			
			if (!isset($this->ModuleManager))
				Loader::loadModels($this, array("ModuleManager"));
				
			$module = $this->ModuleManager->initModule($vars['module_id']);
			
			if ($module) {
				$vars['meta'] = $module->editPackage($package, $vars);
				
				// If any errors encountered through the module, set errors and return
				if (($errors = $module->errors())) {
					$this->Input->setErrors($errors);
					return;
				}
			}
		}
		
		// Set company ID if not given, it's necessary to have this in order to validate package groups
		if (!isset($vars['company_id']))
			$vars['company_id'] = $package->company_id;
		
		$rules = $this->getRules($vars);
		
		$rules['pricing[][id]']['format'] = array(
			'if_set' => true,
			'rule' => array(array($this, "validateExists"), "id", "package_pricing"),
			'message' => $this->_("Packages.!error.pricing[][id].format")
		);
		
		$this->Input->setRules($rules);
		
		if ($this->Input->validates($vars)) {
			// Update packages
			$fields = array("module_id", "name", "description", "description_html",
				"qty", "module_row", "module_group", "taxable", "single_term", "status", "company_id",
				"prorata_day", "prorata_cutoff"
			);
			$this->Record->where("id", "=", $package_id)->update("packages", $vars, $fields);
			
			// Update package email
			if (!empty($vars['email_content']) && is_array($vars['email_content'])) {
				for ($i=0; $i<count($vars['email_content']); $i++) {
					$fields = array("package_id", "lang", "html", "text");
					$vars['email_content'][$i]['package_id'] = $package_id;
					
					$this->Record->duplicate("html", "=", isset($vars['email_content'][$i]['html']) ? $vars['email_content'][$i]['html'] : null)->
						duplicate("text", "=", isset($vars['email_content'][$i]['text']) ? $vars['email_content'][$i]['text'] : null)->
						insert("package_emails", $vars['email_content'][$i], $fields);
				}
			}
			
			// Insert/update package prices
			for ($i=0; $i<count($vars['pricing']); $i++) {
				// Default one-time package to a term of 0 (never renews)
				if (isset($vars['pricing'][$i]['period']) && $vars['pricing'][$i]['period'] == "onetime")
					$vars['pricing'][$i]['term'] = 0;
				
				$vars['pricing'][$i]['company_id'] = $vars['company_id']
				;
				if (!empty($vars['pricing'][$i]['id'])) {
					
					$this->editPackagePricing($vars['pricing'][$i]['id'], $vars['pricing'][$i]);
				}
				else {
					$this->addPackagePricing($package_id, $vars['pricing'][$i]);
				}
			}
			
			// Update package meta data
			$this->setMeta($package_id, $vars['meta']);
			
			// Set package option groups, if given
			$this->removeOptionGroups($package_id);
			if (!empty($vars['option_groups']))
				$this->setOptionGroups($package_id, $vars['option_groups']);
			
			// Replace all group assignments with those that are given (if any given)
			if (isset($vars['groups']))
				$this->setGroups($package_id, $vars['groups']);
		}
		
		// Set any email content parse error
		$this->setParseError();
	}
	
	/**
	 * Permanently removes the given package from the system. Packages can only
	 * be deleted if no services exist for that package.
	 *
	 * @param int $package_id The package ID to delete
	 */
	public function delete($package_id) {

		$vars = array('package_id' => $package_id);
		
		$rules = array(
			'package_id' => array(
				'exists' => array(
					'rule' => array(array($this, "validateServiceExists")),
					'negate' => true,
					'message' => $this->_("Packages.!error.package_id.exists")
				)
			)
		);
		
		$this->Input->setRules($rules);
		
		if ($this->Input->validates($vars)) {
			// No services exist for this package, so it's safe to delete it
			$this->Record->from("packages")->
				leftJoin("package_emails", "package_emails.package_id", "=", "packages.id", false)->
				leftJoin("package_meta", "package_meta.package_id", "=", "packages.id", false)->
				leftJoin("package_pricing", "package_pricing.package_id", "=", "packages.id", false)->
				leftJoin("pricings", "pricings.id", "=", "package_pricing.pricing_id", false)->
				leftJoin("package_group", "package_group.package_id", "=", "packages.id", false)->
				leftJoin("package_option", "package_option.package_id", "=", "packages.id", false)->
				where("packages.id", "=", $package_id)->
				delete(array("packages.*", "package_emails.*", "package_meta.*", "package_pricing.*", "pricings.*", "package_group.*", "package_option.*"));
		}
	}
	
	/**
	 * Save the packages for the given group in the provided order
	 *
	 * @param int $package_group_id The ID of the package group to order packages for
	 * @param array $package_ids A numerically indexed array of package IDs
	 */
	public function orderPackages($package_group_id, array $package_ids) {
		for ($i=0; $i<count($package_ids); $i++) {
			$this->Record->where("package_id", "=", $package_ids[$i])->
				where("package_group_id", "=", $package_group_id)->
				update("package_group", array('order' => $i));
		}
	}
	
	/**
	 * Fetches the given package
	 *
	 * @param int $package_id The package ID to fetch
	 * @return mixed A stdClass object representing the package, false if no such package exists
	 */
	public function get($package_id) {
		$fields = array(
			"packages.*",
			"REPLACE(packages.id_format, ?, packages.id_value)" => "id_code",
		);
		
		$package = $this->Record->select($fields)->
			appendValues(array($this->replacement_keys['packages']['ID_VALUE_TAG']))->
			from("packages")->
			where("id", "=", $package_id)->fetch();
		if ($package) {
			$package->email_content = $this->getPackageEmails($package->id);
			$package->pricing = $this->getPackagePricing($package->id);
			$package->meta = $this->getPackageMeta($package->id);
			$package->groups = $this->getPackageGroups($package->id);
			$package->option_groups = $this->getPackageOptionGroups($package->id);
		}
		
		return $package;
	}
	
	/**
	 * Fetches the given package by package pricing ID
	 *
	 * @param int $package_pricing_id The package pricing ID to use to fetch the package
	 * @return mixed A stdClass object representing the package, false if no such package exists
	 */
	public function getByPricingId($package_pricing_id) {
		$fields = array(
			"packages.*",
			"REPLACE(packages.id_format, ?, packages.id_value)" => "id_code",
		);
		
		$package = $this->Record->select($fields)->
			appendValues(array($this->replacement_keys['packages']['ID_VALUE_TAG']))->
			from("packages")->innerJoin("package_pricing", "package_pricing.package_id", "=", "packages.id", false)->
			where("package_pricing.id", "=", $package_pricing_id)->fetch();
		if ($package) {
			$package->email_content = $this->getPackageEmails($package->id);
			$package->pricing = $this->getPackagePricing($package->id);
			$package->meta = $this->getPackageMeta($package->id);
			$package->groups = $this->getPackageGroups($package->id);
			$package->option_groups = $this->getPackageOptionGroups($package->id);
		}
		
		return $package;
	}
	
	/**
	 * Fetch all packages belonging to the given company
	 *
	 * @param int $company_id The ID of the company to fetch pages for
	 * @param array $order The sort order in key = value order, where 'key' is the field to sort on and 'value' is the order to sort (asc or desc)
	 * @param string $status The status type of packages to retrieve ('active', 'inactive', 'restricted', default null for all)
	 * @param string $type The type of packages to retrieve ('standard', 'addon', default null for all)
	 * @return array An array of stdClass objects each representing a package
	 */
	public function getAll($company_id, array $order=array('name'=>"ASC"), $status=null, $type=null) {
		// If sorting by ID code, use id code sort mode
		if (isset($order_by['id_code']) && Configure::get("Blesta.id_code_sort_mode")) {
			$temp = $order_by['id_code'];
			unset($order_by['id_code']);
			
			foreach ((array)Configure::get("Blesta.id_code_sort_mode") as $key) {
				$order_by[$key] = $temp;
			}
		}
		
		$this->Record = $this->getPackages($status);
		
		if ($type) {
			$this->Record->innerJoin("package_group", "package_group.package_id", "=", "packages.id", false)->
				innerJoin("package_groups", "package_groups.id", "=", "package_group.package_group_id", false)->
				where("package_groups.type", "=", $type)->
				order(array('package_group.order' => "ASC"))->
				group(array("packages.id"));
		}
		
		return $this->Record->order($order)->where("packages.company_id", "=", $company_id)->fetchAll();
	}
	
	/**
	 * Fetches a list of all packages
	 *
	 * @param int $page The page to return results for (optional, default 1)
	 * @param array $order_by The sort and order conditions (e.g. array('sort_field'=>"ASC"), optional)
	 * @param string $status The status type of packages to retrieve ('active', 'inactive', 'restricted', default null for all)
	 * @return array An array of stdClass objects each representing a package
	 */
	public function getList($page=1, array $order_by=array('id_code'=>"asc"), $status=null) {
		// If sorting by ID code, use id code sort mode
		if (isset($order_by['id_code']) && Configure::get("Blesta.id_code_sort_mode")) {
			$temp = $order_by['id_code'];
			unset($order_by['id_code']);
			
			foreach ((array)Configure::get("Blesta.id_code_sort_mode") as $key) {
				$order_by[$key] = $temp;
			}
		}
		
		$this->Record = $this->getPackages($status);
		
		return $this->Record->where("packages.company_id", "=", Configure::get("Blesta.company_id"))->
			order($order_by)->
			limit($this->getPerPage(), (max(1, $page) - 1)*$this->getPerPage())->fetchAll();
	}
	
	/**
	 * Search packages
	 *
	 * @param string $query The value to search packages for
	 * @param int $page The page number of results to fetch (optional, default 1)
	 * @return array An array of packages that match the search criteria
	 */
	public function search($query, $page=1) {
		$this->Record = $this->searchPackages($query);
		
		// Set order by clause
		$order_by = array();
		if (Configure::get("Blesta.id_code_sort_mode")) {
			foreach ((array)Configure::get("Blesta.id_code_sort_mode") as $key) {
				$order_by[$key] = "ASC";
			}
		}
		else
			$order_by = array("name"=>"DESC");
			
		return $this->Record->order($order_by)->
			limit($this->getPerPage(), (max(1, $page) - 1)*$this->getPerPage())->fetchAll();
	}
	
	/**
	 * Return the total number of packages returned from Packages::search(), useful
	 * in constructing pagination
	 *
	 * @param string $query The value to search services for
	 * @see Packages::search()
	 */
	public function getSearchCount($query) {
		$this->Record = $this->searchPackages($query);
		return $this->Record->numResults();
	}
	
	/**
	 * Partially constructs the query for searching packages
	 *
	 * @param string $query The value to search packages for
	 * @return Record The partially constructed query Record object
	 * @see Packages::search(), Packages::getSearchCount()
	 */
	private function searchPackages($query) {
		$this->Record = $this->getPackages();
		$this->Record->where("packages.company_id", "=", Configure::get("Blesta.company_id"));
		
		$sub_query_sql = $this->Record->get();
		$values = $this->Record->values;
		$this->Record->reset();
		
		$this->Record = $this->Record->select()->appendValues($values)->from(array($sub_query_sql => "temp"))->
			like("CONVERT(temp.id_code USING utf8)", "%" . $query . "%", true, false)->
			orLike("temp.name", "%" . $query . "%")->
			orLike("temp.module_name", "%" . $query . "%");
		return $this->Record;
	}
	
	/**
	 * Retrieves a list of package pricing periods
	 *
	 * @param boolean $plural True to return language for plural periods, false for singular
	 * @return array Key=>value pairs of package pricing periods
	 */
	public function getPricingPeriods($plural=false) {
		if (!isset($this->Pricings))
			Loader::loadModels($this, array("Pricings"));
		return $this->Pricings->getPeriods($plural);
	}
	
	/**
	 * Retrieves a list of package status types
	 *
	 * @return array Key=>value pairs of package status types
	 */
	public function getStatusTypes() {
		return array(
			"active"=>$this->_("Packages.getStatusTypes.active"),
			"inactive"=>$this->_("Packages.getStatusTypes.inactive"),
			"restricted"=>$this->_("Packages.getStatusTypes.restricted")
		);
	}
    
	/**
	 * Retrieves a list of acceptable pro rata day options
	 *
	 * @return array A set of key=>value pairs of pro rata days
	 */
	public function getProrataDays() {
		$range = range(1, 28, 1);
		return array_combine($range, $range);
	}
	
	/**
	 * Determines whether the pro rata cutoff day has passed
	 *
	 * @param string $date The date to check
	 * @param int $cutoff_day The day of the month representing the cutoff day
	 * @return boolean True if the given date is after the pro rata cutoff day, or false otherwise
	 */
	public function isDateAfterProrataCutoff($date, $cutoff_day) {
		// Set local date
		$local_date = clone $this->Date;
		$local_date->setTimezone("UTC", Configure::get("Blesta.company_timezone"));
		
		if (!$this->Input->isDate($date) || !$cutoff_day)
			return false;
		return ((int)$local_date->cast($date, "j") > $cutoff_day);
	}
	
	/**
	 * Retrieves the date to prorate a service to
	 *
	 * @param string $start_date The date to start from
	 * @param string $period The period associated with the service
	 * @param int $pro_rata_day The day of the month to prorate to
	 * @return mixed The prorated UTC end date; null if not prorating; or boolean false if it cannot be determined
	 */
	public function getProrateDate($start_date, $period, $pro_rata_day) {
		// Convert date to UTC
		$start_date = $this->dateToUtc($start_date, "c");
		
		// Not prorating if today is the pro rata day
		if ($this->isProrataDay($start_date, $pro_rata_day))
			return null;
		
		try {
			// Determine the prorate date
			$Proration = new Proration($start_date, $pro_rata_day, null, $period);
			$Proration->setTimezone(Configure::get("Blesta.company_timezone"));
			$date = $Proration->prorateDate();
		}
		catch (Exception $e) {
			return false;
		}
		
		// Unable to determine whether proration may occur if there is no date or prorate is false
		if (!$date)
			return false;
		
		// Set the prorate date in UTC
		return $this->dateToUtc($date, "c");
	}
	
	/**
	 * Determines whether the given start date is on the pro rata day
	 *
	 * @param string $start_date The start date
	 * @param int $pro_rata_day The pro rata day of the month
	 * @return boolean True if the given start date is the current pro rata day for the company's timezone, or false otherwise
	 */
	public function isProrataDay($start_date, $pro_rata_day) {
		// Set the Date to the current timezone
		$Date = clone $this->Date;
		$Date->setTimezone("UTC", Configure::get("Blesta.company_timezone"));
		$start_date = $Date->cast($start_date, "c");
		$day = $Date->cast($start_date, "j");
		
		return ($day == $pro_rata_day);
	}
	
	/**
	 * Retrieves the number of days to prorate a service
	 *
	 * @param string $start_date The date to start from
	 * @param string $period The period associated with the service
	 * @param int $pro_rata_day The day of the month to prorate to
	 * @return mixed The number of days to prorate, or boolean false if it cannot be determined
	 */
	public function getDaysToProrate($start_date, $period, $pro_rata_day) {
		// Convert date to UTC
		$start_date = $this->dateToUtc($start_date, "c");
		
		try {
			// Determine the prorate date
			$Proration = new Proration($start_date, $pro_rata_day, null, $period);
			$Proration->setTimezone(Configure::get("Blesta.company_timezone"));
			$prorate_date = $Proration->prorateDate();
			$prorate_days = $Proration->prorateDays();
		}
		catch (Exception $e) {
			return false;
		}
		
		// Return the number of days to prorate, or false if there is no prorate date (because it could not be determined)
		return ($prorate_date ? $prorate_days : false);
	}
	
	/**
	 * Retrieves the number of days between the two given dates
	 *
	 * @deprecated since 3.5.0
	 *
	 * @param string $start_date The start date
	 * @param string $end_date The end date
	 * @return int The number of days between the dates
	 */
	public function getDaysToProrateBetween($start_date, $end_date) {
		// Convert dates to UTC
		$start_date = $this->dateToUtc($start_date, "c");
		$end_date = $this->dateToUtc($end_date, "c");
		
		$second_per_day = 86400;
        return (int) round(abs(strtotime($start_date) - strtotime($end_date)) / $second_per_day);
	}
	
	/**
	 * Retrieves the amount to prorate a value given the number of days to prorate it
	 * 
	 * @deprecated since 3.5.0
	 * @see Packages::getProratePrice
	 *
	 * @param float $amount The total cost for the given term and period
	 * @param int $prorate_days The number of days to prorate the amount for
	 * @param int $term The term length
	 * @param string $period The period type (one of "month", "year", unless $allow_all_periods is true)
	 * @param boolean $allow_all_recurring_periods True to allow all recurring periods, or false to limit to "month" and "year" only (optional, default false)
	 * @return float The prorate amount
	 */
	public function getProrateAmount($amount, $prorate_days, $term, $period, $allow_all_recurring_periods=false) {
		if (!is_numeric($amount) || !is_numeric($prorate_days) || !is_numeric($term) ||
			(!$allow_all_recurring_periods && !in_array($period, array("month", "year"))) ||
			!in_array($period, array_keys($this->getPricingPeriods())) || $period == "onetime")
			return $amount;
		
		// Determine the number of days that span the term period
		$now = date("c");
		$term_days = (int)abs((strtotime($now . " +" . abs((int)$term) . " " . $period) - strtotime($now))/86400);
		
		// Calculate the prorate amount
		$amount = ($term_days == 0 ? $amount : $amount*abs((int)$prorate_days)/$term_days);
		
		return $amount;
	}
	
	/**
	 * Retrieves the price to prorate a value given the date, term, and period to prorate from
	 *
	 * @param float $amount The total cost for the given term and period
	 * @param string $start_date The start date to calculate the price from
	 * @param int $term The term length
	 * @param string $period The period type (e.g. "month", "year")
	 * @param int $pro_rata_day The day of the month to prorate to
	 * @param boolean $allow_all_recurring_periods True to allow all recurring periods, or false to limit to "month" and "year" only (optional, default false)
	 * @param string $prorate_date The date to prorate to. Setting this value will ignore the $pro_rata_day (optional)
	 * @return float The prorate price
	 */
	public function getProratePrice($amount, $start_date, $term, $period, $pro_rata_day, $allow_all_recurring_periods=false, $prorate_date = null) {
		// Return the given amount if invalid proration values were given
		if ((!is_numeric($pro_rata_day) && empty($prorate_date)) || $period == "onetime" || (!$allow_all_recurring_periods && !in_array($period, array("month", "year"))))
			return $amount;
		
		// Convert date to UTC
		$start_date = $this->dateToUtc($start_date, "c");
		
		try {
			// Determine the prorate price
			$Proration = new Proration($start_date, $pro_rata_day, $term, $period);
			$Proration->setTimezone(Configure::get("Blesta.company_timezone"));
			
			// Set the prorate date if given
			if ($prorate_date) {
				$Proration->setProrateDate($this->dateToUtc($prorate_date, "c"));
			}
			
			// Set periods available for proration
			if ($allow_all_recurring_periods) {
				$periods = array(Proration::PERIOD_DAY, Proration::PERIOD_WEEK, Proration::PERIOD_MONTH, Proration::PERIOD_YEAR);
				$Proration->setProratablePeriods($periods);
			}
			
			$price = $Proration->proratePrice($amount);
		}
		catch (Exception $e) {
			$price = 0.0;
		}
		
		return $price;
	}
	
	/**
	 * Fetches package IDs from package pricings, and includes the price for each package pricings (along with proration)
	 * @see Packages::calcLineTotals()
	 *
	 * @param array $package_pricings A reference to a numerically-indexed array of package pricing and quantity values of the form:
	 *  - pricing_id The package pricing ID
	 * 	- qty The qty being purchased for the package pricing ID
	 * 	- fees A numerical array of fee types to include in the pricing calculations, including:
	 * 		- setup
	 * 		- cancel
	 * 	- configoptions An array of key/value pairs where each key is the package option ID and each value is the package option value
	 * @return array A numerically-indexed array of package IDs that have prices. Each $package_pricings element will also contain a price attribute if a price exists
	 */
	private function getPackageIdsFromPricing(array &$package_pricings) {
		if (!isset($this->Invoices))
			Loader::loadModels($this, array("Invoices"));
		if (!isset($this->Services))
			Loader::loadModels($this, array("Services"));
		
		// Fetch pricing for each package price
		$package_ids = array();
		$new_package_pricings = array();
		$now = date("c");
		
		foreach ($package_pricings as $price_index => $pricing) {
			unset($pricing['prorate'], $pricing['start_date'], $pricing['end_date']);
			$pricing['qty'] = (int)$pricing['qty'];
			
			// Skip calculations on any lines that are blank
			if ($pricing['qty'] <= 0)
				continue;
			
			$package_price = $this->getAPackagePricing($pricing['pricing_id']);
			$package = $this->getByPricingId($pricing['pricing_id']);
			
			// Skip if pricing doesn't exist
			if (!$package || !$package_price)
				continue;
			
			$package_ids[] = $package_price->package_id;
			$pricing['price'] = $package_price;
			$pricing['start_date'] = $this->dateToUtc($now);
			$pricing['end_date'] = $this->Services->getNextRenewDate($pricing['start_date'] . "Z", $package_price->term, $package_price->period, "Y-m-d H:i:s", $package->prorata_day);
			$pricing['index'] = $price_index;
			
			// Determine whether proration will be incurred, and add/update the pricing for it
			if ($package->prorata_day && (($prorate_days = $this->getDaysToProrate($now, $package_price->period, $package->prorata_day)) !== false)) {
				$current_pricing = clone $package_price;
				
				// Determine the prorated amount and update it, otherwise use the base price
				// The amount is prorated if the prorate day is not 0, or it is 0 and today is not the pro rata day
				$prorate = false;
				if ($prorate_days != 0 || !$this->isProrataDay($pricing['start_date'], $package->prorata_day)) {
					$prorate = true;
					$pricing['price']->price = $this->getProratePrice($package_price->price, $now, $package_price->term, $package_price->period, $package->prorata_day);
				}
				
				// Check whether the cutoff day has passed, and set a new package pricing field to cover it in whole
				$cutoff = false;
				if ($this->isDateAfterProrataCutoff($now, $package->prorata_cutoff)) {
					$cutoff = true;
					$new_pricing = $pricing;
					$new_pricing['price'] = $current_pricing;
					
					// Remove any setup fee, it would have been set on the prorated pricing
					if (isset($new_pricing['fees'])) {
						foreach ($new_pricing['fees'] as $index => $type) {
							if ($type == "setup") {
								unset($new_pricing['fees'][$index]);
								break;
							}
						}
						$new_pricing['fees'] = array_values($new_pricing['fees']);
					}
					
					// Set the start and end dates
					$new_pricing['start_date'] = $pricing['end_date'];
					$new_pricing['end_date'] = $this->Services->getNextRenewDate($new_pricing['start_date'] . "Z", $package_price->term, $package_price->period);
					$new_pricing['index'] = $price_index;
				}
				
				// Set prorate info for this pricing item
				$pricing['prorate'] = array(
					'pro_rata_day' => $package->prorata_day,
					'from_date' => $now,
					'days' => $prorate_days,
					'cutoff' => $cutoff,
					'is_prorated' => $prorate
				);
			}
			
			// Save the pricing
			$new_package_pricings[] = $pricing;
			// And save any new pricing, as a result of it being passed the cutoff day
			if (isset($new_pricing))
				$new_package_pricings[] = $new_pricing;
			unset($new_pricing);
		}
		unset($pricing);
		
		$package_pricings = $new_package_pricings;
		
		return $package_ids;
	}
	
	/**
	 * Retrieves the total discount amount for an exclusive coupon
	 * @see Packages::calcLineTotals()
	 *
	 * @param stdClass $coupon A stdClass object representing an exclusive coupon
	 * @param stdClass $package_price An stdClass object representing a package price
	 * @param int $quantity The pricing quantity
	 * @param string $currency The ISO 4217 currency code to calculate totals in
	 * @param stdClass $config_price An stdClass object representing all configurable option pricing (optional)
	 * @return float The total discount amount
	 */
	private function getExclusiveCouponDiscount($coupon, $package_price, $quantity, $currency, $config_price = null) {
		$discount = 0;
		
		// Calculate discount (if exclusive)
		if ($coupon && $coupon->type == "exclusive") {
			$coupon_allowed = false;
			foreach ($coupon->packages as $discount_pack) {
				if ($discount_pack->package_id == $package_price->package_id) {
					$coupon_allowed = true;
					break;
				}
			}
			
			if ($coupon_allowed) {
				$package_cost = ($package_price->price * $quantity);
				
				foreach ($coupon->amounts as $amount) {
					if ($amount->currency == $currency) {
						if ($amount->type == "amount") {
							
							// Set the coupon amount to deduct from the package
							$discount_amount = ($amount->amount >= $package_cost ? $package_cost : $amount->amount);
							
							// Determine the coupon discount amount from the package's config options as well
							if ($coupon->apply_package_options == "1" && $config_price && $config_price->price > 0) {
								// Set the coupon amount to deduct from the coupon remainder
								if ($discount_amount < $amount->amount) {
									$discount_amount += (($amount->amount - $discount_amount) >= $config_price->price ? $config_price->price : ($amount->amount - $discount_amount));
								}
							}
							
							$discount += $discount_amount;
						}
						else {
							$discount += ($package_cost * $amount->amount / 100);
							
							// Apply the coupon discount % to the package's config options as well
							if ($coupon->apply_package_options == "1" && $config_price && $config_price->price > 0) {
								$discount += ($config_price->price * $amount->amount / 100);
							}
						}
						break;
					}
				}
			}
		}
		return $discount;
	}
	
	/**
	 * Retrieves the setup and cancel fee amounts
	 * @see Packages::calcLineTotals()
	 *
	 * @param array $fees An array of pricing fees
	 * @param stdClass $package_price An stdClass object representing the package price containing setup and cancel fees
	 * @param stdClass $config_price An stdClass object representing the config price containing setup and cancel fees
	 * @return array An array of fees including:
	 *  - setup The total setup fee amount
	 *  - cancel The total cancel fee amount
	 */
	private function getFeeAmounts(array $fees, $package_price, $config_price) {
		$total_fees = array('setup' => 0, 'cancel' => 0);
		
		foreach ($fees as $fee) {
			if ($fee == "cancel") {
				if (!isset($total_fees['cancel']))
					$total_fees['cancel'] = 0;
				$total_fees['cancel'] += $package_price->cancel_fee;
				
				if ($config_price)
					$total_fees['cancel'] += $config_price->cancel_fee;
			}
			elseif ($fee == "setup") {
				if (!isset($total_fees['setup']))
					$total_fees['setup'] = 0;
				
				$total_fees['setup'] += $package_price->setup_fee;
				
				if ($config_price)
					$total_fees['setup'] += $config_price->setup_fee;
			}
		}
		
		return $total_fees;
	}
	
	/**
	 * Retrieves the tax amounts
	 * @see Packages::calcLineTotals()
	 *
	 * @param int $taxable 1 or 0 denoting whether the package price is taxable
	 * @param array $client_settings A list of key/value pairs representing client settings
	 * @param float $line_total The total line item amount
	 * @param float $setup_fee The total setup fee
	 * @param string $currency The ISO 4217 currency code to calculate totals in
	 * @param array $tax_rules An array of stdClass objects representing line item tax rules
	 * @param array $tax A reference to tax levels and their amounts
	 * 
	 */
	private function getTaxAmounts($taxable, $client_settings, $line_total, $setup_fee, $currency, $tax_rules, &$tax) {
		if (!isset($this->CurrencyFormat))
			Loader::loadHelpers($this, array("CurrencyFormat"=>array(Configure::get("Blesta.company_id"))));
		if (!isset($this->Invoices))
			Loader::loadModels($this, array("Invoices"));
		
		$taxes = array(
			'subtotal' => 0,
			'total' => 0
		);
		
		// Calculate tax for each line item that is taxable IFF tax is enabled
		if ($client_settings['tax_exempt'] != "true" && $client_settings['enable_tax'] == "true" && $taxable) {
			$tax_totals = $this->Invoices->getTaxTotals($line_total, $tax_rules);
			$taxes['subtotal'] += $tax_totals['tax_subtotal'];
			$taxes['total'] += $tax_totals['tax_total'];
			
			// Format tax amount for each tax rule
			foreach ($tax_totals['tax'] as $level_index => $tax_rule) {
				// If a tax is already defined at this level, increment the values
				if (isset($tax[$level_index]))
					$tax_rule['amount'] += $tax[$level_index]['amount'];
				
				// Set tax percentage 
				$tax_rule['percentage'] = $tax_rule['percentage'];
				
				// Format the tax amount
				$tax_rule['amount_formatted'] = $this->CurrencyFormat->format($tax_rule['amount'], $currency);
				$tax[$level_index] = $tax_rule;
			}
			unset($tax_rule);
			
			// Only include setup fee in line total if it is taxable
			if ($setup_fee > 0 && $client_settings['setup_fee_tax'] == "true") {
				$tax_totals = $this->Invoices->getTaxTotals($setup_fee, $tax_rules);
				
				$taxes['subtotal'] += $tax_totals['tax_subtotal'];
				$taxes['total'] += $tax_totals['tax_total'];
				
				// Format tax amount for each tax rule
				foreach ($tax_totals['tax'] as $level_index => $tax_rule) {
					
					// If a tax is already defined at this level, increment the values
					if (isset($tax[$level_index]))
						$tax_rule['amount'] += $tax[$level_index]['amount'];
					
					// Set tax percentage 
					$tax_rule['percentage'] = $tax_rule['percentage'];
					
					// Format the tax amount
					$tax_rule['amount_formatted'] = $this->CurrencyFormat->format($tax_rule['amount'], $currency);
					$tax[$level_index] = $tax_rule;
				}
				unset($tax_rule);
			}
		}
		
		return $taxes;
	}
	
	/**
	 * Retrieves a list of all package items and config options (including prorated items) from those given
	 *
	 * @param array $package_pricings A numerical array of package pricing and quantity values of the form:
	 * 	- pricing_id The package pricing ID
	 * 	- qty The qty being purchased for the package pricing ID
	 * 	- fees A numerical array of fee types to include in the pricing calculations, including:
	 * 		- setup
	 * 		- cancel
	 * 	- configoptions An array of key/value pairs where each key is the package option ID and each value is the package option value
	 * @param int $client_id The ID of the client to which the pricings are to be applied (optional)
	 * @param string $currency The ISO 4217 currency code to calculate totals in (null defaults to default client or company currency)
	 * @return array A numerically-indexed array of all package items with any config options
	 */
	public function getPackageItems(array $package_pricings, $client_id = null, $currency = null) {
		if (!isset($this->Services))
			Loader::loadModels($this, array("Services"));
		Loader::loadComponents($this, array("SettingsCollection"));
        
        // Fetch settings
        if ($client_id)
            $settings = $this->SettingsCollection->fetchClientSettings($client_id);
        if (!isset($settings['multi_currency_pricing']) || !isset($settings['default_currency']))
            $settings = $this->SettingsCollection->fetchSettings(null, Configure::get("Blesta.company_id"));
		
		$package_items = array();
		
		if (!$currency)
			$currency = $settings['default_currency'];
		
		// Get base package items
		$package_ids = $this->getPackageIdsFromPricing($package_pricings);
		
		// Get config option items 
		foreach ($package_pricings as $pricing) {
			$package = $this->getByPricingId($pricing['pricing_id']);
			if (!$package)
				continue;
			
			$prorate = (isset($pricing['prorate']) ? $pricing['prorate'] : false);
			$pricing['qty'] = (int)$pricing['qty'];
			
			// Skip on invalid quantity
			if ($pricing['qty'] <= 0)
				continue;
			
			$package_price = $pricing['price'];

			$config_items = array();
			if ($package_price && isset($pricing['configoptions']))
				$config_items = $this->getConfigOptionItems($package_price, $pricing['configoptions'], $currency, ($settings['multi_currency_pricing'] != "package"), $prorate);
			
			if ($package_price)
				$package_price = $this->convertPricing($package_price, $currency, ($settings['multi_currency_pricing'] != "package"));
			
			// Skip if pricing doesn't exist
			if (!$package_price)
				continue;
			
			$package_items[] = (object)array(
				'package' => $package,
				'prorated' => ($prorate ? (object)$prorate : $prorate),
				'pricing' => $package_price,
				'config_options' => $config_items,
				'start_date' => (isset($pricing['start_date']) ? $pricing['start_date'] : null),
				'end_date' => (isset($pricing['end_date']) ? $pricing['end_date'] : null),
				// Include the original index of the given pricings to allow the caller to reference them
				'index' => (isset($pricing['index']) ? $pricing['index'] : null)
			);
		}
		
		return $package_items;
	}
	
	/**
	 * Retrieves a list of package option items
	 *
	 * @param stdClass $package_pricing The package pricing object
	 * @param array $options A key/value pair array of config options
	 * @param string $currency The ISO 4217 currency code to convert to
	 * @param boolean $allow_conversion True to allow converion, false otherwise
	 * @param array $prorate A key/value array including: (optional)
	 *  - days The number of days to prorate for
	 *  - cutoff Whether or not cutoff day has passed, signifying that new pricing options should be created in addition to the proration
	 * @return array A numerically-indexed array of stdClass objects representing the config option and its pricing
	 */
	private function getConfigOptionItems($package_pricing, array $options, $currency = null, $allow_conversion = null, $prorate = array()) {
		if (!isset($this->PackageOptions))
			Loader::loadModels($this, array("PackageOptions"));
		
		$items = array();
		$prorate_days = (isset($prorate['days']) ? $prorate['days'] : null);
		$prorate_cutoff = (isset($prorate['cutoff']) ? $prorate['cutoff'] : null);
		$is_prorated = (isset($prorate['is_prorated']) ? $prorate['is_prorated'] : null);
		$prorate_from_date = (isset($prorate['from_date']) ? $prorate['from_date'] : null);
		$pro_rata_day = (isset($prorate['pro_rata_day']) ? $prorate['pro_rata_day'] : null);
		
		foreach ($options as $option_id => $option_value) {
			$value = $this->PackageOptions->getValue($option_id, $option_value);
			if (!$value)
				continue;
			
			// Skip config options with a quantity of 0
			$option = $this->PackageOptions->get($option_id);
			if ($option_value == 0 && $option && $option->type == "quantity")
				continue;
			
			// If can't convert to given currency using package pricing currency
			if (!$allow_conversion)
				$currency = $package_pricing->currency;
			
			// Get the prorated price if prorating
			if (($prorate_days != 0 || $is_prorated) && $prorate_from_date && $pro_rata_day)
				$price = $this->PackageOptions->getValueProrateAmount($value->id, $prorate_from_date, $package_pricing->term, $package_pricing->period, $pro_rata_day, $package_pricing->currency, $currency);
			else
				$price = $this->PackageOptions->getValuePrice($value->id, $package_pricing->term, $package_pricing->period, $package_pricing->currency, $currency);
			
			if (!$price)
				continue;
			
			// Set the price
			$price->price = ($value->value === null ? $option_value*$price->price : $price->price);
			
			$items[] = (object)array(
				'option' => $option,
				'prorated' => ($prorate ? (object)$prorate : $prorate),
				'value' => $value,
				'pricing' => $price
			);
		}
		
		return $items;
	}
	
	/**
	 * Caclulates the cost in one or more package pricings for a client with the given coupon.
	 * Tax is only applied if the package is configured as taxable and there exists
	 * tax rules that apply to the given client.
	 *
	 * @param int $client_id The ID of the client to which the pricings are to be applied
	 * @param array $package_pricings A numerical array of package pricing and quantity values of the form:
	 * 	- pricing_id The package pricing ID
	 * 	- qty The qty being purchased for the package pricing ID
	 * 	- fees A numerical array of fee types to include in the pricing calculations, including:
	 * 		- setup
	 * 		- cancel
	 * 	- configoptions An array of key/value pairs where each key is the package option ID and each value is the package option value
	 * @param string $coupon_code The coupon code to apply to each package pricing ID
	 * @param array $tax_rules A numerically indexed array of stdClass objects each representing a tax rule to apply to this client or client group. Must be provided if $client_id not specified
	 * @param int $client_group_id The ID of the client group to calculate line totals for
	 * @param string $currency The ISO 4217 currency code to calculate totals in (null defaults to default client or client group currency)
	 * @return array An array of pricing information including:
	 * 	- subtotal The total before discount, fees, and tax
	 * 	- discount The total savings
	 * 	- fees An array of fees requested including:
	 * 		- setup The setup fee
	 * 		- cancel The cancel fee
	 * 	- total The total after discount, fees, but before tax
	 * 	- total_w_tax The total after discount, fees, and tax
	 * 	- tax The total tax
	 */
	public function calcLineTotals($client_id, array $package_pricings, $coupon_code = null, array $tax_rules = null, $client_group_id = null, $currency = null) {
		Loader::loadHelpers($this, array("CurrencyFormat"=>array(Configure::get("Blesta.company_id"))));
		Loader::loadComponents($this, array("SettingsCollection"));
		Loader::loadModels($this, array("Invoices", "Coupons", "Currencies"));
		
		if ($client_id)
			$client_settings = $this->SettingsCollection->fetchClientSettings($client_id);
		else
			$client_settings = $this->SettingsCollection->fetchClientGroupSettings($client_group_id);	

		if (!$currency)
			$currency = $client_settings['default_currency'];

		// Fetch all tax rules that apply to this client
		if (!$tax_rules)
			$tax_rules = $this->Invoices->getTaxRules($client_id);
		
		// Set cascade tax setting
		foreach ($tax_rules as &$tax_rule) {
			$tax_rule->cascade = $client_settings['cascade_tax'] == "true" ? 1 : 0;
		}
		unset($tax_rule);
		
		$totals = array();
	
		// Subtotal sum
		$subtotal = 0;
		// Discount
		$total_discount = 0;
		// Fees
		$fees = array();
		// Total setup fees
		$setup_fees = 0;
		// Total sum
		$total = 0;
		// Tax total sum (for rules that should be applied to totals i.e. "inclusive")
		$tax_subtotal = 0;
		// Tax total sum including both inclusive and exclusive taxes
		$tax_total = 0;
		// Tax totals
		$tax = array();
		
		// Fetch pricing and package IDs for each package price
		$package_ids = $this->getPackageIdsFromPricing($package_pricings);
		
		$coupon = false;
		if ($coupon_code)
			$coupon = $this->Coupons->getForPackages($coupon_code, null, $package_ids);
		
		// Caclulate totals for each pricing option given
		foreach ($package_pricings as $pricing) {
			$prorate = (isset($pricing['prorate']) ? $pricing['prorate'] : array());
			$pricing['qty'] = (int)$pricing['qty'];
			
			// Skip calculations on any lines that are blank
			if ($pricing['qty'] <= 0)
				continue;
			
			$package_price = $pricing['price'];

			$config_price = null;
			if ($package_price && isset($pricing['configoptions']))
				$config_price = $this->calcConfigOptionTotals($package_price, $pricing['configoptions'], $currency, ($client_settings['multi_currency_pricing'] != "package"), $prorate);
			
			if ($package_price)
				$package_price = $this->convertPricing($package_price, $currency, ($client_settings['multi_currency_pricing'] != "package"));
			
			// Skip if pricing doesn't exist
			if (!$package_price)
				continue;

			$line_total = $pricing['qty'] * $package_price->price;
			
			if ($config_price)
				$line_total += $config_price->price;
				
			$subtotal += $line_total;
			
			// Calculate coupon discount (if exclusive)
			$exclusive_coupon_discount = $this->getExclusiveCouponDiscount($coupon, $package_price, $pricing['qty'], $currency, $config_price);
			$total_discount += ($line_total >= $exclusive_coupon_discount ? $exclusive_coupon_discount : $line_total);
			
			// Calculate the fees
			$item_fees = $this->getFeeAmounts($pricing['fees'], $package_price, $config_price);
			$setup_fees += $item_fees['setup'];
			$fees['setup'] = $item_fees['setup'] + (isset($fees['setup']) ? $fees['setup'] : 0);
			if ($item_fees['cancel'] > 0)
				$fees['cancel'] = $item_fees['cancel'] + (isset($fees['cancel']) ? $fees['cancel'] : 0);
			
			// Calculate tax for each line item that is taxable IFF tax is enabled
			$tax_amounts = $this->getTaxAmounts($package_price->taxable, $client_settings, $line_total, $item_fees['setup'], $currency, $tax_rules, $tax);
			$tax_subtotal += $tax_amounts['subtotal'];
			$tax_total += $tax_amounts['total'];
		}
		unset($tax_rules);
        $discount = ($subtotal >= $total_discount ? $total_discount : $subtotal);
		$total = $subtotal + $setup_fees + -$discount + $tax_subtotal;
		$total_w_tax = $subtotal + $setup_fees + -$discount + $tax_total;
		
		// Calculate discount (if inclusive)
		if ($coupon && $coupon->type == "inclusive") {
			#
			# TODO: Do exactly as we did for each package, but just for this discount
			# as a negative amount (so total = subtotal + -discount) and then
			# tax the negative discount and add it to the taxes as well that way
			# the taxes are reduced
			#
			#
		}
		
		if (isset($fees['setup']))
			$fees['setup'] = array('amount' => $fees['setup'], 'amount_formatted' => $this->CurrencyFormat->format($fees['setup'], $currency));
		if (isset($fees['cancel']))
			$fees['cancel'] = array('amount' => $fees['cancel'], 'amount_formatted' => $this->CurrencyFormat->format($fees['cancel'], $currency));
			
		$totals = array(
			'subtotal' => array('amount' => $subtotal, 'amount_formatted' => $this->CurrencyFormat->format($subtotal, $currency)),
			'total' => array('amount' => $total, 'amount_formatted' => $this->CurrencyFormat->format($total, $currency)),
			'total_w_tax' => array('amount' => $total_w_tax, 'amount_formatted' => $this->CurrencyFormat->format($total_w_tax, $currency)),
			'tax' => $tax,
			'discount' => array('amount' => -$discount, 'amount_formatted' => $this->CurrencyFormat->format(-$discount, $currency)),
			'coupon' => $coupon,
			'fees' => $fees
		);

		return $totals;
	}
	
	/**
	 * Calculate the total for all configurable options
	 *
	 * @param stdClass $package_pricing The package pricing object
	 * @param array $options A key/value pair array of config options
	 * @param string $currency The ISO 4217 currency code to convert to
	 * @param boolean $allow_conversion True to allow converion, false otherwise
	 * @param array $prorate A key/value array including: (optional)
	 *  - days The number of days to prorate for
	 *  - cutoff Whether or not cutoff day has passed, signifying that new pricing options should be created in addition to the proration
	 * @return stdClass The stdClass object representing the config option totals
	 */
	private function calcConfigOptionTotals($package_pricing, array $options, $currency, $allow_conversion, $prorate = array()) {
		Loader::loadModels($this, array("PackageOptions"));
		
		$pricing = new stdClass();
		$pricing->price = 0;
		$pricing->setup_fee = 0;
		$pricing->cancel_fee = 0;
		$pricing->currency = $currency;
		
		$prorate_days = (isset($prorate['days']) ? $prorate['days'] : null);
		$prorate_cutoff = (isset($prorate['cutoff']) ? $prorate['cutoff'] : null);
		$is_prorated = (isset($prorate['is_prorated']) ? $prorate['is_prorated'] : null);
		$prorate_from_date = (isset($prorate['from_date']) ? $prorate['from_date'] : null);
		$pro_rata_day = (isset($prorate['pro_rata_day']) ? $prorate['pro_rata_day'] : null);
		
		foreach ($options as $option_id => $option_value) {
			$value = $this->PackageOptions->getValue($option_id, $option_value);
			
			if (!$value)
				continue;
			
			// If can't convert to given currency using package pricing currency
			if (!$allow_conversion)
				$currency = $package_pricing->currency;
			
			// Get the prorated price if prorating
			if (($prorate_days != 0 || $is_prorated) && $prorate_from_date && $pro_rata_day)
				$price = $this->PackageOptions->getValueProrateAmount($value->id, $prorate_from_date, $package_pricing->term, $package_pricing->period, $pro_rata_day, $package_pricing->currency, $currency);
			else
				$price = $this->PackageOptions->getValuePrice($value->id, $package_pricing->term, $package_pricing->period, $package_pricing->currency, $currency);
			
			if ($price) {
				// Include the option price. If the option is a quantity option, zero quantity should
				// not add to the price and should not include a setup fee
				$pricing->price += ($value->value === null ? $option_value*$price->price : $price->price);
				$pricing->setup_fee += ($value->value === null && $option_value == 0 ? 0 : $price->setup_fee);
				$pricing->cancel_fee += $price->cancel_fee;
			}
		}
		return $pricing;
	}
	
	/**
	 * Convert pricing to the given currency if allowed
	 *
	 * @param stdClass $pricing A stdClass object representing a package pricing
	 * @param string $currency The ISO 4217 currency code to convert to
	 * @param boolean $allow_conversion True to allow converion, false otherwise
	 * @return mixed The stdClass object representing the converted pricing (if conversion allowed), null otherwise
	 */
	public function convertPricing($pricing, $currency, $allow_conversion) {
		if (!isset($this->Currencies))
			Loader::loadModels($this, array("Currencies"));
			
		$company_id = Configure::get("Blesta.company_id");
		
		if ($pricing->currency == $currency)
			return $pricing;
		elseif ($allow_conversion) {
			// Convert prices and set converted currency
			$pricing->price = $this->Currencies->convert($pricing->price, $pricing->currency, $currency, $company_id);
			$pricing->setup_fee = $this->Currencies->convert($pricing->setup_fee, $pricing->currency, $currency, $company_id);
			if (isset($pricing->cancel_fee))
				$pricing->cancel_fee = $this->Currencies->convert($pricing->cancel_fee, $pricing->currency, $currency, $company_id);
			$pricing->currency = $currency;
			
			return $pricing;
		}
		
		return null;
	}
	
	/**
	 * Return the total number of packages returned from Packages::getList(),
	 * useful in constructing pagination for the getList() method.
	 *
	 * @param string $status The status type of packages to retrieve ('active', 'inactive', 'restricted', default null for all)
	 * @return int The total number of packages
	 * @see Packages::getList()
	 */
	public function getListCount($status=null) {
		$this->Record = $this->getPackages($status);
		
		return $this->Record->where("packages.company_id", "=", Configure::get("Blesta.company_id"))->numResults();
	}
	
	/**
	 * Fetches all package groups belonging to a company, or optionally, all package
	 * groups belonging to a specific package
	 *
	 * @param int $company_id The company ID
	 * @param int $package_id The package ID to fetch groups of (optional, default null)
	 * @param string $type The type of group to fetch (null, standard, addon)
	 * @return mixed An array of stdClass objects representing package groups, or false if none found
	 */
	public function getAllGroups($company_id, $package_id=null, $type=null) {
		$this->Record->select(array("package_groups.*"))->from("package_groups");
		
		if ($package_id != null) {
			$this->Record->innerJoin("package_group", "package_group.package_group_id", "=", "package_groups.id", false)->
				innerJoin("packages", "packages.id", "=", "package_group.package_id", false)->
				where("packages.id", "=", $package_id);
		}
		
		if ($type)
			$this->Record->where("package_groups.type", "=", $type);
		
		return $this->Record->where("package_groups.company_id", "=", $company_id)->
			order(array('package_groups.name' => "asc"))->fetchAll();
	}
	
	/**
	 * Returns all addon package groups for the given package group.
	 *
	 * @param int $parent_group_id The ID of the parent package group
	 * @return array A list of addon package groups
	 */
	public function getAllAddonGroups($parent_group_id) {
		return $this->Record->select(array("package_groups.*"))->from("package_group_parents")->
			on("package_groups.type", "=", "addon")->
			innerJoin("package_groups", "package_groups.id", "=", "package_group_parents.group_id", false)->
			where("package_group_parents.parent_group_id", "=", $parent_group_id)->fetchAll();
	}
	
	/**
	 * Fetches all packages belonging to a specific package group
	 *
	 * @param int $package_group_id The ID of the package group
	 * @param string $status The status type of packages to retrieve ('active', 'inactive', 'restricted', default null for all)
	 * @return mixed An array of stdClass objects representing packages, or false if none exist
	 */
	public function getAllPackagesByGroup($package_group_id, $status=null) {
		$this->Record = $this->getPackages($status);
		$packages = $this->Record->innerJoin("package_group", "packages.id", "=", "package_group.package_id", false)->
			where("package_group.package_group_id", "=", $package_group_id)->
			order(array('package_group.order' => "ASC"))->
			fetchAll();
			
		foreach ($packages as &$package)
			$package->pricing = $this->getPackagePricing($package->id);
			
		return $packages;
	}
	
	/**
	 * Get all compatible packages
	 *
	 * @param int $package_id The ID of the package to fetch all compatible packages for
	 * @param int $module_id The ID of the module to include compatible packages for
	 * @param string $type The type of package group to include ("standard", "addon")
	 * @return array An array of stdClass objects, each representing a compatible package and its pricing
	 */
	public function getCompatiblePackages($package_id, $module_id, $type) {
		$subquery_record = clone $this->Record;
		$subquery_record->select(array("package_group.*"))->from("packages")->
			innerJoin("package_group", "package_group.package_id", "=", "packages.id", false)->
			where("packages.id", "=", $package_id);
		$subquery = $subquery_record->get();
		$values = $subquery_record->values;
		unset($subquery_record);
			
		
		$this->Record = $this->getPackages();
		$packages = $this->Record->
			innerJoin("package_group", "packages.id", "=", "package_group.package_id", false)->
			appendValues($values)->
			innerJoin(array($subquery => "temp"), "temp.package_group_id", "=", "package_group.package_group_id", false)->
			innerJoin("package_groups", "package_groups.id", "=", "package_group.package_group_id", false)->
			where("package_groups.type", "=", $type)->
			where("package_groups.allow_upgrades", "=", "1")->
			where("packages.module_id", "=", $module_id)->
			order(array('package_group.order' => "ASC"))->
			fetchAll();
		
		foreach ($packages as &$package)
			$package->pricing = $this->getPackagePricing($package->id);

		return $packages;
	}
	
	/**
	 * Fetches all emails created for the given package
	 *
	 * @param int $package_id The package ID to fetch email for
	 * @return array An array of stdClass objects representing email content
	 */
	private function getPackageEmails($package_id) {
		return $this->Record->select(array("lang", "html", "text"))->from("package_emails")->
			where("package_id", "=", $package_id)->fetchAll();
	}

	/**
	 * Fetches all pricing for the given package
	 *
	 * @param int $package_id The package ID to fetch pricing for
	 * @return array An array of stdClass objects representing package pricing
	 */
	private function getPackagePricing($package_id) {
		$fields = array("package_pricing.id", "package_pricing.pricing_id", "package_pricing.package_id", "pricings.term",
			"pricings.period", "pricings.price", "pricings.setup_fee",
			"pricings.cancel_fee", "pricings.currency");
		return $this->Record->select($fields)->from("package_pricing")->
			innerJoin("pricings", "pricings.id", "=", "package_pricing.pricing_id", false)->
			where("package_pricing.package_id", "=", $package_id)->
			order(array('period' => "ASC", 'term' => "ASC"))->fetchAll();
	}
	
	/**
	 * Fetches a single pricing, including its package's taxable status
	 *
	 * @param int $package_pricing_id The ID of the package pricing to fetch
	 * @return mixed A stdClass object representing the package pricing, false if no such package pricing exists
	 */
	private function getAPackagePricing($package_pricing_id) {
		$fields = array("package_pricing.id", "package_pricing.pricing_id", "package_pricing.package_id", "pricings.term",
			"pricings.period", "pricings.price", "pricings.setup_fee",
			"pricings.cancel_fee", "pricings.currency",
			"packages.taxable");
		return $this->Record->select($fields)->from("package_pricing")->
			innerJoin("pricings", "pricings.id", "=", "package_pricing.pricing_id", false)->
			innerJoin("packages", "packages.id", "=", "package_pricing.package_id", false)->
			where("package_pricing.id", "=", $package_pricing_id)->fetch();
	}
	
	/**
	 * Adds a pricing and package pricing record
	 *
	 * @param int package_id The pacakge ID to add pricing for
	 * @param array $vars An array of pricing info including:
	 * 	- company_id The company ID to add pricing for
	 * 	- term The term as an integer 1-65535 (optional, default 1)
	 * 	- period The period, 'day', 'week', 'month', 'year', 'onetime' (optional, default 'month')
	 * 	- price The price of this term (optional, default 0.00)
	 * 	- setup_fee The setup fee for this package (optional, default 0.00)
	 * 	- cancel_fee The cancelation fee for this package (optional, default 0.00)
	 * 	- currency The ISO 4217 currency code for this pricing (optional, default USD)
	 * @return int The package pricing ID
	 */
	private function addPackagePricing($package_id, array $vars) {
		if (!isset($this->Pricings))
			Loader::loadModels($this, array("Pricings"));
		
		$pricing_id = $this->Pricings->add($vars);
		
		if (($errors = $this->Pricings->errors())) {
			$this->Input->setErrors($errors);
			return;
		}
		
		if ($pricing_id) {
			$this->Record->insert("package_pricing",
				array('package_id' => $package_id, 'pricing_id' => $pricing_id));
			return $this->Record->lastInsertId();
		}
	}
	
	/**
	 * Edit package pricig, removes any pricing with a missing term
	 *
	 * @param int $package_pricing_id The package pricing ID to update
	 * @param array $vars An array of pricing info including:
	 * 	- package_id The pacakge ID to add pricing for
	 * 	- company_id The company ID to add pricing for
	 * 	- term The term as an integer 1-65535 (optional, default 1)
	 * 	- period The period, 'day', 'week', 'month', 'year', 'onetime' (optional, default 'month')
	 * 	- price The price of this term (optional, default 0.00)
	 * 	- setup_fee The setup fee for this package (optional, default 0.00)
	 * 	- cancel_fee The cancelation fee for this package (optional, default 0.00)
	 * 	- currency The ISO 4217 currency code for this pricing (optional, default USD)
	 */
	private function editPackagePricing($package_pricing_id, array $vars) {
		if (!isset($this->Pricings))
			Loader::loadModels($this, array("Pricings"));
		
		$package_pricing = $this->getAPackagePricing($package_pricing_id);
		
		if (isset($vars['term'])) {
			$fields = array("term", "period", "price", "setup_fee", "cancel_fee", "currency");
			$this->Pricings->edit($package_pricing->pricing_id, array_intersect_key($vars, array_flip($fields)));
		}
		// Remove the package pricing, term not set
		else {
			$this->Pricings->delete($package_pricing->pricing_id);
			
			$this->Record->where("id", "=", $package_pricing->id)->
				where("package_id", "=", $package_pricing->package_id)->
				from("package_pricing")->delete();
		}
	}

	/**
	 * Fetches all package meta data for the given package
	 *
	 * @param int $package_id The package ID to fetch meta data for
	 * @return array An array of stdClass objects representing package meta data
	 */	
	private function getPackageMeta($package_id) {
		$fields = array("key", "value", "serialized", "encrypted");
		$this->Record->select($fields)->from("package_meta")->
			where("package_id", "=", $package_id);
			
		return $this->formatRawMeta($this->Record->fetchAll());
	}
	
	/**
	 * Fetches all package group assignment for the given package
	 *
	 * @param int $package_id The package ID to fetch pricing for
	 * @return array An array of stdClass objects representing package groups
	 */
	private function getPackageGroups($package_id) {
		return $this->Record->select(array("package_groups.*"))->from("package_group")->
			innerJoin("package_groups", "package_groups.id", "=", "package_group.package_group_id", false)->
			where("package_group.package_id", "=", $package_id)->fetchAll();
	}
	
	/**
	 * Fetches all package option groups assigned to the given package
	 *
	 * @param int $package_id The package ID to fetch option groups for
	 * @return array An array of stdClass objects representing package option groups
	 */
	private function getPackageOptionGroups($package_id) {
		$fields = array("package_option_groups.id", "package_option_groups.name", "package_option_groups.description");
		return $this->Record->select($fields)->from("package_option")->
			innerJoin("package_option_groups", "package_option_groups.id", "=", "package_option.option_group_id", false)->
			where("package_option.package_id", "=", $package_id)->fetchAll();
	}
	
	/**
	 * Partially constructs the query required by both Packages::getList() and
	 * Packages::getListCount()
	 *
	 * @param string $status The status type of packages to retrieve ('active', 'inactive', 'restricted', default null for all)
	 * @return Record The partially constructed query Record object
	 */
	private function getPackages($status=null) {
		$fields = array("packages.*",
			"REPLACE(packages.id_format, ?, packages.id_value)" => "id_code", "modules.name"=>"module_name"
		);
		
		$this->Record->select($fields)->
			appendValues(array($this->replacement_keys['packages']['ID_VALUE_TAG']))->
			from("packages")->
			leftJoin("module_rows", "module_rows.module_id", "=", "packages.module_id", false)->
			leftJoin("modules", "modules.id", "=", "module_rows.module_id", false);
		
		// Set a specific package status
		if ($status != null)
			$this->Record->where("packages.status", "=", $status);
		
		return $this->Record->group("packages.id");
	}
	
	/**
	 * Removes all existing groups set for the given package, replaces them with
	 * the given list of groups
	 *
	 * @param int $package_id The package to replace groups on
	 * @param array $groups A numerically-indexed array of group IDs
	 * @param mixed $groups An array of groups to add to the package, null or empty string to replace will nothing
	 */
	private function setGroups($package_id, $groups=null) {
		
		// Fetch existing group assignments to maintain their order
		$order = array();
		if (!empty($groups) && is_array($groups)) {
			$package_groups = $this->Record->select()->from("package_group")->
				where("package_id", "=", $package_id)->fetchAll();
			
			foreach ($package_groups as $group) {
				$order[$group->package_group_id] = $group->order;
			}
		}
		
		// Remove all existing groups
		$this->Record->from("package_group")->
			where("package_id", "=", $package_id)->delete();
			
		// Add all given groups
		if (!empty($groups) && is_array($groups)) {
			for ($i=0; $i<count($groups); $i++) {
				$vars = array(
					'package_id'=>$package_id,
					'package_group_id'=>$groups[$i]
				);
				
				// Reset existing package group order
				if (array_key_exists($groups[$i], $order)) {
					$vars['order'] = $order[$groups[$i]];
				}
				
				$this->Record->insert("package_group", $vars);
			}
		}
	}
	
	/**
	 * Assigns the given package option group to the given package
	 *
	 * @param int $package_id The ID of the package to be assigned the option group
	 * @param array $option_groups A numerically-indexed array of package option groups to assign
	 */
	private function setOptionGroups($package_id, array $option_groups) {
		foreach ($option_groups as $option_group_id) {
			$vars = array('package_id' => $package_id, 'option_group_id' => $option_group_id);
			$this->Record->duplicate("option_group_id", "=", $option_group_id)->insert("package_option", $vars);
		}
	}
	
	/**
	 * Removes all package option groups assigned to this package
	 *
	 * @param int $package_id The ID of the package
	 */
	private function removeOptionGroups($package_id) {
		$this->Record->from("package_option")->where("package_id", "=", $package_id)->delete();
	}
	
	/**
	 * Updates the meta data for the given package, removing all existing data and replacing it with the given data
	 *
	 * @param int $package_id The ID of the package to update
	 * @param array $vars A numerically indexed array of meta data containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 */
	private function setMeta($package_id, array $vars) {
		
		// Delete all old meta data for this package
		$this->Record->from("package_meta")->
			where("package_id", "=", $package_id)->delete();
		
		// Add all new module data
		$fields = array("package_id", "key", "value", "serialized", "encrypted");
		$num_vars = count($vars);
		for ($i=0; $i<$num_vars; $i++) {
			$serialize = !is_scalar($vars[$i]['value']);
			$vars[$i]['package_id'] = $package_id;
			$vars[$i]['serialized'] = (int)$serialize;
			$vars[$i]['value'] = $serialize ? serialize($vars[$i]['value']) : $vars[$i]['value'];
			
			if (isset($vars[$i]['encrypted']) && $vars[$i]['encrypted'] == "1")
				$vars[$i]['value'] = $this->systemEncrypt($vars[$i]['value']);
			
			$this->Record->insert("package_meta", $vars[$i], $fields);
		}
	}
	
	/**
	 * Formats an array of raw meta stdClass objects into a stdClass
	 * object whose public member variables represent meta keys and whose values
	 * are automatically decrypted and unserialized as necessary.
	 *
	 * @param array $raw_meta An array of stdClass objects representing meta data
	 */
	private function formatRawMeta($raw_meta) {
		
		$meta = new stdClass();
		// Decrypt data as necessary
		foreach ($raw_meta as &$data) {
			if ($data->encrypted > 0)
				$data->value = $this->systemDecrypt($data->value);
				
			if ($data->serialized > 0)
				$data->value = unserialize($data->value);
			
			$meta->{$data->key} = $data->value;
		}
		return $meta;
	}
	
	/**
	 * Checks whether a service exists for a specific package ID
	 *
	 * @param int $package_id The package ID to check
	 * @return boolean True if a service exists for this package, false otherwise
	 */
	public function validateServiceExists($package_id) {
		$count = $this->Record->select("services.id")->from("package_pricing")->
			innerJoin("services", "services.pricing_id", "=", "package_pricing.id", false)->
			where("package_pricing.package_id", "=", $package_id)->numResults();
			
		if ($count > 0)
			return true;
		return false;
	}
	
	/**
	 * Validates the package 'status' field type
	 *
	 * @param string $status The status type
	 * @return boolean True if validated, false otherwise
	 */
	public function validateStatus($status) {
		switch ($status) {
			case "active":
			case "inactive":
			case "restricted":
				return true;
		}
		return false;
	}
	
	/**
	 * Validates that the term is valid for the period. That is, the term must be > 0
	 * if the period is something other than "onetime".
	 *
	 * @param int $term The Term to validate
	 * @param string $period The period to validate the term against
	 * @return boolean True if validated, false otherwise
	 */
	public function validateTerm($term, $period) {
		if ($period == "onetime")
			return true;
		return $term > 0;
	}
	
	/**
	 * Validates the pricing 'period' field type
	 *
	 * @param string $period The period type
	 * @return boolean True if validated, false otherwise
	 */
	public function validatePeriod($period) {
		$periods = $this->getPricingPeriods();
		
		if (isset($periods[$period]))
			return true;
		return false;
	}
	
	/**
	 * Validates that the given group belongs to the given company ID
	 *
	 * @param int $group_id The ID of the group to test
	 * @param int $company_id The ID of the company to validate exists for the given group
	 * @return boolean True if validated, false otherwise
	 */
	public function validateGroup($group_id, $company_id) {
		return (boolean)$this->Record->select(array("id"))->
			from("package_groups")->where("company_id", "=", $company_id)->fetch();
	}
	
	/**
	 * Validates that the given group is valid
	 *
	 * @param int $option_group_id The ID of the package option group to validate
	 * @return boolean True if the package option group is valid, or false otherwise
	 */
	public function validateOptionGroup($option_group_id, $company_id) {
		// Group may not be given
		if (empty($option_group_id))
			return true;
		
		// Check whether this is a valid option group
		$count = $this->Record->select(array("id"))->from("package_option_groups")->
			where("id", "=", $option_group_id)->where("company_id", "=", $company_id)->numResults();
		
		return ($count > 0);
	}
	
	/**
	 * Validates that the given price is in use
	 *
	 * @param string $term The term of the price point, if non-empty no check is performed.
	 * @param int $pricing_id The package pricing ID
	 * @return boolean True if the price is in use, false otherwise
	 */
	public function validatePriceInUse($term, $pricing_id) {
		if ($term === 0 || $term != "" || $pricing_id == "" || !is_numeric($pricing_id))
			return false;
		return (boolean)$this->Record->select(array("id"))->from("services")->
			where("pricing_id", "=", $pricing_id)->fetch();
	}
	
	
	/**
	 * Validates that the given currency is valid for adding/editing a package
	 *
	 * @param string $currency The ISO 4217 currency code for this pricing option
	 * @param string $term The term for this pricing option
	 * @return boolean True if the currency is valid, or false otherwise
	 */
	public function validateCurrency($currency, $term, $period) {
		// Currency is required if term is given (otherwise it could be deleted)
		if (!empty($term) || $period == "onetime")
			return (preg_match("/^(.){3}$/", $currency) ? true : false);
		
		return true;
	}
	
	/**
	 * Validate that the given email content parses template parsing
	 *
	 * @param array A numerically-indexed array of template data to parse, containing:
	 * 	- html The HTML version of the email content
	 * 	- text The text version of the email content
	 */
	public function validateParse($email_content) {
		if (!class_exists("H2o"))
			Loader::load(VENDORDIR . "h2o" . DS . "h2o.php");

		$parser_options_text = Configure::get("Blesta.parser_options");
		
		if (is_array($email_content)) {
			// Check each email template language's HTML/Text contents
			foreach ($email_content as $email) {
				$html = (isset($email['html']) ? $email['html'] : "");
				$text = (isset($email['text']) ? $email['text'] : "");
				
				try {
					H2o::parseString($html, $parser_options_text)->render();
					H2o::parseString($text, $parser_options_text)->render();
				}
				catch (H2o_Error $e) {
					$this->parseError = $e->getMessage();
					return false;
				}
				catch (Exception $e) {
					// Don't care about any other exception
				}
			}
		}
		return true;
	}
	
	/**
	 * Sets the parse error in the set of errors
	 */
	private function setParseError() {
		// Swap the error with the actual parse error
		$errors = $this->Input->errors();
		if (isset($errors['email_content']['parse'])) {
			$errors['email_content']['parse'] = $this->_("Packages.!error.email_content.parse", $this->parseError);
		}
		
		if ($errors)
			$this->Input->setErrors($errors);
	}
	
	/**
	 * Formats the pricing term
	 *
	 * @param int $term The term length
	 * @param string $period The period of this term
	 * @return mixed The term formatted in accordance to the period, if possible
	 */
	public function formatPricingTerm($term, $period) {
		if ($period == "onetime")
			return 0;
		return $term;
	}
	
	/**
	 * Fetches the rules for adding/editing a package
	 *
	 * @return array The package rules
	 */
	private function getRules($vars) {
		$rules = array(
			// Package rules
			'module_id' => array(
				'exists' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateExists"), "id", "modules"),
					'message' => $this->_("Packages.!error.module_id.exists")
				)
			),
			'name' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("Packages.!error.name.empty")
				)
			),
			'qty' => array(
				'format' => array(
					'if_set' => true,
					'rule' => array("matches", "/^([0-9]+)?$/"),
					'message' => $this->_("Packages.!error.qty.format")
				)
			),
			'option_groups[]' => array(
				'valid' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateOptionGroup"), $this->ifSet($vars['company_id'])),
					'message' => $this->_("Packages.!error.option_groups[].valid")
				)
			),
			'module_row' => array(
				'format' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateExists"), "id", "module_rows"),
					'message' => $this->_("Packages.!error.module_row.format")
				)
			),
			'module_group' => array(
				'format' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateExists"), "id", "module_groups"),
					'message' => $this->_("Packages.!error.module_group.format")
				)
			),
			'taxable' => array(
				'format' => array(
					'if_set' => true,
					'rule' => "is_numeric",
					'message' => $this->_("Packages.!error.taxable.format")
				),
				'length' => array(
					'if_set' => true,
					'rule' => array("maxLength", 1),
					'message' => $this->_("Packages.!error.taxable.length")
				)
			),
			'single_term' => array(
				'valid' => array(
					'if_set' => true,
					'rule' => array("in_array", array("0","1")),
					'message' => $this->_("Packages.!error.single_term.valid")
				)
			),
			'status' => array(
				'format' => array(
					'rule' => array(array($this, "validateStatus")),
					'message' => $this->_("Packages.!error.status.format")
				)
			),
			'company_id' => array(
				'exists' => array(
					'rule' => array(array($this, "validateExists"), "id", "companies"),
					'message' => $this->_("Packages.!error.company_id.exists")
				)
			),
			'prorata_day' => array(
				'format' => array(
					'if_set' => true,
					'rule' => array("between", 1, 28),
					'message' => $this->_("Packages.!error.prorata_day.format")
				)
			),
			'prorata_cutoff' => array(
				'format' => array(
					'if_set' => true,
					'rule' => array("between", 1, 28),
					'message' => $this->_("Packages.!error.prorata_cutoff.format")
				)
			),
			// Package Email rules
			'email_content[][lang]' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("Packages.!error.email_content[][lang].empty")
				),
				'length' => array(
					'rule' => array("maxLength", 5),
					'message' => $this->_("Packages.!error.email_content[][lang].length")
				)
			),
			'email_content' => array(
				'parse' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateParse")),
					'message' => $this->_("Packages.!error.email_content.parse")
				)
			),
			// Package Pricing rules
			'pricing[][currency]' => array(
				'format' => array(
					'rule' => array(array($this, "validateCurrency"), array('_linked'=>"pricing[][term]"), array('_linked'=>"pricing[][period]")),
					'message' => $this->_("Packages.!error.pricing[][currency].format")
				)
			),
			'pricing[][term]' => array(
				'format' => array(
					'if_set' => true,
					'pre_format' => array(array($this, "formatPricingTerm"), array('_linked'=>"pricing[][period]")),
					'rule' => "is_numeric",
					'message' => $this->_("Packages.!error.pricing[][term].format")
				),
				'length' => array(
					'if_set' => true,
					'rule' => array("maxLength", 5),
					'message' => $this->_("Packages.!error.pricing[][term].length")
				),
				'valid' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateTerm"), array('_linked'=>"pricing[][period]")),
					'message' => $this->_("Packages.!error.pricing[][term].valid")
				),
				'deletable' => array(
					'rule' => array(array($this, "validatePriceInUse"), array('_linked' => "pricing[][id]")),
					'negate' => true,
					'message' => $this->_("Packages.!error.pricing[][term].deletable")
				)
			),
			'pricing[][period]' => array(
				'format' => array(
					'if_set' => true,
					'rule' => array(array($this, "validatePeriod")),
					'message' => $this->_("Packages.!error.pricing[][period].format")
				)
			),
			'pricing[][price]' => array(
				'format' => array(
					'if_set' => true,
					'pre_format' => array(array($this, "currencyToDecimal"), array('_linked'=>"pricing[][currency]"), 4),
					'rule' => "is_numeric",
					'message' => $this->_("Packages.!error.pricing[][price].format")
				)
			),
			'pricing[][setup_fee]' => array(
				'format' => array(
					'if_set' => true,
					'pre_format'=>array(array($this, "currencyToDecimal"), array('_linked'=>"pricing[][currency]"), 4),
					'rule' => "is_numeric",
					'message' => $this->_("Packages.!error.pricing[][setup_fee].format")
				)
			),
			'pricing[][cancel_fee]' => array(
				'format' => array(
					'if_set' => true,
					'pre_format'=>array(array($this, "currencyToDecimal"), array('_linked'=>"pricing[][currency]"), 4),
					'rule' => "is_numeric",
					'message' => $this->_("Packages.!error.pricing[][cancel_fee].format")
				)
			),
			'groups[]' => array(
				'exists' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateExists"), "id", "package_groups"),
					'message' => $this->_("Packages.!error.groups[].exists")
				),
				'valid' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateGroup"), isset($vars['company_id']) ? $vars['company_id'] : null),
					'message' => $this->_("Packages.!error.groups[].valid")
				)
			)
		);
		
		if (!isset($vars['module_row']) || $vars['module_row'] == 0)
			unset($rules['module_row']);
		
		return $rules;
	}
}
?>