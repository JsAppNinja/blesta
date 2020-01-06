/**
 * Blesta Javascript Library v0.1.0
 * jQuery extension
 * 
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
(function($) {
	
	$.fn.extend({
		
		/**
		 * Scroll a DOM element from within its container element (i.e. scroll credits)
		 * 
		 * @param content {String} The ID of the content element to scroll
		 * @param container {String} The ID of the container element that holds content
		 * @param start_delay {Int} The number of milliseconds to wait before beginning the scroll
		 * @param end_delay {Int} The number of milliseconds to pause at the end of the scroll before repeating
		 * @param ms_per_100px {Int} The number of milliseconds it takes to scroll 100 px (default 3000)
		 */
		blestaScrollDom: function(content, container, start_delay, end_delay, ms_per_100px) {
			ms_per_100px = (ms_per_100px ? ms_per_100px : 3000);
			
			var content_height = parseInt($("#" + content).height());
			var container_height = parseInt($("#" + container).height());

			if (content_height <= 0)
				content_height = 700;
			
			var total_time = parseInt(ms_per_100px*container_height/100);
			
			clearTimeout(this.timeout_id);
			this.timeout_id = null;
			
			// Reset content
			$("#" + content).css({top: '0px'});
			// Set delay to begin animation
			$("#" + content).delay(start_delay);
			
			// Animate scroll up
			$("#" + content).animate({top: '-=' + (content_height-container_height)}, total_time, "linear", function() {
				// Replay animation after a set period of time
				clearTimeout(this.timeout_id);
				
				// Don't replay if no longer visible
				if (!$(this).is(":visible"))
					return;
				
				this.timeout_id = setTimeout("$(document).blestaScrollDom('" + content + "','" + container + "'," + start_delay + "," + end_delay + "," + ms_per_100px + ")", end_delay);
			});
		},
		
		/**
		 * Performs an Ajax request
		 * 
		 * @param method {String} The method of the request "GET", "POST", etc.
		 * @param uri {String} The URI to request
		 * @param params {Object, String} Data to be sent to the server, automatically converted to a query string if not already a string
		 * @param on_success {Function} The callback function to execute on success
		 * @param on_error {Function} The callback function to execute on error
		 * @param o {Object} An object of option parameters
		 */
		blestaRequest: function(method, uri, params, on_success, on_error, o) {
			params = params ? params : null;
			on_success = on_success ? on_success : null;
			on_error = on_error ? on_error : null;
			
			var defaults = {
				type: method,
				url: uri,
				data: params,
				success: on_success,
				error: on_error
			};
			o = $.extend(defaults, o);
			
			$.ajax(o);
		},
		/**
		 * Performs an Ajax request to and fills the given container in the
		 * next table row (tr) of the currently selected object with the data
		 * returned by the request
		 *
		 * @param uri {String} The URI to request
		 * @param container {Object, String} The jquery object or selector to fill
		 */
		blestaUpdateRow: function(uri, container) {
			var element = $(this).next("tr");
			var id = element.attr("id").split("_")[1];
	
			// Only make the request if the element was just opened
			if (element.is(":visible"))
				return;
			
			$(this).blestaRequest("GET", uri, null, function(data) {
				// A specific element has been referenced, use it
				if (container instanceof jQuery)
					container.html(data);
				// We have a element referenced without context so use it within context of the selected object
				else
					$(container, element).html(data);
			});
		},
		
		/**
		 * Submits the given form ID or the closests form if no form ID given
		 * when the given element is clicked
		 *
		 * @param o {Object} A set of options including:
		 * 	-form_id - The ID of the form to submit when the given element is clicked, defaults to closest form
		 */
		blestaSubmitOnClick: function(o) {
			var defaults = {};
			o = $.extend(defaults, o);
			
			return this.each(function() {
				var form_id = o.form_id ? o.form_id : false;
				$(this).click(function() {
					if (form_id)
						$("#" + form_id).submit();
					else
						$(this).closest("form").submit();
					
					return false;
				});
			});
		},
		blestaContrastColor: function(hex) {
			hex = hex.replace('#', '');
			if (hex.strlen == 3)
				hex = hex.charAt(0) + hex.charAt(0) + hex.charAt(1) + hex.charAt(1) + hex.charAt(2) + hex.charAt(2);
			
			return (parseInt(hex, 16) > 0xffffff/2 ? '000' : 'fff');
		},
		blestaColorPicker: function(o) {
			var element = $(this);
			var defaults = {
				onBeforeShow: function() {
					$(this).ColorPickerSetColor(element.val());
				},
				onChange: function(hasb, hex, rgb) {
					element.val(hex);
					element.css({
						backgroundColor: '#' + hex,
						color: '#' + this.blestaContrastColor(element.val())
					});
				}
			};
			o = $.extend(defaults, o);
			
			
			element.css({
				backgroundColor: '#' + element.val(),
				color: '#' + this.blestaContrastColor(element.val())
			});
			
			element.ColorPicker(defaults);
		},
		
		/**
		 * Vertically align the given element within the given container
		 * 
		 * @param o {Object} A set of options including:
		 * 	-container - The jquery object representing the container to align within (defaults to parent)
		 * 	-offset - The offset factor (defaults to 2)
		 */
		blestaVerticalAlign: function(o) {
			var defaults = {
				container: $(this).parent(),
				offset: 2
			};
			o = $.extend(defaults, o);
			
			return this.each(function() {
				var container_height = $(o.container).height();
				var element_height = $(this).height();
				
				$(this).css("top", Math.max(0, container_height/o.offset - element_height/2));
			});
		},
		/**
		 * Makes an ajax request to the given URI to fetch the invoice line totals for the current form and
		 * sets those totals within the document.
		 *
		 * @param uri {String} The URI to POST to asynchronously
		 */
		blestaSetInvTotals: function(uri) {
			$(this).blestaRequest('POST', uri, $(this).closest('form').serialize(), function(data) {
					// Update subtotal
					if (data.subtotal && data.subtotal.amount_formatted)
						$('.totals_subtotal em').text(data.subtotal.amount_formatted);
						
					// Update tax values
					var tax_level=0;
					$('.totals_tax').each(function() {
						if (data.tax[tax_level] && data.tax[tax_level].amount_formatted) {
							$(this).show();
							$('span', this).text(data.tax[tax_level].name + " (" + data.tax[tax_level].percentage + "%)");
							$('em', this).text(data.tax[tax_level].amount_formatted);
						}
						else {
							$(this).hide();
						}
						tax_level++;
					});
					
					// Update total
					if (data.total_w_tax && data.total_w_tax.amount_formatted)
						$('.totals_total em').text(data.total_w_tax.amount_formatted);
					
					// Update amount due
					if (data.total_due && data.total_due.amount_formatted)
						$('.totals_due em').text(data.total_due.amount_formatted);
				},
				null,
				{dataType:'json'}
			);
		},
		/**
		 * Allows the selected element(s) to be sorted using the given handle
		 *
		 * @param handle {Object, String} The jquery selector for the handle
		 * @param o {Object} A set of options including to override the default option for jquery ui's Sortable(), defaults:
		 * 	-tolerance: pointer
		 * 	-containment: parent
		 * 	-axis: y
		 * 	-helper: function() Fixes issues with sorting table rows by maintaining the column widths
		 * 	-cursor: move
		 * @param disable_select {Bool} True to disable selection of the sortables, default false
		 */
		blestaSortable: function(handle, o, disable_select) {
			handle = handle ? handle : false;
			disable_select = disable_select ? disable_select : false;
			
			var defaults = {
				tolerance: 'pointer',
				containment: 'parent',
				axis: 'y',
				handle: handle,
				// To preserve table rows during sort
				helper: function(e, ui) {
					ui.children().each(function() {
						if ($(this).is("table,tr,td"))
							$(this).width($(this).width());
					});
					return ui;
				}
			};
			o = $.extend(defaults, o);
			
			return this.each(function() {
				if (disable_select)
					$(this).sortable(o).disableSelection();
				else
					$(this).sortable(o);
			});
		},
		/**
		 * Returns the position of the caret within the selected element(s)
		 */
		blestaGetCaret: function() {
			return this.each(function() {
				if (el.selectionStart)
					return el.selectionStart; 
				else if (document.selection) { 
					el.focus(); 
			  
					var r = document.selection.createRange(); 
					if (r == null)
						return 0; 
				
					var re = el.createTextRange(), 
						rc = re.duplicate(); 
					re.moveToBookmark(r.getBookmark()); 
					rc.setEndPoint('EndToStart', re); 
				
					return rc.text.length; 
				}  
				return 0; 
			});
		},
		/**
		 * Loads the given widget at the specified URL
		 * 
		 * @param o {Object} A set of options including:
		 * 	-container - The jquery object representing the container to load widgets within
		 * 	-url - The URL to request to fetch the list of widgets to load
		 */
		blestaLoadWidgets: function(o) {
			
			var defaults = {
				container: null,
				fetch_uri: null,
				update_uri: null,
				toggle_uri: null
			};
			o = $.extend(defaults, o);

			// Fetch a list of widgets to appear
			var widgets = null;
			if (o.fetch_uri) {
				$(this).each(function() {
					var params = {section: null};
					if ($(this).hasClass("section1"))
						params.section = "section1";
					if ($(this).hasClass("section2"))
						params.section = "section2";
					if ($(this).hasClass("section3"))
						params.section = "section3";

					var container = o.container;
					if (container == null)
						container = $(this);
					
					$(this).blestaRequest("get", o.fetch_uri, params, function(widgets) {

						for (var widget in widgets) {
							//console.log(widget);
							$('div.widget-container', container).remove();
							$(container).append('<div id="widget_container_' + widget + '"></div>');

							$(this).blestaRequest("get", widgets[widget].uri, null, function(data) {
								if (typeof innerShiv == "function")
									var temp = $(innerShiv(data.content, false, false));
								else
									var temp = $(data.content);
								
								// Close the widget if its state was closed
								if (!widgets[this.widget_id].open) {
									$("div.common_box_header", temp).last().addClass("close");
									// Hide the widget if it is closed
									$(temp).blestaHideWidgets();
								}
								
								// Append the widget to the page
								$('#widget_container_' + this.widget_id).replaceWith(temp);
								
								// Get the badge value for this widget
								$("#" + this.widget_id).blestaGetWidgetBadge();
								
							}, null, {widget_id: widget});
						}
						
						// Bind GUI Events to wigets that have loaded
						$(container).parent().blestaBindGuiEvents({section: params.section, sort_uri: o.update_uri, toggle_uri: o.toggle_uri});
					}, null, {dataType: "json"});
				});
			}
		},
		blestaGetWidgetBadge: function() {
			var widget = $(this);
			// Remove any badge displayed for this widget
			$('.badge_dot', widget).remove();
			
			// Widget is closed, so fetch badge value
			if ($(".common_box_inner", this).is(':hidden')) {
				// Try to find the badge request URI for this widget
				var badge_uri = $('input[name="badge_uri"]', this).val();
				
				if (badge_uri) {
					// Request the badge value for this widget
					$(widget).blestaRequest("get", badge_uri, null, function(data) {
						// Draw the badge value
						if (data)
							$("h2", widget).append('<strong class="badge_dot">' + data + '</strong>');
					});
				}
			}
		},
		blestaHideWidgets: function() {
			// Hide all closed boxes
			$(".common_box_header.close+.common_box_inner", this).hide();
		},
		/**
		 * Prepares modals to load a confirmation dialogs. Works for performing
		 * confirmation prior to executing a link.
		 *
		 * @param o {Object} A set of options including:
		 * 	-base_url The base URL for the modal popup. 'dialog/confirm/?message=' will be appended, with the
		 * 	message text coming from the anchor's 'rel' attribute
		 * 	-title - The title of the modal, defaults to $(this).text()
		 * 	-close - The text to display for the close link, defaults to 'Close'
		 * 	-submit - If true, will submit the form closest to the click element when confirmed, set to false to follow the click href instead
		 */
		blestaModalConfirm: function(o) {
			$(this).each(function() {
				var elem = $(this);
				$(this).blestaModal({
					title: o.title ? o.title : null,
					close: o.close ? o.close : 'Close',
					url: false,
					onRender: function(event, api) {
						$.ajax({
							url: o.base_url + 'dialog/confirm/',
							data: {message: elem.attr('rel')},
							success: function(data) {
								api.set('content.text', data)
								
								// If 'yes' is clicked, forward to where we wanted to go
								$('.btn_right.yes', api.elements.content).click(function() {
									if (o.submit) {
										api.hide();
										if (o.form)
											o.form.submit();
										else
											elem.closest("form").submit();
										return true;
									}
									window.location = elem.attr('href');
									return false;
								});
								// If 'no' is clicked, close the modal
								$('.btn_right.no', api.elements.content).click(function() {
									api.hide();
									return false;
								});
							}
						});
					}
				});
			});
		},
		/**
		 * Prepares modals to load. Content of the modal box will be loaded via AJAX
		 * from the URL specified by the "href" of the selector by default, or through
		 * the option parameter if set
		 *
		 * @param o {Object} A set of options including:
		 * 	-title - The title of the modal, defaults to $(this).text()
		 * 	-close - The text to display for the close link, defaults to 'Close'
		 * 	-onShow - The callback to execute when the modal is loaded
		 * 	-onContentUpdate - The callback to execute when the modal is updated
		 * 	-url - The URL to request via AJAX and display in the modal box, false if processing the AJAX request via a callback (to prevent this method from making the request)
		 * 	-data - A object representing the data to submit along with the request
		 * 	-ajax - A object representing the ajax request to make (overrides url and data)
		 * 	-text - The text/HTML to display in the modal, initially (will be replaced if URL set)
		 * 	-open - True to open the modal now, false to only open when selector is clicked
		 */
		blestaModal: function(o) {
			var defaults = {
				title: null,
				close: 'Close',
				onShow: function(event, api) {},
				onHide: function(event, api) {},
				onRender: function(event, api) {},
				url: null,
				data: {},
				text: '',
				ajax: {},
				min_width: 400,
				max_width: 400,
				open: false
			};
			o = $.extend(defaults, o);
			
			// Handle modal boxes
			$(this).each(function() {
				$(this).click(function(){return false;});

				if (o.url == null)
					o.url = o.url ? o.url : $(this).attr('href');

				if (o.url != false && $.isEmptyObject(o.ajax)) {
					o.ajax = {
						url: o.url,
						data: o.data
					}
				}

				$(this).qtip({
					content: {
						text: o.text ? o.text : " ",
						title: {
							text: o.title ? o.title : $(this).text(),
							button: o.close
						},
						ajax: o.ajax
					},
					events: {
						show: o.onShow,
						hide: o.onHide,
						render: function(event, api) {
							// Set min/max widths
							$(api.elements.tooltip).css({ 'min-width': o.min_width, 'max-width': o.max_width });
							
							$(this).draggable({
								containment: 'window',
								handle: api.elements.titlebar
							});
							
							o.onRender(event, api);
						}
					},
					position: {
						my: 'center', // ...at the center of the viewport
						at: 'center',
						target: $(window)
					},
					show: {
						event: 'click', // Show it on click...
						solo: true, // ...and hide all other tooltips...
						modal: true, // ...and make it modal
						ready: o.open
					},
					hide: false,
					style: 'ui-tooltip-light ui-tooltip-rounded ui-tooltip-dialogue'
				});
			});
		},
		blestaBindGlobalGuiEvents: function(o) {
			var defaults = {};
			o = $.extend(defaults, o);
			
			// Hide boxes as neccessary
			$(this).blestaHideWidgets();
			
			// Close error, success, alert messages
			this.blestaBindCloseMessage();
			
			// Submit forms using button link (keep persistent)
			$("a.submit").live('click', function() {
				$(this).closest("form").submit();
				return false;
			});
			
			// Show/hide expandable table data
			$("tr.expand", this).live("click", function() {
				$(this).next(".expand_details").toggle();
			});
			$("tr.expand a,tr.expand input", this).live("click", function(e) {
				e.stopPropagation();
			});
			
			// Handle tooltips
			$(this).blestaBindToolTips();
			
			// Date picker
			try {
				$.dpText.TEXT_CHOOSE_DATE = '';
				Date.format = 'yyyy-mm-dd';
				$('input.date').datePicker({startDate:'1996-01-01'});
			}
			catch (err) {
				// date picker not loaded
			}
			
			// Tab slider
			try {
				$(".tab_slider", this).jCarouselLite({btnNext: ".next", btnPrev: ".prev", btnContainer: ".tabs_nav", circular: false, visible: 10 });
			}
			catch (err) {
				// missing required javascript files
			}
			
			var History = window.History;
			
			// Bind to StateChange Event
			if (History.enabled) {
				History.Adapter.bind(window,'statechange',function(){ // Note: We are using statechange instead of popstate
					var State = History.getState(); // Note: We are using History.getState() instead of event.state

					$(this).blestaRequest("GET", State.url,  null,
						// Success response
						function(data) {
							// Replace the content in the replacer section of the box
							// with that provided from the response. If replacer is null,
							// replace previous state box with current data
							if (data.replacer == null) {
								$(State.data.box).html(data.content);
								$(State.data.box).blestaBindToolTips();
							}
							else {
								$(data.replacer, State.data.box).html(data.content);
								$(data.replacer, State.data.box).blestaBindToolTips();
							}
							if (data.message != null) {
								$('#right_container').prepend(data.message);
							}
							
							// Date picker
							try {
								$.dpText.TEXT_CHOOSE_DATE = '';
								Date.format = 'yyyy-mm-dd';
								$('input.date').datePicker({startDate:'1996-01-01'});
							}
							catch (err) {
								// date picker not loaded
							}
			
							// Tab slider
							try {
								$(".tab_slider", State.data.box).jCarouselLite({btnNext: ".next", btnPrev: ".prev", btnContainer: ".tabs_nav", circular: false, visible: 10 });
							}
							catch (err) {
								// missing required javascript files
							}
						},
						// Error response
						null,
						// Options
						{dataType:'json'}				
					);
				});
			}
			
			// Handle AJAX link requests in widgets
			$("a.ajax").live('click', function() {
				// Find parent box
				var parent_box = $(this).closest("section.common_box");
				var url = $(this).attr("href");
				
				// If not in a widget, continue as normal, must be in a widget
				if (!parent_box)
					return true;
				
				return $(this).blestaPushState("#" + parent_box.attr("id"), url);
			});
			
			
			$(document).keyup(function(e) {
				// If escape key pressed remove full screen
				if (e.keyCode == 27) {
					$("section").removeClass("full_screen");
				}
			});
		},
		blestaPushState: function(box, url) {
			if (History.enabled) {
				try{
					History.pushState({box: box}, $("title").text(), url);
				}
				catch (err) {
					return true; // couldn't handle pushState, so execute without it
				}
				return false;
			}
			return true;
		},
		blestaBindGuiEvents: function(o) {
			var defaults = {};
			o = $.extend(defaults, o);

			
			// Tab slider
			try {
				$(".tab_slider", this).jCarouselLite({btnNext: ".next", btnPrev: ".prev", btnContainer: ".tabs_nav", circular: false, visible: 10 });
			}
			catch (err) {
				// missing required javascript files
			}
			

			// Allow sorting boxes
			if (o.sort_uri) {
				$(".column", this).blestaSortable(".common_box_header", {
					connectWith: ".column",
					axis: false,
					containment: false,
					cursor: "move",
					opacity: 0.8,
					start: function(event, ui) {
						$(".column .ui-sortable-placeholder").height($(".common_box.ui-sortable-helper", this).height());
					},
					update: function(event, ui) {
						var section = null;
						if ($(this).hasClass("section1"))
							section = "section1";
						if ($(this).hasClass("section2"))
							section = "section2";
						if ($(this).hasClass("section3"))
							section = "section3";
							
						// Ajax request to update the sort order of the widgets on screen
						$(this).blestaRequest("post", o.sort_uri, $(this).sortable('serialize', {key: "widget[" + section + "][]", expression:/(.*)/}));
					}
				});
			}
			
			$(this).on("hover", ".column .common_box_header", function() {
				$(this).css("cursor", "move");
			});
			
			// Minimize/expand boxes
			$((o.section ? $("." + o.section, this) : this)).on("click", "a.arrow", function() {
				var header = $(this).closest("div.common_box_header");
				header.next(".common_box_inner").slideToggle('slow', function() {
					header.toggleClass("close");
					
					// Save open/close state to update handler, which will return
					// the URI to request badge update information if available
					if (o.toggle_uri) {
						var open = !$(this).is(":hidden");

						// Send the minimize/expand request to record the state
						header.blestaRequest("post", o.toggle_uri, header.parent().attr("id") + "=" + open);
						// Get the badge value for this widget
						header.parent().blestaGetWidgetBadge();
					}
				});
				
				return false;
			});
			// Toggle full screen
			$((o.section ? $("." + o.section, this) : this)).on("click", "a.full_screen", function() {
				$(this).closest("section").toggleClass("full_screen");
			});
		},
		
		/**
		 * Binds the close event action to success, error, and alert messages.
		 * Persists across future (i.e. ajax) created message boxes
		 */
		blestaBindCloseMessage: function() {
			// Close error, success, alert messages
			$(".error_section a.close", this).live('click', function() {
				$(this).parent().animate({
					opacity: 0,
					height: 'hide'
				}, 400, function() {
					// Hide the entire container if there are no elements visible within it
					if ($(".error_section").has("article:visible").length == 0)
						$(".error_section").hide();
				});
	
				return false;
			});
		},
		/**
		 * Binds tooltips to the given elements
		 */
		blestaBindToolTips: function() {
			// Handle tooltips
			$('span.tooltip', this).each(function() {
				$(this).qtip({
					position: {
						adjust: {method: "flip flip"},
						my: "bottom left",
						at: "right top",
						viewport: $(window)
					},
					content: {
						text: $('div', this).html()
					},
					show: {event: 'mouseover'},
					hide: {event: 'mouseout'},
					style: {
						classes: "ui-tooltip-cream"
						/*
						color: '#4b4b4b',
						background: '#fffed9',
						'font-size': 12,
						border: {
							width: 1,
							radius: 4,
							color: '#ebec80'
						},
						width: 250,
						name: 'cream',
						tip: 'bottomLeft'
						*/
					}
				});
			});
				/*
			$('span.tooltip', this).each(function() {
				$(this).qtip({
					position: {
						corner: {
							target: 'rightTop',
							tooltip: 'bottomLeft'
						}
					},
					content: $('div', this).html(),
					show: 'mouseover',
					hide: 'mouseout',
					style: {
						color: '#4b4b4b',
						background: '#fffed9',
						'font-size': 12,
						border: {
							width: 1,
							radius: 4,
							color: '#ebec80'
						},
						width: 250,
						name: 'cream',
						tip: 'bottomLeft'
					}
				});
			});
				*/
		},
		/**
		 * Sets quicklinks for staff members
		 */
		blestaBindQuickLinks: function() {
			$('#quicklink').bind('click', function() {
				var action = "remove";
				if ($(this).hasClass('star-off'))
					action = "add";
				
				var params = {
					action: action,
					title: document.title.split("|")[0],
					uri: window.location.pathname + window.location.search
				};
				
				$(this).blestaRequest("post", $(this).attr('href'), params,
					function(data) {
						if (data.added)
							$('#quicklink').removeClass('star-off').addClass('star-on');
						else
							$('#quicklink').removeClass('star-on').addClass('star-off');
					},
					null,
					{dataType: "json"}
				);
				
				return false;
			});
		},
		/**
		 * Generates a random string
		 */
		blestaRandomString: function(length, pool) {
			var str = '';
			pool = pool || 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
			
			for (var i=0; i<length; i++) {
				str += pool.charAt(Math.floor(Math.random()*pool.length));
			}
			return str;
		},
		/**
		 * Creates or updates the given tag with the given attributes in the <head> of the document.
		 * If a tag in the head contains the ID as given in the attributes object, then that tag will be updated.
		 *
		 * @param tag {String} The name of the tag to create/update
		 * @param attributes {Object} A set of tag attributes
		 */
		blestaSetHeadTag: function(tag, attributes) {
			
			// Overwrite the attributes of a given stylesheet link
			if (attributes.id && $(attributes.id).length)
				$(attributes.id, $('head')).attr(attributes);
			// Append a new style sheet to the head
			else
				$('<' + tag + ' />', attributes).appendTo('head');
		},
		/**
		 * Adds click functionality and content toggling for markup that matches the following structure:
		 *
		 * <some container>
		 *		<ul>
		 *			<li class="current"><a href="">Tab 1</a></li>
		 *			<li><a href="">Tab 2</a></li>
		 *		</ul>
		 *		<div class="inner_content">
		 *			<div>Tab1 content</div>
		 *			<div>Tab2 content</div>
		 *		</div>
		 * </some container>
		 */
		blestaTabbedContent: function() {
			this.each(function() {
				var container = $(this);
				
				// Find the active tab
				var i = $('li', container).index($('ul li.current', container));

				// Hide all tabs except the active tab
				$(container).children('.inner_content').children('div').hide();
				$(container).children('.inner_content').children('div').eq(i).show();
				
				// Handle tab click
				$(container).children('ul').children('li').children('a').click(function() {
					
					// Find the tab clicked
					var i = $('li', $(this).closest('ul')).index($(this).closest('li'));
					
					$(this).closest('ul').children('li').removeClass('current');
					$(this).closest('ul').children('li').eq(i).addClass('current');
					
					// Hide all tabs except the active tab
					$(container).children('.inner_content').children('div').hide();
					$(container).children('.inner_content').children('div').eq(i).show();
					
					return false;
				});
			});
		},
		/**
		 * Toggles a More/Less content section.
		 * Note the clickable tag must contain either "show_content" or "hide_content" classes to swap between
		 *
		 * @param clickable_tag {String} A DOM element that will listen for a "click" event
		 * @param container {String} A DOM element container that will toggle for more/less content
		 * @param open_text {String} Text to display for the clickable tag when the container is open (optional)
		 * @param closed_text {String} Text to display for the clickable tag when the container is closed (optional)
		 */
		blestaBindToggleEvent: function(clickable_tag, container, open_text, closed_text) {
			$(clickable_tag).live('click', function() {
				$(container).toggle();
				
				if ($(this).hasClass("show_content")) {
					$(this).attr("class", "hide_content");
					if (closed_text != 'undefined')
						$(this).text(closed_text);
				}
				else {
					$(this).attr("class", "show_content");
					if (open_text != 'undefined')
						$(this).text(open_text);
				}
				
				return false;
			});
		},
        /**
         * Sorts an object containing key/value pairs by its value
         *
         * @param o {Object} An object to sort containing key/value pairs of int or string type
         * @return An array of sorted objects, each containing a 'key' and 'value' property representing the key/value pairs, respectively
         */
        blestaSortObject: function(o) {
            var sorted = [];

            for (var item in o) {
                if (o.hasOwnProperty(item))
                    sorted.push({'key':item.toString(),'value':o[item]});
            }
            
            return sorted.sort(function(key,value) {return key.value.toLowerCase().localeCompare(value.value.toLowerCase())});
        }
	});
	
	/**
	 * Prepare standard GUI elements
	 */
	$(document).ready(function() {
		// Handle AJAX request failure due to unauthorization
		$(this).ajaxError(function(event, request, settings) {
			// Attempt reload due to 401 unauthorized response, let the system
			// handle the approrpriate redirect.
			if (request.status == 401) {
				window.location = window.location.href;
				/*
				#
				# TODO: display lightbox with login credentials so current page state is not lost
				#
				*/
			}
			// If an ajax request was attempted, but the resource does not support it, reload
			if (request.status == 406) {
				window.location = window.location.href;
			}
		});
			
		$(this).blestaBindGlobalGuiEvents();
		//$(this).blestaBindGuiEvents();
		$(this).blestaBindQuickLinks();
		
		// Create show/hide content links
		$("a.show_all").click(function() {
			$("ul.other_actions").slideDown('fast', function() {
				$("a.show_common").toggle();
				$("a.show_all").toggle();				
			});
			return false;
		});
		$("a.show_common").click(function() {
			$("ul.other_actions").slideUp('fast', function() {
				$("a.show_common").toggle();
				$("a.show_all").toggle();				
			});
			return false;
		});
	});
})(jQuery);