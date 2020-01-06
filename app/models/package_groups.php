<?php
/**
 * Package Group management
 * 
 * @package blesta
 * @subpackage blesta.app.models
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class PackageGroups extends AppModel {
	
	/**
	 * Initialize PackageGroups
	 */
	public function __construct() {
		parent::__construct();
		Language::loadLang(array("package_groups"));
	}	
	
	/**
	 * Returns a list of supported package group types
	 *
	 * @return array A list of package group types and their language name
	 */
	public function getTypes() {
		return array(
			'standard'=>$this->_("PackageGroups.gettypes.standard"),
			'addon'=>$this->_("PackageGroups.gettypes.addon")
		);
	}
	
	/**
	 * Retrieves the number of package groups of a given type
	 *
	 * @param int $company_id The ID of the company whose package groups to count
	 * @param string $type The type of package groups to count ("standard" or "addon", default "standard")
	 * @return int The number of package groups of the given type
	 */
	public function getTypeCount($company_id, $type="standard") {
		return $this->Record->select("id")->from("package_groups")->
			where("company_id", "=", $company_id)->
			where("type", "=", $type)->
			numResults();
	}
	
	/**
	 * Returns a package group
	 *
	 * @param int $package_group_id The package group ID
	 * @return mixed An stdClass object representing the package group, or false if the package group does not exist
	 */
	public function get($package_group_id) {
		$package_group = $this->Record->select()->from("package_groups")->where("id", "=", $package_group_id)->fetch();
		
		if ($package_group) {
			// Get package group parents
			if ($package_group->type == "addon") {
				$package_group->parents = $this->Record->select("package_groups.*")->from("package_group_parents")->
					innerJoin("package_groups", "package_groups.id", "=", "package_group_parents.parent_group_id", false)->
					where("package_group_parents.group_id", "=", $package_group->id)->
					fetchAll();
			}
		}
		
		return $package_group;
	}
	
	/**
	 * Fetches all package groups for a given company
	 *
	 * @param int $company_id The company ID
	 * @param string $type The type of package group to get ('standard' or 'addon', optional, default both)
	 * @return array An array of stdClass objects representing each package group
	 */
	public function getAll($company_id, $type=null) {
		$this->Record->select()->from("package_groups")->where("company_id", "=", $company_id);
		
		// Specify a type to get
		if ($type != null)
			$this->Record->where("type", "=", $type);
		
		return $this->Record->fetchAll();
	}
	
	/**
	 * Fetches a list of all package groups for a given company
	 * 
	 * @param int $company_id The company ID to fetch package groups for
	 * @param int $page The page to return results for
	 * @param string $type The type of package group to get ("standard" or "addon", null for both)
	 * @param array $order_by The sort and order conditions (e.g. array('sort_field'=>"ASC"), optional)
	 * @return array An array of objects, each representing a package group
	 */
	public function getList($company_id, $page=1, $type=null, array $order_by=array('name'=>"asc")) {
		$this->Record = $this->getPackageGroups($company_id, $type);

		// Return the results
		$package_groups = $this->Record->order($order_by)->
			limit($this->getPerPage(), (max(1, $page) - 1)*$this->getPerPage())->fetchAll();
		
		foreach ($package_groups as &$package_group) {
			$package_group->parents = $this->Record->select("package_groups.*")->from("package_group_parents")->
				innerJoin("package_groups", "package_groups.id", "=", "package_group_parents.parent_group_id", false)->
				where("package_group_parents.group_id", "=", $package_group->id)->
				fetchAll();
		}
		
		return $package_groups;
	}
	
	/**
	 * Return the total number of package groups returned from PackageGroups::getList(),
	 * useful in constructing pagination for the getList() method.
	 *
	 * @param int $company_id The company ID to fetch package groups for
	 * @param string $type The type of package group to get ("standard" or "addon", null for both)
	 * @return int The total number of package groups
	 * @see PackageGroups::getList()
	 */
	public function getListCount($company_id, $type=null) {
		$this->Record = $this->getPackageGroups($company_id, $type);
		
		// Return the number of results
		return $this->Record->numResults();
	}
	
	/**
	 * Partially constructs the query required by both PackageGroups::getList() and
	 * PackageGroups::getListCount()
	 *
	 * @param int $company_id The company ID to fetch package groups for
	 * @param string $type The type of package group to get ("standard" or "addon", null for both)
	 * @return Record The partially constructed query Record object
	 */
	private function getPackageGroups($company_id, $type=null) {
		$this->Record->select()->from("package_groups")->where("company_id", "=", $company_id);
		
		if ($type != null)
			$this->Record->where("type", "=", $type);
		
		return $this->Record;
	}
	
	/**
	 * Adds a package group for the given company
	 *
	 * @param array $vars An array of package group info including:
	 * 	- company_id The ID for the company under which to add the package group
	 * 	- name The package group name
	 * 	- type The package group type, ('standard', or 'addon', optional, default 'standard')
	 * 	- description A description for this package group (optional)
	 * 	- parents If type is 'addon', an array of 'standard' package groups this group belongs to
	 * 	- allow_upgrades Whether or not packages within this group can be changed. 1 = true, 0 = false (optional, default 1)
	 * @return int The package group ID, void on error
	 */
	public function add(array $vars) {
		$this->Input->setRules($this->getRules($vars));
		
		if ($this->Input->validates($vars)) {
			$fields = array("name", "type", "description", "company_id", "allow_upgrades");
			$this->Record->insert("package_groups", $vars, $fields);
			$package_group_id = $this->Record->lastInsertId();
			
			if ($vars['type'] == "addon" && isset($vars['parents'])) {
				// Add all parent groups that this group belongs to
				foreach ($vars['parents'] as $parent_group_id) {
					$this->Record->set("group_id", $package_group_id)->
						set("parent_group_id", $parent_group_id)->
						insert("package_group_parents");
				}
			}
			
			return $package_group_id;
		}
	}
	
	/**
	 * Updates a package group
	 *
	 * @param int $package_group_id The package group ID to update
	 * @param array $vars An array of package group info including:
	 *  - company_id The ID for the company to which this package group belongs
	 * 	- name The package group name
	 * 	- type The package group type, 'standard', or 'addon' (optional, default standard)
	 * 	- description A description for this package group (optional)
	 * 	- parents If type is 'addon', a numerically indexed array of 'standard' package groups this group belongs to
	 * 	- allow_upgrades Whether or not packages within this group can be changed. 1 = true, 0 = false (optional, default 1)
	 */
	public function edit($package_group_id, array $vars) {
		$rules = $this->getRules($vars, true);
		
		$this->Input->setRules($rules);
		
		if ($this->Input->validates($vars)) {
			$fields = array("name", "type", "description", "allow_upgrades");
			$this->Record->where("id", "=", $package_group_id)->update("package_groups", $vars, $fields);
			
			// Delete all from parents, re-add as needed
			$this->Record->from("package_group_parents")->
				where("group_id", "=", $package_group_id)->delete();
			
			if ($vars['type'] == "addon") {
				if (isset($vars['parents'])) {
					// Add all parent groups this group belongs to
					foreach ($vars['parents'] as $parent_group_id) {
						$this->Record->set("group_id", $package_group_id)->
							set("parent_group_id", $parent_group_id)->
							insert("package_group_parents");
					}
				}
			}
		}
	}
	
	/**
	 * Permanently removes a package group from the system. 
	 *
	 * @param int $package_group_id The package group ID to delete
	 */
	public function delete($package_group_id) {
		// Start a transaction
		$this->Record->begin();
		
		// Unassign any packages assigned to this package group
		$this->Record->from("package_group")->where("package_group_id", "=", $package_group_id)->delete();
		
		// Delete the references from package_group_parents to this package group
		$this->Record->from("package_group_parents")->where("group_id", "=", $package_group_id)->
			orWhere("parent_group_id", "=", $package_group_id)->delete();
		
		// Delete the package group itself
		$this->Record->from("package_groups")->where("id", "=", $package_group_id)->delete();
		
		$this->Record->commit();
	}
	
	/**
	 * Checks to ensure that every group parent consists of valid data
	 *
	 * @param array $parents A numerically-indexed array of parent group IDs
	 * @param int $company_id The company ID to which this group belongs
	 * @param string $type The type of group
	 * @return boolean True if every group parent consists of valid data, false otherwise
	 */
	public function validateGroupParents(array $parents, $company_id, $type) {
		if ($type != "addon") {
			// error, type must be addon
			return false;
		}
		
		// Get all groups that could potentially be a parent
		$standard_groups = $this->getAll($company_id, "standard");
		
		// Create a list of available parent groups
		$available_groups = array();
		foreach ($standard_groups as $standard_group)
			$available_groups[] = $standard_group->id;
		
		// Check that every parent group ID given is in our list of available parent groups
		foreach ($parents as $parent_group_id) {
			if (!in_array($parent_group_id, $available_groups))
				return false;
		}
		
		return true;		
	}
	
	/**
	 * Validates that the given type is a valid package group type
	 *
	 * @param string $type The package group type
	 * @return boolean True if the package group type is valid, false otherwise
	 */
	public function validateType($type) {
		$types = $this->getTypes();
		return isset($types[$type]);
	}
	
	/**
	 * Returns the rules for adding/editing package groups
	 *
	 * @param array $vars Key/value pairs of data to replace in language
	 * @return array The package group rules
	 */
	private function getRules(array $vars, $edit=false) {
		$rules = array(
			'name' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("PackageGroups.!error.name.empty")
				)
			),
			'type' => array(
				'format' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateType")),
					'message' => $this->_("PackageGroups.!error.type.format")
				)
			),
			'company_id' => array(
				'exists' => array(
					'rule' => array(array($this, "validateExists"), "id", "companies"),
					'message' => $this->_("PackageGroups.!error.company_id.exists")
				)
			),
			'parents' => array(
				'format' => array(
					'if_set' => true,
					'rule' => array(array($this, "validateGroupParents"), $this->ifSet($vars['company_id']), $this->ifSet($vars['type'])),
					'message' => $this->_("PackageGroups.!error.parents.format")
				)
			),
			'allow_upgrades' => array(
				'format' => array(
					'if_set' => true,
					'rule' => array("in_array", array(0, 1)),
					'message' => $this->_("PackageGroups.!error.allow_upgrades.format")
				)
			)
		);
		
		// No company_id will be passed on edit
		if ($edit)
			unset($rules['company_id']);
		
		return $rules;
	}
}
?>