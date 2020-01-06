		$(document).ready(function() {
			resetPaymentMethod($(".payment_type:checked,.gateway:checked"));
			$(".payment_type,.gateway").change(function() {
				resetPaymentMethod($(this));
			});
			
			function resetPaymentMethod(elem) {
				if (elem.hasClass("gateway")) {
					$(".payment_type").prop("checked", false);
				}
				else {
					$(".gateway").prop("checked", false);					
				}
			}
			
			$("#applycoupon").submit(function(event) {
				$(this).blestaRequest('POST', $(this).attr('action'), $(this).serialize(),
					function(data) {
						if (data.error) {
							$("#coupon_box .input-group").removeClass("has-success").addClass("has-error");
							$("#coupon_box .input-group .input-group-addon").remove();
							$("#coupon_box .input-group").prepend('<span class="input-group-addon"><i class="fa fa-ban fa-fw"></i></span>');
						}
						else {
							var success_message = (data.success ? data.success : "");
							
							$(this).blestaRequest('GET', base_uri + 'order/summary/index/' +  order_label, null,
								function(data) {
									$("#summary_section").replaceWith(data);
									
									if (success_message.length > 0) {
										$("#coupon_box .input-group").removeClass("has-error").addClass("has-success");
										$("#coupon_box .input-group .input-group-addon").remove();
										$("#coupon_box .input-group").prepend('<span class="input-group-addon"><i class="fa fa-check fa-fw"></i></span>');
										fetchSummary();
									}
								}
							);
						}
					},
					null,
					{dataType: 'json'}
				);
				
				event.preventDefault();
			});
			
		});