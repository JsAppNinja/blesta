<?php
/**
 * Module Fields
 *
 * Provides the structure for modules to set which fields to appear when
 * interacting with the module via adding/editing packages or services.
 *
 * @package blesta
 * @subpackage blesta.components.modules
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 * @see ModuleFiled
 */
class ModuleFields {

	/**
	 * @var array An array of ModuleField objects, each representing a single input field and (optionally) its label
	 */
	private $fields = array();
	/**
	 * @var string A string of HTML markup to include when outputting the fields. This can include things such as javascript.
	 */
	private $html = null;

	/**
	 * Returns an array of fields set for this group of fields
	 *
	 * @return array An array of ModuleField objects set for this group of fields
	 */
	public function getFields() {
		return $this->fields;
	}
	
	/**
	 * Returns an HTML content set for this group of fields
	 *
	 * @return string The HTML content set for this group of fields
	 */
	public function getHtml() {
		return $this->html;
	}

	/**
	 * Sets HTML content to be rendered into the view when outputting the fields.
	 * This is intended to allow for the inclusion of javascript to dynamically
	 * handle the rendering of the fields, but is not limited to such.
	 *
	 * @param string $html The HTML content to render into the view when outputting the fields
	 */
	public function setHtml($html) {
		$this->html = $html;
	}

	/**
	 * Sets the field into the collection of ModuleField objects
	 * 
	 * @param ModuleField A ModuleField object to be passed set into the list of ModuleField objects
	 */
	public function setField(ModuleField $field) {
		$this->fields[] = $field;
	}

	/**
	 * Creates a label with the given name and marks it for the given field
	 *
	 * @param string $name The name of this label
	 * @param string $for The ID of the form element this label is part of
	 * @param array $attributes Attributes for this label
	 * @param boolean $preserve_tags True to preserve tags in the label name
	 * @return ModuleField A ModuleField object to be passed into one of the various field methods to assign this label to that field
	 */
	public function label($name, $for=null, array $attributes=null, $preserve_tags=false) {
		
		$label = new ModuleField("label");
		$label->setParam("name", $name);
		$label->setParam("for", $for);
		$label->setParam("attributes", $attributes);
		$label->setParam("preserve_tags", $preserve_tags);
		
		return $label;
	}
	
	/**
	 * Creates a tooltip with the given message
	 *
	 * @param string $message The tooltip message
	 * @return ModuleField A ModuleField object that can be attached to a ModuleField label
	 */
	public function tooltip($message) {
		
		$tooltip = new ModuleField("tooltip");
		$tooltip->setParam("message", $message);
		
		return $tooltip;
	}
	
	/**
	 * Creates a text input field
	 *
	 * @param string $name The name to set in the HTML name field
	 * @param string $value The value to set in the HTML value field
	 * @param array $attributes Attributes for this input field
	 * @param ModuleField A ModuleField object representing the label to attach to this field (see ModuleFields::label)
	 * @return ModuleField A ModuleField object that can be attached to a ModuleField label
	 * @see ModuleFields::label()
	 * @see ModuleField::attach()
	 */
	public function fieldText($name, $value=null, $attributes=array(), ModuleField $label=null) {
		
		$field = new ModuleField("fieldText");
		$field->setParam("name", $name);
		$field->setParam("value", $value);
		$field->setParam("attributes", $attributes);
		
		if ($label)
			$field->setLabel($label);
		
		return $field;
	}
	
	/**
	 * Creates a hidden input field
	 *
	 * @param string $name The name to set in the HTML name field
	 * @param string $value The value to set in the HTML value field
	 * @param array $attributes Attributes for this input field
	 * @return ModuleField A ModuleField object that can be attached to a ModuleField label
	 * @see ModuleFields::label()
	 * @see ModuleField::attach()
	 */	
	public function fieldHidden($name, $value=null, $attributes=array()) {
		
		$field = new ModuleField("fieldHidden");
		$field->setParam("name", $name);
		$field->setParam("value", $value);
		$field->setParam("attributes", $attributes);
		
		return $field;
	}
	
	/**
	 * Creates an image input field
	 *
	 * @param string $name The name to set in the HTML name field
	 * @param string $value The value to set in the HTML value field
	 * @param array $attributes Attributes for this input field
	 * @param ModuleField A ModuleField object representing the label to attach to this field (see ModuleFields::label)
	 * @return ModuleField A ModuleField object that can be attached to a ModuleField label
	 * @see ModuleFields::label()
	 * @see ModuleField::attach()
	 */
	public function fieldImage($name, $value=null, $attributes=array(), ModuleField $label=null) {
		
		$field = new ModuleField("fieldImage");
		$field->setParam("name", $name);
		$field->setParam("value", $value);
		$field->setParam("attributes", $attributes);
		
		if ($label)
			$field->setLabel($label);
		
		return $field;
	}
	
