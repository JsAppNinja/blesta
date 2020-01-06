<?php
Loader::load(HELPERDIR . "html" . DS . "html.php");

/**
 * Simplifies the creation of widgets for the client interface
 *
 * @package blesta
 * @subpackage blesta.helpers.widget_client
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class WidgetClient extends Html {
	/**
	 * @var string The string to use as the end of line character, "\n" by default
	 */
	private $eol = "\n";
	/**
	 * @var boolean Whether or not to return output from various widget methods
	 */
	private $return_output = false;
	/**
	 * @var array Buttons that should be displayed within the window 
	 */
	private $widget_buttons = array();
	/**
	 * @var array An array of style sheet attributes to be rendered into the DOM
	 */
	private $style_sheets = array();
	/**
	 * @var string How to render the widget. Options include:
	 * 	- full The entire widget (default)
	 * 	- inner The content only (everything excluding the nav and title)
	 */
	private $render;
	/**
	 * @param boolean True if to automatically start the widget body
	 */
	private $auto_start_body = false;
	/**
	 * @param boolean True if the body of the widget is open
	 */
	private $body_open = false;
	/**
	 * @param boolean True if the footer of the widget is open
	 */
	private $footer_open = false;
	/**
	 * @param array $nav An array of navigation elements
	 */
	private $nav = array();
	/**
	 * @param string $nav_type Sets the navigation type:
	 * 	- links
	 * 	- tabs
	 * 	- pills
	 */
	private $nav_type = "links";
	/**
	 * @param array $link_buttons An array of link buttons
	 */
	private $link_buttons = array();
	
	/**
	 * Clear this widget, making it ready to produce the next widget
	 */
	public function clear() {
		$this->widget_buttons = array();
		$this->nav = array();
		$this->nav_type = "links";
		$this->link_buttons = array();
		$this->style_sheets = array();
		$this->render = "full";
		$this->body_open = false;
		$this->footer_open = false;
		$this->auto_start_body = false;
	}

	/**
	 * Sets navigation links within the widget
	 *
	 * @param array $tabs A multi-dimensional array of tab info including:
	 * 	- name The name of the link to be displayed
	 * 	- current True if this element is currently active
	 * 	- attributes An array of attributes to set for this tab (e.g. array('href'=>"#"))
	 */	
	public function setLinks(array $link, $type = "links") {
		$this->nav = $link;
		$this->nav_type = $type;
	}
	
	/**
	 * Sets navigation buttons along with Widget::setLinks(). This method may
	 * only be used in addition with Widget::setLinks()
	 *
	 * @param array $link_buttons A multi-dimensional array of button links including:
	 * 	- name The name of the button link to be displayed
	 * 	- attributes An array of attributes to set for this button link (e.g. array('href'=>"#"))
	 */
	public function setLinkButtons(array $link_buttons) {
		$this->link_buttons = $link_buttons;
	}
	
	/**
	 * Set a widget button to be displayed in the title bar of the widget
	 */
	public function setWidgetButton($button) {
		$this->widget_buttons[] = $button;
	}
	
	/**
	 * Sets a style sheet to be linked into the document
	 *
	 * @param string $path the web path to the style sheet
	 * @param array An array of attributes to set for this element
	 */
	public function setStyleSheet($path, array $attributes = null) {
		$default_attributes = array('media'=>"screen", 'type'=>"text/css", 'rel'=>"stylesheet", 'href'=>$path);
		$attributes = array_merge((array)$attributes, $default_attributes);
		
		$this->style_sheets[] = $attributes;
	}
	
	/**
	 * Sets whether or not the sub heading section should be rendered
	 *
	 * @param boolean $render True to render the sub heading, false otherwise
	 * @deprecated since 3.2
	 */
	public function renderSubHead($render) {
		
	}

	/**
	 * Sets whether or not the body panel should start when WidgetClient::create() is called
	 * 
	 * @param boolean $auto_start True to auto begin the widget body when WidgetClient::create() is called
	 */
	public function autoStartBody($auto_start) {
		$this->auto_start_body = $auto_start;
	}
	
	/**
	 * Creates the widget with the given title and attributes
	 *
	 * @param string $title The title to display for this widget
	 * @param array $attributes An list of attributes to set for this widget's primary container
	 * @param string $render How to render the widget. Options include:
	 * 	- full The entire widget (default)
	 * 	- inner_content (everthing but the title)
	 * @return mixed An HTML string containing the widget, void if the string is output automatically
	 */
	public function create($title = null, array $attributes = null, $render = null) {
		// Don't output until this section is completely built
		$output = $this->setOutput(true);

		$this->render = ($render == null ? "full" : $render);
		$default_attributes = array('class' => "panel panel-blesta content_section");
		
		// Set the attributes, don't allow overwriting the default class, concat instead
		if (isset($attributes['class']) && isset($default_attributes['class']))
			$attributes['class'] .= " " . $default_attributes['class'];
		$attributes = array_merge((array)$attributes, $default_attributes);
		
		$html = null;
		$html .= $this->buildStyleSheets();
		// Render container and heading
		if ($this->render == "full") {
			$html .= '
				<div' . $this->buildAttributes($attributes) . '>
					<div class="panel-heading">
						<h3 class="panel-title">' . $this->_($title, true) . $this->buildWidgetButtons() . '</h3>
					</div>
					<div class="panel_content">';
		}
		if (($this->render == "full" || $this->render == "inner_content") && $this->auto_start_body)
			$html .= $this->startBody(false);
		
		// Restore output setting
		$this->setOutput($output);
		
		return $this->output($html);
	}
	
	/**
	 * End the widget, closing an loose ends
	 *
	 * @return mixed An HTML string ending the widget, void if the string is output automatically
	 */
	public function end() {
		// Don't output until this section is completely built
		$output = $this->setOutput(true);
		
		$html = null;
		$html .= $this->endBody(false);
		$html .= $this->endFooter(false);
		
		if ($this->render == "full") {
			// Close container
			$html .= '
					</div>
				</div>';
		}
		
		// Restore output setting
		$this->setOutput($output);
		
		return $this->output($html);
	}
	
	/**
	 * Start the widget body
	 *
	 * @param boolean $output True to output the body, false to return it
	 * @return mixed An HTML string beginning the widget body, void if the string is output automatically
	 */
	public function startBody($output = true) {
		$this->body_open = true;
		
		$panel_nav = $this->buildNav() . $this->buildLinkButtons();
		
		$html = '
			<div class="panel-body">';
		if ($panel_nav != "") {
			$html .= '
				<div class="panel-nav">
					' . $panel_nav . '
					<div class="clearfix"></div>
				</div>';
		}
		
		if ($output)
			$this->setOutput(false);
		return $this->output($html);
	}
	
	/**
	 * End the widget body
	 *
	 * @param boolean $output True to output the body, false to return it
	 * @return mixed An HTML string ending the widget body, void if the string is output automatically
	 */
	public function endBody($output = true) {
		if ($this->body_open) {
			$this->body_open = false;
			if ($output)
				$this->setOutput(false);
			return $this->output('</div>' . $this->eol);
		}
		return null;
	}
	
	/**
	 * Start the widget footer
	 *
	 * @param boolean $output True to output the footer, false to return it
	 * @return mixed An HTML string beginning the widget footer, void if the string is output automatically
	 */
	public function startFooter($output = true) {
		$this->footer_open = true;
		if ($output)
			$this->setOutput(false);
		return $this->output('<div class="panel-footer">' . $this->eol);
	}
	
	/**
	 * End the widget footer
	 *
	 * @param boolean $output True to output the footer, false to return it
	 * @return mixed An HTML string ending the widget footer, void if the string is output automatically
	 */
	public function endFooter($output = true) {
		if ($this->footer_open) {
			$this->footer_open = false;
			if ($output)
				$this->setOutput(false);
			return $this->output('</div>' . $this->eol);
		}
		return null;
	}
	
	/**
	 * Creates the window buttons that appear in the title bar of the widget
	 *
	 * @return string An HTML string containing the window buttons
	 */
	private function buildWidgetButtons() {		
		$html = null;
		if (!empty($this->widget_buttons)) {
			$html .= '<div class="btn-group pull-right">';
			
			$defaults = array(
				'class' => "btn btn-primary btn-xs",
				'href' => "#"
			);
			foreach ($this->widget_buttons as $button) {
				$attributes = array_merge($defaults, (array)$button);
				unset($attributes['icon']);
				$icon = isset($button['icon']) ? $button['icon'] : "fa fa-cog";
				$html .= '<a' . $this->buildAttributes($attributes) . '><i class="' . $this->_($icon, true) . '"></i></a>';
			}
			
			$html .= '</div>';
		}
		return $html;
	}
	
	/**
	 * Builds the nav for this widget
	 *
	 * @return mixed A string of HTML, or void if HTML is output automatically
	 */
	private function buildNav() {
		$html = null;
		
		$nav_elements = $this->buildNavElements();
		if ($nav_elements) {
			$html .= '
				<div class="pull-left">
					' . $nav_elements . '
				</div>' . $this->eol;
		}
		
		return $html;
	}
	
	/**
	 * Builds the nav elements for this widget
	 * 
	 * @return string A string of HTML
	 */
	private function buildNavElements() {
		if (empty($this->nav))
			return null;
		
		$nav_class = null;
		switch ($this->nav_type) {
			default:
			case "links":
				$nav_class = "panel-links";
				break;
			case "tabs":
				$nav_class = "nav nav-tabs";
				break;
			case "pills":
				$nav_class = "nav nav-pills";
				break;
		}
		
		$html = '<ul class="' . $nav_class . '">' . $this->eol;
		$i=0;
		foreach ($this->nav as $element) {
			// Set attributes on the anchor element
			$a_attr = "";
			if (isset($element['attributes']))
				$a_attr = $this->buildAttributes($element['attributes']);
			
			// Set attributes on the list element
			$li_attr = "";
			if ($i == 0 || isset($element['current']) || isset($element['highlight'])) {
				$li_attr = $this->buildAttributes(
					array(
						'class'=>$this->concat(" ",
							($this->ifSet($element['current']) ? "active" : ""),
							($this->ifSet($element['highlight']) && !$this->ifSet($element['current']) ? "highlight" : "")
						)
					)
				);
			}
			
			$html .= "<li" . $li_attr . "><a" . $a_attr . ">" . $this->ifSet($element['name']) . "</a></li>" . $this->eol;
			
			$i++;
		}
		$html .= "</ul>" . $this->eol;

		return $html;
	}
	
	/**
	 * Builds link buttons for use with link navigation
	 *
	 * @return string A string of HTML
	 */
	private function buildLinkButtons(array $attributes = null) {
		$default_attributes = array('class'=>"btn btn-sm btn-default");
		// Override default attributes
		$attributes = array_merge($default_attributes, (array)$attributes);
		
		$html = null;
		if (!empty($this->link_buttons)) {
			$html = "<div class=\"pull-right\">" . $this->eol;
			foreach ($this->link_buttons as $element) {
				$icon = array('class' => isset($element['icon']) ? $element['icon'] : "fa fa-plus-circle");
				$element['attributes'] = array_merge($attributes, (array)(isset($element['attributes']) ? $element['attributes'] : array()));
				$html .= "<a" . $this->buildAttributes($element['attributes']) . "><i" . $this->buildAttributes($icon) . "></i> " . $this->_($element['name'], true) . "</a>" . $this->eol;
			}
			$html .= "</div>" . $this->eol;
		}
		
		return $html;
	}
	
	/**
	 * Builds the markup to link style sheets into the DOM using jQuery
	 *
	 * @return string A string of HTML
	 */
	private function buildStyleSheets() {

		$html = "";
		if (is_array($this->style_sheets) && !empty($this->style_sheets)) {
			$html .= "<script type=\"text/javascript\">" . $this->eol;
			foreach ($this->style_sheets as $style) {
				$attributes = "";
				$i=0;
				foreach ($style as $key => $value)
					$attributes .= ($i++ > 0 ? "," . $this->eol : "") . $key . ": \"" . $value . "\"";
				$html .= "$(document).blestaSetHeadTag(\"link\", { " . $attributes . " });" . $this->eol;
			}
			$html .=  $this->eol . "</script>";
		}
		
		return $html;
	}
	
	/**
	 * Set whether to return $output generated by these methods, or to echo it out instead
	 *
	 * @param boolean $return True to return output from these widget methods, false to echo results instead 
	 */
	public function setOutput($return) {
		$this->return_output = $return;
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
}
?>