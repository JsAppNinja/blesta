<?php
/**
 * PHPIDS plugin handler
 * 
 * @package blesta
 * @subpackage blesta.plugins.phpids
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class PhpidsPlugin extends Plugin {

	/**
	 * @var string The version of this plugin
	 */
	private static $version = "1.1.2";
	/**
	 * @var string The authors of this plugin
	 */
	private static $authors = array(array('name'=>"Phillips Data, Inc.",'url'=>"http://www.blesta.com"));
	
	public function __construct() {
		Language::loadLang("phpids_plugin", null, dirname(__FILE__) . DS . "language" . DS);
		
		// Load components required by this plugin
		Loader::loadComponents($this, array("Input", "Record"));
	}
	
	/**
	 * Returns the name of this plugin
	 *
	 * @return string The common name of this plugin
	 */
	public function getName() {
		return Language::_("PhpidsPlugin.name", true);	
	}
	
	/**
	 * Returns the version of this plugin
	 *
	 * @return string The current version of this plugin
	 */
	public function getVersion() {
		return self::$version;
	}

	/**
	 * Returns the name and URL for the authors of this plugin
	 *
	 * @return array The name and URL of the authors of this plugin
	 */
	public function getAuthors() {
		return self::$authors;
	}
	
	/**
	 * Performs any necessary bootstraping actions
	 *
	 * @param int $plugin_id The ID of the plugin being installed
	 */
	public function install($plugin_id) {
		Loader::loadModels($this, array("Phpids.PhpidsSettings", "Emails", "EmailGroups", "Languages"));
		Configure::load("phpids", dirname(__FILE__) . DS . "config" . DS);
		
		try {
			// log_phpids
			$this->Record->
				setField("id", array('type' => "int", 'size' => 10, 'unsigned' => true, 'auto_increment' => true))->
				setField("company_id", array('type' => "int", 'size' => 10, 'unsigned' => true))->
				setField("name", array('type' => "varchar", 'size' => 128))->
				setField("value", array('type' => "text"))->
				setField("uri", array('type' => "varchar", 'size' => 255))->
				setField("user_id", array('type' => "int", 'size' => 10, 'unsigned' => true))->
				setField("tags", array('type' => "varchar", 'size' => 128))->
				setField("ip", array('type' => "varchar", 'size' => 39))->
				setField("impact", array('type' => "int", 'size' => 10, 'unsigned' => true))->
				setField("date_added", array('type'=>"datetime"))->
				setKey(array("id"), "primary")->
				setKey(array("company_id", "date_added"), "index")->
				setKey(array("user_id"), "index")->
				setKey(array("ip"), "index")->
				create("log_phpids", true);
				
			// phpids_settings
			$this->Record->
				setField("company_id", array('type' => "int", 'size' => 10, 'unsigned' => true))->
				setField("key", array('type' => "varchar", 'size' => 128))->
				setField("value", array('type' => "text"))->
				setKey(array("company_id", "key"), "primary")->
				create("phpids_settings", true);
		}
		catch (Exception $e) {
			// Error adding... no permission?
			$this->Input->setErrors(array('db'=> array('create'=>$e->getMessage())));
			return;
		}
		
		// Fetch all currently-installed languages for this company, for which email templates should be created for
		$languages = $this->Languages->getAll(Configure::get("Blesta.company_id"));
		
		// Add all email templates
		$emails = Configure::get("Phpids.install.emails");
		foreach ($emails as $email) {
			$group = $this->EmailGroups->getByAction($email['action']);
			if ($group)
				$group_id = $group->id;
			else {
				$group_id = $this->EmailGroups->add(array(
					'action' => $email['action'],
					'type' => $email['type'],
					'plugin_dir' => $email['plugin_dir'],
					'tags' => $email['tags']
				));
			}
			
			// Set from hostname to use that which is configured for the company
			if (isset(Configure::get("Blesta.company")->hostname))
				$email['from'] = str_replace("@mydomain.com", "@" . Configure::get("Blesta.company")->hostname, $email['from']);
			
			// Add the email template for each language
			foreach ($languages as $language) {
				$this->Emails->add(array(
					'email_group_id' => $group_id,
					'company_id' => Configure::get("Blesta.company_id"),
					'lang' => $language->code,
					'from' => $email['from'],
					'from_name' => $email['from_name'],
					'subject' => $email['subject'],
					'text' => $email['text'],
					'html' => $email['html']
				));
			}
		}
		
		// Default settings
		$settings = array(
			'compound_impact' => "false",
			'email_min_score' => 30,
			'email_addresses' => "",
			'log_min_score' => 15,
			'log_rotate_freq' => 30,
			'redirect_min_score' => "",
			'redirect_url' => "",
			'rotate_impact' => 300
		);
		$this->PhpidsSettings->update($settings);
	}
	
	/**
	 * Performs migration of data from $current_version (the current installed version)
	 * to the given file set version
	 *
	 * @param string $current_version The current installed version of this plugin
	 * @param int $plugin_id The ID of the plugin being upgraded
	 */
	public function upgrade($current_version, $plugin_id) {
		Configure::load("phpids", dirname(__FILE__) . DS . "config" . DS);
		
		// Upgrade if possible
		if (version_compare($this->getVersion(), $current_version, ">")) {
			// Handle the upgrade, set errors using $this->Input->setErrors() if any errors encountered
			
			// Upgrade to v1.0.2
			if (version_compare($current_version, "1.0.2", "<")) {
				Loader::loadModels($this, array("Emails", "EmailGroups", "Languages"));
				
				// Add emails missing in additional languages that have been installed before the plugin was installed
				$languages = $this->Languages->getAll(Configure::get("Blesta.company_id"));
				
				// Add all email templates in other languages IFF they do not already exist
				$emails = Configure::get("Phpids.install.emails");
				foreach ($emails as $email) {
					$group = $this->EmailGroups->getByAction($email['action']);
					if ($group)
						$group_id = $group->id;
					else {
						$group_id = $this->EmailGroups->add(array(
							'action' => $email['action'],
							'type' => $email['type'],
							'plugin_dir' => $email['plugin_dir'],
							'tags' => $email['tags']
						));
					}
					
					// Set from hostname to use that which is configured for the company
					if (isset(Configure::get("Blesta.company")->hostname))
						$email['from'] = str_replace("@mydomain.com", "@" . Configure::get("Blesta.company")->hostname, $email['from']);
					
					// Add the email template for each language
					foreach ($languages as $language) {
						// Check if this email already exists for this language
						$template = $this->Emails->getByType(Configure::get("Blesta.company_id"), $email['action'], $language->code);
						
						// Template already exists for this language
						if ($template !== false)
							continue;
						
						// Add the missing email for this language
						$this->Emails->add(array(
							'email_group_id' => $group_id,
							'company_id' => Configure::get("Blesta.company_id"),
							'lang' => $language->code,
							'from' => $email['from'],
							'from_name' => $email['from_name'],
							'subject' => $email['subject'],
							'text' => $email['text'],
							'html' => $email['html']
						));
					}
				}
			}
			
			if (version_compare($current_version, "1.1.1", "<")) {
				$this->Record->query(
					"ALTER TABLE `log_phpids` DROP INDEX `company_id` ,
					ADD INDEX `company_id` ( `company_id` , `date_added` );"
				);
			}
		}
	}
	
	/**
	 * Performs any necessary cleanup actions
	 *
	 * @param int $plugin_id The ID of the plugin being uninstalled
	 * @param boolean $last_instance True if $plugin_id is the last instance across all companies for this plugin, false otherwise
	 */
	public function uninstall($plugin_id, $last_instance) {
		
		Loader::loadModels($this, array("EmailGroups", "Emails"));
		// Fetch the email template created by this plugin
		$group = $this->EmailGroups->getByAction("Phpids.email_alert");
		
		// Delete all emails templates belonging to this plugin's email group and company
		if ($group)
			$this->Emails->deleteAll($group->id, Configure::get("Blesta.company_id"));
		
		if ($last_instance) {
			try {
				$this->Record->drop("log_phpids");
				$this->Record->drop("phpids_settings");
				
				// Remove the email template created by this plugin
				if ($group)
					$this->EmailGroups->delete($group->id);
			}
			catch (Exception $e) {
				// Error dropping... no permission?
				$this->Input->setErrors(array('db'=> array('create'=>$e->getMessage())));
				return;
			}
		}
	}
	
	/**
	 * Returns all events to be registered for this plugin (invoked after install() or upgrade(), overwrites all existing events)
	 *
	 * @return array A numerically indexed array containing:
	 * 	- event The event to register for
	 * 	- callback A string or array representing a callback function or class/method. If a user (e.g. non-native PHP) function or class/method, the plugin must automatically define it when the plugin is loaded. To invoke an instance methods pass "this" instead of the class name as the 1st callback element.
	 */	
	public function getEvents() {
		return array(
			array(
				'event' => "Appcontroller.preAction",
				'callback' => array("this", "run")
			)
		);
	}
	
	/**
	 * Runs PHPIDS
	 *
	 * @param EventObject $event The event to process
	 */
	public function run($event) {
        // Set include path for IDS
        $path = get_include_path();
		$dir = dirname(__FILE__) . DS . "lib" . DS;
        set_include_path($dir);

        Loader::load($dir . "IDS/Init.php");

        // Run IDS
        $this->init = IDS_Init::init($dir . "IDS" . DS . "Config" . DS . "Config.ini.php");
		
		$this->init->config['General']['base_path'] = $dir . "IDS" . DS;
		$this->init->config['General']['use_base_path'] = true;
		$this->init->config['Caching']['caching'] = 'none';
		
        $ids = new IDS_Monitor(array(
				'GET' => $_GET,
				'POST' => $_POST,
				'COOKIE' => $_COOKIE,
			), $this->init);
		
        $report = $ids->run();

        // Reset include path
        set_include_path($path);

        if (!$report->isEmpty())
            $this->react($report);
	}
	
	/**
	 * React to the IDS report
	 *
     * @param IDS_Report $report The report from analyzing the $_REQUEST
	 */
	private function react(IDS_Report $report) {	
		
		Loader::loadModels($this, array("Phpids.PhpidsSettings", "Phpids.PhpidsLogs"));
		Loader::loadComponents($this, array("Session"));
		
		$impact = $report->getImpact();

		$data = array();
		foreach ($report as $event) {
			
			$data[] = array(
				'name' => $event->getName(),
				'value' => stripslashes($event->getValue()),
				'uri' => $_SERVER['REQUEST_URI'],
				'user_id' => $this->Session->read("blesta_id"),
				'ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null,
				'impact' => $impact,
				'tags' => $event->getTags()
			);
		}
		
		if ($impact > 0) {
			$settings = $this->PhpidsSettings->getAll();

			// Handle compound impact values
			if ($settings->compound_impact != "true")
				$this->Session->clear("phpids_impacts");
				
			$impacts = $this->Session->read("phpids_impacts");
			$session_impact = 0;
			if ($settings->compound_impact == "true") {
				if (is_array($impacts)) {
					foreach ($impacts as $time => $value) {
						if ($time < (time() - $settings->rotate_impact) % (24*60*60))
							unset($impacts[$time]);
						else
							$session_impact += $value;
					}
				}
				
				// Round all session entries to the nearest minute. Conserve
				// space by only storing the last 24-hours worth of digits
				$min = (round(time()/60)*60) % (24*60*60);
				if (!isset($impacts[$min]))
					$impacts[$min] = 0;
				$impacts[$min] += $impact;
				
				$this->Session->write("phpids_impacts", $impacts);
			}
			
			$impact = $session_impact + $impact;
			
			// Log
			if ($settings->log_min_score > 0 && $impact >= $settings->log_min_score) {
				if (isset($settings->log_rotate_freq) && $settings->log_rotate_freq > 0) {
					$this->PhpidsLogs->rotate(
						Configure::get("Blesta.company_id"),
						$settings->log_rotate_freq
					);
				}

				// Log the impact
				for ($i=0; $i<count($data); $i++)
					$this->PhpidsLogs->add($data[$i]);
			}
			
			// Email
			if ($settings->email_min_score > 0 && $impact >= $settings->email_min_score) {
				Loader::loadModels($this, array("Emails"));
			
				// Convert alert tags to string
				foreach ($data as &$item) {
					$item['value'] = htmlentities($item['value'], ENT_QUOTES, "UTF-8");
					$item['tags'] = implode(", ", $item['tags']);
				}
				$tags = array('data' => $data);

				$this->Emails->send("Phpids.email_alert", Configure::get("Blesta.company_id"), Configure::get("Blesta.language"), explode(",", $settings->email_addresses), $tags);
			}
			
			// Redirect
			if ($settings->redirect_min_score > 0 && $impact >= $settings->redirect_min_score) {
				if ($settings->redirect_url == "")
					$settings->redirect_url = WEBDIR;
				$this->redirect($settings->redirect_url);
			}
		}
	}
	
	/**
	 * Initiates a header redirect to the given URI/URL. Automatically prepends
	 * WEBDIR to $uri if $uri is relative (e.g. does not start with a '/' and is
	 * not a url)
	 *
	 * @param string $uri The URI or URL to redirect to
	 */
	private function redirect($uri) {
		$parts = parse_url($uri);
		$relative = true;
		if (substr($uri, 0, 1) == "/")
			$relative = false;
		// If not scheme is specified, assume http(s)
		if (!isset($parts['scheme'])) {
			$uri = "http" . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != "off" ? "s" : "") .
				"://" . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME']) .
				($relative ? WEBDIR : "") . $uri;
		}
		
		header("Location: " . $uri);
		exit;
	}
}
