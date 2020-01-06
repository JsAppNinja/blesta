<?php
/**
 * Module Field
 *
 * Stores information regarding a particular Module Field, which may consist of
 * a label, tooltip, input field, or some combination thereof.
 *
 * @package blesta
 * @subpackage blesta.components.modules
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 * @see ModuleFields
 */
class ModuleField {
	
	/**
	 * @var string The type of ModuleField
	 */
	public $type;
	
	/**
	 * @var array All parameters set for this ModuleField
	 */
	public $params = array();
	
	/**
	 * @var array All fields or tooltips attached to this label
	 */
	public $fields = array();
	
	/**
	 * Constructs a new ModuleField of the given type. Types directly correlate
	 * to Form helper method names.
	 *
	 * @param string $type The type of ModuleField
	 */
	public function __construct($type) {
		$this->type = $type;
	}
	
	/**
	 * Sets all parameters for this ModuleField, which will be dispatched to
	 * the appropriate Form helper method when needed, or to the tooltip.
	 *
	 * @param string $name The name of the parameter. For tooltip types this should be 'message'
	 * @param mixed $value The value of the parameter
	 * @return ModuleField
	 */
	public function setParam($name, $value) {
		$this->params[$name] = $value;
		return $this;
	}
	
	/**
	 * Sets the label associated with this specific field.
	 *
	 * @param ModuleField $label The ModuleField label to associated with this field
	 * @return ModuleField
	 */
	public function setLabel(ModuleField $label) {
		$this->label = $label;
		return $this;
	}
	
	/**
	 * Attaches a field to a label ModuleField, or a tooltip to a label ModuleField.
	 * Only field and tooltip types can be attached to a label. So the current
	 * object must be of type "label". And $field must be of some other type.
	 *
	 * @param ModuleField $field The ModuleField to attach to this label
	 * @return ModuleField
	 */
	public function attach(ModuleField $field) {
		if ($this->type != "label")
			return false;
		if ($field->type == "label")
			return false;
		
		if (!isset($this->fields))
			$this->fields = array();
			
		$this->fields[] = $field;
		return $this;
	}
}
?>