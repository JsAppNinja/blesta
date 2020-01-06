<?php
Configure::set("broadsat.map", array(
	'module' => "universal_module",
	'module_row_key' => "login",
	'module_row_meta' => array(
		(object)array('key' => "package_field_label_0", 'value' => "Login", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "package_field_name_0", 'value' => "login", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "package_field_required_0", 'value' => "true", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "package_field_type_0", 'value' => "secret", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "package_field_encrypt_0", 'value' => "false", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "package_field_values_0", 'value' => (object)array('module' => "login"), 'serialized' => 0, 'encrypted' => 0),

		(object)array('key' => "package_field_label_1", 'value' => "Password", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "package_field_name_1", 'value' => "password", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "package_field_required_1", 'value' => "true", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "package_field_type_1", 'value' => "secret", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "package_field_encrypt_1", 'value' => "false", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "package_field_values_1", 'value' => (object)array('module' => "password"), 'serialized' => 0, 'encrypted' => 0),
		
		(object)array('key' => "service_field_label_0", 'value' => "Hardware MAC", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "service_field_name_0", 'value' => "user1", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "service_field_required_0", 'value' => "true", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "service_field_type_0", 'value' => "text", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "service_field_encrypt_0", 'value' => "false", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "service_field_values_0", 'value' => "", 'serialized' => 0, 'encrypted' => 0),
		
		(object)array('key' => "service_field_label_1", 'value' => "User Login", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "service_field_name_1", 'value' => "user2", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "service_field_required_1", 'value' => "true", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "service_field_type_1", 'value' => "text", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "service_field_encrypt_1", 'value' => "false", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "service_field_values_1", 'value' => "", 'serialized' => 0, 'encrypted' => 0),
		
		(object)array('key' => "service_field_label_2", 'value' => "Consumed Traffic", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "service_field_name_2", 'value' => "opt1", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "service_field_required_2", 'value' => "true", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "service_field_type_2", 'value' => "text", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "service_field_encrypt_2", 'value' => "false", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "service_field_values_2", 'value' => "", 'serialized' => 0, 'encrypted' => 0)
	),
	'package_meta' => array(),
	'service_fields' => array(
		'user1' => (object)array('key' => "user1", 'serialized' => 0, 'encrypted' => 0),
		'user2' => (object)array('key' => "user2", 'serialized' => 0, 'encrypted' => 0),
		'pass' => null,
		'opt1' => (object)array('key' => "opt1", 'serialized' => 0, 'encrypted' => 0),
		'opt2' => null
	)
));
?>