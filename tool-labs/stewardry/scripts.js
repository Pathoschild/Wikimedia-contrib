/*jshint bitwise:true, eqeqeq:true, forin:false, immed:true, latedef:true, loopfunc:true, noarg:true, noempty:true, nonew:false, smarttabs:true, strict:true, trailing:true, undef:true*/
/*global $:true, google: true*/
var pathoschild = pathoschild || {};
(function() {
	"use strict";
	
	$(function() {
		$('.sortable').tablesorter({sortList:[[1,1], [2,1]]});
	});
}());
