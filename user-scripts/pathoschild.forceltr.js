var pathoschild = pathoschild || {};
(function() {
	'use strict';

	/**
	 * Forces MediaWiki into displaying text in left-to-right format, even if the wiki's primary language is right-to-left.
	 * @see https://github.com/Pathoschild/Wikimedia-contrib#readme
	 */
	pathoschild.ForceLtr = {
		version: '1.0',

		/**
		 * Apply the changes.
		 */
		initialize: function() {
			$('.mw-content-ltr').removeClass('mw-content-ltr');
			$('.sitedir-rtl').removeClass('sitedir-rtl').addClass('sitedir-ltr');
			$('body').removeClass('rtl').addClass('ltr');
			$('[dir="rtl"]').attr('dir', 'ltr');
		}
	};

	$(pathoschild.ForceLtr.initialize);
}());
