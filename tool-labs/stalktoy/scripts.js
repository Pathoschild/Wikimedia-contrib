/*jshint bitwise:true, eqeqeq:true, forin:false, immed:true, latedef:true, loopfunc:true, noarg:true, noempty:true, nonew:false, smarttabs:true, strict:true, trailing:true, undef:true*/
/*global $:true, google: true*/
var pathoschild = pathoschild || {};
(function() {
	"use strict";
	
	/**
	 * Generates data visualisations based on Stalktoy's output data.
	 */
	pathoschild.Stalktoy = {
		/**
		 * Provides a fluent interface for configuring and drawing visualisations.
		 * @param string id The unique ID to assign to the visualisation's container.
		 * @param obj options The options object to pass to the underlying chart object.
		 */
		Chart: function(id, options) {
			this.$chart = $(document.createElement('div')).attr('id', id).addClass('viz-chart').prependTo('#account-visualizations');
			this.options = $.extend(true, {}, { width: 200, height: 200, is3D: true, chartArea: { width: 200, height: 170 }, legend: { position: 'bottom' } }, options);
			this.chart = null;
			this.data = null;

			/**
			 * Set the chart object to render.
			 * @param function getChart Given a reference to the created chart container, returns the chart object to render.
			 */
			this.withChart = function(getChart) {
				this.chart = getChart(this.$chart[0]);
				return this;
			};

			/**
			 * Set the data to render.
			 * @param google.visualization.DataTable data The data to render.
			 */
			this.withData = function(data) {
				this.data = data;
				this.data.sort([{ column: 1, desc: true }]);
				return this;
			};
			
			/**
			 * Sort the chart data.
			 * @param object sort The google sorting options.
			 */
			this.withSort = function(sort) {
				this.data.sort(sort);
				return this;
			};

			/**
			 * Render the chart in the created container.
			 */
			this.draw = function() {
				this.chart.draw(this.data, this.options);
			};
		},

		/**
		 * Extract the data from the page and generate the visualisations.
		 */
		Initialize: function() {
			if(!$('#account-visualizations').length)
				return;
		
			// build data table
			var data = new google.visualization.DataTable();
			data.addColumn('string', 'Wiki');
			data.addColumn('string', 'Family');
			data.addColumn('string', 'Language');
			data.addColumn('number', 'Edits');
			$('tr[data-wiki]').each(function(i, row) {
				var $row = $(row);
				if($row.attr('data-exists') === '0')
					return;
				data.addRow([
					$row.attr('data-wiki'),
					$row.attr('data-family'),
					$row.attr('data-lang'),
					parseInt($row.attr('data-edits'), 10)
				]);
			});

			// edits by language
			new pathoschild.Stalktoy.Chart('viz-edits-by-language', { title: 'Edits by language:' })
				.withChart(function(e) { return new google.visualization.PieChart(e); })
				.withData(google.visualization.data.group(data, [2], [{ column: 3, aggregation: google.visualization.data.sum, type: 'number' }]))
				.draw();

			// edits by project
			new pathoschild.Stalktoy.Chart('viz-edits-by-project', { title: 'Edits by project:' })
				.withChart(function(e) { return new google.visualization.PieChart(e); })
				.withData(google.visualization.data.group(data, [1], [{ column: 3, aggregation: google.visualization.data.sum, type: 'number' }]))
				.draw();
		}
	};

	google.load('visualization', '1.0', { 'packages': ['corechart'], 'callback': function() { pathoschild.Stalktoy.Initialize(); } });
	$(function() {
		$('#local-ips, #local-accounts').tablesorter({sortList:[[1,1]]});
	});
}());
