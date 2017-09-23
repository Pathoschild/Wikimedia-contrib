/*


TemplateScript adds a menu of configurable templates and scripts to the sidebar.
For more information, see <https://github.com/Pathoschild/Wikimedia-contrib#readme>.


*/
/* global $, Handlebars, JSON, mw, pathoschild */
/* jshint eqeqeq: true, latedef: true, nocomma: true, undef: true */
window.pathoschild = window.pathoschild || {}; // use window for ResourceLoader compatibility
(function() {
    "use strict";

    if (pathoschild.TemplateScript)
        return; // already initialised, don't overwrite


    /**
     * Singleton responsible for handling user-defined templates available through a sidebar menu.
     * @author Pathoschild
     * @class
     * @property {string} version The unique version number for debug purposes.
     */
    pathoschild.TemplateScript = (function() {
        var self = {};

        /*********
        ** Fields
        *********/
        self.version = "2.5.1";
        self.strings = {
            defaultHeaderText: "TemplateScript", // the sidebar header text label for the default group
            regexEditor: "Regex editor" // the default 'regex editor' script
        };
        var state = {
            // user configuration
            config: mw.config.get("userjs-templatescript") || {},

            // bootstrapping
            dependencies: [], // internal lookup used to manage asynchronous script dependencies
            isInited: false,  // whether TemplateScript has started (or finished) initialising
            isReady: false,   // whether TemplateScript has been initialised and hooked into the DOM
            templates: [],    // the registered template objects
            queue: [],        // the template objects to add to the DOM when it's ready
            sidebarCount: 0,  // number of rendered sidebars (excluding the default sidebar)
            sidebars: {},     // hash of rendered sidebars by name
            libraries: [],    // the imported libraries
            customLibrarySettings: {}, // custom settings to apply to imported library script (as a key => Template hash)

            // state management
            renderers: {},    // the plugins which render template/script links
            escaped: {},      // contains metadata for the editor.escape and editor.unescape methods
            $target: null,     // the primary input element (e.g., the edit textarea) for the current form
            $editSummary: null // the edit summary input element (if relevant to the current form)
        };


        /*********
        ** Objects
        *********/
        /**
         * Represents an configured script or template.
         * @property {string} key The unique key within its library (if it's from a library).
         * @property {string} name The name displayed as the sidebar link text.
         * @property {boolean} enabled Whether this template is available.
         * @property {string} category An arbitrary category name (for grouping templates into multiple sidebars). The default is `self.strings.defaultHeaderText`.
         * @property {string[]} forActions The context.action values for which the template is enabled, or '*' for all actions *including* view. The default is 'edit'.
         * @property {int[]} forNamespaces The namespaces in which the template is enabled, or '*' to enable in all namespaces. The default is '*'.
         * @property {string} accessKey A keyboard shortcut key which invokes the template or script directly; see [[w:Wikipedia:Keyboard shortcuts]].
         * @property {string} tooltip A short explanation of the template or script, typically shown when the user hovers their cursor over the link.
         * @property {string} renderer The unique key of the render plugin used to add the tool link that activates the template. The default value is 'sidebar'.
         *
         * @property {string} template The template text to insert.
         * @property {string} position The position at which to insert the template, matching a {Position} value. The default value is 'cursor' when editing a page, and 'replace' in all other cases.
         * @property {string} editSummary The edit summary to use (if applicable).
         * @property {string} editSummaryPosition The position at which to insert the edit summary, matching a {Position} value. The default value is 'replace'.
         * @property {string} headline The subject or headline summary to use (if applicable). This appears when editing a page with &section=new in the URL.
         * @property {string} headlinePosition The position at which to insert the headline, matching a {Position} value. The default value is 'replace'.
         * @property {boolean} isMinorEdit Whether to mark the edit as minor (if applicable).
         *
         * @property {string} scriptUrl A script URL (or page name on the current wiki) to fetch before adding the template.
         * @property {function} script An arbitrary JavaScript function that is called after the template and edit summary are applied. It is passed a reference to the context object.
         *
         * @property {int} id The internal template ID. (Modifying this value may cause unexpected behaviour.)
         * @class
         */
        self.Template = {
            /* UI options */
            key: null,
            name: null,
            enabled: true,
            category: null,
            forActions: "edit",
            forNamespaces: "*",
            accessKey: null,
            tooltip: null,
            renderer: "sidebar",

            /* template options */
            template: null,
            position: "cursor",
            editSummary: null,
            editSummaryPosition: "after",
            headline: null,
            headlinePosition: "after",
            isMinorEdit: false,

            /* script options */
            scriptUrl: null,
            script: null,

            /* internal */
            id: null,
            fromLibrary: false
        };

        /**
         * Represents a text insertion method.
         * @enum {string}
         * @property {string} before Insert before the text.
         * @property {string} after Insert after the text.
         * @property {string} cursor Insert the template at the current cursor position (replacing any selected text).
         * @property {string} replace Replace the current text entirely.
         */
        self.Position = {
            before: "before",
            after: "after",
            cursor: "cursor",
            replace: "replace"
        };

        /**
         * Represents a library of scripts which can be configured via [[Special:TemplateScript]].
         * @property {string} key A unique key which identifies the library.
         * @property {string} url The URL to a page with more information about the library.
         * @property {string} description An HTML string describing the library for the user.
         * @property {boolean} defaultEnabled Whether the library is enabled by default. If this is false, the user must explicitly enable it through [[Special:TemplateScript]].
         * @property {LibraryCategory[]} categories The script categories to import.
         * @class
         */
        self.Library = {
            key: null,
            name: null,
            url: null,
            description: null,
            defaultEnabled: true,
            categories: []
        };

        /**
         * Represents a script category in a library which can be configured via [[Special:TemplateScript]].
         * @property {string} name The name of the library shown to the user.
         * @property {Template[]} scripts The script to import.
         * @class
         */
        self.LibraryCategory = {
            name: null,
            scripts: []
        };

        /**
         * Provides a unified API for making changes to the current page's form.
         * @property {string} action The string representing the current MediaWiki action.
         */
        self.Context = (function() {
            /*********
            ** Fields
            *********/
            var context = {
                action: (function() {
                    var action = mw.config.get("wgAction");
                    var specialPage = mw.config.get("wgCanonicalSpecialPageName");
                    switch (action) {
                        case "submit":
                            return "edit";

                        case "view":
                            if ($("#movepage").length)
                                return "move";
                            if (specialPage === "Block")
                                return "block";
                            if (specialPage === "Emailuser")
                                return "emailuser";

                        default:
                            return action;
                    }
                })()
            };


            /*********
            ** Private methods
            *********/
            /**
             * Get the CodeEditor instance for the page (if any).
             */
            var _getCodeEditor = function() {
                if (context.action === "edit") {
                    var ace = $(".ace_editor:first").get(0);
                    if (ace)
                        return ace.env.editor;
                }
            };

            /**
             * Get the editor for the main input. This assumes there's no custom editor like
             * CodeEditor or VisualEditor.
             */
            var _getFieldEditor = function() {
                return context.forField(state.$target);
            };


            /*********
            ** Public methods
            *********/
            /*****
            ** Any form
            *****/
            /**
             * Wraps an input field with shorthand methods for manipulating its contents. This
             * wrapper isn't compatible with custom editors like CodeEditor or VisualEditor, so it
             * shouldn't be used on the main edit input.
             * @param {jQuery|string} field The jQuery collection or selector for the field to edit.
             */
            context.forField = function(field) {
                var wrapper = {};

                /*********
                ** Properties
                *********/
                /**
                 * The jQuery collection containing the field being edited.
                 */
                wrapper.field = field = $(field).first();


                /*********
                ** Public methods
                *********/
                /**
                 * Get whether the target element contains a search value.
                 * @param {string|RegExp} search A literal or regex search pattern.
                 */
                wrapper.contains = function(search) {
                    return search instanceof RegExp
                        ? wrapper.get().search(search) !== -1
                        : wrapper.get().indexOf(search) !== -1;
                };

                /**
                 * Get the value of the target element.
                 */
                wrapper.get = function() {
                    return field.val();
                };

                /**
                 * Set the value of the target element.
                 * @param {string} text The text to set.
                 * @returns The wrapper for chaining.
                 */
                wrapper.set = function(text) {
                    field.val(text);
                    return wrapper;
                };

                /**
                 * Perform a search & replace in the target element.
                 * @param {string|regexp} search The search string or regular expression.
                 * @param {string|function} replace The replace pattern or function (as described at https://developer.mozilla.org/en/docs/Web/JavaScript/Reference/Global_Objects/String/replace ).
                 * @returns The wrapper for chaining.
                 */
                wrapper.replace = function(search, replace) {
                    field.val(function(i, val) { return val.replace(search, replace); });
                    return wrapper;
                };

                /**
                 * Prepend text to the target element.
                 * @param {string} text The text to append.
                 * @returns The wrapper for chaining.
                 */
                wrapper.prepend = function(text) {
                    field.val(function(i, val) { return text + val; });
                    return wrapper;
                };

                /**
                 * Append text to the target element.
                 * @param {string} text The text to append.
                 * @returns The wrapper for chaining.
                 */
                wrapper.append = function(text) {
                    field.val(function(i, val) { return val + text; });
                    return wrapper;
                };

                /**
                 * Get whether the user has selected text in the target field.
                 */
                wrapper.hasSelection = function() {
                    var box = field.get(0);

                    // most browsers
                    if (box.selectionStart || box.selectionStart === false || box.selectionStart === "0" || box.selectionStart === 0)
                        return box.selectionStart !== box.selectionEnd;

                    // older browsers
                    else if (document.selection) {
                        var selection = document.selection.createRange();
                        return selection && selection.text && selection.text.length;
                    }

                    // unknown implementation
                    else
                        return false;
                };

                /**
                 * Replace the selected text in the target field.
                 * @param {string|function} text The new text with which to overwrite the selection (with any template format values preparsed), or a function which takes the selected text and returns the new text. If no text is selected, the function is passed an empty value and its return value is added to the end.
                 * @returns The wrapper for chaining.
                 */
                wrapper.replaceSelection = function(text) {
                    var box = field.get(0);
                    box.focus();

                    // standardise input
                    if (!$.isFunction(text)) {
                        var _t = text;
                        text = function() { return _t; };
                    }

                    // most browsers
                    if (box.selectionStart || box.selectionStart === false || box.selectionStart === "0" || box.selectionStart === 0) {
                        var startPos = box.selectionStart;
                        var endPos = box.selectionEnd;
                        var scrollTop = box.scrollTop;

                        var newText = text(box.value.substring(startPos, endPos));
                        box.value = box.value.substring(0, startPos) + newText + box.value.substring(endPos, box.value.length);
                        box.focus();

                        box.selectionStart = startPos + text.length;
                        box.selectionEnd = startPos + text.length;
                        box.scrollTop = scrollTop;
                    }

                    // older browsers
                    else if (document.selection) {
                        var selection = document.selection.createRange();
                        selection.text = text(selection.text);
                        box.focus();
                    }

                    // unknown implementation
                    else {
                        _warn("can't figure out the browser's cursor selection implementation, appending instead.");
                        box.value += text("");
                        return;
                    }
                    return wrapper;
                };

                return wrapper;
            };

            /**
             * Get whether the target element contains a search value.
             * @param {string|RegExp} search A literal or regex search pattern.
             */
            context.contains = function(search) {
                return search instanceof RegExp
                    ? context.get().search(search) !== -1
                    : context.get().indexOf(search) !== -1;
            };

            /**
             * Get the value of the target element.
             */
            context.get = function() {
                // code editor
                var codeEditor = _getCodeEditor();
                if (codeEditor)
                    return codeEditor.getValue();

                // no editor
                return _getFieldEditor().get();
            };

            /**
             * Set the value of the target element.
             * @param {string} text The text to set.
             */
            context.set = function(text) {
                // code editor
                var codeEditor = _getCodeEditor();
                if (codeEditor) {
                    // When we overwrite CodeEditor's text, it moves the cursor position to the end
                    // of the text. We'll track the current position and restore it after setting
                    // the new value, which is the typical behaviour for non-CodeEditor inputs.
                    var pos = codeEditor.session.selection.toJSON();
                    codeEditor.setValue(text);
                    codeEditor.session.selection.fromJSON(pos);
                    return context;
                }

                // no editor
                _getFieldEditor().set(text);
                return context;
            };

            /**
             * Perform a search & replace in the target element.
             * @param {string|regexp} search The search string or regular expression.
             * @param {string} replace The replace pattern.
             * @returns The helper instance for chaining.
             */
            context.replace = function(search, replace) {
                // code editor
                var codeEditor = _getCodeEditor();
                if (codeEditor)
                    return context.set(context.get().replace(search, replace));

                // no editor
                _getFieldEditor().replace(search, replace);
                return context;
            };

            /**
             * Prepend text to the target element.
             * @param {string} text The text to prepend.
             * @returns The helper instance for chaining.
             */
            context.prepend = function(text) {
                return context.set(text + context.get());
            };

            /**
             * Append text to the target element.
             * @param {string} text The text to append.
             * @returns The helper instance for chaining.
             */
            context.append = function(text) {
                return context.set(context.get() + text);
            };

            /**
             * Escape the matching substrings in the target element to avoid conflicts. This returns a state used to unescape.
             * @param {string|regexp} search The search string or regular expression.
             * @returns The helper instance for chaining.
             */
            context.escape = function(search) {
                var text = context.get();

                var tokenFormat = "~" + (new Date()).getTime() + ".$1~";
                var i = 0;
                text = text.replace(search, function(match) {
                    var token = tokenFormat.replace("$1", i++);
                    state.escaped[token] = match;
                    return token;
                });

                context.set(text);
                return context;
            };

            /**
             * Restore substrings in the target element escaped by the escape(search) method.
             * @returns The helper instance for chaining.
             */
            context.unescape = function() {
                var text = context.get();

                for (var token in state.escaped)
                    text = text.replace(token, state.escaped[token]);

                context.set(text);
                return context;
            };

            /**
             * Get whether the user has selected text in the target field.
             */
            context.hasSelection = function() {
                // code editor
                var codeEditor = _getCodeEditor();
                if (codeEditor)
                    return codeEditor.getSelectedText().length > 0;

                // no editor
                return _getFieldEditor().hasSelection();
            };

            /**
             * Replace the selected text in the target field.
             * @param {string|function} text The new text with which to overwrite the selection (with any template format values preparsed), or a function which takes the selected text and returns the new text. If no text is selected, the function is passed an empty value and its return value is added to the end.
             */
            context.replaceSelection = function(text) {
                // code editor
                var codeEditor = _getCodeEditor();
                if (codeEditor) {
                    var selected = $.isFunction(text)
                        ? text(codeEditor.getSelectedText())
                        : text;
                    codeEditor.insert(selected); // overwrites selected text
                    return context;
                }

                // no editor
                _getFieldEditor().replaceSelection(text);
                return context;
            };

            /**
             * Set checkbox values by their ID. For example, mark the edit as minor and watch the page with context.options({ minor: true, watch: true }).
             * @param {object} values An object representing the checkboxes to set, where the key is their ID and the value is the boolean value. The key may also be one of [minor, watch], which will be mapped to the correct ID.
             */
            context.options = function(values) {
                // validate
                if (!$.isPlainObject(values))
                    return _warn("options(...) ignored because no valid argument was given");

                // set values
                $.each(values, function(id, value) {
                    // map aliases
                    id = { minor: "wpMinoredit", watch: "wpWatchthis" }[id] || id;

                    // set element
                    var element = $("#" + id);
                    if (!element.is('input[type="checkbox"]'))
                        return _warn("options({" + id + ": " + value + "}) ignored because there's no valid checkbox with that ID");
                    element.prop("checked", value);
                });

                return context;
            };

            /*****
            ** Editing pages
            *****/
            /**
             * Append text to the edit summary (with a ', ' separator) if editing a page.
             * @param {string} summary The edit summary.
             * @returns The helper instance for chaining.
             */
            context.appendEditSummary = function(summary) {
                var editor = context.forField(state.$editSummary);
                var text = editor.get().replace(/\s+$/, ""); // get text without trailing whitespace

                if (text.match(/\*\/$/))
                    editor.set(text + " " + summary); // "/* section */ reason"
                else if (text.length)
                    editor.set(text + ", " + summary); // old summary, new summary
                else
                    editor.set(summary); // new summary

                return context;
            };

            /**
             * Overwrite the edit summary if editing a page.
             * @param {string} summary The edit summary.
             * @returns The helper instance for chaining.
             */
            context.setEditSummary = function(summary) {
                context.forField(state.$editSummary).set(summary);
                return context;
            };

            /**
             * Click the 'show changes' button if editing a page.
             */
            context.clickDiff = function() {
                $("#wpDiff").click();
            };

            /**
             * Click the 'show preview' button if editing a page.
             */
            context.clickPreview = function() {
                $("#wpPreview").click();
            };

            return context;
        })();


        /*********
        ** Default plugins
        *********/
        /***
        ** Renderers create the UI which the user clicks to activate a template.
        ** These are simply functions that accept a template object, add the UI to the page, and return a jQuery reference to the created entry.
        ***/
        /**
         * Get a rendering plugin that generates the default sidebar UI. This function returns a generated function matching the expected plugin interface.
         */
        var _getSidebarRenderer = function() {
            /*********
            ** Private methods
            *********/
            /**
             * Add a navigation menu portlet to the sidebar.
             * @param {string} id The unique portlet ID.
             * @param {string} name The display name displayed in the portlet header.
             */
            var _addPortlet = function(id, name) {
                // copy the portlet structure for the current skin
                var sidebar = $("#p-tb").clone();

                // adjust content
                sidebar.attr({ id: id, 'aria-labelledby': id + "-label" });
                sidebar.find("#p-tb-label, h1, h2, h3, h4, h5").first().text(name).attr({ id: id + "-label" });
                sidebar.find("ul").empty();

                // add to DOM
                $("#p-tb").parent().append(sidebar);
                return sidebar;
            };

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
            var _addPortletLink = function(portletID, text, id, tooltip, accessKey, target) {
                // create link
                var isCallback = $.isFunction(target);
                var uri = isCallback ? "#" : target;
                var link = $(mw.util.addPortletLink(portletID, uri, text, id, tooltip || ""));
                if (isCallback)
                    link.click(function(e) { e.preventDefault(); target(e); });

                // add access key
                if (accessKey) {
                    // steal access key if needed
                    var previousTarget = $('[accesskey="' + accessKey.replace('"', '\\"') + '"]');
                    if (previousTarget.length) {
                        _warn("overwrote access key [" + accessKey + '] previously assigned to "' + previousTarget.text() + '".');
                        previousTarget.removeAttr("accesskey");
                    }

                    // set key
                    link.find("a:first").attr("accesskey", accessKey);
                }

                return link;
            };

            /**
             * Add a [[Special:TemplateScript]] link to a sidebar containing scripts from a library.
             * @param {string} portletID The unique navigation portlet ID.
             */
            var _addLibrarySettingsLink = function(portletID) {
                // get sidebar
                var sidebar = $("#" + portletID);
                if (sidebar.hasClass("ts-library-portlet"))
                    return;

                // add link
                sidebar.addClass("ts-library-portlet");
                sidebar.find("h1, h2, h3, h4, h5").first().append([
                    " ",
                    $("<a></a>", { href: "/wiki/Special:TemplateScript", html: "&#9965;", target: "_blank" })
                ]);
            };


            /*********
            ** Plugin method
            *********/
            /**
             * Add a sidebar entry for a template.
             * @param {Template} template The template for which to create an entry.
             * @param {TemplateScript} instance The script instance.
             * @returns the generated item.
             */
            return function(template, instance) {
                // build the sidebar
                var category = template.category;
                if (!(category in state.sidebars)) {
                    var id = state.sidebars[category] = "p-templatescript-" + state.sidebarCount;
                    _addPortlet(id, category);
                    ++state.sidebarCount;
                }
                var sidebarID = state.sidebars[category];

                // add link
                var $item = _addPortletLink(sidebarID, template.name, "ts-link-" + template.id, template.tooltip, template.accessKey, function() { instance.apply(template.id); });
                if (template.accessKey) {
                    $item.append(
                        $("<small>")
                            .addClass("ts-shortcut")
                            .append(template.accessKey)
                    );
                }

                // add library setting link
                if (template.fromLibrary)
                    _addLibrarySettingsLink(sidebarID);
                return $item;
            };
        };


        /*********
        ** Private methods
        *********/
        /**
         * Bootstrap TemplateScript and hook into the UI. This method should only be called once the DOM is ready.
         */
        var _initialise = function() {
            if (state.isInited)
                return;

            // init context
            state.isInited = true;
            state.$target = $("#wpTextbox1, #wpReason, #wpComment, #mwProtect-reason, #mw-bi-reason").first();
            state.$editSummary = $("#wpSummary:first");

            // init plugins
            self.addRenderer("sidebar", _getSidebarRenderer());

            // init UI
            mw.util.addCSS(".ts-shortcut { margin-left:.5em; color:#CCC; }");
            _loadDependency("//tools-static.wmflabs.org/meta/scripts/pathoschild.util.js").then(function() {
                state.isReady = true;
                for (var i = 0; i < state.queue.length; i++)
                    self.add(state.queue[i]);
            });

            // initialise settings UI
            _updateSettingsView();
            $("a.new").filter('[title^="' + mw.config.get("wgFormattedNamespaces")[-1] + ':TemplateScript"]').removeClass("new"); // unredlink [[Special:TemplateScript]]
        };

        /**
         * Asynchronously load a script and cache it.
         * @param {string} url The URL of the script to load.
         * @returns Returns a promise completed when the script has been fetched.
         */
        var _loadDependency = function(url) {
            if (!state.dependencies[url])
                state.dependencies[url] = $.ajax(url, { cache: true, dataType: "script" });
            return state.dependencies[url];
        };

        /**
         * Write a warning to the debug console, if it's available.
         * @param {string} message The warning message to write.
         */
        var _warn = function(message) {
            if (console && console.log)
                console.log("[TemplateScript] " + message);
        };

        /**
         * Create a tool link that triggers the template.
         * @param {Template} template The template for which to create an entry.
         */
        var _renderEntry = function(template) {
            // get renderer
            var rendererKey = template.renderer;
            if (!(rendererKey in state.renderers)) {
                _warn('couldn\'t add tool "' + template.name + '": there\'s no "' + rendererKey + '" renderer');
                return $();
            }
            var renderer = state.renderers[rendererKey];

            // render entry
            return renderer(template, self);
        };

        /*
         * Check whether the value is equal to the scalar haystack or in the array haystack.
         * @param {Object} value The search value.
         * @param {Object | Object[]} haystack The object to compare against, or array to search.
         * @returns {boolean} Returns whether the value is equal to or in the haystack.
         */
        var _isEqualOrIn = function(value, haystack) {
            if ($.isArray(haystack))
                return $.inArray(value, haystack) !== -1;
            return value === haystack;
        };

        /**
         * Normalise a template to provide a consistent representation, and throw an error message if the template is invalid.
         * @param {Template} opts The template to normalise.
         */
        var _normalise = function(opts) {
            // validate required fields
            if (!opts.name)
                throw "must have a name";
            if (opts.script && !$.isFunction(opts.script))
                throw "script must be a function";
            if (!opts.template && !opts.script)
                throw "must have either a template or a script";

            // normalise schema
            opts = pathoschild.util.ApplyArgumentSchema("pathoschild.TemplateScript::add(name:" + (opts.name || "unnamed") + ")", opts, self.Template);
            opts.position = pathoschild.util.ApplyEnumeration("Position", opts.position, self.Position);
            opts.editSummaryPosition = pathoschild.util.ApplyEnumeration("Position", opts.editSummaryPosition, self.Position);
            opts.headlinePosition = pathoschild.util.ApplyEnumeration("Position", opts.headlinePosition, self.Position);

            // normalise script URL
            if (opts.scriptUrl && !opts.scriptUrl.match(/^(?:http:|https:)?\/\//))
                opts.scriptUrl = mw.config.get("wgServer") + mw.config.get("wgScriptPath") + "/index.php?title=" + encodeURIComponent(opts.scriptUrl) + "&action=raw&ctype=text/javascript";

            // normalise actions
            if (opts.forActions) {
                // cast to array
                if (!$.isArray(opts.forActions))
                    opts.forActions = [opts.forActions];

                // normalise values
                opts.forActions = $.map(opts.forActions, function(value) { return value.toLowerCase(); });
            }
            else
                opts.forActions = ["*"];

            // normalise namespaces
            if (opts.forNamespaces) {
                // cast to array
                if (!$.isArray(opts.forNamespaces))
                    opts.forNamespaces = [opts.forNamespaces];

                // normalise values
                opts.forNamespaces = $.map(opts.forNamespaces, function(value) {
                    // *
                    if (value === "*")
                        return "*";

                    // parse numeric value
                    var numeric = parseInt(value, 10);
                    if (!isNaN(numeric))
                        return numeric;

                    // convert namespace names
                    var key = value.toLowerCase().replace(/ /g, "_");
                    numeric = mw.config.get("wgNamespaceIds")[key];
                    if (numeric || numeric === 0)
                        return numeric;

                    // invalid value
                    _warn('ignored unknown namespace "' + value + '"');
                    return null;
                });
            }
            else
                opts.forNamespaces = ["*"];

            // normalise defaults
            opts.category = opts.category || self.strings.defaultHeaderText;
            opts.position = opts.position || (self.Context.action === "edit" ? "cursor" : "replace");
            opts.editSummaryPosition = opts.editSummaryPosition || "replace";
            opts.headlinePosition = opts.headlinePosition || "replace";
            opts.renderer = opts.renderer || "sidebar";
        };

        /**
         * Insert text at the specified position using a field editor. This should only be used to
         * map template options to the underlying editor.
         * @param {Context|object} editor The field editor, either Context or the object returned by Context.forField(...).
         * @param {string} text The text to insert.
         * @param {Position} position The position at which to insert the text.
         */
        function _insert(editor, text, position) {
            switch (position) {
                case self.Position.before:
                    editor.prepend(text);
                    break;

                case self.Position.after:
                    editor.append(text);
                    break;

                case self.Position.replace:
                    editor.set(text);
                    break;

                case self.Position.cursor:
                    editor.replaceSelection(text);
                    break;

                default:
                    _warn('can\'t insert text: unknown position "' + position + '"');
                    return;
            }
        }

        /**
         * Load or reload the TemplateScript library settings view if the current page is [[Special:TemplateScript]]. This replaces the current page content.
         */
        var _updateSettingsView = function() {
            if (mw.config.get("wgCanonicalNamespace") !== "Special" || mw.config.get("wgTitle").toLowerCase() !== "templatescript")
                return;

            // initialise UI
            $("title:first, #firstHeading").text("TemplateScript settings");
            $("#mw-content-text").html("↻ loading...");

            // fetch dependencies
            $.when(
                mw.loader.using(["mediawiki.api.options"]),
                _loadDependency("//tools-static.wmflabs.org/cdnjs/ajax/libs/handlebars.js/4.0.2/handlebars.js")
            )

                // render template
                .then(function() {
                    return $.ajax("//tools-static.wmflabs.org/meta/scripts/templates/pathoschild.templatescript.settings.htm", { dataType: "html" });
                })
                .then(function(template) {
                    // fetch settings
                    var settings = self.library.getSettings();

                    // generate view
                    Handlebars.registerHelper("optional", function(condition, str) { return condition ? str : ""; });
                    Handlebars.registerHelper("counter", function(index, startFrom) { return startFrom + index; });
                    template = Handlebars.compile(template);
                    var html = template({ username: mw.config.get("wgUserName"), libraries: state.libraries });

                    // load view
                    $("#mw-content-text").html(html);

                    // save settings on change
                    $('#mw-content-text input[type="checkbox"]').click(function() {
                        var key = this.name;
                        var enabled = this.checked;

                        if (enabled)
                            delete settings[key];
                        else
                            settings[key] = false;

                        self.library.saveSettings(settings);
                    });
                });
        };


        /*********
        ** Public methods
        *********/
        /*****
        ** Interface
        *****/
        /**
         * Add templates to the sidebar menu.
         * @param {Template | Template[]} opts The template(s) to add.
         * @param {Template} common A set of fields to apply to all templates in the given list.
         */
        self.add = function(opts, common) {
            // handle multiple templates
            if ($.isArray(opts)) {
                for (var t = 0; t < opts.length; t++)
                    self.add(opts[t], common);
                return;
            }

            // apply common fields
            if (common)
                $.extend(opts, common);

            // queue if DOM isn't ready
            if (!state.isReady) {
                state.queue.push(opts);
                return;
            }

            // normalise options
            try {
                _normalise(opts);
            }
            catch (error) {
                _warn('template "' + (opts && opts.name || "unnamed") + '" couldn\'t be normalised: ' + error);
                return; // invalid template
            }

            // add template
            if (self.isEnabled(opts)) {
                // add to UI
                opts.id = state.templates.push(opts) - 1;
                var $entry = _renderEntry(opts);

                /* load dependency */
                if (opts.scriptUrl) {
                    $entry.hide();
                    _loadDependency(opts.scriptUrl).then(function() { $entry.show(); });
                }
            }
        };

        /**
         * Contains methods for managing script libraries. A library is a collection of scripts imported by the user, who can choose which scripts are enabled through [[Special:TemplateScript]].
         */
        self.library = {
            /**
             * Define a library.
             * @param {Library} library The library configuration.
             */
            define: function(library) {
                _loadDependency("//tools-static.wmflabs.org/meta/scripts/pathoschild.util.js").then(function() {
                    // validate library
                    library = pathoschild.util.ApplyArgumentSchema("pathoschild.TemplateScript.library::define(key:" + (library.key || "no key") + ")", library, self.Library);
                    if (!library.key)
                        return _warn("can't add library: it doesn't define a key");
                    if (!library.name)
                        return _warn("can't add library '" + library.key + "': it doesn't define a name");
                    if (!library.categories || !library.categories.length)
                        return _warn("can't add library '" + library.key + "': it doesn't contain any scripts");

                    // preprocess scripts
                    var settings = self.library.getSettings();
                    var scripts = [];
                    $.each(library.categories, function(c, category) {
                        // validate
                        if (!category.scripts)
                            return _warn("can't add category '" + category + "' from library '" + library.key + "': there are no scripts defined");

                        $.each(category.scripts, function(s, script) {
                            var key = library.key + "\\" + script.key;

                            // validate
                            if (!script.key)
                                return _warn("can't add script '" + script.name + "' from library '" + library.key + "': it doesn\t define a key");

                            // apply overrides
                            script = $.extend({}, script, state.customLibrarySettings[key]);

                            // normalise
                            script.category = category.name;
                            script.key = key;
                            script.enabled = settings[script.key] !== false && (library.defaultEnabled || !!settings[script.key]);
                            script.fromLibrary = true;

                            scripts.push(script);
                        });
                    });

                    // load library
                    state.libraries.push(library);
                    self.add(scripts);

                    // reload settings view if shown
                    _updateSettingsView();
                });
            },

            /**
             * Get the user's saved library settings.
             */
            getSettings: function() {
                try {
                    var options = mw.user.options.get("userjs-ts-libraries") || "{}";
                    return JSON.parse(options);
                }
                catch (err) {
                    _warn('ignored saved settings due to parse error: "' + err + "'.");
                    return {};
                }
            },

            /**
             * Save the user's library settings.
             * @param {object} settings The settings to save.
             */
            saveSettings: function(settings) {
                new mw.Api().saveOptions({ 'userjs-ts-libraries': JSON.stringify(settings) });
            },

            /**
             * Override the configuration for an imported template or script.
             * @param {string} library The unique key of the library.
             * @param {string} key The unique key of the template or script.
             * @param {Template} settings The settings to apply. This should match the normal Template fields, with each field overwriting the corresponding field of the imported script.
             */
            override: function(library, key, settings) {
                state.customLibrarySettings[library + "\\" + key] = settings;
            }
        };

        /**
         * Add a plugin responsible for creating the link UI that activates a template. You can add multiple renderers, and choose how each template is rendered by adding "renderer: rendererKey" to its options.
         * @param {string} key The unique key for the renderer.
         * @param {function} renderer The function will accepts a template object, and returns a jQuery reference to the created entry.
         */
        self.addRenderer = function(key, renderer) {
            if (key in state.renderers) {
                _warn('can\'t add renderer "' + key + '", there\'s already a renderer with that name');
                return;
            }
            state.renderers[key] = renderer;
        };

        /**
         * Apply a template to the form.
         * @param {int} id The identifier of the template to insert, as returned by Add().
         */
        self.apply = function(id) {
            // validate
            if (!(id in state.templates))
                return _warn("can't apply template #" + id + " because there's no template with that ID; there's something wrong with TemplateScript's internal state");
            if (!state.$target.length)
                return _warn("can't apply template because the current page has no recognisable form.");

            // get template data
            var editor = self.Context;
            var opts = state.templates[id];

            // apply template
            var isSectionNew = editor.action === "edit" && $("#wpTextbox1, #wpSummary").first().attr("id") === "wpSummary"; // if #wpSummary is first, it's not the edit summary (MediaWiki reuses the ID)
            if (opts.template)
                _insert(editor, opts.template, opts.position);
            if (opts.editSummary && !isSectionNew)
                _insert(editor.forField(state.$editSummary), opts.editSummary, opts.editSummaryPosition);
            if (opts.headline && isSectionNew)
                _insert(editor.forField(state.$editSummary), opts.headline, opts.headlinePosition);
            if (opts.isMinorEdit)
                editor.options({ minor: true });

            /* invoke script */
            if (opts.script)
                opts.script(editor);
        };

        /**
         * Check whether the template is enabled for the current page context, based on its for* condition properties. This
         * method also accepts an arbitrary object which exposes the for* property names from the Template interface.
         * @param {Template | object} template
         * @returns {boolean} Returns true if all for* conditions were met, or no conditions were found; else false.
         */
        self.isEnabled = function(template) {
            /* check enabled flag */
            if ("enabled" in template && template.enabled !== null && !template.enabled)
                return false;

            /* match context values */
            var context = self.Context;
            if ($.inArray("*", template.forNamespaces) === -1 && !_isEqualOrIn(mw.config.get("wgNamespaceNumber"), template.forNamespaces))
                return false;
            if ($.inArray("*", template.forActions) === -1 && !_isEqualOrIn(context.action, template.forActions))
                return false;

            return true;
        };


        /*****
        ** Bootstrap TemplateScript
        *****/
        // init localisation
        if (pathoschild.i18n && pathoschild.i18n.templatescript)
            $.extend(self.strings, pathoschild.i18n.templatescript);

        // init regex editor
        if (state.config.regexEditor !== false) {
            self.add({
                name: self.strings.regexEditor,
                scriptUrl: "//tools-static.wmflabs.org/meta/scripts/pathoschild.regexeditor.js",
                script: function(editor) {
                    var regexEditor = new pathoschild.RegexEditor();
                    regexEditor.create(state.$target, editor);
                }
            });
        }

        // init TemplateScript
        $.when($.ready, mw.loader.using(["mediawiki.api", "mediawiki.util"])).done(_initialise);
        return self;
    })();
}());