<?php
/**
 * CMS Pages
 *
 * Manages CMS pages
 *
 * @package blesta
 * @subpackage blesta.plugins.cms.models
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */

class CmsPages extends CmsModel {
	
	/**
	 * Initialize
	 */
	public function __construct() {
		parent::__construct();
		
		Language::loadLang("cms_pages", null, PLUGINDIR . "cms" . DS . "language" . DS);
	}
	
	/**
	 * Adds a new CMS page or updates an existing one
	 *
	 * @param array $vars A list of input vars for creating a CMS page, including:
	 * 	- uri The URI of the page
	 * 	- company_id The ID of the company this page belongs to
	 * 	- title The page title
	 * 	- content The page content
	 */
	public function add(array $vars) {
		// Set rules
		$this->Input->setRules($this->getRules($vars));
		
		// Add a new CMS page
		if ($this->Input->validates($vars)) {
			$fields = array("uri", "company_id", "title", "content");
			$this->Record->duplicate("title", "=", $vars['title'])->
				duplicate("content", "=", $vars['content'])->
				insert("cms_pages", $vars, $fields);
		}
	}
	
	/**
	 * Fetches a page at the given URI for the given company
	 *
	 * @param string $uri The URI of the page
	 * @param int $company_id The ID of the company the page belongs to
	 * @return mixed An stdClass object representing the CMS page, or false if none exist
	 */
	public function get($uri, $company_id) {
		return $this->Record->select()->from("cms_pages")->
			where("uri", "=", $uri)->
			where("company_id", "=", $company_id)->
			fetch();
	}
	
	/**
	 * Retrieves a list of input rules for adding a CMS page
	 *
	 * @param array $vars A list of input vars
	 * @return array A list of input rules
	 */
	private function getRules(array $vars) {
		$rules = array(
			'uri' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("CmsPages.!error.uri.empty")
				)
			),
			'company_id' => array(
				'exists' => array(
					'rule' => array(array($this, "validateExists"), "id", "companies"),
					'message' => $this->_("CmsPages.!error.company_id.exists")
				)
			),
			'title' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => $this->_("CmsPages.!error.title.empty")
				)
			)
		);
		
		return $rules;
	}
}
?>