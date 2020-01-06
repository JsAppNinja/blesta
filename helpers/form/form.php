<?php
Loader::load(HELPERDIR . "html" . DS . "html.php");

/**
 * Form helper, requires Html helper
 *
 * Allow the creation of forms and their respective fields and attributes
 *
 * @package minPHP
 * @subpackage minPHP.helpers.form
 */
class Form extends Html {
	/**
	 * @var string The string to use as the end of line character
	 */
	private $eol = "\n";
	
	/**
	 * @var boolean Whether or not to return output from various form methods
	 */
	private $return_output = false;
	
	/**
	 * @var string The CSRF Token name
	 */
	private $csrf_token_name = "_csrf_token";
	
	/**
	 * @var boolean True to set the CSRF token automatically on create
	 * @see Form::create()
	 */
	private $csrf_auto_create = true;
	
	/**
	 * @var string The CSRF Token key used to make each token unique
	 */
	private $csrf_token_key;
	
	/**
	 * Sets the CSRF Token options
	 *
	 * @param array $options An array of CSRF token options including:
	 * 	- token_name The field name of the CSRF token
	 * 	- set_on_create True to automatically set the CSRF token on create
	 * 	- token_key The CSRF token key used to make each token unique
	 */
	public function setCsrfOptions(array $options) {		
		if (isset($options['token_name']))
			$this->csrf_token_name = $options['token_name'];
		if (isset($options['set_on_create']))
			$this->csrf_auto_create = $options['set_on_create'];
		if (isset($options['token_key']))
			$this->csrf_token_key = $options['token_key'];
	}
	
	/**
	 * Sets the end of line character to use
	 *
	 * @param string $eol The end of line character to use
	 */
	public function setEol($eol) {
		$this->eol = $eol;
	}
	
	/**
	 * Set whether to return $output generated by these methods, or to echo it out instead
	 *
	 * @param boolean $return True to return output from these form methods, false to echo results instead 
	 */
	public function setOutput($return) {
		if ($return)
			$this->return_output = true;
		else
			$this->return_output = false;
	}
	
	/**
	 * Collapses an object array down to a simple key/value array or numerically indexed array
	 * whose values are member variables of the given object array
	 *
	 * @param array $obj_arr The object array to collapse
	 * @param mixed $value_var A string representing the name of the member variable, or an array of member variable values to concatenate
	 * @param string $key_var The name of the member variable to use as the key in the array, null for numerically indexed array
	 * @param string $glue The value to use to concat multiple $value_var values together, if null, will simply print only the first non-null value in $value_var
	 * @return array An array in key/value form
	 */
	public function collapseObjectArray($obj_arr, $value_var, $key_var=null, $glue=null) {
		$result = array();
		foreach ($obj_arr as $key => $obj) {
			$temp = get_object_vars($obj);
			$value = "";
			// Use either a list of values or a single value
			if (is_array($value_var)) {
				for ($i=0; $i<count($value_var); $i++) {
						$value .= ($i>0 && $temp[$value_var[$i]] != "" ? $glue : "") . $temp[$value_var[$i]];
						
						// Print only the first non-null value
						if ($glue === null && $temp[$value_var[$i]] != "")
							break;
				}
			}
			else
				$value = $temp[$value_var];
			
			if ($key_var == null)
				$result[] = $value;
			else
				$result[$temp[$key_var]] = $value;
		}
		return $result;
	}
	
	/**
	 * Begins a new form. Default method='post', but $attributes may overwrite that.
	 *
	 * @param string $uri The $uri for the form to post to, defaults to $_SERVER['REQUEST_URI']
	 * @param array $attributes The attributes and values to add in the <form> tag, in key=value pairs
	 * @return string The HTML for the <form> tag, void if output enabled
	 */
	public function create($uri=null, $attributes=array()) {
		if ($uri == null)
			$uri = $_SERVER['REQUEST_URI'];
			
		$default_attributes = array("method"=>"post","action"=>$uri);
		$attributes = array_merge($default_attributes, (array)$attributes);
		
		$html = "<form" . $this->buildAttributes($attributes) . ">" . $this->eol;
		
		// Set the CSRF token if set to do so
		if ($this->csrf_auto_create) {
			$output = $this->return_output;
			$this->setOutput(true);
			$html .= $this->fieldHidden($this->csrf_token_name, $this->getCsrfToken($this->csrf_token_key));
			$this->setOutput($output);
		}
		
		return $this->output($html);
	}
	