	/**
	 * Creates a reset input field
	 *
	 * @param string $name The name to set in the HTML name field
	 * @param string $value The value to set in the HTML value field
	 * @param array $attributes Attributes for this input field
	 * @param ModuleField A ModuleField object representing the label to attach to this field (see ModuleFields::label)
	 * @return ModuleField A ModuleField object that can be attached to a ModuleField label
	 * @see ModuleFields::label()
	 * @see ModuleField::attach()
	 */
	public function fieldReset($name, $value=null, $attributes=array(), ModuleField $label=null) {
		
		$field = new ModuleField("fieldReset");
		$field->setParam("name", $name);
		$field->setParam("value", $value);
		$field->setParam("attributes", $attributes);
		
		if ($label)
			$field->setLabel($label);
		
		return $field;
	}

	/**
	 * Creates a checkbox
	 *
	 * @param string $name The name to set in the HTML name field
	 * @param string $value The value to set in the HTML value field
	 * @param boolean $checked True to set this field as checked
	 * @param array $attributes Attributes for this input field
	 * @param ModuleField A ModuleField object representing the label to attach to this field (see ModuleFields::label)
	 * @return ModuleField A ModuleField object that can be attached to a ModuleField label
	 * @see ModuleFields::label()
	 * @see ModuleField::attach()
	 */
	public function fieldCheckbox($name, $value=null, $checked=false, $attributes=array(), ModuleField $label=null) {
		
		$field = new ModuleField("fieldCheckbox");
		$field->setParam("name", $name);
		$field->setParam("value", $value);
		$field->setParam("checked", $checked);
		$field->setParam("attributes", $attributes);
		
		if ($label)
			$field->setLabel($label);
		
		return $field;
	}
	
	/**
	 * Creates a radio box
	 *
	 * @param string $name The name to set in the HTML name field
	 * @param string $value The value to set in the HTML value field
	 * @param boolean $checked True to set this field as checked
	 * @param array $attributes Attributes for this input field
	 * @param ModuleField A ModuleField object representing the label to attach to this field (see ModuleFields::label)
	 * @return ModuleField A ModuleField object that can be attached to a ModuleField label
	 * @see ModuleFields::label()
	 * @see ModuleField::attach()
	 */	
	public function fieldRadio($name, $value=null, $checked=false, $attributes=array(), ModuleField $label=null) {
		
		$field = new ModuleField("fieldRadio");
		$field->setParam("name", $name);
		$field->setParam("value", $value);
		$field->setParam("checked", $checked);
		$field->setParam("attributes", $attributes);
		
		if ($label)
			$field->setLabel($label);
		
		return $field;
	}

	/**
	 * Creates a textarea field
	 *
	 * @param string $name The name to set in the HTML name field
	 * @param string $value The value to set in this textarea
	 * @param array $attributes Attributes for this input field
	 * @param ModuleField A ModuleField object representing the label to attach to this field (see ModuleFields::label)
	 * @return ModuleField A ModuleField object that can be attached to a ModuleField label
	 * @see ModuleFields::label()
	 * @see ModuleField::attach()
	 */
	public function fieldTextarea($name, $value=null, $attributes=array(), ModuleField $label=null) {
		
		$field = new ModuleField("fieldTextarea");
		$field->setParam("name", $name);
		$field->setParam("value", $value);
		$field->setParam("attributes", $attributes);
		
		if ($label)
			$field->setLabel($label);
		
		return $field;
	}	

	/**
	 * Creates a password input field
	 *
	 * @param string $name The name to set in the HTML name field
	 * @param array $attributes Attributes for this input field
	 * @param ModuleField A ModuleField object representing the label to attach to this field (see ModuleFields::label)
	 * @return ModuleField A ModuleField object that can be attached to a ModuleField label
	 * @see ModuleFields::label()
	 * @see ModuleField::attach()
	 */
	public function fieldPassword($name, $attributes=array(), ModuleField $label=null) {
		
		$field = new ModuleField("fieldPassword");
		$field->setParam("name", $name);
		$field->setParam("attributes", $attributes);
		
		if ($label)
			$field->setLabel($label);
		
		return $field;
	}
	
