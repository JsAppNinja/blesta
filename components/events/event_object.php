<?php
/**
 * Event Object
 *
 * Holds event data regarding a single event, and is passed between the dispatcher and the listener
 *
 * @package blesta
 * @subpackage blesta.components.events
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class EventObject {
	
	/**
	 * @var string $event_name The name of the event
	 */
	protected $event_name;
	/**
	 * @var array $params An array of parameters held by this event
	 */
	protected $params;
	/**
	 * @var mixed $return_val The return value (if any) from the event listener
	 */
	protected $return_val;
	
	/**
	 * Creates a new EventObject
	 *
	 * @param string $event_name The name of the event
	 * @param array $params An array of parameters to be held by this event
	 */
	public function __construct($event_name, array $params=null) {
		$this->event_name = $event_name;
		$this->setParams($params);
	}
	
	/**
	 * Returns the name of this event
	 *
	 * @return string The name of the event
	 */
	public function getName() {
		return $this->event_name;
	}
	
	/**
	 * Returns the parameters set for this event
	 *
	 * @return array The parameters for the event
	 */
	public function getParams() {
		return $this->params;
	}
	
	/**
	 * Sets params for this event
	 * 
	 * @param array $params An array of parameters to be held by this event
	 */
	public function setParams(array $params=null) {
		$this->params = $params;
	}
	
	/**
	 * Returns the return value set for this event
	 *
	 * @return mixed The return value for the event
	 */
	public function getReturnVal() {
		return $this->return_val;
	}
	
	/**
	 * Sets the return value for this event
	 *
	 * @param mixed $return_val The return value for the event
	 */
	public function setReturnVal($return_val) {
		$this->return_val = $return_val;
	}
}
?>