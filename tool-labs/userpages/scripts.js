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
		// reset filters
		$('.result-box').find('ul, li, h2').filter(':hidden').show();

		// apply filters
		var items = $('.result-box li');
		$('[data-filters]').each(function() {
			var toggle = $(this);
			if(!toggle.hasClass('selected'))
				items.filter(toggle.data('filters')).hide();
		});

		// hide wikis with no pages shown
		$('.result-box ul:not(:has(li:visible))').hide();
		$('.result-box ul:not(:visible)').prev('h2').hide();
	};

	this.getToggle = function(key) {
		/**
		 * Get the toggle element for a filter.
		 * @param key {string} The unique key for the filter.
		 */
		return filters.filter('[data-filter-key="' + key + '"]');
	}

	this.isEnabled = function(key) {
		/**
		 * Get whether the specified filter is enabled.
		 * @param key {string} The unique key for the filter.
		 */
		return this.getToggle(key).hasClass('selected');
	};

	this.toggle = function(key) {
		/**
		 * Toggle a filter in the UI and URL.
		 * @param key {string} The unique key for the filter.
		 */
		this.getToggle(key).toggleClass('selected');
		location.hash = '#' + $('[data-filters].selected').map(function() { return $(this).attr('data-filter-key'); }).get().join();
		this.apply();
	}

	this.readHash = function() {
		/**
		 * Update the filters based on the URL's hash.
		 */
		// read hash from URL
		var selected = location.hash.replace(/^#/, '');
		if(!selected.length)
			return;
		selected = decodeURIComponent(selected.replace(/\./g, '%')); // reverse MediaWiki link munging

		// apply
		selected = selected.split(',');
		if(selected.length) {
			$('[data-filters]').removeClass('selected');
			for(var i = 0, len = selected.length; i < len; i++)
				this.getToggle(selected[i]).addClass('selected');
			this.apply();
		}
	};

	return this;
};

$(function() {
	var filters = pathoschild.PageFilters();
	$('.filter').click(function(event) {
		filters.toggle($(this).attr('data-filter-key'));
		event.preventDefault();
	});
	filters.readHash();
});
