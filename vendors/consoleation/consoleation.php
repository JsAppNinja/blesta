<?php
/**
 * Consoleation - A Command Line User Interface
 *
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://opensource.org/licenses/mit-license.php The MIT License
 */
class Consoleation {
	
	protected $progress_bar = "=>";
	
	/**
	 *
	 */
	public function getLine() {
		return rtrim(fgets(STDIN));
	}
	
	/**
	 *
	 */
	public function output($line) {
		$argc = func_num_args();
		if ($argc > 1) {
			$args = array_slice(func_get_args(), 1, $argc-1);
			// If printf args are passed as an array use those instead
			if (is_array($args[0]))
				$args = $args[0];
			array_unshift($args, $line);
			
			$line = call_user_func_array("sprintf", $args);
		}
		
		fwrite(STDOUT, $line);
	}
	
	/**
	 * Creates a progress bar of the format:
	 * ^x/y [==============================          ] PER%$
	 *
	 * @param int $current The current progress (# of items completed)
	 * @param int $total The total number of items to complete
	 * @param int $size The size of the progress bar
	 */
	public function progressBar($current, $total, $size = 40) {
		
		if ($current > $total)
			return;
		
		// Message
		$msg = $current . "/" . $total;
		// % completed decimal
		$completed = ($total > 0 ? ($current / $total) : 1);
		// Number of bars to display
		$bars = floor($completed * $size);
		// % done formatted
		$per = str_pad(floor($completed * 100), 3);
		
		$bar = str_repeat($this->progress_bar[0], $bars) . $this->progress_bar[1];
		$bar = substr(str_pad($bar, $size, ' '), 0, $size);
		
		$this->output("\r%s [%s] %s%%", $msg, $bar, $per);
		
		flush();
		
		// New line when done
		if ($current == $total)
			$this->output("\n");
	}
}
?>