/**
 * Pincode Checker for WooCommerce Frontend Script
 * Version: 1.3.5
 * Author:  Akhil Nichani (https://github.com/akinich)
 * Handles real-time validation, AJAX checks against WooCommerce Shipping Zones,
 * session & localStorage synchronization, header popovers, mobile delivery strip & bottom sheet,
 * prominent 1-click browser geolocation, auto-focus text selection, and one-click location reset.
 * Uses robust delegated event listeners.
 */

(function ($) {
	'use strict';

	var STORAGE_KEY = 'wc_verified_pincode';
	var params = window.wc_pincode_params || {};

	/**
	 * Retrieve verified pincode from localStorage or localized session parameters.
	 */
	function getActivePincode() {
		try {
			var localPin = localStorage.getItem(STORAGE_KEY);
			if (localPin && /^[0-9]{6}$/.test(localPin)) {
				return localPin;
			}
		} catch (e) {
			// LocalStorage unavailable.
		}
		if (params.current_pincode && /^[0-9]{6}$/.test(params.current_pincode)) {
			return params.current_pincode;
		}
		return '';
	}

	/**
	 * Save verified pincode to client-side localStorage.
	 */
	function setActivePincode(pincode) {
		try {
			if (pincode) {
				localStorage.setItem(STORAGE_KEY, pincode);
			} else {
				localStorage.removeItem(STORAGE_KEY);
			}
		} catch (e) {
			// LocalStorage unavailable.
		}
	}

	/**
	 * Clear verified pincode from client-side storage.
	 */
	function clearActivePincode() {
		try {
			localStorage.removeItem(STORAGE_KEY);
		} catch (e) {
			// LocalStorage unavailable.
		}
		params.current_pincode = '';
	}

	/**
	 * Update Header & Mobile Strip Pill Button text.
	 */
	function updateHeaderTrigger($widget, pincode) {
		var $trigger = $widget.find('.wc-header-pincode-trigger');
		if (!$trigger.length) {
			return;
		}

		var $triggerText = $trigger.find('.wc-trigger-text');
		if (pincode) {
			$trigger.addClass('has-verified-pincode');
			$triggerText.html(
				(params.i18n ? params.i18n.delivering_to : 'Delivering to') +
				' <strong class="wc-verified-code">' + $('<div>').text(pincode).html() + '</strong>' +
				' <span class="wc-trigger-change">(' + (params.i18n ? params.i18n.change : 'Change') + ')</span>'
			);
		} else {
			$trigger.removeClass('has-verified-pincode');
			var defaultLabel = $widget.hasClass('wc-mobile-delivery-strip-wrapper')
				? (params.i18n ? params.i18n.select_pincode : 'Select your delivery pincode')
				: (params.i18n ? params.i18n.check_deliverability : 'Check Deliverability');

			$triggerText.html('<span class="wc-default-trigger-label">' + defaultLabel + '</span>');
		}
	}

	/**
	 * Switch widget instance to Verified State.
	 */
	function setWidgetVerified($widget, pincode) {
		$widget.addClass('has-verified-pincode');
		$widget.find('.wc-verified-code').text(pincode);
		$widget.find('.wc-pincode-input').val(pincode);

		// If inside a header popover or mobile strip, keep input form available for changes.
		if ($widget.hasClass('wc-header-delivery-checker') || $widget.hasClass('wc-mobile-delivery-strip-wrapper') || $widget.closest('.wc-header-popover').length) {
			updateHeaderTrigger($widget, pincode);
			$widget.find('.wc-pincode-form').show().css('display', 'flex');
			$widget.find('.wc-badge-card').hide();
			if (pincode) {
				$widget.find('.wc-input-clear-btn').show().css('display', 'flex');
			}
			return;
		}

		// Standard in-page widget behavior (product pages, sidebars, footers)
		$widget.find('.wc-geo-btn-prominent, .wc-geo-divider').stop(true, true).hide();
		$widget.find('.wc-badge-card').stop(true, true).fadeIn(180).css('display', 'flex');
		$widget.find('.wc-pincode-form').stop(true, true).hide();
	}

	/**
	 * Switch widget instance to Input Form View / Reset state.
	 */
	function setWidgetInputMode($widget) {
		$widget.removeClass('has-verified-pincode');
		$widget.find('.wc-verified-code').text('');
		$widget.find('.wc-pincode-input').val('');
		$widget.find('.wc-input-clear-btn').hide();

		if ($widget.hasClass('wc-header-delivery-checker') || $widget.hasClass('wc-mobile-delivery-strip-wrapper') || $widget.closest('.wc-header-popover').length) {
			updateHeaderTrigger($widget, '');
			$widget.find('.wc-pincode-form').show().css('display', 'flex');
			$widget.find('.wc-badge-card').hide();
		} else {
			$widget.find('.wc-badge-card').stop(true, true).hide();
			$widget.find('.wc-geo-btn-prominent, .wc-geo-divider').stop(true, true).fadeIn(180).css('display', 'flex');
			$widget.find('.wc-pincode-form').stop(true, true).fadeIn(180).css('display', 'flex');
		}
		$widget.find('.wc-pincode-input').trigger('focus');
	}

	/**
	 * Show result message inside widget or popover.
	 */
	function showResultMessage($widget, message, isSuccess, subtext) {
		var $result = $widget.find('.wc-result-box');
		$result.removeClass('wc-success wc-error').empty();

		if (isSuccess) {
			$result.addClass('wc-success').html(message);
			if (subtext) {
				$result.append('<span class="wc-result-subtext">' + subtext + '</span>');
			}
		} else {
			$result.addClass('wc-error').html(message);
		}

		$result.stop(true, true).slideDown(200);
	}

	/**
	 * Synchronize all widgets across the page with a newly verified pincode.
	 */
	function syncAllWidgets(pincode) {
		$('.wc-pincode-checker-widget').each(function () {
			var $inst = $(this);
			$inst.find('.wc-verified-code').text(pincode);
			$inst.find('.wc-pincode-input').val(pincode);
			setWidgetVerified($inst, pincode);
		});
	}

	/**
	 * Fallback Reverse Geocoder: OpenStreetMap Nominatim.
	 */
	function fetchNominatimFallback(lat, lon, successCallback, errorCallback) {
		var nominatimUrl = 'https://nominatim.openstreetmap.org/reverse?format=json&lat=' +
			encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lon);

		$.ajax({
			url: nominatimUrl,
			type: 'GET',
			dataType: 'json',
			timeout: 5000
		}).done(function (data) {
			var postcode = '';
			if (data && data.address && data.address.postcode) {
				postcode = String(data.address.postcode).replace(/\D/g, '').slice(0, 6);
			}

			if (postcode && postcode.length === 6) {
				successCallback(postcode);
			} else {
				errorCallback(
					(params.i18n && params.i18n.geo_postcode_failed)
						? params.i18n.geo_postcode_failed
						: 'Could not detect 6-digit postal code. Please enter manually.'
				);
			}
		}).fail(function () {
			errorCallback(
				(params.i18n && params.i18n.geo_unavailable)
					? params.i18n.geo_unavailable
					: 'Unable to detect your location. Please enter your pincode manually.'
			);
		});
	}

	/**
	 * Primary Reverse Geocoder: BigDataCloud Client API with Nominatim Fallback.
	 */
	function reverseGeocode(lat, lon, successCallback, errorCallback) {
		var bdcUrl = 'https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=' +
			encodeURIComponent(lat) + '&longitude=' + encodeURIComponent(lon) + '&localityLanguage=en';

		$.ajax({
			url: bdcUrl,
			type: 'GET',
			dataType: 'json',
			timeout: 5000
		}).done(function (data) {
			var postcode = '';
			if (data && data.postcode) {
				postcode = String(data.postcode).replace(/\D/g, '').slice(0, 6);
			}

			if ((!postcode || postcode.length !== 6) && data && data.localityInfo) {
				var infoList = (data.localityInfo.informative || []).concat(data.localityInfo.administrative || []);
				for (var i = 0; i < infoList.length; i++) {
					var item = infoList[i];
					if (item && item.name && /^[0-9]{6}$/.test(String(item.name).trim())) {
						postcode = String(item.name).trim();
						break;
					}
					if (item && item.description && item.description.indexOf('postal code') !== -1 && item.name) {
						var candidate = String(item.name).replace(/\D/g, '').slice(0, 6);
						if (candidate.length === 6) {
							postcode = candidate;
							break;
						}
					}
				}
			}

			if (postcode && postcode.length === 6) {
				successCallback(postcode);
			} else {
				fetchNominatimFallback(lat, lon, successCallback, errorCallback);
			}
		}).fail(function () {
			fetchNominatimFallback(lat, lon, successCallback, errorCallback);
		});
	}

	/**
	 * Hydrate all widget instances on page load.
	 */
	function hydrateWidgets() {
		var $fallbackStrip = $('.wc-mobile-delivery-strip-wrapper.wc-fallback-strip');
		if ($fallbackStrip.length) {
			var $header = $('header#masthead, header.site-header, header').first();
			if ($header.length) {
				$fallbackStrip.insertAfter($header);
			} else {
				$(document.body).prepend($fallbackStrip);
			}
			$fallbackStrip.removeClass('wc-fallback-strip');
		}

		var activePin = getActivePincode();

		$('.wc-header-delivery-checker').closest('.elementor-widget, .elementor-icon-box-wrapper, .elementor-widget-freshio2-icon-box').addClass('wc-has-delivery-checker');

		$('.wc-pincode-checker-widget').each(function () {
			var $widget = $(this);
			var $input = $widget.find('.wc-pincode-input');
			var $clearBtn = $widget.find('.wc-input-clear-btn');

			if (activePin) {
				setWidgetVerified($widget, activePin);
			} else if (!params.current_pincode) {
				setWidgetInputMode($widget);
			}

			if ($input.val().length > 0) {
				$clearBtn.show().css('display', 'flex');
			} else {
				$clearBtn.hide();
			}
		});
	}

	/* ==========================================================================
	   Delegated Event Architecture
	   ========================================================================== */

	// 1. Delegated Input Format (Digits Only, max 6) & Clear Button Toggle.
	$(document).on('input keyup paste', '.wc-pincode-checker-widget .wc-pincode-input', function () {
		var $input = $(this);
		var cleanVal = $input.val().replace(/\D/g, '').slice(0, 6);
		if ($input.val() !== cleanVal) {
			$input.val(cleanVal);
		}

		var $clearBtn = $input.siblings('.wc-input-clear-btn');
		if (cleanVal.length > 0) {
			$clearBtn.show().css('display', 'flex');
		} else {
			$clearBtn.hide();
		}
	});

	// 2. Delegated Clear Input Button Click.
	$(document).on('click', '.wc-pincode-checker-widget .wc-input-clear-btn', function (e) {
		e.preventDefault();
		e.stopPropagation();
		var $btn = $(this);
		var $widget = $btn.closest('.wc-pincode-checker-widget');
		var $input = $widget.find('.wc-pincode-input');
		$input.val('').trigger('focus');
		$btn.hide();
		$widget.find('.wc-result-box').stop(true, true).slideUp(150);
	});

	// 2b. Delegated 1-Click Geolocation Click.
	$(document).on('click', '.wc-pincode-checker-widget .wc-detect-location-btn', function (e) {
		e.preventDefault();
		e.stopPropagation();

		var $btn = $(this);
		var $widget = $btn.closest('.wc-pincode-checker-widget');
		var $form = $widget.find('.wc-pincode-form');
		var $input = $form.find('.wc-pincode-input');
		var $btnText = $btn.find('.wc-geo-btn-text');
		var origBtnText = $btnText.text();

		if ($btn.hasClass('is-loading')) {
			return;
		}

		function resetGeoButton() {
			$btn.removeClass('is-loading').prop('disabled', false);
			if ($btnText.length && origBtnText) {
				$btnText.text(origBtnText);
			}
		}

		if (window.isSecureContext === false && window.location.protocol !== 'https:' && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
			var insecureMsg = (params.i18n && params.i18n.geo_insecure)
				? params.i18n.geo_insecure
				: 'Geolocation requires a secure connection (HTTPS). Please enter your pincode manually.';
			showResultMessage($widget, insecureMsg, false);
			return;
		}

		if (!navigator.geolocation) {
			var notSupportedMsg = (params.i18n && params.i18n.geo_not_supported)
				? params.i18n.geo_not_supported
				: 'Geolocation is not supported by your browser.';
			showResultMessage($widget, notSupportedMsg, false);
			return;
		}

		$btn.addClass('is-loading').prop('disabled', true);
		var detectingMsg = (params.i18n && params.i18n.detecting_location)
			? params.i18n.detecting_location
			: 'Detecting your delivery location...';
		if ($btnText.length) {
			$btnText.text(detectingMsg);
		}
		showResultMessage($widget, detectingMsg, true);

		navigator.geolocation.getCurrentPosition(
			function (position) {
				var lat = position.coords.latitude;
				var lon = position.coords.longitude;

				reverseGeocode(
					lat,
					lon,
					function (postcode) {
						resetGeoButton();
						$input.val(postcode).trigger('input');
						$form.trigger('submit');
					},
					function (errorMsg) {
						resetGeoButton();
						showResultMessage($widget, errorMsg, false);
					}
				);
			},
			function (err) {
				resetGeoButton();
				var errMsg;
				if (err.code === 1) {
					errMsg = (params.i18n && params.i18n.geo_permission_denied)
						? params.i18n.geo_permission_denied
						: 'Location access was denied. Please enter your pincode manually.';
				} else {
					errMsg = (params.i18n && params.i18n.geo_unavailable)
						? params.i18n.geo_unavailable
						: 'Unable to detect your location. Please enter your pincode manually.';
				}
				showResultMessage($widget, errMsg, false);
			},
			{
				timeout: 10000,
				enableHighAccuracy: true,
				maximumAge: 60000
			}
		);
	});

	// 3. Delegated Change Button on Standard In-Page Badge.
	$(document).on('click', '.wc-pincode-checker-widget .wc-change-pincode-btn', function (e) {
		e.preventDefault();
		var $widget = $(this).closest('.wc-pincode-checker-widget');
		setWidgetInputMode($widget);
	});

	// 4. Delegated Form Submission via AJAX.
	$(document).on('submit', '.wc-pincode-checker-widget .wc-pincode-form', function (e) {
		e.preventDefault();

		var $form = $(this);
		var $widget = $form.closest('.wc-pincode-checker-widget');
		var $input = $form.find('.wc-pincode-input');
		var pincode = $input.val().replace(/\D/g, '').trim();
		var $submitBtn = $form.find('.wc-submit-btn');

		if (pincode.length !== 6) {
			showResultMessage(
				$widget,
				params.i18n ? params.i18n.invalid_pincode : 'Please enter a valid 6-digit numeric pincode.',
				false
			);
			$input.trigger('focus');
			return;
		}

		$submitBtn.addClass('is-loading').prop('disabled', true);
		showResultMessage(
			$widget,
			params.i18n ? params.i18n.checking : 'Checking delivery availability...',
			true
		);

		$.ajax({
			url: params.ajax_url || '/wp-admin/admin-ajax.php',
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'wc_check_pincode',
				nonce: params.nonce,
				pincode: pincode
			},
			success: function (res) {
				$submitBtn.removeClass('is-loading').prop('disabled', false);

				if (res && res.success && res.data) {
					var data = res.data;
					setActivePincode(data.pincode);
					params.current_pincode = data.pincode;

					showResultMessage($widget, data.message, true, data.subtext || '');
					syncAllWidgets(data.pincode);

					$(document.body).trigger('wc_pincode_verified', [pincode, data]);
					$(document.body).trigger('update_checkout');

					// Smooth auto-dismiss for popover on success
					if ($widget.hasClass('wc-header-delivery-checker') || $widget.hasClass('wc-mobile-delivery-strip-wrapper')) {
						setTimeout(function () {
							$widget.find('.wc-header-popover').fadeOut(220);
							$widget.find('.wc-mobile-backdrop').fadeOut(220);
							$widget.find('.wc-header-pincode-trigger').attr('aria-expanded', 'false');
							$('body').removeClass('wc-modal-open');
						}, 1400);
					}
				} else {
					var failMsg = (res && res.data && res.data.message)
						? res.data.message
						: (params.i18n ? params.i18n.server_error : 'Unable to verify pincode. Please try again.');
					showResultMessage($widget, failMsg, false);
				}
			},
			error: function () {
				$submitBtn.removeClass('is-loading').prop('disabled', false);
				showResultMessage(
					$widget,
					params.i18n ? params.i18n.server_error : 'Unable to verify pincode at this moment. Please try again.',
					false
				);
			}
		});
	});

	// 5. Header & Mobile Strip Pill Button Click (Toggle Popover / Bottom Sheet).
	$(document).on('click', '.wc-header-pincode-trigger', function (e) {
		e.preventDefault();
		e.stopPropagation();

		var $trigger = $(this);
		var $checker = $trigger.closest('.wc-header-delivery-checker, .wc-mobile-delivery-strip-wrapper');
		var $popover = $checker.find('.wc-header-popover');
		var $backdrop = $checker.find('.wc-mobile-backdrop');
		var isOpen = $popover.is(':visible');

		// Close any other open popovers
		$('.wc-header-popover').not($popover).fadeOut(150);
		$('.wc-mobile-backdrop').not($backdrop).fadeOut(150);
		$('.wc-header-pincode-trigger').not($trigger).attr('aria-expanded', 'false');
		$('body').removeClass('wc-modal-open');

		if (isOpen) {
			$popover.fadeOut(150);
			$backdrop.fadeOut(150);
			$trigger.attr('aria-expanded', 'false');
			$('body').removeClass('wc-modal-open');
		} else {
			$backdrop.fadeIn(180);
			$popover.fadeIn(180);
			$trigger.attr('aria-expanded', 'true');

			if ($checker.hasClass('wc-mobile-delivery-strip-wrapper')) {
				$('body').addClass('wc-modal-open');
			}

			// Auto-focus and auto-select text
			var $input = $popover.find('.wc-pincode-input');
			setTimeout(function () {
				$input.trigger('focus').select();
			}, 100);
		}
	});

	// 6. Close Button Click inside Popover.
	$(document).on('click', '.wc-popover-close-btn', function (e) {
		e.preventDefault();
		e.stopPropagation();

		var $closeBtn = $(this);
		var $popover = $closeBtn.closest('.wc-header-popover');
		var $checker = $closeBtn.closest('.wc-header-delivery-checker, .wc-mobile-delivery-strip-wrapper');

		$popover.fadeOut(150);
		$checker.find('.wc-mobile-backdrop').fadeOut(150);
		$checker.find('.wc-header-pincode-trigger').attr('aria-expanded', 'false');
		$('body').removeClass('wc-modal-open');
	});

	// 6b. Dimmed Mobile Backdrop Tap-to-Dismiss.
	$(document).on('click', '.wc-mobile-backdrop', function (e) {
		e.preventDefault();
		e.stopPropagation();

		var $backdrop = $(this);
		var $checker = $backdrop.closest('.wc-mobile-delivery-strip-wrapper');
		$backdrop.fadeOut(150);
		$checker.find('.wc-header-popover').fadeOut(150);
		$checker.find('.wc-header-pincode-trigger').attr('aria-expanded', 'false');
		$('body').removeClass('wc-modal-open');
	});

	// 7. One-Click Reset / Clear Saved Location Action.
	$(document).on('click', '.wc-reset-pincode-btn', function (e) {
		e.preventDefault();
		e.stopPropagation();

		var $btn = $(this);
		var $checker = $btn.closest('.wc-header-delivery-checker, .wc-mobile-delivery-strip-wrapper');
		var $popover = $btn.closest('.wc-header-popover');

		$btn.prop('disabled', true);
		clearActivePincode();

		$('.wc-pincode-checker-widget').each(function () {
			setWidgetInputMode($(this));
		});

		$.ajax({
			url: params.ajax_url || '/wp-admin/admin-ajax.php',
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'wc_clear_pincode',
				nonce: params.nonce
			},
			complete: function () {
				$btn.prop('disabled', false);
			},
			success: function (res) {
				showResultMessage(
					$checker,
					(res && res.data && res.data.message) ? res.data.message : 'Location reset.',
					true
				);
				$(document.body).trigger('wc_pincode_cleared');
				$(document.body).trigger('update_checkout');

				setTimeout(function () {
					$popover.fadeOut(200);
					$checker.find('.wc-mobile-backdrop').fadeOut(200);
					$checker.find('.wc-header-pincode-trigger').attr('aria-expanded', 'false');
					$('body').removeClass('wc-modal-open');
				}, 1200);
			}
		});
	});

	// 8. Outside Click Dismiss (for Desktop Popover).
	$(document).on('click', function (e) {
		if (!$(e.target).closest('.wc-header-delivery-checker, .wc-mobile-delivery-strip-wrapper').length) {
			$('.wc-header-popover').fadeOut(150);
			$('.wc-mobile-backdrop').fadeOut(150);
			$('.wc-header-pincode-trigger').attr('aria-expanded', 'false');
			$('body').removeClass('wc-modal-open');
		}
	});

	// 9. Keyboard Accessibility: Escape key dismiss & Enter/Space on role="button".
	$(document).on('keydown', function (e) {
		if (e.key === 'Escape' || e.keyCode === 27) {
			if ($('.wc-header-popover:visible').length) {
				$('.wc-header-popover').fadeOut(150);
				$('.wc-mobile-backdrop').fadeOut(150);
				$('.wc-header-pincode-trigger').attr('aria-expanded', 'false');
				$('body').removeClass('wc-modal-open');
			}
		}
	});

	$(document).on('keydown', '.wc-mobile-delivery-strip[role="button"]', function (e) {
		if (e.key === 'Enter' || e.key === ' ' || e.keyCode === 13 || e.keyCode === 32) {
			e.preventDefault();
			$(this).trigger('click');
		}
	});

	// Initialize on DOM Ready and on WooCommerce AJAX fragment updates.
	$(document).ready(function () {
		hydrateWidgets();
	});

	$(document).on('wc_fragments_refreshed wc_fragments_loaded updated_wc_div', function () {
		hydrateWidgets();
	});

})(jQuery);