	/**
	 * Ends a form, appends an optional $end_str
	 *
	 * @param string $end_str The string to add after the </form> tag
	 * @return string The </form> tag, void if output enabled
	 */
	public function end($end_str=null) {
		$html = "</form>" . $end_str . $this->eol;
		
		return $this->output($html);
	}
	
	/**
	 * Generates a CSRF token
	 *
	 * @param string $key The key used to generate the CSRF token
	 * @return string The computed CSRF token
	 */
	public function getCsrfToken($key = null) {
		$session_id = session_id();
		
		if ($key == null)
			$key = $this->csrf_token_key;
		
		// Prefer computing CSRF using HMAC
		if (function_exists("hash_hmac"))
			return hash_hmac("sha256", $session_id, $key);
		// Sha256 hash is the next best thing
		if (function_exists("hash"))
			return hash("sha256", $key . $session_id);
		// Regretably, fallback to md5
		return md5($key . $session_id);
	}
	
	/**
	 * Verifies that the given CSRF token is valid
	 *
	 * @param string $key The key used to generate the original CSRF token
	 * @param string $csrf_token The given CSRF token, null to automatically pull the CSRF token from the $_POST data
	 * @return boolean True if the token is valid, false otherwise
	 */
	public function verifyCsrfToken($key = null, $csrf_token = null) {
		
		if ($key == null)
			$key = $this->csrf_token_key;
		
		if ($csrf_token === null && isset($_POST[$this->csrf_token_name]))
			$csrf_token = $_POST[$this->csrf_token_name];
			
		return $this->getCsrfToken($key) == $csrf_token;
	}
	
	/**
	 * Allows fields to be created from an array of fields.
	 *
	 * @param array $fields An array of fields to set
	 * @return string The fields given, void if output enabled
	 */
	public function fields($fields) {
		// Set data to return, because we don't want to echo until we have everything built
		$output = $this->return_output;
		$this->setOutput(true);
		
		$html = "";
		$num_fields = count($fields);
		
		for ($i=0; $i<$num_fields; $i++) {
			if (!isset($fields[$i]['type']) || !isset($fields[$i]['name']))
				continue;
			
			if (!isset($fields[$i]['attributes']))
				$fields[$i]['attributes'] = null;
			if (!isset($fields[$i]['value']))
				$fields[$i]['value'] = null;
			if (!isset($fields[$i]['checked']))
				$fields[$i]['checked'] = null;
			if (!isset($fields[$i]['selected_values']))
				$fields[$i]['selected_values'] = null;				
			
			switch ($fields[$i]['type']) {
				case "button":
					$html .= $this->fieldButton($fields[$i]['name'], $fields[$i]['value'], $fields[$i]['attributes']);
					break;
				case "checkbox":
					$html .= $this->fieldCheckbox($fields[$i]['name'], $fields[$i]['value'], $fields[$i]['checked'], $fields[$i]['attributes']);
					break;
				case "file":
					$html .= $this->fieldFile($fields[$i]['name'], $fields[$i]['attributes']);
					break;
				case "hidden":
					$html .= $this->fieldHidden($fields[$i]['name'], $fields[$i]['value'], $fields[$i]['attributes']);
					break;
				case "image":
					$html .= $this->fieldImage($fields[$i]['name'], $fields[$i]['value'], $fields[$i]['attributes']);
					break;
				case "password":
					$html .= $this->fieldPassword($fields[$i]['name'], $fields[$i]['attributes']);
					break;
				case "radio":
					$html .= $this->fieldRadio($fields[$i]['name'], $fields[$i]['value'], $fields[$i]['checked'], $fields[$i]['attributes']);
					break;
				case "reset":
					$html .= $this->fieldReset($fields[$i]['name'], $fields[$i]['value'], $fields[$i]['attributes']);
					break;
				case "submit":
					$html .= $this->fieldSubmit($fields[$i]['name'], $fields[$i]['value'], $fields[$i]['attributes']);
					break;
				case "text":
				default:
					$html .= $this->fieldText($fields[$i]['name'], $fields[$i]['value'], $fields[$i]['attributes']);
					break;
				case "textarea":
					$html .= $this->fieldTextarea($fields[$i]['name'], $fields[$i]['value'], $fields[$i]['attributes']);
					break;
				case "select":
				case "multiselect":
					if (!isset($fields[$i]['options']))
						$fields[$i]['options'] = null;
					if (!isset($fields[$i]['selected_value']))
						$fields[$i]['selected_value'] = null;
					if (!isset($fields[$i]['selected_values']))
						$fields[$i]['selected_values'] = null;
						
					if ($fields[$i]['type'] == "select")
						$html .= $this->fieldSelect($fields[$i]['name'], $fields[$i]['options'], $fields[$i]['selected_value'], $fields[$i]['attributes']);
					else
						$html .= $this->fieldMultiSelect($fields[$i]['name'], $fields[$i]['options'], $fields[$i]['selected_values'], $fields[$i]['attributes']);
					break;
			}
		}
		
		// Restore the original output type
		$this->setOutput($output);
		
		return $this->output($html);
	}
	
