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
});
