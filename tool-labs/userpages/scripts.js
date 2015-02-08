var pathoschild = pathoschild || {};
pathoschild.PageFilters = function() {
	/**
	 * Filters the user page list to only show pages matching the selected filters.
	 */

	var filters = $('.filter');
	this.apply = function() {
		/**
		 * Reapply the selected filters.
		 */
		// apply filters
		$('.result-box').find('ul, li, h2').filter(':hidden').show();
		if(!this.isEnabled('misc'))
			$('.result-box li.type-misc:visible').hide();
		if(!this.isEnabled('css'))
			$('.result-box li.type-css:visible').hide();
		if(!this.isEnabled('js'))
			$('.result-box li.type-js:visible').hide();

		// hide wikis with no pages shown
		$('.result-box ul:not(:has(li:visible))').hide();
		$('.result-box ul:not(:visible)').prev('h2').hide();
	};

	this.isEnabled = function(key) {
		/**
		 * Get whether the specified filter is enabled.
		 */
		return filters.filter('[data-filter="' + key + '"]').hasClass('selected');
	};

	return this;
};

$(function() {
	var filters = pathoschild.PageFilters();
	$('.filter').click(function(event) {
		$(this).toggleClass('selected');
		filters.apply();
		event.preventDefault();
	});
});
