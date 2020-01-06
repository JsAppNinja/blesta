<?php
/**
 * Language definitions for the Admin Company Plugin settings controller/views
 * 
 * @package blesta
 * @subpackage blesta.language.en_us
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */

// Success messages
$lang['AdminCompanyPlugins.!success.installed'] = "The plugin was successfully installed. It may have registered ACL permissions for various resources. You may need to grant your staff group access to these permissions in order to access these resources.";
$lang['AdminCompanyPlugins.!success.uninstalled'] = "The plugin was successfully uninstalled.";
$lang['AdminCompanyPlugins.!success.upgraded'] = "The plugin was successfully upgraded.";
$lang['AdminCompanyPlugins.!success.enabled'] = "The plugin was successfully enabled.";
$lang['AdminCompanyPlugins.!success.disabled'] = "The plugin was successfully disabled.";


// Error messages
$lang['AdminCompanyPlugins.!error.setting_controller_invalid'] = "The settings controller specified does not exist for that plugin.";


// Available plugins
$lang['AdminCompanyPlugins.available.page_title'] = "Settings > Company > Plugins > Available";
$lang['AdminCompanyPlugins.available.boxtitle_plugins'] = "Available Plugins";
$lang['AdminCompanyPlugins.available.text_version'] = "(ver %1\$s)"; // %1$s is the version number of the plugin
$lang['AdminCompanyPlugins.available.text_author'] = "Author: ";
$lang['AdminCompanyPlugins.available.btn_install'] = "Install";
$lang['AdminCompanyPlugins.available.text_none'] = "There are no available plugins.";


// Installed plugins
$lang['AdminCompanyPlugins.installed.page_title'] = "Settings > Company > Plugins > Installed";
$lang['AdminCompanyPlugins.installed.boxtitle_plugin'] = "Installed Plugins";
$lang['AdminCompanyPlugins.installed.text_version'] = "(ver %1\$s)"; // %1$s is the version number of the plugin
$lang['AdminCompanyPlugins.installed.text_author'] = "Author: ";
$lang['AdminCompanyPlugins.installed.confirm_uninstall'] = "Really uninstall this plugin?";
$lang['AdminCompanyPlugins.installed.confirm_disable'] = "Really disable this plugin?";
$lang['AdminCompanyPlugins.installed.confirm_enable'] = "Really enable this plugin?";
$lang['AdminCompanyPlugins.installed.btn_uninstall'] = "Uninstall";
$lang['AdminCompanyPlugins.installed.btn_disable'] = "Disable";
$lang['AdminCompanyPlugins.installed.btn_enable'] = "Enable";
$lang['AdminCompanyPlugins.installed.btn_manage'] = "Manage";
$lang['AdminCompanyPlugins.installed.btn_upgrade'] = "Upgrade";
$lang['AdminCompanyPlugins.installed.text_none'] = "There are no installed plugins.";
?>