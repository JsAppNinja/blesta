<?php
Configure::set("blesta.map", array(
	'module' => "legacy_license",
	'module_row_key' => null,
	'module_row_meta' => array(
		(object)array('key' => "label", 'value' => "blesta_legacy", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "name", 'value' => "Blesta Legacy", 'serialized' => 0, 'encrypted' => 0),
	),
	'package_meta' => array(
	),
	'service_fields' => array(
		'user1' => (object)array('key' => "host", 'serialized' => 0, 'encrypted' => 0),
		'user2' => (object)array('key' => "key", 'serialized' => 0, 'encrypted' => 0),
		'pass' => null,
		'opt1' => (object)array('key' => "last_callhome", 'serialized' => 0, 'encrypted' => 0),
		'opt2' => (object)array('key' => "version", 'serialized' => 0, 'encrypted' => 0)
	),
	'package_tags' => array(
		'[domain]' => "{service.host}",
		'[key]' => "{service.key}",
		'[version]' => "{service.version}"
	)
));
?>