	/**
	 * Creates a file input field
	 *
	 * @param string $name The name to set in the HTML name field
	 * @param array $attributes Attributes for this input field
	 * @param ModuleField A ModuleField object representing the label to attach to this field (see ModuleFields::label)
	 * @return ModuleField A ModuleField object that can be attached to a ModuleField label
	 * @see ModuleFields::label()
	 * @see ModuleField::attach()
	 */
	public function fieldFile($name, $attributes=array(), ModuleField $label=null) {
		
		$field = new ModuleField("fieldFile");
		$field->setParam("name", $name);
		$field->setParam("attributes", $attributes);
		
		if ($label)
			$field->setLabel($label);
		
		return $field;
	}

	/**
	 * Creates a select list
	 *
	 * @param string $name The name to set in the HTML name field
	 * @param array $options The options to place in this select list
	 * @param mixed $selected_value The option(s) to set as selected
	 * @param array $attributes Attributes for this input field
	 * @param array $option_attributes Attributes for each option to set. If single dimension will set the attributes for every option, if multi-dimensional will set option for each element key that matches in $options
	 * @param ModuleField A ModuleField object representing the label to attach to this field (see ModuleFields::label)
	 * @return ModuleField A ModuleField object that can be attached to a ModuleField label
	 * @see ModuleFields::label()
	 * @see ModuleField::attach()
	 */	
	public function fieldSelect($name, $options=array(), $selected_value=null, $attributes=array(), $option_attributes=array(), ModuleField $label=null) {
		
		$field = new ModuleField("fieldSelect");
		$field->setParam("name", $name);
		$field->setParam("options", $options);
		$field->setParam("selected_value", $selected_value);
		$field->setParam("attributes", $attributes);
		$field->setParam("option_attributes", $option_attributes);
		
		if ($label)
			$field->setLabel($label);
		
		return $field;
	}
	
	/**
	 * Creates a select list with multiple selectable options
	 *
	 * @param string $name The name to set in the HTML name field
	 * @param array $options The options to place in this select list
	 * @param string $selected_values The options to set as selected
	 * @param array $attributes Attributes for this input field
	 * @param array $option_attributes Attributes for each option to set. If single dimension will set the attributes for every option, if multi-dimensional will set option for each element key that matches in $options
	 * @param ModuleField A ModuleField object representing the label to attach to this field (see ModuleFields::label)
	 * @return ModuleField A ModuleField object that can be attached to a ModuleField label
	 * @see ModuleFields::label()
	 * @see ModuleField::attach()
	 */		
	public function fieldMultiSelect($name, $options=array(), $selected_values=array(), $attributes=array(), $option_attributes=array(), ModuleField $label=null) {
		
		$field = new ModuleField("fieldMultiSelect");
		$field->setParam("name", $name);
		$field->setParam("options", $options);
		$field->setParam("selected_value", $selected_values);
		$field->setParam("attributes", $attributes);
		$field->setParam("option_attributes", $option_attributes);
		
		if ($label)
			$field->setLabel($label);
		
		return $field;
	}
	
	/**
	 * Creates a button with default type=button, can be overriden by attirbutes
	 *
	 * @param string $name The name to set in the HTML name field
	 * @param string $value The value to set in the HTML value field
	 * @param array $attributes Attributes for this input field
	 * @param ModuleField A ModuleField object representing the label to attach to this field (see ModuleFields::label)
	 * @return ModuleField A ModuleField object that can be attached to a ModuleField label
	 * @see ModuleFields::label()
	 * @see ModuleField::attach()
	 */	
	public function fieldButton($name, $value=null, $attributes=array(), ModuleField $label=null) {

		$field = new ModuleField("fieldButton");
		$field->setParam("name", $name);
		$field->setParam("value", $value);
		$field->setParam("attributes", $attributes);
		
		if ($label)
			$field->setLabel($label);
		
		return $field;
	}
	
	/**
	 * Creates a button of type submit
	 *
	 * @param string $name The name to set in the HTML name field
	 * @param string $value The value to set in the HTML value field
	 * @param array $attributes Attributes for this input field
	 * @param ModuleField A ModuleField object representing the label to attach to this field (see ModuleFields::label)
	 * @return ModuleField A ModuleField object that can be attached to a ModuleField label
	 * @see ModuleFields::label()
	 * @see ModuleField::attach()
	 */		
	public function fieldSubmit($name, $value=null, $attributes=array(), ModuleField $label=null) {
		
		$field = new ModuleField("fieldSubmit");
		$field->setParam("name", $name);
		$field->setParam("value", $value);
		$field->setParam("attributes", $attributes);
		
		if ($label)
			$field->setLabel($label);
		
		return $field;
	}
}
?>