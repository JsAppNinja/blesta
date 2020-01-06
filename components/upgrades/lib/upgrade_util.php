<?php
/**
 * Upgrade Utility that all upgrade objects must extend
 * 
 * @package blesta
 * @subpackage blesta.components.upgrades
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
abstract class UpgradeUtil {
	/**
	 * Returns a numerically indexed array of tasks to execute for the upgrade process
	 *
	 * @retrun array A numerically indexed array of tasks to execute for the upgrade process
	 */
	abstract function tasks();
	
	/**
	 * Processes the given task
	 *
	 * @param string $task The task to process
	 */
	abstract function process($task);
	
	/**
	 * Rolls back all tasks completed for the upgrade process
	 */
	abstract function rollback();
	
	/**
	 * Adds a config setting to the given file
	 *
	 * @param string $file The full path to the config file
	 * @param string $key The config setting name
	 * @param mixed $value The config setting value
	 */
	public function addConfig($file, $key, $value) {
		$data = file_get_contents($file);
		
		$eol_pos = strrpos($data, "?>");
		if ($eol_pos !== false)
			$data = substr($data, 0, strrpos($data, "?>")) . "\n";
			
		$data .= 'Configure::set("' . $key . '", ' . var_export($value, true) . ');';
		$data .= "\n?>";
		file_put_contents($file, $data);
	}

	/**
	 * Updates a config setting in the given file with the given value
	 *
	 * @param string $file The full path to the config file
	 * @param string $key The config setting name
	 * @param mixed $value The config setting value
	 */	
	public function editConfig($file, $key, $value) {
		file_put_contents($file, $this->replaceConfig($key, $value, file_get_contents($file)));
	}
	
	/**
	 * Extracts all config settings from $file, merges their values into $template
	 * and writes the result back in $file.
	 *
	 * @param string $file The full path to the config
	 * @param string $template The full path to the config template
	 */
	public function mergeConfig($file, $template) {
		$pattern = '/Configure::set\("(.*?)",(.*?)\);/is';
		$template_config = file_get_contents($template);
		preg_match_all($pattern, $template_config, $matches, PREG_SET_ORDER);
		
		// Massage into key/value
		$template_pairs = array();
		foreach ($matches as $match) {
			$template_pairs[$match[1]] = $match[2];
		}
		unset($match);
		
		$file_config = file_get_contents($file);
		preg_match_all($pattern, $file_config, $matches, PREG_SET_ORDER);
		
		$file_config = $template_config;
		
		foreach ($matches as $match) {
			if (isset($template_pairs[$match[1]])) {
				$file_config = $this->replaceConfig($match[1], ltrim($match[2]), $file_config);
			}
		}
		
		file_put_contents($file, $file_config);
	}
	
	/**
	 * Replaces a config setting within the context of a string
	 *
	 * @param string $key The config key to replace the value of
	 * @param string $value The new value
	 * @param string $contents The context in which the $key is set
	 * @return string $contents with the given $value replaced
	 */
	protected function replaceConfig($key, $value, $contents) {
		$search = '/Configure::set\("' . preg_quote($key, "/") . '",(.*?)\);/is';
		if (!is_string($value))
			$value = var_export($value, true);
			
		$replacement = $this->escapeReplacement('Configure::set("' . $key . '", ' . $value . ');');
		return preg_replace($search, $replacement, $contents, 1);
	}
	
	/**
	 * Escape preg_replace replacement values
	 *
	 * @param string $value The value to escape
	 * @return string The properly escape value
	 */
	private static function escapeReplacement($value) {
		$patterns = array(
			'\\' => '\\\\',
			'$' => '\$'
		);
		return str_replace(array_keys($patterns), array_values($patterns), $value);
	}
	
	/**
	 * Return all validation errors encountered
	 *
	 * @return mixed Boolean false if no errors encountered, an array of errors otherwise
	 */
	public function errors() {
		if (isset($this->Input) && is_object($this->Input) && $this->Input instanceof Input)
			return $this->Input->errors();
	}
}
?>