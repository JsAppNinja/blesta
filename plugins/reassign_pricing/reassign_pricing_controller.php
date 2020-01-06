<?php
/**
 * Reassign Pricing parent controller
 *
 * @package blesta
 * @subpackage blesta.plugins.reassign_pricing
 * @copyright Copyright (c) 2015, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class ReassignPricingController extends AppController
{
    /**
     * Setup
     */
    public function preAction()
    {
        $this->structure->setDefaultView(APPDIR);
        parent::preAction();

        // Auto load language for the controller
        Language::loadLang(
            array(Loader::fromCamelCase(get_class($this))),
            null,
            dirname(__FILE__) . DS . "language" . DS
        );

        // Override default view directory
        $this->view->view = "default";
        $this->orig_structure_view = $this->structure->view;
        $this->structure->view = "default";
    }

    /**
     * Sets Pagination to the current view
     *
     * @param type $uri The pagination URI
     * @param type $total_results The total number of results
     * @param array $params Any key/value parameters to include
     */
    protected function setPagination($uri, $total_results, array $params = array())
    {
        // Overwrite default pagination settings
		$settings = array_merge(Configure::get("Blesta.pagination"), array(
				'total_results' => $total_results,
				'uri' => $uri,
				'params' => $params
			)
		);

		$this->helpers(array("Pagination"=>array($this->get, $settings)));
    }
}
