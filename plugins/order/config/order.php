<?php
// Emails
Configure::set("Order.install.emails", array(
	array(
		'action' => "Order.received",
		'type' => "staff",
		'plugin_dir' => "order",
		'tags' => "{order},{services},{invoice}",
		'from' => "sales@mydomain.com",
		'from_name' => "Blesta Order System",
		'subject' => "An order has been received",
		'text' => "A new order has been received by the system.

Summary

Order Form: {order.order_form_name}
Order Number: {order.order_number}
Status: {order.status}
Amount: {invoice.total} {order.currency}{% if order.fraud_status !=\"\" %}
Fraud Status: {order.fraud_status}{% endif %}

Client Details

{order.client_id_code}
{order.client_first_name} {order.client_last_name}
{order.client_company}
{order.client_address1}
{order.client_email}

Items Ordered

{% for item in services %}{item.package.name}
{item.name}{% for option in item.options %}
{option.option_label} x{option.qty}: {option.option_value}{% endfor %}
--
{% endfor %}",
		'html' => "<p>
	A new order has been received by the system.</p>
<p>
	<strong>Summary</strong></p>
<p>
	Order Form: {order.order_form_name}<br />
	Order Number: {order.order_number}<br />
	Status: {order.status}<br />
	Amount: {invoice.total} {order.currency}{% if order.fraud_status !=\"\" %}<br />
	Fraud Status: {order.fraud_status}{% endif %}</p>
<p>
	<strong>Client Details</strong></p>
<p>
	{order.client_id_code}<br />
	{order.client_first_name} {order.client_last_name}<br />
	{order.client_company}<br />
	{order.client_address1}<br />
	{order.client_email}</p>
<p>
	<strong>Items Ordered</strong></p>
<p>
	{% for item in services %}{item.package.name}<br />
	{item.name}{% for option in item.options %}<br />
	{option.option_label} x{option.qty}: {option.option_value}{% endfor %}</p>
<p>
	--<br />
	{% endfor %}</p>
"
	),
	array(
		'action' => "Order.received_mobile",
		'type' => "staff",
		'plugin_dir' => "order",
		'tags' => "{order},{services},{invoice}",
		'from' => "sales@mydomain.com",
		'from_name' => "Blesta Order System",
		'subject' => "An order has been received",
		'text' => "Order Form: {order.order_form_name}
Order Number: {order.order_number}
Status: {order.status}
Amount: {invoice.total} {order.currency}{% if order.fraud_status !=\"\" %}
Fraud Status: {order.fraud_status}{% endif %}

Client Details

{order.client_id_code}
{order.client_first_name} {order.client_last_name}
{order.client_company}
{order.client_address1}
{order.client_email}

Items Ordered

{% for item in services %}{item.package.name}
{item.name}{% for option in item.options %}
{option.option_label} x{option.qty}: {option.option_value}{% endfor %}
--
{% endfor %}
",
		'html' => "<p>
	<strong>Summary</strong></p>
<p>
	Order Form: {order.order_form_name}<br />
	Order Number: {order.order_number}<br />
	Status: {order.status}<br />
	Amount: {invoice.total} {order.currency}{% if order.fraud_status !=\"\" %}<br />
	Fraud Status: {order.fraud_status}{% endif %}</p>
<p>
	<strong>Client Details</strong></p>
<p>
	{order.client_id_code}<br />
	{order.client_first_name} {order.client_last_name}<br />
	{order.client_company}<br />
	{order.client_address1}<br />
	{order.client_email}</p>
<p>
	<strong>Items Ordered</strong></p>
<p>
	{% for item in services %}{item.package.name}<br />
	{item.name}{% for option in item.options %}<br />
	{option.option_label} x{option.qty}: {option.option_value}{% endfor %}<br />
	--<br />
	{% endfor %}</p>
"
	)
));
?>