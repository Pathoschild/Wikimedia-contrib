/*jshint bitwise:true, eqeqeq:true, forin:false, immed:true, latedef:true, loopfunc:true, noarg:true, noempty:true, nonew:true, smarttabs:true, strict:true, trailing:true, undef:true*/
var pathoschild = pathoschild || {};

/**
 * Forces MediaWiki into displaying text in left-to-right format, even if the wiki's primary language is right-to-left.
 * @see https://github.com/Pathoschild/Wikimedia-contrib#readme
 */
pathoschild.ForceLtr = {
	/**
	 * Initialize the script.
	 */
	Initialize: function() {
		/* swap load.php to an LTR language */
		if ($('html:first').attr('dir') == 'rtl') {
			var $links = $('link[rel="stylesheet"]');
			$links = $links.filter('[href^="' + mw.config.get('wgLoadScript') + '"]');
			$links.each(function(i, item) {
				var $item = $(item);
				$item.attr('href', $item.attr('href').replace(/&lang=[^&]+/, '&lang=en'));
			});
		}

		/* swap dir values */
		$('*[dir="rtl"]').attr('dir', 'ltr');
	}
};

$(pathoschild.ForceLtr.Initialize);