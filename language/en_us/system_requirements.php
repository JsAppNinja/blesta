<?php
/**
 * Language definitions for system requirements
 * 
 * @package blesta
 * @subpackage blesta.language.en_us
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */

// Minimum requirements
$lang['SystemRequirements.!error.php.minimum'] = "PHP version %1\$s or greater is required. Your version: %2\$s."; // %1$s is the minimum version of PHP (i.e. 5.1.3), %2$s is the current version
$lang['SystemRequirements.!error.pdo.minimum'] = "The PDO extension is required.";
$lang['SystemRequirements.!error.pdo_mysql.minimum'] = "The pdo_mysql extension is required.";
$lang['SystemRequirements.!error.curl.minimum'] = "The curl extension with a minimum version of %1\$s is required. Your version: %2\$s"; // %1$s is the minimum curl version, %2$s is the current version
$lang['SystemRequirements.!error.openssl.minimum'] = "The openssl extension with a minimum version of %1\$s is required. Your version: %2\$s"; // %1$s is the minimum curl version, %2$s is the current version
$lang['SystemRequirements.!error.ioncube.minimum'] = "The ionCube Loader extension is required.";
$lang['SystemRequirements.!error.config_writable.minimum'] = "The config file (%1\$s) and directory (%2\$s) must be writable by the webserver (you can change these back after installation is complete)."; // %1$s is the absolute path to the config file, %2$s is the absolute path to the config directory

// Recommended requirements
$lang['SystemRequirements.!warning.php.recommended'] = "For best results, PHP %1\$s or greater is recommended."; // %1$s is the version of PHP that is recommended (i.e. 5.2)
$lang['SystemRequirements.!warning.ldap.recommended'] = "The LDAP extension is recommended for organizations using LDAP.";
$lang['SystemRequirements.!warning.mcrypt.recommended'] = "The mcrypt extension is recommended for better performance.";
$lang['SystemRequirements.!warning.mbstring.recommended'] = "The mbstring extension is required by some optional features.";
$lang['SystemRequirements.!warning.gmp.recommended'] = "The gmp extension is highly recommended for better performance.";
$lang['SystemRequirements.!warning.json.recommended'] = "The json extension is recommended for better performance.";
$lang['SystemRequirements.!warning.cache_writable.recommended'] = "For better performance ensure that %1\$s is writable by the webserver."; // %1$s is the absolute path to the cache directory
$lang['SystemRequirements.!warning.memory_limit.recommended'] = "For best results a memory limit of at least %1\$s is recommended. Your setting: %2\$s"; // %1$s is the recommended memory limit for PHP, %2$s is the current memory limit
$lang['SystemRequirements.!warning.register_globals.recommended'] = "For added security, 'register_globals' should be disabled.";
$lang['SystemRequirements.!warning.imap.recommended'] = "The imap extension is required to send and receive mail via SMTP and IMAP.";
$lang['SystemRequirements.!warning.simplexml.recommended'] = "The simplexml and libxml extensions are highly recommended as they may be required to interface with some systems.";
$lang['SystemRequirements.!warning.zlib.recommended'] = "The zlib extension is highly recommend for better performance.";
$lang['SystemRequirements.!warning.gd.recommended'] = "The gd extension is recommended for better image support during PDF generation.";
?>