	/**
	 * Creates a label with the given name and marks it for the given field
	 *
	 * @param string $name The name of this label
	 * @param string $for The ID of the form element this label is part of
	 * @param array $attributes Attributes for this label
	 * @param boolean $preserve_tags True to preserve tags in the label name
	 * @return string The label specified, void if output enabled
	 */
	public function label($name, $for=null, array $attributes=null, $preserve_tags=false) {
		$default_attributes = array("for"=>$for);
		$attributes = array_merge($default_attributes, (array)$attributes);
		return $this->output("<label" . $this->buildAttributes($attributes) . ">" . $this->_($name, true, $preserve_tags) . "</label>" . $this->eol);
	}
	
	/**
	 * Creates a text input field
	 *
	 * @param string $name The name to set in the HTML name field
	 * @param string $value The value to set in the HTML value field
	 * @param array $attributes Attributes for this input field
	 * @return string The text field specified, void if output enabled
	 */
	public function fieldText($name, $value=null, $attributes=array()) {
		return $this->output($this->fieldInput("input", "text", $name, $value, $attributes));
	}
	
	/**
	 * Creates a hidden input field
	 *
	 * @param string $name The name to set in the HTML name field
	 * @param string $value The value to set in the HTML value field
	 * @param array $attributes Attributes for this input field
	 * @return string The hidden field specified, void if output enabled
	 */	
	public function fieldHidden($name, $value=null, $attributes=array()) {
		return $this->output($this->fieldInput("input", "hidden", $name, $value, $attributes));
	}
	
	/**
	 * Creates an image input field
	 *
	 * @param string $name The name to set in the HTML name field
	 * @param string $value The value to set in the HTML value field
	 * @param array $attributes Attributes for this input field
	 * @return string The image field specified, void if output enabled
	 */
	public function fieldImage($name, $value=null, $attributes=array()) {
		$default_attributes = array("src"=>"", "alt"=>$value);
		$attributes = array_merge($default_attributes, (array)$attributes);
		return $this->output($this->fieldInput("input", "image", $name, $value, $attributes));
	}
	
	/**
	 * Creates a reset input field
	 *
	 * @param string $name The name to set in the HTML name field
	 * @param string $value The value to set in the HTML value field
	 * @param array $attributes Attributes for this input field
	 * @return string The reset field specified, void if output enabled
	 */
	public function fieldReset($name, $value=null, $attributes=array()) {
		return $this->output($this->fieldInput("input", "reset", $name, $value, $attributes));
	}

	/**
	 * Creates a checkbox
	 *
	 * @param string $name The name to set in the HTML name field
	 * @param string $value The value to set in the HTML value field
	 * @param boolean $checked True to set this field as checked
	 * @param array $attributes Attributes for this input field
	 * @return string The checkbox field specified, void if output enabled
	 */
	public function fieldCheckbox($name, $value=null, $checked=false, $attributes=array()) {
		return $this->output($this->radioCheck("checkbox", $name, $value, $checked, $attributes));
	}
	
	/**
	 * Creates a radio box
	 *
	 * @param string $name The name to set in the HTML name field
	 * @param string $value The value to set in the HTML value field
	 * @param boolean $checked True to set this field as checked
	 * @param array $attributes Attributes for this input field
	 * @return string The radio field specified, void if output enabled
	 */	
	public function fieldRadio($name, $value=null, $checked=false, $attributes=array()) {
		return $this->output($this->radioCheck("radio", $name, $value, $checked, $attributes));
	}

