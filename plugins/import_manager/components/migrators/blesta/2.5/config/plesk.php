<?php
Configure::set("plesk.map", array(
	'module' => "plesk",
	'module_row_key' => "hostip",
	'module_row_meta' => array(
		(object)array('key' => "server_name", 'value' => (object)array('module' => "hostip"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "ip_address", 'value' => (object)array('module' => "hostip"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "port", 'value' => "8443", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "username", 'value' => (object)array('module' => "user"), 'serialized' => 0, 'encrypted' => 1),
		(object)array('key' => "password", 'value' => (object)array('module' => "password"), 'serialized' => 0, 'encrypted' => 1),
		(object)array('key' => "account_count", 'value' => (object)array('module' => "cur"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "account_limit", 'value' => (object)array('module' => "max"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "panel_version", 'value' => "11.5", 'serialized' => 0, 'encrypted' => 0)
	),
	'package_meta' => array(
		(object)array('key' => "plan", 'value' => (object)array('package' => "instantact"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "type", 'value' => "standard", 'serialized' => 0, 'encrypted' => 0)
	),
	'service_fields' => array(
		'user1' => (object)array('key' => "plesk_domain", 'serialized' => 0, 'encrypted' => 0),
		'user2' => (object)array('key' => "plesk_username", 'serialized' => 0, 'encrypted' => 0),
		'pass' => (object)array('key' => "plesk_password", 'serialized' => 0, 'encrypted' => 1),
		'opt1' => null,
		'opt2' => null
	),
	'package_tags' => array(
		'[domain]' => "{service.plesk_domain}",
		'[username]' => "{service.plesk_username}",
		'[pass]' => "{service.plesk_password}",
		'[server]' => "{module.host_name}",
		'[term]' => "{pricing.term}"
	)
));
?>