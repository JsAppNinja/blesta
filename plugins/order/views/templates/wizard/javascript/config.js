	$(document).ready(function() {
		
		// Bind change events
		$('#package-config').on('change', 'input[name^="configoptions["], select[name^="configoptions["], ' +
			'input[name="qty"], select[name="qty"], ' +
			'input[name^="addon["]', function() {
			fetchSummary();
		});
		
		// Fetch package options
		fetchPackageOptions();
		$('[name="pricing_id"]').change(function() {
			fetchPackageOptions();
		});
	});