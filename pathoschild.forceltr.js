var pathoschild = pathoschild || {};
(function() {
	'use strict';

	/**
	 * Forces MediaWiki into displaying text in left-to-right format, even if the wiki's primary language is right-to-left.
	 * @see https://github.com/Pathoschild/Wikimedia-contrib#readme
	 */
	pathoschild.ForceLtr = {
		version: '1.1',
		langUrlToken: /(\?|&|&amp;)lang=(.+?)(&|$)/,
		rtlCodes: /ar|arc|arz|azb|bcc|ckb|bqi|dv|fa|fa-af|glk|ha|he|kk-arab|kk-cn|ks|ku-arab|mzn|pnb|prd|ps|sd|ug|ur|ydd|yi/, // derived from meta.wikimedia.org/wiki/Template:Dir

		/**
		 * Apply the changes.
		 */
		initialize: function() {
			var self = pathoschild.ForceLtr;

			// adjust classes
			$('body').removeClass('mw-content-ltr mw-content-rtl');
			$('.sitedir-rtl').removeClass('sitedir-rtl').addClass('sitedir-ltr');
			$('.rtl').removeClass('rtl').addClass('ltr');
			$('[dir="rtl"]').attr('dir', 'ltr');

			// switch rtl styles
			$('link[rel="stylesheet"]').each(function() {
				var link = $(this);
				var href = link.attr('href');
				var lang = href.match(self.langUrlToken);
				if(lang && lang[2].match(self.rtlCodes))
					link.attr('href', href.replace(self.langUrlToken, '$1lang=en$3'));
			});
		}
	};

	$(pathoschild.ForceLtr.initialize);
}());
