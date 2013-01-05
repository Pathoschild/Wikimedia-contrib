/*jshint bitwise:true, eqeqeq:true, forin:false, immed:true, latedef:true, loopfunc:true, noarg:true, noempty:true, nonew:true, smarttabs:true, strict:true, trailing:true, undef:true*/
/*global $:true, mw:true*/
var pathoschild = pathoschild || {};
(function() {
	"use strict";

	/**
	 * Forces MediaWiki into displaying text in left-to-right format, even if the wiki's primary language is right-to-left.
	 * @see https://github.com/Pathoschild/Wikimedia-contrib#readme
	 */
	pathoschild.ForceLtr = {
		version: '0.9.1',

		/**
		 * Initialize the script.
		 */
		Initialize: function() {
			if ($('body').hasClass('sitedir-rtl')) {
				// Swap load.php to an LTR language
				$('link[rel="stylesheet"][href^="' + mw.config.get('wgLoadScript') + '"]')
					.attr('href', function(i, val) {
						return val.replace(/&lang=[^&]+/, '&lang=en');
					});

				// Swap direction
				$('body').removeClass('rtl').addClass('ltr');
				$('[dir="rtl"]').attr('dir', 'ltr');
			}
		}
	};

	$(pathoschild.ForceLtr.Initialize);
}());
