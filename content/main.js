$(function () {
	"use strict";

	/* default layout page effects */
	$("#profiling")
		.collapse({
			head: "span",
			group: "ul",
			show: function () {
				this.animate({
					opacity: "toggle",
					height: "toggle"
				}, 300);
			},
			hide: function () {
				this.animate({
					opacity: "toggle",
					height: "toggle"
				}, 300);
			}
		});

	/* analytics */
	try {
		var piwikTracker = Piwik.getTracker('//toolserver.org/~pathoschild/backend/piwik/piwik.php', 1);
		piwikTracker.trackPageView();
		piwikTracker.enableLinkTracking();
	} catch (err) {
		if (window.console && console.log)
			console.log(err);
	}
});
