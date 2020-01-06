<?php
Configure::set("solusvm_xen.map", array(
	'module' => "solusvm",
	'module_row_key' => "serverip",
	'module_row_meta' => array(
		(object)array('key' => "server_name", 'value' => (object)array('module' => "serverip"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "host", 'value' => (object)array('module' => "serverip"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "port", 'value' => "5656", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "user_id", 'value' => (object)array('module' => "username"), 'serialized' => 0, 'encrypted' => 1),
		(object)array('key' => "key", 'value' => (object)array('module' => "key"), 'serialized' => 0, 'encrypted' => 1)
	),
	'package_meta' => array(
		(object)array('key' => "type", 'value' => "standard", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "nodes", 'value' => (object)array('package' => "instantact"), 'serialized' => 1, 'encrypted' => 0, 'callback' => "solusvm_xen_nodes"),
		(object)array('key' => "plan", 'value' => (object)array('package' => "instantact"), 'serialized' => 0, 'encrypted' => 0, 'callback' => "solusvm_xen_plan"),
		(object)array('key' => "set_template", 'value' => "client", 'serialized' => 0, 'encrypted' => 0)
	),
	'service_fields' => array(
		'user1' => (object)array('key' => "solusvm_hostname", 'serialized' => 0, 'encrypted' => 0),
		// Hybrid (user "/" pass)
		'user2' => (object)array('key' => "solusvm_username", 'serialized' => 0, 'encrypted' => 0),
		'pass' => (object)array('key' => "solusvm_root_password", 'serialized' => 0, 'encrypted' => 1),
		'opt1' => (object)array('key' => "solusvm_vserver_id", 'serialized' => 0, 'encrypted' => 0),
		'opt2' => (object)array('key' => "solusvm_main_ip_address", 'serialized' => 0, 'encrypted' => 0)
	),
	'package_tags' => array(
		'[serverip]' => "{service.solusvm_main_ip_address}",
		'[user1]' => "{service.solusvm_hostname}",
		'[user2]' => "{service.solusvm_username}",
		'[pass]' => "{service.solusvm_root_password}",
		'[opt2]' => "{service.solusvm_main_ip_address}",
		'[term]' => "{pricing.term}"
	)
));

function solusvm_xen_nodes($str) {
	$parts = explode("|", $str);
	return array($parts[0]);
}

function solusvm_xen_plan($str) {
	$parts = explode("|", $str);
	return $parts[1];	
}
?>