<?php
/**
 * Handles navigation.
 *
 * @package blesta
 * @subpackage blesta.app.models
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class Navigation extends AppModel {
	
	/**
	 * Initialize Navigation
	 */
	public function __construct() {
		parent::__construct();
		Language::loadLang(array("navigation"));
	}
	
	/**
	 * Retrieves the primary navigation
	 *
	 * @param string $base_uri The base_uri for the currently logged in user
	 * @return array An array of main navigation elements in key/value pairs where each key is the URI and each value is an array representing that element including:
	 * 	- name The name of the link
	 * 	- active True if the element is active
	 * 	- sub An array of subnav elements (optional) following the same indexes as above
	 */
	public function getPrimary($base_uri) {
		
		$nav = array(
			$base_uri => array(
				'name' => $this->_("Navigation.getprimary.nav_home"),
				'active' => false,
				'sub' => array(
					$base_uri => array(
						'name' => $this->_("Navigation.getprimary.nav_home_dashboard"),
						'active' => false
					),
					$base_uri . "main/calendar/" => array(
						'name' => $this->_("Navigation.getprimary.nav_home_calendar"),
						'active' => false
					)
				)
			),
			$base_uri . "clients/" => array(
				'name' => $this->_("Navigation.getprimary.nav_clients"),
				'active' => false,
				'sub' => array(
					$base_uri . "clients/" => array(
						'name' => $this->_("Navigation.getprimary.nav_clients_browse"),
						'active' => false
					)
				)
			),
			$base_uri . "billing/" => array(
				'name' => $this->_("Navigation.getprimary.nav_billing"),
				'active' => false,
				'sub' => array(
					$base_uri . "billing/" => array(
						'name' => $this->_("Navigation.getprimary.nav_billing_overview"),
						'active' => false
					),
					$base_uri . "billing/invoices/" => array(
						'name' => $this->_("Navigation.getprimary.nav_billing_invoices"),
						'active' => false
					),
					$base_uri . "billing/transactions/" => array(
						'name' => $this->_("Navigation.getprimary.nav_billing_transactions"),
						'active' => false
					),
					$base_uri . "billing/services/" => array(
						'name' => $this->_("Navigation.getprimary.nav_billing_services"),
						'active' => false
					),
					$base_uri . "reports/" => array(
						'name' => $this->_("Navigation.getprimary.nav_billing_reports"),
						'active' => false
					),
					$base_uri . "billing/printqueue/" => array(
						'name' => $this->_("Navigation.getprimary.nav_billing_printqueue"),
						'active' => false
					),
					$base_uri . "billing/batch/" => array(
						'name' => $this->_("Navigation.getprimary.nav_billing_batch"),
						'active' => false
					)
				)
			),
			$base_uri . "packages/" => array(
				'name' => $this->_("Navigation.getprimary.nav_packages"),
				'active' => false,
				'sub' => array(
					$base_uri . "packages/" => array(
						'name' => $this->_("Navigation.getprimary.nav_packages_browse"),
						'active' => false
					),
					$base_uri . "packages/groups/" => array(
						'name' => $this->_("Navigation.getprimary.nav_packages_groups"),
						'active' => false
					),
					$base_uri . "package_options/" => array(
						'route' => array(
							'controller' => "admin_package_options",
							'action' => "*"
						),
						'name' => $this->_("Navigation.getprimary.nav_package_options"),
						'active' => false
					)
				)
			),
			$base_uri . "tools/" => array(
				'name' => $this->_("Navigation.getprimary.nav_tools"),
				'active' => false,
				'sub' => array(
					$base_uri . "tools/logs/" => array(
						'name' => $this->_("Navigation.getprimary.nav_tools_logs"),
						'active' => false
					),
					$base_uri . "tools/convertcurrency/" => array(
						'name' => $this->_("Navigation.getprimary.nav_tools_currency"),
						'active' => false
					)
				)
			),
			// sets "settings" sub nav for admin_company_* controllers
			$base_uri . "settings/company/" => array(
				'route' => array(
					'controller' => "admin_company_(.+)",
					'action' => "*"
				),
				'name' => "", // intentionally left blank so it won't render
				'active' => false,
				'sub' => array(
					$base_uri . "settings/company/" => array(
						'route' => array(
							'controller' => "admin_company_(.+)",
							'action' => "*"
						),
						'name' => $this->_("Navigation.getprimary.nav_settings_company"),
						'active' => false
					),
					$base_uri . "settings/system/" => array(
						'route' => array(
							'controller' => "admin_system_(.+)",
							'action' => "*"
						),
						'name' => $this->_("Navigation.getprimary.nav_settings_system"),
						'active' => false
					)
				)
			),
			// sets "settings" sub nav for admin_system_* controllers
			$base_uri . "settings/system/" => array(
				'route' => array(
					'controller' => "admin_system_(.+)",
					'action' => "*"
				),
				'name' => "", // intentionally left blank so it won't render
				'active' => false,
				'sub' => array(
					$base_uri . "settings/company/" => array(
						'route' => array(
							'controller' => "admin_company_(.+)",
							'action' => "*"
						),
						'name' => $this->_("Navigation.getprimary.nav_settings_company"),
						'active' => false
					),
					$base_uri . "settings/system/" => array(
						'route' => array(
							'controller' => "admin_system_(.+)",
							'action' => "*"
						),
						'name' => $this->_("Navigation.getprimary.nav_settings_system"),
						'active' => false
					)
				)
			)
		);
		
		// Set plugin primary nav elements
		$plugin_nav = $this->getPluginNav("nav_primary_staff");
		
		foreach ($plugin_nav as $element) {
			$nav[$base_uri . $element->uri] = array(
				'name' => $element->name,
				'active' => false
			);
			
			// Set primary nav sub nav items if set
			if (isset($element->options['sub'])) {
				$nav[$base_uri . $element->uri]['sub'] = array();
				foreach ($element->options['sub'] as $sub) {
					$nav[$base_uri . $element->uri]['sub'][$base_uri . $sub['uri']] = array(
						'name' => $sub['name'],
						'active' => false
					);
				}
			}
		}
		
		// Set plugin secondary nav elements
		$plugin_nav = $this->getPluginNav("nav_secondary_staff");
		
		foreach ($plugin_nav as $element) {
			if (!isset($element->options['parent']))
				continue;
			
			if (isset($nav[$base_uri . $element->options['parent']])) {
				$nav[$base_uri . $element->options['parent']]['sub'][$base_uri . $element->uri] = array(
					'name' => $element->name,
					'active' => false
				);
			}
		}
		return $nav;
	}
	
	/**
	 * Retrieves the primary navigation for the client interface
	 *
	 * @param string $base_uri The base_uri for the currently logged in user
	 * @return array An array of main navigation elements in key/value pairs where each key is the URI and each value is an array representing that element including:
	 * 	- name The name of the link
	 * 	- active True if the element is active
	 * 	- sub An array of subnav elements (optional) following the same indexes as above
	 */
	public function getPrimaryClient($base_uri) {
		
		$nav = array(
			$base_uri => array(
				'name' => $this->_("Navigation.getprimaryclient.nav_dashboard"),
				'active' => false
			),
			$base_uri . "accounts/" => array(
				'name' => $this->_("Navigation.getprimaryclient.nav_paymentaccounts"),
				'active' => false,
				'secondary' => array(
					$base_uri . "accounts/" => array(
						'name' => $this->_("Navigation.getprimaryclient.nav_paymentaccounts"),
						'active' => false,
						'icon' => "fa fa-list"
					),
					$base_uri . "accounts/add/" => array(
						'name' => $this->_("Navigation.getprimaryclient.nav_paymentaccounts_add"),
						'active' => false,
						'icon' => "fa fa-plus-square"
					),
					$base_uri => array(
						'name' => $this->_("Navigation.getprimaryclient.nav_return"),
						'active' => false,
						'icon' => "fa fa-arrow-left"
					)
				)
			),
			$base_uri . "contacts/" => array(
				'name' => $this->_("Navigation.getprimaryclient.nav_contacts"),
				'active' => false,
				'secondary' => array(
					$base_uri . "contacts/" => array(
						'name' => $this->_("Navigation.getprimaryclient.nav_contacts"),
						'active' => false,
						'icon' => "fa fa-list"
					),
					$base_uri . "contacts/add/" => array(
						'name' => $this->_("Navigation.getprimaryclient.nav_contacts_add"),
						'active' => false,
						'icon' => "fa fa-plus-square"
					),
					$base_uri => array(
						'name' => $this->_("Navigation.getprimaryclient.nav_return"),
						'active' => false,
						'icon' => "fa fa-arrow-left"
					)
				)
			)
		);
		
		$plugin_nav = $this->getPluginNav("nav_primary_client");
		
		foreach ($plugin_nav as $element) {
			$nav[$base_uri . $element->uri] = array(
				'name' => $element->name,
				'active' => false,
				'icon' => isset($element->icon) ? $element->icon : null
			);
			
			// Set secondary nav sub nav items if set
			if (isset($element->options['secondary'])) {
				$nav[$base_uri . $element->uri]['secondary'] = array();
				foreach ($element->options['secondary'] as $sub) {
					$nav[$base_uri . $element->uri]['secondary'][$base_uri . $sub['uri']] = array(
						'name' => $sub['name'],
						'active' => false,
						'icon' => isset($sub['icon']) ? $sub['icon'] : null
					);
				}
			}
			
			// Set primary nav sub nav items if set
			if (isset($element->options['sub'])) {
				$nav[$base_uri . $element->uri]['sub'] = array();
				foreach ($element->options['sub'] as $sub) {
					$nav[$base_uri . $element->uri]['sub'][$base_uri . $sub['uri']] = array(
						'name' => $sub['name'],
						'active' => false,
						'icon' => isset($sub['icon']) ? $sub['icon'] : null
					);
				}
			}
		}
		
		return $nav;
	}
	
	/**
	 * Retrieves the navigation for company settings
	 *
	 * @param string $base_uri The base_uri for the currently logged in user
	 * @return array A numerically-indexed array of the company settings navigation where each element contains an array which includes:
	 * 	- name The name of the element
	 * 	- class The CSS class name for the element
	 * 	- uri The URI for the element
	 * 	- children An array of child elements which follow the same indexes as above
	 */
	public function getCompany($base_uri) {
		$nav = array(
			array(
				'name' => $this->_("Navigation.getcompany.nav_general"),
				'class' => "general",
				'uri' => $base_uri . "settings/company/general/",
				'children' => array(
					array(
						'name' => $this->_("Navigation.getcompany.nav_general_localization"),
						'uri' => $base_uri . "settings/company/general/localization/"
					),
					array(
						'name' => $this->_("Navigation.getcompany.nav_general_international"),
						'uri' => $base_uri . "settings/company/general/international/"
					),
					array(
						'name' => $this->_("Navigation.getcompany.nav_general_encryption"),
						'uri' => $base_uri ."settings/company/general/encryption/"
					),
					array(
						'name' => $this->_("Navigation.getcompany.nav_general_contacttypes"),
						'uri' => $base_uri . "settings/company/general/contacttypes/"
					)
				)
			),
			array(
				'name' => $this->_("Navigation.getcompany.nav_lookandfeel"),
				'class' => "lookandfeel",
				'uri' => $base_uri . "settings/company/themes/",
				'children' => array(
					array(
						'name' => $this->_("Navigation.getcompany.nav_lookandfeel_themes"),
						'uri' => $base_uri . "settings/company/themes/"
					),
					array(
						'name' => $this->_("Navigation.getcompany.nav_lookandfeel_template"),
						'uri' => $base_uri . "settings/company/lookandfeel/template/"
					),
					/*
					array(
						'name' => $this->_("Navigation.getcompany.nav_lookandfeel_customize"),
						'uri' => $base_uri . "settings/company/lookandfeel/customize/"
					)
					*/
				)
			),
			array(
				'name' => $this->_("Navigation.getcompany.nav_automation"),
				'class' => "automation",
				'uri' => $base_uri . "settings/company/automation/"
			),
			array(
				'name' => $this->_("Navigation.getcompany.nav_billing"),
				'class' => "billing",
				'uri' => $base_uri . "settings/company/billing/",
				'children' => array(
					array(
						'name' => $this->_("Navigation.getcompany.nav_billing_invoices"),
						'uri' => $base_uri . "settings/company/billing/invoices/"
					),
					array(
						'name' => $this->_("Navigation.getcompany.nav_billing_custominvoice"),
						'uri' => $base_uri . "settings/company/billing/customization/"
					),
					array(
						'name' => $this->_("Navigation.getcompany.nav_billing_deliverymethods"),
						'uri' => $base_uri . "settings/company/billing/deliverymethods/"
					),
					array(
						'name' => $this->_("Navigation.getcompany.nav_billing_acceptedtypes"),
						'uri' => $base_uri . "settings/company/billing/acceptedtypes/"
					),
					array(
						'name' => $this->_("Navigation.getcompany.nav_billing_notices"),
						'uri' => $base_uri . "settings/company/billing/notices/"
					),
					array(
						'name' => $this->_("Navigation.getcompany.nav_billing_coupons"),
						'uri' => $base_uri . "settings/company/billing/coupons/"
					)
				)
			),
			array(
				'name' => $this->_("Navigation.getcompany.nav_modules"),
				'class' => "modules",
				'uri' => $base_uri . "settings/company/modules/",
				'children' => array(
					array(
						'name' => $this->_("Navigation.getcompany.nav_modules_instmod"),
						'uri' => $base_uri . "settings/company/modules/installed/"
					),
					array(
						'name' => $this->_("Navigation.getcompany.nav_modules_availmod"),
						'uri' => $base_uri . "settings/company/modules/available/"
					)
				)
			),
			array(
				'name' => $this->_("Navigation.getcompany.nav_gateways"),
				'class' => "gateways",
				'uri' => $base_uri . "settings/company/gateways/",
				'children' => array(
					array(
						'name' => $this->_("Navigation.getcompany.nav_gateways_instgate"),
						'uri' => $base_uri . "settings/company/gateways/installed/"
					),
					array(
						'name' => $this->_("Navigation.getcompany.nav_gateways_availgate"),
						'uri' => $base_uri . "settings/company/gateways/available/"
					)
				)
			),
			array(
				'name' => $this->_("Navigation.getcompany.nav_taxes"),
				'class' => "taxes",
				'uri' => $base_uri . "settings/company/taxes/",
				'children' => array(
					array(
						'name' => $this->_("Navigation.getcompany.nav_taxes_basictax"),
						'uri' => $base_uri . "settings/company/taxes/basic/"
					),
					array(
						'name' => $this->_("Navigation.getcompany.nav_taxes_taxrules"),
						'uri' => $base_uri . "settings/company/taxes/rules/"
					)
				)
			),
			array(
				'name' => $this->_("Navigation.getcompany.nav_emails"),
				'class' => "email",
				'uri' => $base_uri . "settings/company/emails/",
				'current' => false,
				'children' => array(
					array(
						'name' => $this->_("Navigation.getcompany.nav_emails_templates"),
						'uri' => $base_uri . "settings/company/emails/templates/"
					),
					array(
						'name' => $this->_("Navigation.getcompany.nav_emails_mail"),
						'uri' => $base_uri . "settings/company/emails/mail/"
					),
					array(
						'name' => $this->_("Navigation.getcompany.nav_emails_signatures"),
						'uri' => $base_uri . "settings/company/emails/signatures/"
					)
				)
			),
			array(
				'name' => $this->_("Navigation.getcompany.nav_customfields"),
				'class' => "custom",
				'uri' => $base_uri . "settings/company/customfields/"
			),
			array(
				'name' => $this->_("Navigation.getcompany.nav_currencies"),
				'class' => "currencies",
				'uri' => $base_uri . "settings/company/currencies/",
				'children' => array(
					array(
						'name' => $this->_("Navigation.getcompany.nav_currency_currencysetup"),
						'uri' => $base_uri . "settings/company/currencies/setup/"
					),
					array(
						'name' => $this->_("Navigation.getcompany.nav_currency_active"),
						'uri' => $base_uri . "settings/company/currencies/active/"
					)
				)
			),
			array(
				'name' => $this->_("Navigation.getcompany.nav_plugins"),
				'class' => "plugins",
				'uri' => $base_uri . "settings/company/plugins/",
				'children' => array(
					array(
						'name' => $this->_("Navigation.getcompany.nav_plugins_instplug"),
						'uri' => $base_uri . "settings/company/plugins/installed/"
					),
					array(
						'name' => $this->_("Navigation.getcompany.nav_plugins_availplug"),
						'uri' => $base_uri . "settings/company/plugins/available/"
					)
				)
			),
			array(
				'name' => $this->_("Navigation.getcompany.nav_groups"),
				'class' => "groups",
				'uri' => $base_uri . "settings/company/groups/"
			)
		);
		
		return $nav;
	}
	
	/**
	 * Retrieves the navigation for system settings
	 *
	 * @param string $base_uri The base_uri for the currently logged in user
	 * @return array A numerically-indexed array of the system settings navigation where each element contains an array which includes:
	 * 	- name The name of the element
	 * 	- class The CSS class name for the element
	 * 	- uri The URI for the element
	 * 	- children An array of child elements which follow the same indexes as above
	 */
	public function getSystem($base_uri) {
		$nav = array(
			array(
				'name' => $this->_("Navigation.getsystem.nav_general"),
				'class' => "general",
				'uri' => $base_uri . "settings/system/general/",
				'children' => array(
					array(
						'name' => $this->_("Navigation.getsystem.nav_general_basic"),
						'uri' => $base_uri . "settings/system/general/basic/"
					),
					array(
						'name' => $this->_("Navigation.getsystem.nav_general_geoip"),
						'uri' => $base_uri . "settings/system/general/geoip/"
					),
					array(
						'name' => $this->_("Navigation.getsystem.nav_general_maintenance"),
						'uri' => $base_uri . "settings/system/general/maintenance/"
					),
					array(
						'name' => $this->_("Navigation.getsystem.nav_general_license"),
						'uri' => $base_uri . "settings/system/general/license/"
					),
					array(
						'name' => $this->_("Navigation.getsystem.nav_general_paymenttypes"),
						'uri' => $base_uri . "settings/system/general/paymenttypes/"
					)
				)
			),
			array(
				'name' => $this->_("Navigation.getsystem.nav_automation"),
				'class' => "automation",
				'uri' => $base_uri . "settings/system/automation/"
			),
			array(
				'name' => $this->_("Navigation.getsystem.nav_companies"),
				'class' => "companies",
				'uri' => $base_uri . "settings/system/companies"
			),
			array(
				'name' => $this->_("Navigation.getsystem.nav_backup"),
				'class' => "backup",
				'uri' => $base_uri . "settings/system/backup/",
				'children' => array(
					array(
						'name' => $this->_("Navigation.getsystem.nav_backup_index"),
						'uri' => $base_uri . "settings/system/backup/index/"
					),
					array(
						'name' => $this->_("Navigation.getsystem.nav_backup_ftp"),
						'uri' => $base_uri . "settings/system/backup/ftp/"
					),
					array(
						'name' => $this->_("Navigation.getsystem.nav_backup_amazon"),
						'uri' => $base_uri . "settings/system/backup/amazon/"
					)
				)
			),
			array(
				'name' => $this->_("Navigation.getsystem.nav_staff"),
				'class' => "staff",
				'uri' => $base_uri . "settings/system/staff/",
				'children' => array(
					array(
						'name' => $this->_("Navigation.getsystem.nav_staff_manage"),
						'uri' => $base_uri . "settings/system/staff/manage/"
					),
					array(
						'name' => $this->_("Navigation.getsystem.nav_staff_groups"),
						'uri' => $base_uri . "settings/system/staff/groups/"
					)
				)
			),
			array(
				'name' => $this->_("Navigation.getsystem.nav_api"),
				'class' => "api",
				'uri' => $base_uri . "settings/system/api/"
			),
			array(
				'name' => $this->_("Navigation.getsystem.nav_upgrade"),
				'class' => "upgrade",
				'uri' => $base_uri . "settings/system/upgrade/"
			),
			array(
				'name' => $this->_("Navigation.getsystem.nav_help"),
				'class' => "help",
				'uri' => $base_uri . "settings/system/help/",
				'children' => array(
					array(
						'name' => $this->_("Navigation.getsystem.nav_help_index"),
						'uri' => $base_uri . "settings/system/help/index/"
					),
					array(
						'name' => $this->_("Navigation.getsystem.nav_help_notes"),
						'uri' => "http://www.blesta.com/changelog/",
						'attributes' => array(
							'target' => "blank"
						)
					),
					array(
						'name' => $this->_("Navigation.getsystem.nav_help_about"),
						'uri' => $base_uri . "settings/system/help/credits/",
						'attributes'=>array('rel'=>"modal")
					)
				)
			),
			array(
				'name' => $this->_("Navigation.getsystem.nav_marketplace"),
				'class' => "marketplace",
				'uri' => Configure::get("Blesta.marketplace_url"),
				'attributes' => array('target' => "_blank")
			)
		);
		return $nav;
	}
	
	/**
	 * Fetches all search options available to the current company
	 *
	 * @param string $base_uri The base_uri for the currently logged in user
	 * @return array An array of search items in key/value pairs, where each key is the search type and each value is the language for the search type
	 */
	public function getSearchOptions($base_uri = null) {

		$options = array(
			'smart' => $this->_("Navigation.getsearchoptions.smart"),
			'clients' => $this->_("Navigation.getsearchoptions.clients"),
			'invoices' => $this->_("Navigation.getsearchoptions.invoices"),
			'transactions' => $this->_("Navigation.getsearchoptions.transactions"),
			'services' => $this->_("Navigation.getsearchoptions.services"),
			'packages' => $this->_("Navigation.getsearchoptions.packages")
		);

		// Allow custom search options to be appended to the list of search options
		$this->Events->register("Navigation.getSearchOptions", array("EventsNavigationCallback", "getSearchOptions"));
		$event = $this->Events->trigger(new EventObject("Navigation.getSearchOptions", compact("options", "base_uri")));
		
		$params = $event->getParams();
		
		if (isset($params['options']))
			$options = $params['options'];
		
		return $options;
	}
	
	/**
	 * Returns all plugin navigation for the requested location
	 *
	 * @param string $location The location to fetch plugin navigation for
	 * @return array An array of plugin navigation
	 */
	public function getPluginNav($location) {
		if (!isset($this->PluginManager))
			Loader::loadModels($this, array("PluginManager"));
			
		return $this->PluginManager->getActions(Configure::get("Blesta.company_id"), $location, true);
	}
}
?>