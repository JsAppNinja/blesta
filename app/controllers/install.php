<?php
/**
 * Handle the installation process via web or command line
 *
 * @package blesta
 * @subpackage blesta.app.controllers
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class Install extends Controller {
	
	/**
	 * @var array An array of database connection details
	 */
	private $db_info = array();
	/**
	 * @var array An array of parameters passed via CLI
	 */
	private $params = array();
	/**
	 * @var boolean True if already installed, false otherwise
	 */
	private $installed = false;
	/**
	 * @var string The default server timezone
	 */
	private $server_timezone = "UTC";
	/**
	 * @var array An array of helpers
	 */
	protected $helpers = array("Html", "Form");
	
	/**
	 * Set up
	 */
	public function __construct() {
		// Set the vendor web directory path
		if (!defined("VENDORWEBDIR"))
			define("VENDORWEBDIR", str_replace("/index.php", "", WEBDIR) . "vendors/");

		// get default timezone from the server
		if (function_exists("date_default_timezone_get"))
			$this->server_timezone = date_default_timezone_get();
			
		// Set default timezone to UTC-time
		if (function_exists("date_default_timezone_set"))
			date_default_timezone_set("UTC");
			
		parent::__construct();
	}
	
	/**
	 * Check installed status
	 */
	public function preAction() {
		parent::preAction();
		
		// Check if installed
		Configure::load("blesta");
		$db_info = Configure::get("Blesta.database_info");
		if ($db_info && !empty($db_info))
			$this->installed = true;
		unset($db_info);
		
		if ($this->is_cli && $this->action != "index")
			return $this->processCli();
	}
	
	/**
	 * Process CLI installation
	 */
	private function processCli() {
		
		Loader::load(VENDORDIR . "consoleation" . DS . "consoleation.php");
		$this->Console = new Consoleation();
		
		if ($this->installed) {
			$this->Console->output("Already installed.\n");
			exit;
		}
		
		// Welcome message
		$this->Console->output("%s\nBlesta CLI Installer\n%s\n", str_repeat("-", 40), str_repeat("-", 40));
		
		// Set CLI args
		foreach ($_SERVER['argv'] as $i => $val) {
			if ($val == "-dbhost" && isset($_SERVER['argv'][$i+1]))
				$this->params['dbhost'] = $_SERVER['argv'][$i+1];
			if ($val == "-dbname" && isset($_SERVER['argv'][$i+1]))
				$this->params['dbname'] = $_SERVER['argv'][$i+1];
			if ($val == "-dbuser" && isset($_SERVER['argv'][$i+1]))
				$this->params['dbuser'] = $_SERVER['argv'][$i+1];
			if ($val == "-dbpass" && isset($_SERVER['argv'][$i+1]))
				$this->params['dbpass'] = $_SERVER['argv'][$i+1];
			if ($val == "-hostname" && isset($_SERVER['argv'][$i+1]))
				$this->params['hostname'] = $_SERVER['argv'][$i+1];
			if ($val == "-help") {
				$this->Console->output("The options are as follows:\n");
				$this->Console->output("-dbhost\tThe database host\n");
				$this->Console->output("-dbname\tThe database name\n");
				$this->Console->output("-dbuser\tThe database user\n");
				$this->Console->output("-dbpass\tThe database password\n");
				$this->Console->output("-hostname\tThe server hostname\n");
				$this->Console->output("Pass no paremters to install via interactive mode.\n");
				exit;
			}
		}

		if (empty($this->params)) {
			$this->agreeCli();
			$this->systemRequirementsCli();
		}
		$this->databaseCli();
		
		// Write the config
		$config_written = false;
		while (!$config_written) {
			$this->Console->output("Attempting to write config... ");
			
			try {
				$config_written = $this->writeConfig();
			}
			catch (Exception $e) {
				// nothing to do
			}
			
			if ($config_written)
				$this->Console->output("Success.\n");
			else {
				$this->Console->output("Ensure that the file (%s) is writable.\nPress any key to retry.", CONFIGDIR . "blesta-new.php");
				$this->Console->getLine();
			}
		}
		
		try {
			$this->postInstall();
		}
		catch (Exception $e) {
			$this->Console->output("\nERROR:" . $e->getMessage() . "\n");
			$this->Console->output("Installation FAILED.\n");
			exit(1);
		}
		
		$this->Console->output("\nFinished. To complete setup visit /admin/login/ in your browser, \nor if you do not have mod_rewrite, /index.php/admin/login/.\n");
		
		return false;
	}
	
	/**
	 * Agree to terms and conditions
	 */
	private function agreeCli() {
		$this->Console->output("Please acknowledge your agreement to the terms and conditions as explained at\nhttp://www.blesta.com/license/\n\n");
		$this->Console->output("Do you agree? (Y/N): ");
		
		$agreed = false;
		while (!$agreed) {
			switch (strtolower(substr($this->Console->getLine(), 0, 1))) {
				case "y":
					$agreed = true;
					break;
				case "n":
					exit;
				default:
					$this->Console->output("You must agree to the terms and conditions in order to continue.\n");
					$this->Console->output("Do you agree? (Y/N): ");
					break;
			}
		}
	}
	
	/**
	 * Handle system requirements check
	 */
	private function systemRequirementsCli() {
		$this->Console->output("Performing system requirements check...\n");
		
		if (($reqs = $this->meetsMinReq()) !== true) {
			
			$this->Console->output("The following minimum requirements failed:\n");
			
			foreach ($reqs as $key => $req) {
				$this->Console->output("\t" . $key . ": " . $req['message'] . "\n");
			}
			
			$this->Console->output("Failed minimum system requirements. You must correct these issues before continuing.\n");
			exit(2);
		}
		elseif (($reqs = $this->meetsRecReq()) !== true) {
			
			$this->Console->output("The following recommended requirements failed:\n");
			
			foreach ($reqs as $key => $req) {
				$this->Console->output("\t" . $key . ": " . $req['message'] . "\n");
			}
			
			$this->Console->output("Do you wish to continue anyway? (Y): ");
			if (strtolower(substr($this->Console->getLine(), 0, 1)) != "y")
				exit;
		}
	}
	
	/**
	 * Collect DB information, verify credentials and run DB installation
	 */
	private function databaseCli() {
		if (empty($this->params))
			$this->Console->output("You will now be asked to enter your database credentials.\n");
		
		$valid = false;
		while (!$valid) {
			if (empty($this->params)) {
				$this->Console->output("Database host (default localhost): ");
				$host = $this->Console->getLine();
				if (!$host)
					$host = "localhost";
				
				$database = "";
				while ($database == "") {
					$this->Console->output("Database name: ");
					$database = $this->Console->getLine();
					
					if ($database == "")
						$this->Console->output("\nA database name is required\n");
				}
				$this->Console->output("Database user: ");
				$user = $this->Console->getLine();
				$this->Console->output("Database password: ");
				$password = $this->Console->getLine();
				
				$this->Console->output("Attempting to verify database credentials... ");
			}
			else {
				$host = isset($this->params['dbhost']) ? $this->params['dbhost'] : null;
				$database = isset($this->params['dbname']) ? $this->params['dbname'] : null;
				$user = isset($this->params['dbuser']) ? $this->params['dbuser'] : null;
				$password = isset($this->params['dbpass']) ? $this->params['dbpass'] : null;
			}
			$this->db_info = array(
				'driver' => "mysql",
				'host'	=> $host,
				'database' => $database,
				'user' => $user,
				'pass' => $password,
				'persistent' => false,
				'charset_query' => "SET NAMES 'utf8'",
				'options' => array()
			);
			
			try {
				// Verify credentials by attempting connection to DB
				$this->components(array('Upgrades' => array($this->db_info)));
				
				$this->Console->output("OK\n");

				$this->Console->output("Checking InnoDB support... ");
				if (!$this->checkDb()) {
					$this->Console->output("FAILED\n");
					exit(3);
				}
				$this->Console->output("OK\n");
				
				$valid = true;
			}
			catch (Exception $e) {
				$this->Console->output("\nConnection FAILED. Ensure that you have created the database and that the credentials are correct.\n");
				if (!empty($this->params))
					exit(4);
			}
		}
		
		$statement = $this->Upgrades->query("SHOW TABLES");
		if ($statement->fetch()) {
			$this->Console->output("Installation cannot continue unless the database is empty.\n");
			exit(5);
		}
		$statement->closeCursor();
		
		// Install database schema
		$this->Console->output("Installing database...\n");
		$this->Upgrades->processSql(COMPONENTDIR . "upgrades" . DS . "db" . DS . "schema.sql", array($this->Console, "progressBar"));
		$this->Console->output("Completed.\n");
		
		// Set initial state of the database
		$this->Console->output("Configuring database...\n");
		$this->Upgrades->processSql(COMPONENTDIR . "upgrades" . DS . "db" . DS . "3.0.0" . DS . "1.sql", array($this->Console, "progressBar"));
		$this->Console->output("Completed.\n");
		
		// Attempt to upgrade from base database to current version
		$this->Console->output("Upgrading database...\n");
		$this->Upgrades->start("3.0.0-a3", null, array($this->Console, "progressBar"));
		
		if (($errors = $this->Upgrades->errors())) {
			$this->Console->output("Upgrade could not complete, the following errors occurred:\n");
			foreach ($errors as $key => $value) {
				$this->Console->output(implode("\n", $value) . "\n");
			}
		}
		else
			$this->Console->output("Completed.\n\n");
	}
	
	/**
	 * Write the config file details
	 *
	 * @return false If the file could not be renamed (e.g. written to)
	 */
	private function writeConfig() {
		
		// Attempt to rename the config from blesta-new.php to blesta.php
		if (!rename(CONFIGDIR . "blesta-new.php", CONFIGDIR . "blesta.php"))
			return false;
		
		// Generate a sufficiently large random value
		Loader::load(VENDORDIR . "phpseclib" . DS . "Crypt" . DS . "Random.php");
		$system_key = md5(crypt_random() . uniqid(php_uname('n'), true)) . md5(uniqid(php_uname('n'), true) . crypt_random());
		
		$config = file_get_contents(CONFIGDIR . "blesta.php");
		$replacements = array(
			'{database_host}' => $this->db_info['host'],
			'{database_name}' => $this->db_info['database'],
			'{database_user}' => $this->db_info['user'],
			'{database_password}' => $this->db_info['pass'],
			'{system_key}' => $system_key
		);
		foreach ($replacements as &$value) {
			$value = str_replace(array('\\', '$', '"'), array('\\\\', '\$', '\"'), $value);
		}
		
		file_put_contents(CONFIGDIR . "blesta.php", str_replace(array_keys($replacements), array_values($replacements), $config));
		
		return true;
	}
	
	/**
	 * Install
	 */
	public function index() {
		
		// Process the command line installation if requested via CLI
		if ($this->is_cli)
			return $this->processCli();

		// If already installed send to admin interface
		if ($this->installed)
			$this->redirect(WEBDIR);
			
		$this->structure->set("title", "Blesta Installer");
	}
	
	/**
	 * Process GUI installation
	 */
	public function process() {
		
		// Nothing to do here if CLI
		if ($this->is_cli)
			return;

		// If already installed send to admin interface
		if ($this->installed)
			$this->redirect(WEBDIR);
		
		// Test for minimum requirements
		$pass_min = $this->meetsMinReq();
		// Test for recommended requirements
		$pass_rec = $this->meetsRecReq();
		
		$error = false;
		if (!empty($this->post)) {
			
			// Ensure acceptance of license agreement
			if (!isset($this->post['agree']) || $this->post['agree'] != "yes") {
				$error = "You must agree to the terms and conditions in order to continue.";
			}
			
			// Ensure passes min requirements
			if (!$error && $pass_min !== true) {
				$error = "Failed minimum system requirements. You must correct these issues before continuing.";
			}
			
			// Check database credentials
			if (!$error) {
				$this->db_info = array(
					'driver' => "mysql",
					'host'	=> $this->post['host'],
					'database' => $this->post['database'],
					'user' => $this->post['user'],
					'pass' => $this->post['password'],
					'persistent' => false,
					'charset_query' => "SET NAMES 'utf8'",
					'options' => array()
				);
				
				try {
					// Verify credentials by attempting connection to DB
					$this->components(array('Upgrades' => array($this->db_info)));
					
					if (!$this->checkDb())
						$error = "Failed InnoDB support check.";
				}
				catch (Exception $e) {
					$error = "Database connection FAILED. Ensure that you have created the database and that the credentials are correct.";
				}
			}
			
			// Install database
			if (!$error) {
				$statement = $this->Upgrades->query("SHOW TABLES");
				if ($statement->fetch()) {
					$error = "Installation cannot continue unless the database is empty.\n";
				}
				$statement->closeCursor();
				
				if (!$error) {
					// Install database schema
					$this->Upgrades->processSql(COMPONENTDIR . "upgrades" . DS . "db" . DS . "schema.sql");
					
					// Set initial state of the database
					$this->Upgrades->processSql(COMPONENTDIR . "upgrades" . DS . "db" . DS . "3.0.0" . DS . "1.sql");
					
					// Attempt to upgrade from base database to current version
					$this->Upgrades->start("3.0.0-a3", null);
					
					if (($errors = $this->Upgrades->errors())) {
						if ($this->is_cli)
							$this->Console->output("Upgrade could not complete, the following errors occurred:\n");
						$error = array();
						foreach ($errors as $key => $value) {
							if ($this->is_cli)
								$this->Console->output(implode("\n", (array)$value) . "\n");
							$error[] = implode("\n", (array)$value); 
						}
					}
				}
			}
			
			// Write config
			if (!$error) {
				$config_written = false;
				try {
					$config_written = $this->writeConfig();
				}
				catch (Exception $e) {
					// nothing to do
				}
				
				if (!$config_written) {
					$error = sprintf("Ensure that the file (%s) is writable.", CONFIGDIR . "blesta-new.php");
				}
			}
			
			// Post install
			if (!$error) {
				try {
					$this->postInstall();
					$this->redirect(WEBDIR . Configure::get("Route.admin") . "/");
				}
				catch (Exception $e) {
					$error = sprintf("Installation FAILED: %s", $e->getMessage());
				}
			}
			
			if ($error)
				$this->setMessage("error", $error);
		}
		
		$this->set("vars", (object)$this->post);
		$this->set("min_requirements", $this->getMinReq());
		$this->set("pass_min", $pass_min);
		$this->set("rec_requirements", $this->getRecReq());
		$this->set("pass_rec", $pass_rec);
		$this->structure->set("title", "Blesta Installer");
	}
	
	/**
	 * Finish the installation process by generating key pairs and installing base plugins
	 */
	private function postInstall() {
		
		// Load our newly created config file
		Configure::load("blesta");
		// Set the database connection profile
		Configure::set("Database.profile", Configure::get("Blesta.database_info"));
		
		Configure::set("Blesta.company_id", 1);
		
		$this->uses(array("Companies", "PluginManager", "Settings"));
		
		// Set temp directory
		$this->Settings->setSetting("temp_dir", $this->tmpDir());
		
		// Set default timezone
		$this->Companies->setSetting(Configure::get("Blesta.company_id"), "timezone", $this->server_timezone);
		
		if ($this->is_cli)
			$this->Console->output("Generating encryption keys. This may take a minute or two... ");
		
		// Generate/set key pair
		$key_length = 1024;
		// Only allow large keys if the system can handle them efficiently
		if (extension_loaded('gmp'))
			$key_length = 3072;
		$key_pair = $this->Companies->generateKeyPair(Configure::get("Blesta.company_id"), $key_length);

		// Set hostname for default company
		$hostname = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : null;
		if ($this->is_cli)
			$hostname = isset($this->params['hostname']) ? $this->params['hostname'] : php_uname("n");
			
		$this->Companies->edit(Configure::get("Blesta.company_id"), array('hostname' => $hostname));

		if ($this->is_cli)
			$this->Console->output("Done.\n");

		// Set plugins that need to be installed by default
		$plugins = array(
			"system_status",
			"system_overview",
			"billing_overview",
			"feed_reader",
			"cms",
			"order",
			"support_manager"
		);
		
		if ($this->is_cli)
			$this->Console->output("Installing default plugins... ");
		
		// Install base plugins
		foreach ($plugins as $plugin) {
			$this->PluginManager->add(array('dir' => $plugin, 'company_id' => Configure::get("Blesta.company_id"), 'staff_group_id' => 1));
		}
		
		if ($this->is_cli)
			$this->Console->output("Done.\n");
	}
	
	/**
	 * Determine the location of the temp directory on this system
	 */
	private function tmpDir() {
		$dir = ini_get("upload_tmp_dir");

		if (!$dir && function_exists("sys_get_temp_dir"))
			$dir = sys_get_temp_dir();
			
		if (!$dir) {
			$dir = "/tmp/";		
			if ($this->getOs() == "WIN")
				$dir = "C:\\Windows\\TEMP\\";
		}
		
		$dir = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
		
		return $dir;
	}
	
	/**
	 * Check DB support
	 *
	 * @return boolean True if InnoDB is supported, false otherwise
	 */
	private function checkDb() {
		$innodb_supported = false;

		try {
			$engines = $this->Upgrades->query("SHOW ENGINES")->fetchAll();
			foreach ($engines as $engine) {
				if (strtolower($engine->engine) == "innodb") {
					if (strtolower($engine->support) == "yes" || strtolower($engine->support) == "default") {
						$innodb_supported = true;
					}
					break;
				}
			}
		}
		catch (Exception $e) {
			// Check for InnoDB support
			$statement = $this->Upgrades->query("SHOW VARIABLES LIKE 'have_innodb'");
			$have_innodb = $statement->fetch();
			$statement->closeCursor();
			
			if (strtolower($have_innodb->value) == "yes")
				$innodb_supported = true;
		}
		return $innodb_supported;
	}
	
	/**
	 * Sets the given error type into the view
	 * 
	 * @param string $type The type of message ("message", "error", "info", or "notice")
	 * @param string $value The text to display
	 * @param boolean $return True to return the message, false to set it withing the view
	 * @param array $params An array of additional parameters to set to the message view
	 * @param boolean $in_current_view True to set the message in the current view, false to set in the default view directory. That is, if invoking this method from a plugin, then setting $in_current_view to false will set the message to the default view, else a message.pdt file will be loaded from the plugin view directory.
	 * @param boolean $return Whether or not to return the message content rather than set it
	 */
	protected function setMessage($type, $value, $return=false) {
		$this->messages[$type] = $value;
		
		$message = $this->partial("message", $this->messages, Configure::get("System.default_view"));
		
		if ($return)
			return $message;
		$this->set("message", $message);
	}
	
	/**
	 * Returns an array of minimum requirements
	 *
	 * @return array An associative array of requirements
	 */
	protected function getMinReq() {
		Language::loadLang("system_requirements");
		
		// Fetch current version of extensions
		$openssl = array();
		if (defined("OPENSSL_VERSION_TEXT"))
			preg_match("/[0-9]+\.[0-9]+\.[0-9]+/i", OPENSSL_VERSION_TEXT, $openssl);
		$curl = null;
		if (function_exists("curl_version")) {
			$temp = curl_version();
			$curl = $temp['version'];
			unset($temp);
		}

		// Requirements and their values
		$reqs = array(
			'php' => array("message"=>Language::_("SystemRequirements.!error.php.minimum", true, "5.1.3", PHP_VERSION), "req"=>"5.1.3", "cur"=>PHP_VERSION), // 5.1.3 because of ReflectionClass::newInstanceArgs()
			'PDO' => array("message"=>Language::_("SystemRequirements.!error.pdo.minimum", true), "req"=>true, "cur"=>extension_loaded("PDO")),
			'pdo_mysql' => array("message"=>Language::_("SystemRequirements.!error.pdo_mysql.minimum", true), "req"=>true, "cur"=>extension_loaded("pdo_mysql")),
			'curl' => array("message"=>Language::_("SystemRequirements.!error.curl.minimum", true, "7.10.5", $curl), "req"=>"7.10.5", "cur"=>$curl),
			'openssl' => array("message"=>Language::_("SystemRequirements.!error.openssl.minimum", true, "0.9.6", isset($openssl[0]) ? $openssl[0] : ""), "req"=>"0.9.6", "cur"=>isset($openssl[0]) ? $openssl[0] : ""),
			'ioncube' => array("message"=>Language::_("SystemRequirements.!error.ioncube.minimum", true), "req"=>true, "cur"=>extension_loaded("ionCube Loader")),
			'config_writable' => array("message"=>Language::_("SystemRequirements.!error.config_writable.minimum", true, CONFIGDIR . "blesta-new.php", CONFIGDIR), "req"=>true, "cur"=>is_writable(CONFIGDIR) && is_writable(CONFIGDIR . "blesta-new.php")), // To auto-write config file to config dir
		);
		
		return $reqs;
	}
	
	/**
	 * Returns an array of recommended requirements
	 *
	 * @return array An associative array of requirements
	 */
	protected function getRecReq() {
		Language::loadLang("system_requirements");
		
		// Requirements and their values
		$reqs = array(
			'php' => array("message"=>Language::_("SystemRequirements.!warning.php.recommended", true, "5.2"), "req"=>"5.2", "cur"=>PHP_VERSION), // Recommended version of PHP
			//'ldap' => array("message"=>Language::_("SystemRequirements.!warning.ldap.recommended", true), "req"=>true, "cur"=>extension_loaded("ldap")), // LDAP login
			'mcrypt' => array("message"=>Language::_("SystemRequirements.!warning.mcrypt.recommended", true), "req"=>true, "cur"=>extension_loaded("mcrypt")), // Faster encryption/decryption
			'gmp' => array("message"=>Language::_("SystemRequirements.!warning.gmp.recommended", true), "req"=>true, "cur"=>extension_loaded("gmp")), // Faster BigInteger (for RSA encryption/decrpytion)
			'json' => array("message"=>Language::_("SystemRequirements.!warning.json.recommended", true), "req"=>true, "cur"=>extension_loaded("json")), // Faster JSON encoding/decoding
			'cache_writable' => array("message"=>Language::_("SystemRequirements.!warning.cache_writable.recommended", true, CACHEDIR), "req"=>true, "cur"=>is_writable(CACHEDIR)), // To cache view files for performance
			'memory_limit' => array("message"=>Language::_("SystemRequirements.!warning.memory_limit.recommended", true, "32M", ini_get("memory_limit")), "req"=>"32M", "cur"=>ini_get("memory_limit")), // Generally for TCPDF docs with unicode fonts
			'register_globals' => array("message"=>Language::_("SystemRequirements.!warning.register_globals.recommended", true), "req"=>false, "cur"=>ini_get("register_globals")), // Disabled to prevent magic variables
			'imap' => array("message"=>Language::_("SystemRequirements.!warning.imap.recommended", true), "req"=>true, "cur"=>extension_loaded("imap")), // send/receive mail via SMTP/IMAP
			'simplexml' => array("message"=>Language::_("SystemRequirements.!warning.simplexml.recommended", true), "req"=>true, "cur"=>extension_loaded("simplexml") && extension_loaded("libxml")), // Some modules/gateways may require XML parsing support
			'zlib' => array("message"=>Language::_("SystemRequirements.!warning.zlib.recommended", true), "req"=>true, "cur"=>extension_loaded("zlib")),
			'mbstring' => array("message"=>Language::_("SystemRequirements.!warning.mbstring.recommended", true), "req"=>true, "cur"=>extension_loaded("mbstring")), // Libraries that require multi-byte string handling
			'gd' => array("message"=>Language::_("SystemRequirements.!warning.gd.recommended", true), "req"=>true, "cur"=>extension_loaded("gd")) // Invoice PDFs may perform image manipulation
		);
		
		return $reqs;
	}
	
	/**
	 * Checks to ensure that the current system meets the required minimum
	 * requirements.
	 *
	 * @return mixed Boolean true if all requirements met, an array of failed requirements on failure
	 */
	protected function meetsMinReq() {
		$failed_reqs = array();
		$min_reqs = $this->getMinReq();
		
		// Fetch all installed extensions
		$extensions = get_loaded_extensions();
		
		// Check PHP version >= 5.1
		if (version_compare($min_reqs['php']['cur'], $min_reqs['php']['req'], "<"))
			$failed_reqs['php'] = $min_reqs['php'];
		// Check PDO installed
		if (!in_array("PDO", $extensions))
			$failed_reqs['PDO'] = $min_reqs['PDO'];
		// Check PDO MySQL driver available
		if (!in_array("pdo_mysql", $extensions))
			$failed_reqs['pdo_mysql'] = $min_reqs['pdo_mysql'];
		// Check curl installed
		if (!in_array("curl", $extensions) || version_compare($min_reqs['curl']['cur'], $min_reqs['curl']['req'], "<"))
			$failed_reqs['curl'] = $min_reqs['curl'];
		// Check openSSL installed
		if (!in_array("openssl", $extensions) || version_compare($min_reqs['openssl']['cur'], $min_reqs['openssl']['req'], "<"))
			$failed_reqs['openssl'] = $min_reqs['openssl'];
		// Check IonCube installed
		if (!in_array("ionCube Loader", $extensions))
			$failed_reqs['ioncube'] = $min_reqs['ioncube'];
		// Check that the config is writable, if expected
		if (!$min_reqs['config_writable']['cur'] && $min_reqs['config_writable']['req'])
			$failed_reqs['config_writable'] = $min_reqs['config_writable'];
		
		if (empty($failed_reqs))
			return true;
		return $failed_reqs;
	}

	/**
	 * Checks to ensure that the current system meets the recommended minimum
	 * requirements.
	 *
	 * @return mixed Boolean true on success, an array of failed requirements on failure
	 */	
	protected function meetsRecReq() {
		$failed_reqs = array();
		$rec_reqs = $this->getRecReq();
		
		// Check that PHP is at least the required version
		if (version_compare($rec_reqs['php']['cur'], $rec_reqs['php']['req'], "<"))
			$failed_reqs['php'] = $rec_reqs['php'];
			
		// Check that ldap is installed, if expected
		//if (!$rec_reqs['ldap']['cur'] && $rec_reqs['ldap']['req'])
		//	$failed_reqs['ldap'] = $rec_reqs['ldap'];
			
		// Check that mcrypt is installed, if expected
		if (!$rec_reqs['mcrypt']['cur'] && $rec_reqs['mcrypt']['req'])
			$failed_reqs['mcrypt'] = $rec_reqs['mcrypt'];
			
		// Check that json is installed, if expected
		if (!$rec_reqs['json']['cur'] && $rec_reqs['json']['req'])
			$failed_reqs['json'] = $rec_reqs['json'];
			
		// Check that the cache is writable, if expected
		if (!$rec_reqs['cache_writable']['cur'] && $rec_reqs['cache_writable']['req'])
			$failed_reqs['cache_writable'] = $rec_reqs['cache_writable'];
			
		// Check that imap is installed, if expected
		if (!$rec_reqs['imap']['cur'] && $rec_reqs['imap']['req'])
			$failed_reqs['imap'] = $rec_reqs['imap'];

		// Check that simplyxml is installed, if expected
		if (!$rec_reqs['simplexml']['cur'] && $rec_reqs['simplexml']['req'])
			$failed_reqs['simplexml'] = $rec_reqs['simplexml'];
			
		// Check that register globals is disabled
		if ($rec_reqs['register_globals']['cur'] && !$rec_reqs['register_globals']['req'])
			$failed_reqs['register_globals'] = $rec_reqs['register_globals'];
			
		// Check that zlib is installed, if expected
		if (!$rec_reqs['zlib']['cur'] && $rec_reqs['zlib']['req'])
			$failed_reqs['zlib'] = $rec_reqs['zlib'];
			
		// Check that mbstring is installed, if expected
		if (!$rec_reqs['mbstring']['cur'] && $rec_reqs['mbstring']['req'])
			$failed_reqs['mbstring'] = $rec_reqs['mbstring'];

		// Check that gd is installed, if expected
		if (!$rec_reqs['gd']['cur'] && $rec_reqs['gd']['req'])
			$failed_reqs['gd'] = $rec_reqs['gd'];
		
		// Check that memory_limit is large enough
		$bytes = array("K"=>1024, "M"=>1024*1024, "G"=>1024*1024*1024);
		$cur = (int)$rec_reqs['memory_limit']['cur'] * (($c = substr($rec_reqs['memory_limit']['cur'], -1)) && isset($bytes[$c])) ? $bytes[$c] : 1;
		$req = (int)$rec_reqs['memory_limit']['req'] * (($c = substr($rec_reqs['memory_limit']['req'], -1)) && isset($bytes[$c])) ? $bytes[$c] : 1;
		
		if ($cur > 0 && $cur < $req)
			$failed_reqs['memory_limit'] = $rec_reqs['memory_limit'];
		
		if (empty($failed_reqs))
			return true;
		return $failed_reqs;
	}
	
	/**
	 * Creates a data dump of current system settings for the given field or fields.
	 *
	 * @param mixed $fields The system info field to fetch or "all", or an array of system info fields (server, ini, ext, php)
	 * @return array An array of system data
	 */
	protected function systemInfo($fields="all") {
		$default_fields = array('server', 'ini', 'ext', 'php');
		if (!is_array($fields))
			$fields = ($fields == "all" ? $default_fields : array($fields));
		
		// Remove any duplicate fields
		$fields = array_unique($fields);

		$dump = array();
		
		$field_count = count($fields);
		for ($i=0; $i<$field_count; $i++) {
			switch ($fields[$i]) {
				case "server":
					$dump['server'] = $_SERVER;
					break;
				case "ini":
					$dump['ini'] = ini_get_all();
					break;
				case "ext":
					$dump['ext'] = array();
					$temp_ext = get_loaded_extensions();
					$ext_count = count($temp_ext);
					// Get the version of each extension (where available)
					for ($j=0; $j<$ext_count; $j++)
						$dump['ext'][$temp_ext[$j]] = phpversion($temp_ext[$j]);
						
					unset($ext_count);
					unset($temp_ext);
					break;
				case "php":
					$dump['php'] = PHP_VERSION;
					break;
			}
		}
		
		return $dump;
	}
	
	/**
	 * Determine the operating system on this system
	 *
	 * @return string A 3-character OS name (e.g. LIN or WIN)
	 */
	protected function getOs() {
		return strtoupper(substr(php_uname("s"), 0, 3));
	}
}
?>