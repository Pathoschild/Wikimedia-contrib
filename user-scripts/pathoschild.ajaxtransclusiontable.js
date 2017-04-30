/*


The ajax transclusion table script adds a "[show]" link in the first cell of every row in tables
with the "attable" class. Clicking the link will display the linked page below. No show/hide link
will be shown in rows that don't contain a link, like headings.


*/
/* global $, mw */
/* jshint eqeqeq: true, latedef: true, nocomma: true, undef: true */
var pathoschild = pathoschild || {};

$(function() {
    "use strict";

    if (pathoschild.ajaxTransclusionTables)
        return; // already initialised, don't overwrite


    /**
     * Singleton responsible for handling ajax transclusion tables.
     * @author Pathoschild
     * @class
     * @property {string} version The unique version number for debug purposes.
     */
    pathoschild.ajaxTransclusionTables = (function() {
        var self = {};

        /*********
        ** Fields
        *********/
        self.version = "0.2";


        /*********
        ** Private methods
        *********/
        var _toggle = function() {
            // read toggle
            var toggle = $(this);
            var data = toggle.data();

            // toggle transclusion
            if (data.expanded) {
                $(data.container).remove();
                toggle.text("[show] ").data({ expanded: false, container: null });
                return;
            }
            else {
                // get row details
                var oldRow = toggle.closest("tr");
                var rowID = "att-" + (new Date()).getTime();
                var colspan = oldRow.find("> td").length;

                // update UI
                toggle.text("[hide] ").data({ expanded: true, container: "#" + rowID });
                var newDiv = $('<div class="att-container">').appendTo(
                    $("<td>").attr("colspan", colspan).appendTo(
                        $("<tr>").attr("id", rowID).insertAfter(oldRow)
                    )
                );
                newDiv.append($("<img>").attr("src", "//upload.wikimedia.org/wikipedia/commons/d/d2/Spinning_wheel_throbber.gif"));

                $.ajax(mw.config.get("wgServer") + "/wiki/" + data.title + "?action=render").then(function(data) {
                    newDiv.html(data);
                });
            }
        };


        /*********
        ** Public methods
        *********/
        /**
         * Bootstrap and hook into the UI. This method should only be called once the DOM is ready.
         */
        self.initialise = function() {
            // find cells to inject
            var rows = $("table.attable tr");
            if (!rows.length)
                return;

            // add styles
            mw.util.addCSS(
                ".att-container { margin:0.5em; padding:0.5em; border:2px solid gray; }"
                + ".att-toggle { font-size:0.9em; cursor:pointer; }"
            );

            // inject links
            var toggle = $("<a>").addClass("att-toggle").text("[show] ");
            rows.each(function(i, row) {
                // get title to transclude
                row = $(row);
                var cell = row.find("td:first");
                var link = cell.find("a:first");
                var title = link.attr("title");
                if (!link.length || !link.attr("href").match(/^\/wiki/) || !title)
                    return;

                // inject toggle
                cell.prepend(
                    toggle.clone().data({ title: title, expanded: false }).click(_toggle)
                );
            });
        };

        $(self.initialise);
        return self;
    })();
});
