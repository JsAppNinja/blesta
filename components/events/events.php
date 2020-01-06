<?php
Loader::load(dirname(__FILE__) . DS . "event_object.php");
Loader::load(dirname(__FILE__) . DS . "default" . DS . "event_callback.php");

/**
 * Event Handler
 *
 * Stores callbacks for particular events that may be executed when the event
 * is triggered. Events are static, so each instance may register events
 * triggered in this or other instances of the Event handler. 
 * 
 * @package blesta
 * @subpackage blesta.components.events
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class Events {
	/**
	 * @var array Holds all registered listeners
	 */
	private static $listeners;
	
	/**
	 * Register a listener, to be notified when the event is triggered. Only permits
	 * one registered event per callback.
	 *
	 * @param string $event_name The name of the event to register $callback under
	 * @param callback $callback The Class/Method or Object/Method or function to execute when the event is triggered.
	 * @param string $file_name The full path to the file that contains the callback, null will default to looking in COMPONENTDIR/events/default/class_name.php (where class_name is the filecase format of the callback class [e.g. ClassName])
	 */
	public function register($event_name, $callback, $file_name=null) {
		
		if ($file_name !== null)
			Loader::load($file_name);
			
		if (is_array($callback)) {
			// If the 1st parameter isn't an object, attempt to load the class
			if (!is_object($callback[0])) {
				// Load the default file for the callback
				if ($file_name === null) {
					$class_name = Loader::fromCamelCase($callback[0]);
					$class_file = COMPONENTDIR . "events" . DS . "default" . DS . $class_name . ".php";
	
					// Load the class
					Loader::load($class_file);
				}
			}
		}
		
		if (empty(self::$listeners[$event_name]) || !in_array($callback, self::$listeners[$event_name]))
			self::$listeners[$event_name][] = $callback;
	}
	
	/**
	 * Unregisters a listener if the event has been registered. Will remove all
	 * copies of the registered event in the case that it was registered multiple times.
	 *
	 * @param string $event_name The name of the event to unregister $callback from
	 * @param callback $callback The Class/Method or Object/Method or function to unregister for the event
	 */
	public function unregister($event_name, $callback) {
		foreach ($this->getRegistered($event_name) as $i => $event_callback) {
			if ($callback == $event_callback)
				unset(self::$listeners[$event_name][$i]);
		}
	}
	
	/**
	 * Notifies all registered listeners of the event (called in the order they were set).
	 *
	 * @param EventObject $event The event object to pass to the registered listeners
	 * @return EventObject The event processed
	 */
	public function trigger(EventObject $event) {
		
		foreach ($this->getRegistered($event->getName()) as $callback) {
			if (is_array($callback)) {
				// If object not passed in and this callback class does not exist, skip
				if (!is_object($callback[0]) && !class_exists($callback[0]))
					continue;
			}
			call_user_func($callback, $event);
		}
		return $event;
	}
	
	/**
	 * Notifies all registered listeners of the event (called in the order they were set),
	 * until one returns true, then ceases notifying all remaining listeners.
	 *
	 * @param EventObject $event The event object to pass to the registered listeners
	 * @return EventObject The event processed
	 */
	public function triggerUntil(EventObject $event) {
		
		foreach ($this->getRegistered($event->getName()) as $callback) {
			if (is_array($callback)) {
				// If object not passed in and this callback class does not exist, skip
				if (!is_object($callback[0]) && !class_exists($callback[0]))
					continue;
			}
			if (call_user_func($callback, $event) === true)
				break;
		}
		return $event;
	}
	
	/**
	 * Returns all registered listeners for the given event name
	 *
	 * @param string $event_name The name of the event to fetch registered callbacks for
	 * @return array An array of registered callbacks
	 */
	public function getRegistered($event_name) {
		if (isset(self::$listeners[$event_name]))
			return self::$listeners[$event_name];
		return array();
	}
}
?>