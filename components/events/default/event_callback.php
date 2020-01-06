<?php
/**
 * Base Event Callback
 *
 * @package blesta
 * @subpackage blesta.components.events.default
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class EventCallback {
	
	/**
	 * Handle plugin event triggers. This event automatically triggers
	 * all registered plugin events for the given event.
	 *
	 * @param EventObject $event An event object
	 * @return EventObject The processed event object
	 */
	public static function triggerPluginEvent(EventObject $event) {
		
		if (!class_exists("PluginManager"))
			Loader::load(MODELDIR . "plugin_manager.php");
			
		$PluginManager = new PluginManager();
		
		$event = $PluginManager->invokeEvents(Configure::get("Blesta.company_id"), $event);
		unset($PluginManager);
		
		return $event;
	}
}
?>