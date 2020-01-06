<?php
/**
 * The Migrator. Facilitates migration between one remote system and the local system
 *
 * @package blesta
 * @subpackage blesta.plugins.import_manager.components.migrators
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
abstract class Migrator {

	/**
	 * @var Record The database connection object to the local server
	 */
	protected $local;

	/**
	 * @var Record The database connection object to the remote server
	 */
	protected $remote;
	
	/**
	 * @var array A multi-dimensional array, each defined as a key (e.g. 'clients') that represents a key/value pair array of remote IDs with local IDs
	 */
	protected $mappings = array();
	
	/**
	 * Runs the import, sets any Input errors encountered
	 */
	abstract public function import();
	
	/**
	 * Construct
	 *
	 * @param Record $local The database connection object to the local server
	 */
	public function __construct(Record $local) {
		Loader::loadComponents($this, array("Input"));
		$this->local = $local;
	}
	
	/**
	 * Processes settings (validating input). Sets any necessary input errors
	 *
	 * @param array $vars An array of key/value input pairs
	 */
	public function processSettings(array $vars = null) {
		
	}

	/**
	 * Processes configuration (validating input). Sets any necessary input errors
	 *
	 * @param array $vars An array of key/value input pairs
	 */	
	public function processConfiguration(array $vars = null) {
		
	}
	
	/**
	 * Returns a view to handle settings
	 *
	 * @param array $vars An array of input key/value pairs
	 * @return string The HTML used to request input settings
	 */
	public function getSettings(array $vars) {
		
	}
	
	/**
	 * Returns a view to configuration run after settings but before import
	 *
	 * @param array $vars An array of input key/value pairs
	 * @return string The HTML used to request input settings, return null to bypass
	 */
	public function getConfiguration(array $vars) {
		return null;
	}
	
	/**
	 * Add staff
	 *
	 * @param array An array of key/value pairs
	 * @param mixed $remote_id The ID of this items on the remote server
	 * @return mixed
	 */
	public function addStaff(array $vars, $remote_id = null) {
		if (!isset($this->Staff))
			Loader::loadModels($this, array("Staff"));
			
		$result = $this->Staff->add($vars);
		if (($errors = $this->Staff->errors()))
			$this->Input->setErrors($errors);
		
		if ($remote_id !== null && $result)
			$this->mappings['staff'][$remote_id] = $result;
		
		return $result;
	}

	/**
	 * Add client
	 *
	 * @param array An array of key/value pairs
	 * @param mixed $remote_id The ID of this items on the remote server
	 * @return mixed
	 */	
	public function addClient(array $vars, $remote_id = null) {
		if (!isset($this->Clients))
			Loader::loadModels($this, array("Clients"));
			
		$result = $this->Clients->add($vars);
		if (($errors = $this->Clients->errors()))
			$this->Input->setErrors($errors);
			
		if ($remote_id !== null && $result)
			$this->mappings['clients'][$remote_id] = $result;
			
		return $result;
	}
	
	/**
	 * Add contact
	 *
	 * @param array An array of key/value pairs
	 * @param mixed $remote_id The ID of this items on the remote server
	 * @return mixed
	 */
	public function addContact(array $vars, $remote_id = null) {
		if (!isset($this->Contacts))
			Loader::loadModels($this, array("Contacts"));
			
		$result = $this->Contacts->add($vars);
		if (($errors = $this->Contacts->errors()))
			$this->Input->setErrors($errors);
			
		if ($remote_id !== null && $result)
			$this->mappings['contacts'][$remote_id] = $result;
			
		return $result;
	}
	
	/**
	 * Add tax
	 *
	 * @param array An array of key/value pairs
	 * @param mixed $remote_id The ID of this items on the remote server
	 * @return mixed
	 */
	public function addTax(array $vars, $remote_id = null) {
		if (!isset($this->Taxes))
			Loader::loadModels($this, array("Taxes"));
			
		$result = $this->Taxes->add($vars);
		if (($errors = $this->Taxes->errors()))
			$this->Input->setErrors($errors);
			
		if ($remote_id !== null && $result)
			$this->mappings['taxes'][$remote_id] = $result;
			
		return $result;
	}
	
	/**
	 * Add currency
	 *
	 * @param array An array of key/value pairs
	 * @param mixed $remote_id The ID of this items on the remote server
	 * @return mixed
	 */
	public function addCurrency(array $vars, $remote_id = null) {
		if (!isset($this->Currencies))
			Loader::loadModels($this, array("Currencies"));
			
		$result = $this->Currencies->add($vars);
		if (($errors = $this->Currencies->errors()))
			$this->Input->setErrors($errors);
			
		if ($remote_id !== null && $result)
			$this->mappings['currencies'][$remote_id] = $result;
			
		return $result;
	}

	/**
	 * Add invoice
	 *
	 * @param array An array of key/value pairs
	 * @param mixed $remote_id The ID of this items on the remote server
	 * @return mixed
	 */
	public function addInvoice(array $vars, $remote_id = null) {
		if (!isset($this->Invoices))
			Loader::loadModels($this, array("Invoices"));
			
		$result = $this->Invoices->add($vars);
		if (($errors = $this->Invoices->errors()))
			$this->Input->setErrors($errors);
			
		if ($remote_id !== null && $result)
			$this->mappings['invoices'][$remote_id] = $result;
			
		return $result;
	}
	
	/**
	 * Add transaction
	 *
	 * @param array An array of key/value pairs
	 * @param mixed $remote_id The ID of this items on the remote server
	 * @return mixed
	 */
	public function addTransaction(array $vars, $remote_id = null) {
		if (!isset($this->Transactions))
			Loader::loadModels($this, array("Transactions"));
			
		$result = $this->Transactions->add($vars);
		if (($errors = $this->Transactions->errors()))
			$this->Input->setErrors($errors);
			
		if ($remote_id !== null && $result)
			$this->mappings['transactions'][$remote_id] = $result;
			
		return $result;
	}
	
	/**
	 * Add package
	 *
	 * @param array An array of key/value pairs
	 * @param mixed $remote_id The ID of this items on the remote server
	 * @return mixed
	 */
	public function addPackage(array $vars, $remote_id = null) {
		if (!isset($this->Packages))
			Loader::loadModels($this, array("Packages"));
			
		$result = $this->Packages->add($vars);
		if (($errors = $this->Packages->errors()))
			$this->Input->setErrors($errors);
			
		if ($remote_id !== null && $result)
			$this->mappings['packages'][$remote_id] = $result;
			
		return $result;
	}
	
	/**
	 * Add coupon
	 *
	 * @param array An array of key/value pairs
	 * @param mixed $remote_id The ID of this items on the remote server
	 * @return mixed
	 */
	public function addCoupon(array $vars, $remote_id = null) {
		if (!isset($this->Coupons))
			Loader::loadModels($this, array("Coupons"));
			
		$result = $this->Coupons->add($vars);
		if (($errors = $this->Coupons->errors()))
			$this->Input->setErrors($errors);
			
		if ($remote_id !== null && $result)
			$this->mappings['coupons'][$remote_id] = $result;
			
		return $result;
	}
	
	/**
	 * Add service
	 *
	 * @param array An array of key/value pairs
	 * @param mixed $remote_id The ID of this items on the remote server
	 * @return mixed
	 */
	public function addService(array $vars, $remote_id = null) {
		if (!isset($this->Services))
			Loader::loadModels($this, array("Services"));
			
		$result = $this->Services->add($vars);
		if (($errors = $this->Services->errors()))
			$this->Input->setErrors($errors);
			
		if ($remote_id !== null && $result)
			$this->mappings['services'][$remote_id] = $result;
			
		return $result;
	}
	
	/**
	 * Add support department
	 *
	 * @param array An array of key/value pairs
	 * @param mixed $remote_id The ID of this items on the remote server
	 * @return mixed
	 */
	public function addSupportDepartment(array $vars, $remote_id = null) {
		if (!isset($this->SupportManagerDepartments))
			Loader::loadModels($this, array("SupportManager.SupportManagerDepartments"));
			
		$result = $this->SupportManagerDepartments->add($vars);
		if (($errors = $this->SupportManagerDepartments->errors()))
			$this->Input->setErrors($errors);
			
		if ($remote_id !== null && $result)
			$this->mappings['support_departments'][$remote_id] = $result->id;
			
		return $result;
	}
	
	/**
	 * Add support ticket
	 *
	 * @param array An array of key/value pairs
	 * @param mixed $remote_id The ID of this items on the remote server
	 * @return mixed
	 */
	public function addSupportTicket(array $vars, $remote_id = null) {
		if (!isset($this->SupportManagerTickets))
			Loader::loadModels($this, array("SupportManager.SupportManagerTickets"));
			
		$result = $this->SupportManagerTickets->add($vars);
		if (($errors = $this->SupportManagerTickets->errors()))
			$this->Input->setErrors($errors);
			
		if ($remote_id !== null && $result)
			$this->mappings['support_tickets'][$remote_id] = $result;
			
		return $result;
	}
	
	/**
	 * Returns any input errors encountered
	 *
	 * @return array An array of input errors
	 */
	public function errors() {
		return $this->Input->errors();
	}
	
	/**
	 * Initializes a View object and returns it
	 *
	 * @param string $file The view file to load
	 * @param string $view The view directory name to find the view file
	 * @param string $view_path The path to the $view relative to the root web directory
	 * @return View An instance of the View object
	 */
	protected function makeView($file, $view="default", $view_path=null) {
		// Load the view into this object, so helpers can be automatically added to the view
		$view = new View($file, $view);
		
		if ($view_path !== null)
			$view->setDefaultView($view_path);
		return $view;
	}
}
?>