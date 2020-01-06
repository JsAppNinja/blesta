		$(document).ready(function() {
			$("#applycoupon").submit(function(event) {
				$(this).blestaRequest('POST', $(this).attr('action'), $(this).serialize(),
					function(data) {
						if (data.error) {
							$("#coupon_box .input-group").removeClass("has-success").addClass("has-error");
							$("#coupon_box .input-group .input-group-addon").remove();
							$("#coupon_box .input-group").prepend('<span class="input-group-addon"><i class="fa fa-ban fa-fw"></i></span>');
						}
						else {
							fetchSummary();
						}
					},
					null,
					{dataType: 'json'}
				);
				
				event.preventDefault();
			});
			
		});