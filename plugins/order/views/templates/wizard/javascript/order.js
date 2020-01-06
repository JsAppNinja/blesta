
	function initPackages(packages) {
		// Handle boxes
		$(".package-boxes .order button").click(function() {
			$(".package-boxes .package").removeClass("selected");
			var pack_elem = $(this).closest(".package");
			
			$("#pricing_id").val(pack_elem.attr('data-pricing-id'));
			pack_elem.addClass("selected");
		});
		
		// Handle slider
		var slide_val = 1;
		$('#package_slider').slider({
			tooltip: 'hide'
		}).on('slide', function(e) {
			var cur_val = $(this).slider('getValue').val();
			if (slide_val != cur_val && !isNaN(cur_val)) {
				$(".package-block").removeClass("active");
				$("#package_" + cur_val).addClass("active");
			}
		}).on('slideStop', function(e) {
			var cur_val = $(this).slider('getValue').val();
			if (slide_val != cur_val && !isNaN(cur_val)) {
				slide_val = cur_val;

				packages[cur_val].group_id, packages[cur_val].pricing_id
				$("#pricing_id").val(packages[cur_val].pricing_id);
			}
		});
	}
	
	function fetchSummary() {
		var data = $("#package_config").serialize() + "&" + $("#checkout_form").serialize();
		var url = base_uri + 'order/summary/index/' + order_label;
		
		if ($("#package_config").attr('action').indexOf("?") >= 0)
			url += $("#package_config").attr('action').substr($("#package_config").attr('action').indexOf("?"));
		
		$.ajax({
			method: 'POST',
			url: url,
			data: data,
			success: function(data) {
				$("#order-summary").html(data);
			},
			beforeSend: function() {
				$("#order-summary").append($(this).blestaLoadingDialog());
			},
			complete: function() {
				$(".loading_container", $("#order-summary")).remove();
			},
			dataType: 'json'
		});
	}
	
	function fetchPackageOptions() {
		var uri = base_uri + 'order/config/packageoptions/' + order_label + '/';
		var pricing_id = $('[name="pricing_id"]').val();
		if (pricing_id) {
			var params = $('[name^="configoptions"]', $('.package_options').closest('form')).serialize();
			$(this).blestaRequest('GET', uri + pricing_id, params, function(data) {
				$('.package_options').html(data);
				
				fetchSummary();
			},
			null,
			{dataType: 'json'});
		}
	}