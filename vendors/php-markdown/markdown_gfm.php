<?php
require_once "markdown.php";

/**
 * Github Flavored Markdown
 *
 * @link https://gist.github.com/koenpunt/3194002 Original Credit
 */
class MarkdownGfm extends Markdown_Parser {
	
	private $extractions = array();
	
	/**
	 * Overwrite original Markdown_Parser::transform
	 *
	 * @param string $text The GFM markdown syntax to transform
	 * @return string A string of HTML content derived from the given markdown $text
	 */
	public function transform($text) {
		return parent::transform($this->gfm($text));
	}
	
	/**
	 * Pre-parse GFM syntax into suitable value for markdown
	 *
	 * @param string $text The text to manipulate
	 * @return string The $text in markdown syntax
	 */
	protected function gfm($text) {
		// Extract pre blocks
		$this->extractions = array();
		
		$text = preg_replace_callback('/<pre>.*?<\/pre>/s', array($this, "preblockExtract"), $text);
	
		// prevent foo_bar_baz from ending up with an italic word in the middle
		$text = preg_replace_callback('/(^(?! {4}|\t)\w+_\w+_\w[\w_]*)/m', array("MarkdownGfm", "italicFix"), $text);
	
		// in very clear cases, let newlines become <br /> tags
		$text = preg_replace_callback('/^[\w\<][^\n]*\n+/m', array("MarkdownGfm", "newlineFix"), $text);
	
		// Insert pre block extractions
		$text = preg_replace_callback('/\{gfm-extraction-([0-9a-f]{32})\}/', array($this, "preblockInsert"), $text);
	
		return $text;
	}
	
	/**
	 * Escapes underscores used in the middle of words
	 *
	 * @param array $matches An array of strings to format
	 * @return string The formatted string
	 */
	protected static function italicFix($matches) {
		$x = $matches[0];
		$x_parts = str_split($x);
		sort($x_parts);
		if (substr(implode('', $x_parts), 0, 2) == '__') {
			return str_replace('_', '\_', $x);
		}
	}

	/**
	 * Ensure that newline are treated as breaks
	 *
	 * @param array $matches An array of strings to format
	 * @return string The formatted string
	 */	
	protected static function newlineFix($matches) {
		$x = $matches[0];
		if (!preg_match('/\n{2}/', $x)) {
			$x = trim($x);
			$x .= "  \n";
		}
		return $x;
	}
	
	/**
	 * Extract content from pre-blocks and sets it aside
	 *
	 * @param array $matches An array of strings to format
	 * @return string The formatted string
	 */	
	protected function preblockExtract($matches) {
		$match = $matches[0];
		$md5 = md5($match);
		$this->extractions[$md5] = $match;
		return "{gfm-extraction-${md5}}";
	}
	
	/**
	 * Replaces pre-block content after other rules have been processed
	 *
	 * @param array $matches An array of strings to format
	 * @return string The formatted string
	 */	
	protected function preblockInsert($matches) {
		$match = $matches[1];
		return "\n\n" . $this->extractions[$match];
	}
}
?>