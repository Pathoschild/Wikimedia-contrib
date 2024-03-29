﻿/*


A small library of self-contained helper methods used to create Pathoschild's MediaWiki user scripts.

For more information, see <https://github.com/Pathoschild/Wikimedia-contrib#readme>.


*/
var pathoschild = pathoschild || {};
(function() {
    "use strict";

    /**
     * Self-contained utility methods.
     * @namespace
     */
    pathoschild.util = {
        _version: "1.1",

        /**
         * Enforce a schema defining valid arguments and default values on a key:value object.
         * @param {string} method The name of the method for which the schema is applied, for error-logging purposes.
         * @param {object} args An argument object to conform to the schema.
         * @param {object} schema An argument schema to apply. Every argument key must have an equivalent key in the schema. If a schema key is missing from the args object, the default value is assigned.
         * @returns {object} The schema-conforming object.
         */
        ApplyArgumentSchema: function(method, args, schema) {
            // check key validity
            var i;
            if (args) {
                for (i in args) {
                    if (typeof (schema[i]) === typeof (undefined)) {
                        // throw error if invalid
                        var validArgs = [];
                        var x;
                        for (x in schema)
                            validArgs.push(x);
                        pathoschild.util.Log('Ignoring invalid argument "' + i + '"; valid arguments for method "' + method + '" are [' + validArgs.toString() + "].");
                        delete args[i];
                    }
                }
            }
            else
                args = {};

            // enforce default values
            var n;
            for (n in schema) {
                if (typeof (args[n]) === typeof (undefined) || args[n] === null)
                    args[n] = schema[n];
            }

            // return schema-conformant object
            return args;
        },

        /**
         * Check a value against an enumeration, throw an error if it does not represent an enumeration value, and return the matched enumeration value.
         * @param {string} enumName The name of the enumeration, for error-logging purposes.
         * @param {string} value The value to check against the enum, which may be either a key or value in the enumeration.
         * @param {object | string[]} enumObj The enumeration to check, consisting of a one-dimensional string:string mapping or an array.
         * @returns {string} The matched enumeration value.
         * @throws  Error    An exception indicating that some arguments were invalid.
         */
        ApplyEnumeration: function(enumName, value, enumObj) {
            // enumeration is undefined or empty
            if (!enumObj)
                return null;

            // ...or value is an enumeration key
            if (typeof (enumObj[value]) !== typeof (undefined))
                return enumObj[value];

            // ...or value is an enumeration value
            if (Array.isArray(enumObj)) {
                if ($.inArray(value, enumObj))
                    return value;
            }
            else {
                var i;
                for (i in enumObj) {
                    if (enumObj[i] === value)
                        return value;
                }
            }

            // ...or value is invalid
            var valid = [];
            var k;
            for (k in enumObj)
                valid.push(enumObj[k]);
            throw new Error('The value "' + value + '" is not a valid ' + enumName + " enumeration value, expected one of [" + valid.toString() + "].");
        },

        /**
         * Write a message to the browser debugging console.
         * @param {string} message The message to log.
         */
        Log: function(message) {
            if (window.console && window.console.log)
                window.console.log(message);
            else if (window.mw)
                mw.log(message);
        },

        /**
         * Add a block of CSS to the page. Scripts only used with MediaWiki should call mw.loader.load(...) or mw.util.addCSS(...) instead.
         * @param {string} css The CSS text to add.
         */
        AddStyles: function(css) {
            $(document.createElement("style"))
                .attr({ rel: "stylesheet", type: "text/css" })
                .text(css)
                .appendTo("head:first");
        },

        /**
         * Provides access to the browser's local storage.
         */
        storage: {
            /**
             * Get whether the browser supports HTML local storage.
             * See https://caniuse.com/json and https://caniuse.com/localstorage
             */
            IsAvailable: function() {
                return window.localStorage && window.JSON;
            },

            /**
             * Save a JavaScript object to local browser storage (if the browser supports it).
             * @param {string} key The unique key which identifies the value in storage.
             * @param {object} value An arbitrary object to store.
             */
            Write: function(key, value) {
                if (this.IsAvailable())
                    localStorage.setItem(key, JSON.stringify(value));
            },

            /**
             * Read a JavaScript object from local browser storage (if the browser supports it).
             * If not supported, returns null.
             * @param {string} key The unique key which identifies the value in storage.
             */
            Read: function(key) {
                if (window.localStorage && window.JSON)
                    return JSON.parse(localStorage.getItem(key));
                return null;
            },

            /**
             * Delete a value from local browser storage (if the browser supports it).
             * @param {string} key The unique key which identifies the value in storage.
             */
            Delete: function(key) {
                if (window.localStorage && window.JSON)
                    localStorage.removeItem(key);
            }
        },

        /**
         * Provides generic hooks to the MediaWiki UI.
         */
        mediawiki: {
            /**
             * Add a navigation menu portlet to the sidebar.
             * @param {string} id The unique portlet ID.
             * @param {string} name The display name displayed in the portlet header.
             */
            AddPortlet: function(id, name) {
                // copy the portlet structure for the current skin
                var $sidebar = $("#p-tb").clone().attr("id", id);
                $sidebar.find("h1, h2, h3, h4, h5").first().text(name);
                $sidebar.find("ul").empty();
                $("#p-tb").parent().append($sidebar);

                return $sidebar;
            },

            /**
             * Add a link to a navigation sidebar menu.
             * @param {string} portletID The unique navigation portlet ID.
             * @param {string} text The link text.
             * @param {string} id A unique ID for the link.
             * @param {string} accessKey A keyboard shortcut key which invokes the template or script directly; see [[w:Wikipedia:Keyboard shortcuts]].
             * @param {string} tooltip A short explanation of the template or script, typically shown when the user hovers their cursor over the link.
             * @param {string|function} target The link URI or callback.
             * @return
             */
            AddPortletLink: function(portletID, text, id, tooltip, accessKey, target) {
                // create link
                var isCallback = typeof target === 'function';
                var uri = isCallback ? "#" : target;
                var $link = $(mw.util.addPortletLink(portletID, uri, text, id, tooltip || ""));
                if (isCallback)
                    $link.click(function(e) { e.preventDefault(); target(e); });

                // add access key
                if (accessKey) {
                    // steal access key if needed
                    var previousTarget = $('[accesskey="' + accessKey.replace('"', '\\"') + '"]')
                    if (previousTarget.length) {
                        pathoschild.util.Log("pathoschild.util::AddPortletLink: overwrote access key [" + accessKey + '] previously assigned to "' + previousTarget.text() + '".');
                        previousTarget.removeAttr("accesskey");
                    }

                    // set key
                    $link.find("a:first").attr("accesskey", accessKey);
                }

                return $link;
            }
        },

        /**
         * Run JavaScript unit tests using Mocha and Chai, and print the results to the console. This
         * enables unit tests with zero framework code, but you can do more if needed by using Mocha and
         * Chai directly instead. To use this method, just pass a callback containing your Mocha/Chai unit
         * tests as described at https://www.chaijs.com/guide/styles/.
         *
         * @param {function} tests A callback to invoke when Mocha and Chai are ready.
         */
        RunTests: function(tests) {
            // load scripts
            $.when(
                $.ajax("https://tools-static.wmflabs.org/cdnjs/ajax/libs/mocha/2.2.1/mocha.min.js", { dataType: "script", cache: true }),
                $.ajax("https://tools-static.wmflabs.org/cdnjs/ajax/libs/chai/2.2.0/chai.min.js", { dataType: "script", cache: true }),
                $.ajax("https://tools-static.wmflabs.org/meta/scripts/dependencies/WebConsole.js", { dataType: "script", cache: true })
            ).done(function() {
                // configure
                mocha.setup({ ui: "bdd", reporter: WebConsole });
                chai.config.includeStack = false;

                // run tests
                tests(chai);
                mocha.run();
            });
        }
    };
}());
