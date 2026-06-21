(function ($) {
	'use strict';

	var settings = window.ogifyAdmin || {};

	$(function () {
		if ($.fn.wpColorPicker) {
			$('.wp-color-picker').wpColorPicker();
		}
	});

	$(document).on('click', '[data-ogify-media-select]', function (event) {
		event.preventDefault();

		var $button = $(this);
		var $field = $button.closest('[data-ogify-media-field]');
		var frame = wp.media({
			title: $button.data('title') || settings.mediaTitle,
			button: {
				text: $button.data('button') || settings.mediaButton
			},
			multiple: false
		});

		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			var thumb = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;

			$field.find('[data-ogify-media-id]').val(attachment.id);
			$field.find('[data-ogify-media-preview]').html($('<img>', {
				src: thumb,
				alt: $button.data('alt') || settings.previewAlt
			}));
			$field.find('[data-ogify-media-remove]').prop('hidden', false);
		});

		frame.open();
	});

	$(document).on('click', '[data-ogify-media-remove]', function (event) {
		event.preventDefault();

		var $field = $(this).closest('[data-ogify-media-field]');

		$field.find('[data-ogify-media-id]').val('0');
		$field.find('[data-ogify-media-preview]').empty();
		$(this).prop('hidden', true);
	});
})(jQuery);