	/**
	 * Creates a textarea field
	 *
	 * @param string $name The name to set in the HTML name field
	 * @param string $value The value to set in this textarea
	 * @param array $attributes Attributes for this input field
	 * @return string The textarea field, void if output enabled
	 */
	public function fieldTextarea($name, $value=null, $attributes=array()) {
		$default_attributes = array("name"=>$name);
		$attributes = array_merge($default_attributes, (array)$attributes);
		
		$html = "<textarea" . $this->buildAttributes($attributes) . ">" .
			$this->_($value, true) . "</textarea>" . $this->eol;
		
		return $this->output($html);
	}	

	/**
	 * Creates a password input field
	 *
	 * @param string $name The name to set in the HTML name field
	 * @param array $attributes Attributes for this input field
	 * @return string The password field, void if output enabled
	 */
	public function fieldPassword($name, $attributes=array()) {
		return $this->output($this->fieldInput("input", "password", $name, null, $attributes));
	}
	
	/**
	 * Creates a file input field
	 *
	 * @param string $name The name to set in the HTML name field
	 * @param array $attributes Attributes for this input field
	 * @return string The file field, void if output enabled
	 */
	public function fieldFile($name, $attributes=array()) {
		return $this->output($this->fieldInput("input", "file", $name, null, $attributes));
	}

	/**
	 * Creates a select list
	 *
	 * @param string $name The name to set in the HTML name field
	 * @param array $options The options to place in this select list
	 * @param mixed $selected_value The option(s) to set as selected
	 * @param array $attributes Attributes for this input field
	 * @param array $option_attributes Attributes for each option to set. If single dimension will set the attributes for every option, if multi-dimensional will set option for each element key that matches in $options
	 * 	
	 * @return string The select field, void if output enabled
	 */	
	public function fieldSelect($name, $options=array(), $selected_value=null, $attributes=array(), $option_attributes=array()) {
		$default_attributes = array("name"=>$name);
		$attributes = array_merge($default_attributes, (array)$attributes);
		
		$html = "<select" . $this->buildAttributes($attributes) . ">" . $this->eol .
			$this->selectOptions($options, $selected_value, $option_attributes) . "</select>" . $this->eol;
		
		return $this->output($html);
	}
	
	/**
	 * Creates a select list with multiple selectable options
	 *
	 * @param string $name The name to set in the HTML name field
	 * @param array $options The options to place in this select list
	 * @param string $selected_value The option to set as selected
	 * @param array $attributes Attributes for this input field
	 * @param array $option_attributes Attributes for each option to set. If single dimension will set the attributes for every option, if multi-dimensional will set option for each element key that matches in $options
	 * 
	 * @return string The multi-select field, void if output enabled
	 */		
	public function fieldMultiSelect($name, $options=array(), $selected_values=array(), $attributes=array(), $option_attributes=array()) {
		$attributes['multiple'] = "multiple";
		return $this->fieldSelect($name, $options, $selected_values, $attributes, $option_attributes);
	}
	
	/**
	 * Creates a button with default type=button, can be overriden by attirbutes
	 *
	 * @param string $name The name to set in the HTML name field
	 * @param string $value The value to set in the HTML value field
	 * @param array $attributes Attributes for this input field
	 * @return string The button field, void if output enabled
	 */	
	public function fieldButton($name, $value=null, $attributes=array()) {
		return $this->output($this->fieldInput("button", "button", $name, $value, $attributes));
	}
	
	/**
	 * Creates a button of type submit
	 *
	 * @param string $name The name to set in the HTML name field
	 * @param string $value The value to set in the HTML value field
	 * @param array $attributes Attributes for this input field
	 * @return string The submit field, void if output enabled
	 */		
	public function fieldSubmit($name, $value=null, $attributes=array()) {
		return $this->output($this->fieldInput("input", "submit", $name, $value, $attributes));
	}
	
