<?php
Configure::set("generic_registrar.map", array(
	'module' => "universal_module",
	'module_row_meta' => array(
		(object)array('key' => "name", 'value' => (object)array('module' => "type"), 'serialized' => 0, 'encrypted' => 0),
		
		(object)array('key' => "package_field_label_0", 'value' => "Username", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "package_field_name_0", 'value' => "username", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "package_field_required_0", 'value' => "true", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "package_field_type_0", 'value' => "secret", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "package_field_encrypt_0", 'value' => "false", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "package_field_values_0", 'value' => (object)array('module' => "username"), 'serialized' => 0, 'encrypted' => 0),
		
		(object)array('key' => "package_field_label_1", 'value' => "Password", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "package_field_name_1", 'value' => "password", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "package_field_required_1", 'value' => "true", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "package_field_type_1", 'value' => "secret", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "package_field_encrypt_1", 'value' => "true", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "package_field_values_1", 'value' => (object)array('module' => "password"), 'serialized' => 0, 'encrypted' => 0),
		
		(object)array('key' => "service_field_label_0", 'value' => "Domain", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "service_field_name_0", 'value' => "domain", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "service_field_type_0", 'value' => "text", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "service_field_required_0", 'value' => "true", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "service_field_encrypt_0", 'value' => "false", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "service_field_values_0", 'value' => "", 'serialized' => 0, 'encrypted' => 0)
	),
	'package_meta' => array(),
	'service_fields' => array(
		'domain' => (object)array('key' => "domain", 'serialized' => 0, 'encrypted' => 0)
	)
));
?>