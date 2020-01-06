<?php
/**
 * API Key management
 *
 * @package blesta
 * @subpackage blesta.app.models
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class ApiKeys extends AppModel {
	
	public function __construct() {
		parent::__construct();
		Language::loadLang(array("api_keys"));
	}
	
	/**
	 * Authenticates the given credentials and returns the company ID the API user
	 * has access to.
	 *
	 * @param string $user The API user
	 * @param string $key The API user's key
	 * @return int The ID of the company the user belongs to, void if the credentials are invalid. Raises Input::errors() on error.
	 */
	public function auth($user, $key) {
		
		$result = $this->Record->select(array("company_id"))->from("api_keys")->
			where("user", "=", $user)->
			where("key", "=", $key)->fetch();
		
		if ($result)
			return $result->company_id;
		else {
			$this->Input->setErrors(array(
				'user' => array(
					'valid'=>$this->_("ApiKeys.!error.user.valid")
				)
			));
		}
	}
	
	/**
	 * Returns a list of API keys
	 *
	 * @param int $page The page to fetch results on
	 * @param array $order_by $order_by The sort and order conditions (e.g. array('sort_field'=>"ASC"))
	 * @return array An array of stdClass objects
	 */
	public function getList($page=1, $order_by=array('date_created'=>"desc")) {
		$this->Record = $this->keys();
		
		return $this->Record->
			order($order_by)->
			limit($this->getPerPage(), (max(1, $page) - 1)*$this->getPerPage())->fetchAll();
	}
	
	/**
	 * Returns a count of the number of results in a list of API keys
	 *
	 * @return int The number of results in a list of API keys
	 */
	public function getListCount() {
		$this->Record = $this->keys();
		
		return $this->Record->numResults();
	}
	
	/**
	 * Fetches the API key information for the given user
	 *
	 * @param int $id The ID of the key to fetch
	 * @return mixed A stdClass object representing the API key, false if no such key exists
	 */
	public function get($id) {
		$this->Record = $this->keys();
		
		return $this->Record->where("api_keys.id", "=", $id)->fetch();
	}
	
	/**
	 * Builds a partial query to fetch a list of API keys
	 *
	 * @return Record
	 */
	private function keys() {
		return $this->Record->select(array("api_keys.*",'companies.name'=>"company_name"))->
			from("api_keys")->innerJoin("companies", "companies.id", "=", "api_keys.company_id", false);
		
	}
	
	/**
	 * Adds a new API key for the given company ID and user.
	 *
	 * @param array $vars An array of API credential information including:
	 * 	-company_id The ID of the company to add the API key for
	 * 	-user The user to use as the API user
	 */
	public function add(array $vars) {
		
		$vars['date_created'] = date("Y-m-d H:i:s");
		$vars['key'] = "";
		$this->Input->setRules($this->getRules($vars));
		
		if ($this->Input->validates($vars)) {
			$fields = array("company_id", "user", "key", "date_created", "notes");
			$this->Record->insert("api_keys", $vars, $fields);
		}
	}
	
	/**
	 * Updates an API key
	 *
	 * @param int $id The ID of the API key to edit
	 * @param array $var An array of API key data to update including:
	 * 	-notes Notes about this key
	 * 	-user The username of the API key
	 * 	-company_id The ID of the company the API key belongs to
	 */
	public function edit($id, array $vars) {
		
		$vars['id'] = $id;
		$rules = $this->getRules($vars);
		unset($rules['key']);
		
		$this->Input->setRules($rules);
		
		if ($this->Input->validates($vars)) {
			
			$fields = array("user","company_id","notes");
			$this->Record->where("id", "=", $id)->
				update("api_keys", $vars, $fields);
		}
	}
	
	/**
	 * Permanently removes an API key
	 *
	 * @param int $id The ID of the API key to delete
	 */
	public function delete($id) {
		$this->Record->from("api_keys")->
			where("id", "=", $id)->delete();
	}
	
	/**
	 * Generates an API key using the company ID as a seed. Not intended to be
	 * invoked independently. See ApiKeys::add().
	 *
	 * @param string $key The variable to set the key into
	 * @param int $company_id The ID of the company to generate the key for
	 * @return string The generated key for the given company ID
	 * @see ApiKeys::add()
	 */
	public function generateKey($key, $company_id) {
		// Generate a sufficiently large random value
		Loader::load(VENDORDIR . "phpseclib" . DS . "Crypt" . DS . "Random.php");
		$data = md5(crypt_random() . uniqid(php_uname('n'), true)) . md5(uniqid(php_uname('n'), true) . crypt_random());
		
		$key = $this->systemHash($key . $data, $company_id, "md5");
		return $key;
	}
	
	/**
	 * Validates the given user is unique across all API keys for the given company
	 *
	 * @param string $user The user to be validated against the given company
	 * @param int $company_id The company ID to validate uniqueness across
	 * @param int $api_id The ID of the API key (if given) to exclude from the uniqueness test
	 * @return boolean True if the user is unique for the given company (besides this $api_id), false otherwise
	 */
	public function validateUniqueUser($user, $company_id, $api_id=null) {
		$this->Record->select("id")->from("api_keys")->where("user", "=", $user)->
			where("company_id", "=", $company_id);
			
		if ($api_id !== null)
			$this->Record->where("id", "!=", $api_id);
			
		return  !($this->Record->numResults() > 0);
	}
	
	/**
	 * Rules to validate when adding an API key
	 *
	 * @return array Rules to validate
	 */
	private function getRules(array $vars) {
		$rules = array(
			'company_id' => array(
				'valid' => array(
					'rule' => array(array($this, "validateExists"), "id", "companies"),
					'message' => $this->_("ApiKeys.!error.company_id.exists")
				)				
			),
			'user' => array(
				'valid' => array(
					'rule' => array("betweenLength", 3, 64),
					'message' => $this->_("ApiKeys.!error.user.exists")
				),
				'unique' => array(
					'rule' => array(array($this, "validateUniqueUser"), $this->ifSet($vars['company_id']), $this->ifSet($vars['id'])),
					'message' => $this->_("ApiKeys.!error.user.unique")
				)
			),
			'key' => array(
				'generate' => array(
					'pre_format' => array(array($this, "generateKey"), $vars['company_id']),
					'rule' => array("betweenLength", 16, 64),
					'message' => $this->_("ApiKeys.!error.key.generate")
				)
			)
		);
		
		return $rules;
	}
}
?>