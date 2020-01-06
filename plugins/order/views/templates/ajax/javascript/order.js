
	function initPackages(packages) {
		// Handle list
		$(".package-list .order button").click(function() {
			$(".package-list").removeClass("selected");
			var pack_elem = $(this).closest(".package-list");
			pack_elem.addClass("selected");
			
			// Load config section
			fetchConfig(pack_elem.attr('data-group-id'), pack_elem.attr('data-pricing-id'), function() {
				// Animate to config section
				$('html,body').animate({scrollTop: $('#package-config').offset().top}, 'slow');
			});
		});
		if ($(".package-list.selected").length > 0) {
			var pack_elem = $(".package-list.selected");
			var pricing_id = pack_elem.attr('data-selected-pricing-id') != "" ? pack_elem.attr('data-selected-pricing-id') : pack_elem.attr('data-pricing-id');
			// Load config section
			fetchConfig(pack_elem.attr('data-group-id'), pricing_id, function() {
				// Animate to config section
				$('html,body').animate({scrollTop: $('#package-config').offset().top}, 'slow');
			});
		}
		
		// Handle boxes
		$(".package-boxes .order button").click(function() {
			$(".package-boxes .package").removeClass("selected");
			var pack_elem = $(this).closest(".package");
			pack_elem.addClass("selected");
			
			// Load config section
			fetchConfig(pack_elem.attr('data-group-id'), pack_elem.attr('data-pricing-id'), function() {
				// Animate to config section
				$('html,body').animate({scrollTop: $('#package-config').offset().top}, 'slow');
			});
		});
		if ($(".package-boxes .package.selected").length > 0) {
			var pack_elem = $(".package-boxes .package.selected");
			var pricing_id = pack_elem.attr('data-selected-pricing-id') != "" ? pack_elem.attr('data-selected-pricing-id') : pack_elem.attr('data-pricing-id');
			// Load config section
			fetchConfig(pack_elem.attr('data-group-id'), pricing_id, function() {
				// Animate to config section
				$('html,body').animate({scrollTop: $('#package-config').offset().top}, 'slow');
			});
		}
		
		// Handle slider
		var slide_val = 0;
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

				// Load config section
				fetchConfig(packages[cur_val].group_id, packages[cur_val].pricing_id, function() {
					// Animate to config section
					if (slide_val != 0)
						$('html,body').animate({scrollTop: $('#package-config').offset().top}, 'slow');
				});
			}
		});
		
		// Bind change events
		$('#package-config').on('change', 'input[name^="configoptions["], select[name^="configoptions["], ' +
			'input[name="qty"], select[name="qty"], ' +
			'input[name^="addon["]', function() {
			fetchSummary();
		});
		
		$('#package-config').on('submit', '#package_config', function(event) {
			submitConfig($(this));
			event.preventDefault();
		});
		
		$('#package-config').on('change', 'input[name^="addon["]', function() {
			setSubmitButtonGroup($(this));
		});
	}
	
	function submitConfig(elem) {
		$.ajax({
			method: 'POST',
			url: $(elem).attr('action'),
			data: $(elem).serialize(),
			success: function(data) {
				// Display error
				if (data.error) {
					$("#config_message").html(data.error);
					// Animate to config section
					$('html,body').animate({scrollTop: $('#package-config').offset().top}, 'slow');
				}
				// Redirect to checkout page
				else if (data.empty_queue) {
					
					$('#checkout_form input[name="agree_tos"]').val($("#signup_agree_tos:checked").val());
					$('#checkout_form').submit();
				}
				// Fetch next item
				else if (data.next_uri) {
					// Animate to config section
					$('html,body').animate({scrollTop: $('#package-config').offset().top}, 'slow');
		
					$.ajax({
						method: 'GET',
						url: data.next_uri,
						success: function(data) {
							$("#package-config").html(data);
						},
						beforeSend: function() {
							$("#package-config").append($(this).blestaLoadingDialog());
						},
						complete: function() {
							$(".loading_container", $("#package-config")).remove();
						},
						dataType: 'json'
					})
				}
				else {
					$("#package-config").html(data);
				}
			},
			beforeSend: function() {
				$("#package-config").append($(this).blestaLoadingDialog());
			},
			complete: function() {
				$(".loading_container", $("#package-config")).remove();
			},
			dataType: 'json'
		});
	}
	
	function fetchConfig(group_id, pricing_id, callback) {
		if (group_id == '' || pricing_id == '') {
			var pack_elem = $(".package-boxes .package.selected");
			group_id = pack_elem.attr('data-group-id');
			pricing_id = pack_elem.attr('data-pricing-id');
		}
		var default_pricing_id = $("#default_pricing_id").val();

		$.ajax({
			method: 'GET',
			data: {group_id: group_id, pricing_id: pricing_id, default_pricing_id: default_pricing_id},
			url: base_uri + 'order/config/index/' + order_label,
			success: function(data) {
				$("#package-config").html(data);
				
				setSubmitButtonGroup();
				
				if (typeof callback == 'function')
					callback();
			},
			beforeSend: function() {
				$("#package-config").append($(this).blestaLoadingDialog());
			},
			complete: function() {
				$(".loading_container", $("#package-config")).remove();
			},
			dataType: 'json'
		});
	}
	
	function fetchSummary() {
		// Package config element must be available, or the system is likely not configured correctly
		if (!$("#package_config").length) {
			return;
		}
		
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

	function fetchSignup() {
		$.ajax({
			type: 'GET',
			url: base_uri + 'order/signup/index/' + order_label,
			success: function(data) {
				$("#create-account").html(data);
				setSubmitButtonGroup();
			},
			beforeSend: function() {
				$("#create-account").append($(this).blestaLoadingDialog());
			},
			complete: function() {
				$(".loading_container", $("#create-account")).remove();
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
	
	function logout(elem) {
		$.ajax({
			method: $(elem).attr('method'),
			data: $(elem).serialize(),
			url: $(elem).attr('action'),
			success: function(data) {
				$.ajax({
					url: $("#signup_url").val(),
					success: function(data) {
						// Fetch sumamry
						fetchSummary();
						$("#create-account").html(data);
						setSubmitButtonGroup();
					},
					beforeSend: function() {
						$("#create-account").append($(elem).blestaLoadingDialog());
					},
					complete: function() {
						$(".loading_container", $("#create-account")).remove();
					},
					dataType: 'json'
				});
			}
		});
	}
	
	function login(elem) {
		$.ajax({
			method: $(elem).attr('method'),
			data: $(elem).serialize(),
			url: $(elem).attr('action'),
			success: function(data) {
				if (data.error)
					$("#message_section").html(data.error);
				else {
					$.ajax({
						url: $("#order_signup").attr('action'),
						success: function(data) {
							// Fetch sumamry
							fetchSummary();
							$("#create-account").html(data);
							setSubmitButtonGroup();
						},
						dataType: 'json'
					});
				}
			},
			beforeSend: function() {
				$("#create-account").append($(elem).blestaLoadingDialog());
			},
			complete: function() {
				$(".loading_container", $("#create-account")).remove();
			},
			dataType: 'json'
		});
	}
	
	function signup(elem) {
		$.ajax({
			method: $(elem).attr('method'),
			data: $(elem).serialize(),
			url: $(elem).attr('action'),
			success: function(data) {
				// Fetch sumamry
				fetchSummary();
				$("#create-account").html(data);
				setSubmitButtonGroup();
			},
			beforeSend: function() {
				$("#create-account").append($(elem).blestaLoadingDialog());
			},
			complete: function() {
				$(".loading_container", $("#create-account")).remove();
			},
			dataType: 'json'
		});
	}
	
	function getStates(elem) {
		$(this).blestaRequest("get", base_uri + 'order/signup/getstates/' + order_label + "/" + $(elem).val(), null, function(data) {
				// Remove all existing items
				$("option", "#state").remove();
				
				// Append all new items
                $.each($(this).blestaSortObject(data), function(index, item) {
                    $('#state').append( new Option(item.value, item.key));
                });
			},
			null,
			{dataType: "json"}
		);
	}
	
	function setSubmitButtonGroup(elem) {
		elem = elem ? elem : $('#package_config input[name^="addon["]:checked');
		if (elem.val() != "") {
			$("#queue_empty_btns").hide();
			$("#queue_nonempty_btns").show();
		}
		else {
			$("#queue_empty_btns").show();
			$("#queue_nonempty_btns").hide();
		}
	}