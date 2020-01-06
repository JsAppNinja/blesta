<?php
Loader::load(HELPERDIR . "html" . DS . "html.php");

/**
 * Simplifies the creation of widget interfaces
 *
 * @package blesta
 * @subpackage blesta.helpers.widget
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class Widget extends Html {
	/**
	 * @var string The string to use as the end of line character, "\n" by default
	 */
	private $eol = "\n";
	/**
	 * @var boolean Whether or not to return output from various widget methods
	 */
	private $return_output = false;
	/**
	 * @var string The URI to fetch when requesting the badge value for this widget
	 */
	private $badge_uri = null;
	/**
	 * @var string The badge value to display for this widget
	 */
	private $badge_value = null;
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
	 * 	- content_section The full content including nav (everything exluding box frame and title section)
	 * 	- common_box_content The content only (full_content excluding the nav)
	 */
	private $render;
	
	private $nav;
	private $nav_type = "tabs";
	private $link_buttons;
	
	/**
	 * Clear this widget, making it ready to produce the next widget
	 */
	public function clear() {
		$this->nav = null;
		$this->nav_type = "tabs";
		$this->link_buttons = null;
		$this->badge_uri = null;
		$this->widget_buttons = array();
		$this->style_sheets = array();
		$this->render = "full";
	}
	
	/**
	 * Sets navigation tabs within the widget
	 *
	 * @param array $tabs A multi-dimensional array of tab info including:
	 * 	- name The name of the tab to be displayed
	 * 	- current True if this element is currently active
	 * 	- attributes An array of attributes to set for this tab (e.g. array('href'=>"#"))
	 */
	public function setTabs(array $tabs) {
		$this->nav = $tabs;
		$this->nav_type = "tabs";
	}

	/**
	 * Sets navigation links within the widget
	 *
	 * @param array $tabs A multi-dimensional array of tab info including:
	 * 	- name The name of the link to be displayed
	 * 	- current True if this element is currently active
	 * 	- attributes An array of attributes to set for this tab (e.g. array('href'=>"#"))
	 */	
	public function setLinks(array $link) {
		$this->nav = $link;
		$this->nav_type = "links";
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
	 * Sets the URI to request when fetching a badge value for this widget
	 *
	 * @param string $uri The URI to request for the badge value for this widget
	 */
	public function setBadgeUri($uri) {
		$this->badge_uri = $uri;
	}
	
	/**
	 * Sets the badge value to appear on this widget, any thing other than null will be displayed
	 *
	 * @param string $value The value of the badge to be displayed
	 */
	public function setBadgeValue($value=null) {
		$this->badge_value = $value;
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
	public function setStyleSheet($path, array $attributes=null) {
		$default_attributes = array('media'=>"screen", 'type'=>"text/css", 'rel'=>"stylesheet", 'href'=>$path);
		$attributes = array_merge((array)$attributes, $default_attributes);
		
		$this->style_sheets[] = $attributes;
	}
	
	/**
	 * Creates the widget with the given title and attributes
	 *
	 * @param string $title The title to display for this widget
	 * @param array $attributes An list of attributes to set for this widget's primary container
	 * @param string $render How to render the widget. Options include:
	 * 	- full The entire widget
	 * 	- content_section The full content including nav (everything excluding box frame and title section)
	 * 	- common_box_content The content only (full_content excluding the nav)
	 * @return mixed An HTML string containing the widget, void if the string is output automatically
	 */
	public function create($title=null, array $attributes=null, $render=null) {
		// Don't output until this section is completely built
		$output = $this->setOutput(true);
		
		$this->render = ($render == null ? "full" : $render);
		
		$default_attributes = array('class'=>"common_box");

		// Set the attributes, don't allow overwriting the default class, concat instead
		if (isset($attributes['class']) && isset($default_attributes['class']))
			$attributes['class'] .= " " . $default_attributes['class'];
		$attributes = array_merge((array)$attributes, $default_attributes);
		
		// Set the badge URI to be displayed
		$badge_uri = $this->badge_uri != "" ? '<input type="hidden" name="badge_uri" value="' . $this->_($this->badge_uri, true) . '" />' : '';
		
		// Control which sections are rendered
		$html = "";
		$html .= $this->buildStyleSheets();
		if ($this->render == "full") {
			$html .= '
				<section' . $this->buildAttributes($attributes) . '>
					' . $badge_uri . '
					<div class="common_box_header">
						<h2><span>' . $this->_($title, true) . '</span>' . $this->buildBadge() . $this->buildWidgetButtons() . '</h2>
					</div>
					<div class="common_box_inner">
						<div class="content_section">';
		}
		
		// Only render nav and common_box_content container if set to do so
		if ($this->render == "full" || $this->render == "content_section") {
			$html .= $this->buildNav();
			$html .= '<div class="common_box_content">';
		}
		
		// Restore output setting
		$this->setOutput($output);
		
		return $this->output($html);
	}
	
	/**
	 * Sets a row for this widget to be displayed.
	 *
	 * @param string $left An HTML string to set on the left of the row
	 * @param string $right An HTML string to set on the right of the row
	 * @return mixed An HTML string containing the row, void if the string is output automatically
	 */
	public function setRow($left=null, $right=null) {
		$html = '<div class="row">
			<div class="left_section">' . $left . '</div>
			<div class="right_section">' . $right . '</div>
		</div>';
		
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
		
		$html = '';
		
		// Handle special case where links were used as nav
		if ($this->render == "full" && (!empty($this->nav) || !empty($this->link_buttons)))
			$html .= '</div>'; // end div.inner or div.tabs_content
			
		if ($this->render == "full" || $this->render == "content_section") {
			$html .= '
							</div>
						</div>';
		}
		if ($this->render == "full") {
			$html .= '
					</div>
					<div class="shadow"></div>
				</section>';
		}
			
		// Restore output setting
		$this->setOutput($output);
		
		return $this->output($html);
	}
	
	/**
	 * Creates the window buttons that appear in the title bar of the widget
	 *
	 * @return string An HTML string containing the window buttons
	 */
	private function buildWidgetButtons() {
		
		$num_widget_buttons = count($this->widget_buttons);
		
		$buttons = '';
		for ($i=0; $i<$num_widget_buttons; $i++) {
			if (is_array($this->widget_buttons[$i]))
				$attributes = $this->widget_buttons[$i];
			else
				$attributes = array('href'=>"#", 'class'=>$this->widget_buttons[$i]);
			$buttons .= '<a' . $this->buildAttributes($attributes) . '><em></em></a>';
		}
		return $buttons;
	}
	
	/**
	 * Creates the badge value to appear next to the title of the widget
	 *
	 * @return string An HTML string containing the badge value
	 */
	private function buildBadge() {
		$html = '';
		if ($this->badge_value !== null)
			$html = '<strong class="badge_dot">' . $this->_($this->badge_value, true) . '</strong>';
		return $html;
	}
	
	/**
	 * Builds the nav for this widget
	 *
	 * @return mixed A string of HTML, or void if HTML is output automatically
	 */
	private function buildNav() {
		if (empty($this->nav) && empty($this->link_buttons))
			return null;
		
		if ($this->nav_type == "tabs" && !empty($this->nav)) {
			$html = '
				<div class="tabs_row">
					<div class="tabs_nav"><a href="#" class="prev">&nbsp;</a><a href="#" class="next">&nbsp;</a></div>
					<div class="tab_slider">
						' . $this->buildNavElements() . '
					</div>
				</div>
				<div class="tabs_content">' . $this->eol;
		}
		elseif (!empty($this->nav) || !empty($this->link_buttons)) {
			$html = '
				<div class="inner">
					<div class="links_row">
						' . $this->buildNavElements() . '
						' . $this->buildLinkButtons() . '
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
		
		$html = "<ul>" . $this->eol;
		$i=0;
		if (is_array($this->nav)) {
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
							'class'=>$this->concat(" ", ($i == 0 ? "first" : ""),
								($this->ifSet($element['current']) ? "current" : ""),
								($this->ifSet($element['highlight']) && !$this->ifSet($element['current']) ? "highlight" : "")
							)
						)
					);
				}
				
				$html .= "<li" . $li_attr . "><a" . $a_attr . ">" . $this->ifSet($element['name']) . "</a></li>" . $this->eol;
				
				$i++;
			}
			$html .= "</ul>" . $this->eol;
		}

		return $html;
	}
	
	/**
	 * Builds link buttons for use with link navigation
	 *
	 * @return string A string of HTML
	 */
	private function buildLinkButtons() {
		$default_attributes = array('class'=>"btn_right");
		
		$html = "";
		if (is_array($this->link_buttons)) {
			foreach ($this->link_buttons as $element) {
				// Set the attributes, don't allow overwriting the default class, concat instead
				if (isset($element['attributes']['class']) && isset($default_attributes['class']))
					$element['attributes']['class'] .= " " . $default_attributes['class'];
				$element['attributes'] = array_merge($default_attributes, (array)$element['attributes']);
				$html .= "<a" . $this->buildAttributes($element['attributes']) . "><span>" . $this->_($element['name'], true) . "</span></a>" . $this->eol;
			}
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
				//$html .= "$('head').append('<link" . $this->buildAttributes($style) . " />');";
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
		if ($return)
			$this->return_output = true;
		else
			$this->return_output = false;
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