	/**
	 * Builds a field for input or button
	 *
	 * @param string $tag The HTML tag of this input field ("input" or "button")
	 * @param string $type The input type to set in the type field
	 * @param string $name The name of this form field
	 * @param string $value The value of this field
	 * @param array $attributes A list of attributes to set for this field
	 * @return string The input or button field of the given type
	 */
	private function fieldInput($tag, $type, $name, $value=null, $attributes=array()) {
		$default_attributes = array("type"=>$type, "name"=>$name);
		if ($value !== null)
			$default_attributes['value'] = $value;

		$end = "";
		switch ($tag) {
			case "button":
				$end = ">" . $value . "</" . $tag . ">";
				break;
			case "input":
			default:
				$tag = "input";
				$end = $this->xhtml ? " />" : ">";
				break;
		}
		
		$attributes = array_merge($default_attributes, (array)$attributes);

		return "<" . $tag . $this->buildAttributes($attributes) . $end . $this->eol;
	}
	
	/**
	 * Builds select options and optgroups if given. An optgroup will continue
	 * until the end of the select options, or until the next optgroup begins.
	 *
	 * @param array $options A list of 'name'=>'value' options to set into <option> tags, if 'name' is 'optgroup' an <optgroup> tag is created instead
	 * @param array $selected_values Values corresponding to an option's value to set as selected
	 * @param array $attributes Attributes for each option field
	 * @return string The option fields along with any optgroups
	 */
	private function selectOptions($options, $selected_values=array(), $attributes=array()) {
		$open_group_tag = false;
		
		if (!is_array($selected_values))
			$selected_values = (array)$selected_values;
		
		$html = "";
		// Do we apply the given attributes to each option (e.g. "cover" each option) or apply individually
		$temp_attr = (array)array_values($attributes);
		$cover_attributes = is_array(array_pop($temp_attr)) ? false : true;
		
		if (is_array($options)) {
			foreach ($options as $value => $name) {
				
				$attr = array();
				
				// Allow multi-dimensional array in addition to name/value pairs
				if (is_array($name)) {
					$value = $name['value'];
					$name = $name['name'];
				}
				
				if ($cover_attributes)
					$attr = $attributes;
				elseif (isset($attributes[$value]))
					$attr = $attributes[$value];
					
				if (strpos($value, "optgroup") === 0) {
					if ($open_group_tag)
						$html .= "</optgroup>" . $this->eol; //close an open tag before starting another
					
					$html .= "<optgroup label=\"" . $this->_($name, true) . "\">";
					$open_group_tag = true;
				}
				else {
					$attr['value'] = $this->_($value, true);
					
					$html .= "<option";
					
					if (!empty($selected_values) && $this->inArray($value, $selected_values))
						$attr['selected'] = "selected";
					
					$html .= $this->buildAttributes($attr) . ">" . $this->_($name, true) . "</option>" . $this->eol;
				}
			}
		}
		
		//Before returning add closing optgroup tag if necessary
		if ($open_group_tag)
			$html .= "</optgroup>" . $this->eol;
		
		return $html;
	}
	
	/**
	 * Creates a radio or check box
	 *
	 * @param string $type The type of box to create: radio or checkbox
	 * @param string $name The name to set in the HTML name field
	 * @param string $value The value to set in the HTML value field
	 * @param boolean $checked True to set this box as checked
	 * @param array $attributes Attributes for this input field
	 * @return string The radio or checkbox field
	 */		
	private function radioCheck($type, $name, $value=null, $checked=false, $attributes=array()) {
		switch ($type) {
			case "radio":
			case "checkbox":
				break;
			default:
				$type = "checkbox";
				break;
		}
		
		if ($checked)
			$attributes['checked'] = "checked";
		
		return $this->fieldInput("input", $type, $name, $value, $attributes);
	}
	
	/**
	 * Handles whether to output or return $html
	 *
	 * @param string $html The HTML to output/return
	 * @return string The HTML given, void if output enabled
	 */	
	private function output($html) {
		if ($this->return_output)
			return $html;
		echo $html;
	}
	
	/**
	 * An alternative to PHP's in_array function that checks if $needle
	 * exists in $haystack
	 *
	 * @param mixed $needle A value to look for
	 * @param array $haystack An array of key=>value pairs
	 * @param boolean $strict True to also check the type of $needle in $haystack, false to ignore type (default false)
	 * @return boolean True if $needle is in $haystack, false otherwise
	 */
	private function inArray($needle, array $haystack, $strict=false) {
		foreach ($haystack as $key => $value) {
			if (!$strict && (string)$needle == (string)$value)
				return true;
			if ($strict && $needle === $value)
				return true;
		}
		return false;
	}
}
?>