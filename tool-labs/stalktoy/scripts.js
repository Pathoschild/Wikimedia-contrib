/*jshint bitwise:true, eqeqeq:true, forin:false, immed:true, latedef:true, loopfunc:true, noarg:true, noempty:true, nonew:false, smarttabs:true, strict:true, trailing:true, undef:true*/
/*global $:true, echarts: true*/
var pathoschild = pathoschild || {};
(function () {
    "use strict";

    /**
     * Generates pie charts based on Stalktoy's output data.
     */
    pathoschild.Stalktoy = {
        /**
         * The minimum percentage for a pie chart slice which should always be shown.
         */
        majorPercent: 5,

        /**
         * The maximum total slices to show, before any remaining slices below `majorPercent` are grouped into 'other'.
         */
        maxSlices: 15,

        /**
         * The custom pie chart labels to show for each value, if different. (This doesn't affect tooltips.)
         */
        shortLabels: {
            multilingual: "multi" // shorten to fit in chart margins
        },

        /**
         * The rendered chart instances.
         */
        charts: [],

        /**
         * Read the result data from the rendered table.
         * @returns {object[]} The results in the form `{wiki, family, language, edits}`.
         */
        getWikiData: function () {
            const wikis = [];

            $("tr[data-wiki]").each((i, row) => {
                const $row = $(row);
                if ($row.attr("data-exists") === "0")
                    return;

                wikis.push({
                    wiki: $row.attr("data-wiki"),
                    family: $row.attr("data-family"),
                    language: $row.attr("data-lang"),
                    edits: parseInt($row.attr("data-edits"), 10)
                });
            });

            return wikis;
        },

        /**
         * Group wiki rows into slices based on a field, sorted from most to fewest edits.
         * @param {object[]} wikis The wikis to aggregate.
         * @param {string} field The wiki field name to group by.
         * @returns {object[]} The `{name, value}` slices to render.
         */
        getSlicesByField: function (wikis, field) {
            // get total edits by field value
            const totals = {};
            $.each(wikis, (i, wiki) => {
                totals[wiki[field]] = (totals[wiki[field]] || 0) + wiki.edits;
            });

            // get slices
            const slices = [];
            $.each(totals, (name, value) => {
                if (value > 0)
                    slices.push({ name: name, value: value });
            });

            // sort descending
            return slices.sort((a, b) => b.value - a.value);
        },

        /**
         * Merge slices below the display threshold into one 'other' slice.
         * @param {object[]} slices The `{name, value}` slices, sorted from largest to smallest.
         * @returns {object[]} The slices to render.
         */
        mergeTailSlices: function (slices) {
            // get total edits across all slices
            const total = slices.reduce((sum, slice) => sum + slice.value, 0);
            if (!total)
                return slices;

            // split into keep vs 'other'
            const minEditsToAlwaysShow = total * this.majorPercent / 100;
            const keepSlices = [];
            const otherSlices = [];
            $.each(slices, (i, slice) => {
                if (slice.value >= minEditsToAlwaysShow || keepSlices.length < this.maxSlices)
                    keepSlices.push(slice);
                else
                    otherSlices.push(slice);
            });

            // merge 'other' slices
            if (otherSlices.length > 1) {
                const otherTotal = otherSlices.reduce((sum, slice) => sum + slice.value, 0);
                keepSlices.push({
                    name: "other (" + otherSlices.length + ")",
                    value: otherTotal,
                    itemStyle: { color: "#BBB" }
                });
            }
            else
                keepSlices.push(...otherSlices);

            return keepSlices;
        },

        /**
         * Render a pie chart.
         * @param {string} id The unique ID to assign to the chart's container.
         * @param {string} title The title to show above the chart.
         * @param {object[]} slices The `{name, value}` slices to render.
         */
        drawChart: function (id, title, slices) {
            const container = $(document.createElement("div"))
                .attr("id", id)
                .addClass("chart")
                .appendTo("#account-charts")[0];

            const chart = echarts.init(container);
            chart.setOption({
                title: {
                    text: title,
                    left: "left",
                    textStyle: {
                        fontFamily: "Roboto, sans-serif",
                        fontSize: 13
                    }
                },
                toolbox: {
                    top: 0,
                    right: 0,
                    feature: {
                        saveAsImage: {
                            title: "Save as image",
                            name: id,
                            pixelRatio: 2,
                            backgroundColor: "#FFF",
                            excludeComponents: ["toolbox"]
                        }
                    }
                },
                tooltip: {
                    trigger: "item",
                    formatter: "{b}: {c} ({d}%)"
                },
                series: [{
                    type: "pie",
                    data: slices,
                    percentPrecision: 1,
                    radius: "75%",
                    label: {
                        show: true,
                        fontSize: 11,
                        formatter: entry => `${this.shortLabels[entry.name] || entry.name}  ${entry.percent}%`,
                        bleedMargin: 2 // pixel gap from edge before a label is truncated to fit (default 10)
                    },
                    labelLine: {
                        show: true,
                        length: 8,
                        length2: 10,
                        maxSurfaceAngle: 80
                    },
                    avoidLabelOverlap: true
                }]
            });

            this.charts.push(chart);
        },

        /**
         * Extract the data from the page and generate the visualisations.
         */
        initialize: function () {
            if (!$("#account-charts").length)
                return;

            const wikis = this.getWikiData();
            if (!wikis.length)
                return;

            this.drawChart("edits-by-project", "Edits by project:", this.mergeTailSlices(this.getSlicesByField(wikis, "family")));
            this.drawChart("edits-by-language", "Edits by language:", this.mergeTailSlices(this.getSlicesByField(wikis, "language")));

            $(window).on("resize", () =>
                $.each(this.charts, (i, chart) => chart.resize())
            );
        }
    };

    $(() => {
        $("#local-ips, #local-accounts").tablesorter({sortList: [[1, 1]]});

        pathoschild.Stalktoy.initialize();
    });
}());
