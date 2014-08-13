/*


TemplateScript adds a menu of configurable templates and scripts to the sidebar.
For more information, see <https://github.com/Pathoschild/Wikimedia-contrib#readme>.


*/
/// <reference path="pathoschild.util.js" />
var pathoschild = pathoschild || {};
(function() {
	'use strict';

	if (pathoschild.TemplateScript)
		return; // already initialized, don't overwrite

	/*********
	** TemplateScript
	*********/
	/**
	 * Singleton responsible for handling user-defined templates available through a sidebar menu.
	 * @author Pathoschild
	 * @class
	 * @property {Template[]} _templates The registered templates.
	 * @property {string} _defaultHeaderText The sidebar header text label for the default group.
	 * @property {Object} _menus A hash of menu references indexed by name.
	 * @property {int} _menuCount The number of registered menus (excluding the default menu).
	 * @property {boolean} _isReady Whether TemplateScript has been initialized and hooked into the DOM.
	 * @property {string} _revision The unique revision number, for debug purposes.
	 * @property {array} _dependencies An internal lookup used to manage asynchronous dependencies.
	 */
	pathoschild.TemplateScript = {
		_version: '0.9.15-alpha',

		/*********
		** Objects
		*********/
		/**
		 * Represents an insertable template schema.
		 * @property {string} name The name displayed as the sidebar link text.
		 * @property {boolean} enabled Whether this template is available.
		 * @property {string} category An arbitrary category name (for grouping templates into multiple sidebars), or null to use the default sidebar.
		 * @property {string[]} forActions The wgAction values for which the template is enabled, or null to enable for all actions.
		 * @property {int[]} forNamespaces The namespaces in which the template is enabled, or null to enable in all namespaces.
		 *
		 * @property {string} template The template text to insert.
		 * @property {string} position The position at which to insert the template, matching a {pathoschild.TemplateScript.Position} value. The default value is 'cursor' when editing a page, and 'replace' in all other cases.
		 * @property {string} editSummary The edit summary to use (if applicable).
		 * @property {string} editSummaryPosition The position at which to insert the edit summary, matching a {pathoschild.TemplateScript.Position} value. The default value is 'replace'.
		 * @property {string} headline The subject or headline summary to use (if applicable). This appears when editing a page with &section=new in the URL.
		 * @property {string} headlinePosition The position at which to insert the headline, matching a {pathoschild.TemplateScript.Position} value. The default value is 'replace'.
		 * @property {boolean} isMinorEdit Whether to mark the edit as minor (if applicable).
		 *
		 * @property {boolean} autoSubmit Whether to submit the form automatically after insertion.
		 * @property {function} script An arbitrary JavaScript function that is called after the template and edit summary are applied, but before autoSubmit is applied (if true). It is passed a reference to the context object.
		 *
		 * @property {int} id The internal template ID. (Modifying this value may cause unexpected behaviour.)
		 * @class
		 */
		Template: {
			/* UI options */
			name: null,
			enabled: true,
			category: null,
			forActions: null,
			forNamespaces: null,

			/* template options */
			template: null,
			position: 'cursor',
			editSummary: null,
			editSummaryPosition: 'after',
			headline: null,
			headlinePosition: 'after',
			isMinorEdit: false,

			/* script options */
			autoSubmit: false,
			script: null,

			/* internal */
			id: null
		},

		/**
		 * Represents a text insertion method.
		 * @enum {string}
		 * @property {string} before Insert before the text.
		 * @property {string} after Insert after the text.
		 * @property {string} cursor Insert the template at the current cursor position (replacing any selected text).
		 * @property {string} replace Replace the current text entirely.
		 */
		Position: {
			before: 'before',
			after: 'after',
			cursor: 'cursor',
			replace: 'replace'
		},

		/**
		 * Provides convenient access to singleton properties about the current page. (Changing the values may cause unexpected behaviour.)
		 * @property {int} namespace The number of the current MediaWiki namespace.
		 * @property {string} action The string representing the current MediaWiki action.
		 * @property {pathoschild.TemplateScript} singleton The TemplateScript instance for the page.
		 * @property {jQuery} $target The primary input element (e.g., the edit textarea) for the current form.
		 * @property {jQuery} $editSummary The edit summary input element (if relevant to the current form).
		 */
		Context: {
			namespace: mw.config.get('wgNamespaceNumber'),
			action: (mw.config.get('wgAction') === 'submit'
			? 'edit'
			: (mw.config.get('wgCanonicalSpecialPageName') === 'Blockip'
				? 'block'
				: mw.config.get('wgAction')
			)
		),
			isSectionNew: $('#wpTextbox1, #wpSummary').first().attr('id') === 'wpSummary', // if #wpSummary is first, it's not the edit summary (MediaWiki reused ID)
			singleton: null,
			$target: null,
			$editSummary: null
		},

		/*********
		** Properties
		*********/
		_isReady: false,
		_templates: [],
		_defaultHeaderText: 'TemplateScript',
		_menus: {},
		_menuCount: 0,
		_queue: [],

		/*********
		** Private methods
		*********/
		/**
		 * Asynchronously load a script and invoke the callback when loaded.
		 * @param {string} url The URL of the script to load.
		 * @param {bool} test Indicates whether the dependency is already loaded.
		 * @param {function} callback The method to invoke (with no arguments) when the dependencies have been loaded.
		 */
		_LoadDependency: function(url, test, callback) {
			var invokeCallback = function() { callback.call(pathoschild.TemplateScript); };
			if (test)
				invokeCallback();
			else
				$.ajax({ url:url, dataType:'script', crossDomain:true, cached:true, success:invokeCallback });
		},

		/**
		 * Initialize the template script.
		 */
		_Initialize: function() {
			if (this.Context.singleton)
				return;

			// initialize
			this.Context.singleton = this;
			this.Context.$target = $('#wpTextbox1, #wpReason, #wpComment, #mwProtect-reason, #mw-bi-reason').first();
			this.Context.$editSummary = $('#wpSummary:first');

			// load utilities & hook into page
			this._LoadDependency('//tools.wmflabs.org/meta/scripts/pathoschild.util.js', pathoschild.util, function() {
				this._isReady = true;
				for (var i = 0; i < this._queue.length; i++)
					this.Add(this._queue[i]);
			});
		},

		/**
		 * Get the unique ID for a TemplateScript sidebar portlet, creating it if necessary.
		 * @param {string} [name=null] The display name of the header to retrieve, or null to get the default sidebar.
		 * @returns {string} Returns the unique ID of the sidebar.
		 * @private
		 */
		_GetSidebar: function(name) {
			// set default text
			if (name === null || typeof(name) === typeof(undefined))
				name = this._defaultHeaderText;

			// create menu if missing
			if (!(name in this._menus)) {
				var id = this._menus[name] = 'p-templatescript-' + this._menuCount;
				pathoschild.util.mediawiki.AddPortlet(id, name);
				++this._menuCount;
			}

			/* return menu ID */
			return this._menus[name];
		},

		/**
		 * Create a link in the sidebar that triggers the template.
		 * @param {pathoschild.TemplateScript.Template} template The template for which to create an entry.
		 */
		_CreateSidebarEntry: function(template) {
			var id = this._GetSidebar(template.category);
			pathoschild.util.mediawiki.AddPortletLink(id, template.name, function() { pathoschild.TemplateScript.Apply(template.id); });
		},

		/*
		 * Check whether the value is equal to the scalar haystack or in the array haystack.
		 * @param {Object} value The search value.
		 * @param {Object | Object[]} haystack The object to compare against, or array to search.
		 * @returns {boolean} Returns whether the value is equal to or in the haystack.
		 */
		_IsEqualOrIn: function(value, haystack) {
			if ($.isArray(haystack))
				return $.inArray(value, haystack) !== -1;
			return value === haystack;
		},


		/*********
		** Public methods
		*********/
		/*****
		** Interface
		*****/
		/**
		 * Add templates to the sidebar menu.
		 * @param {pathoschild.TemplateScript.Template | pathoschild.TemplateScript.Template[]} opts The template(s) to add.
		 */
		Add: function(opts) {
			if (!this._isReady) {
				this._queue.push(opts);
				return;
			}

			var log = function(message) {
				opts = opts || {};
				pathoschild.util.Log('pathoschild.TemplateScript::Add(name:"' + (opts.name || 'unnamed') + '"): ' + message);
			};

			/* handle multiple templates */
			if ($.isArray(opts)) {
				for (var t = 0; t < opts.length; t++)
					this.Add(opts[t]);
				return;
			}

			/* normalize option types */
			try {
				opts = pathoschild.util.ApplyArgumentSchema('pathoschild.TemplateScript::Add(name:' + (opts.name || 'unnamed') + ')', opts, this.Template);
				opts.position = pathoschild.util.ApplyEnumeration('Position', opts.position, pathoschild.TemplateScript.Position);
				opts.editSummaryPosition = pathoschild.util.ApplyEnumeration('Position', opts.editSummaryPosition, pathoschild.TemplateScript.Position);
				opts.headlinePosition = pathoschild.util.ApplyEnumeration('Position', opts.headlinePosition, pathoschild.TemplateScript.Position);
			}
			catch (err) {
				return log('normalization error: ' + err);
			}

			/* validate */
			if (opts.script && !$.isFunction(opts.script)) {
				log('ignoring non-function value passed to "script" option: ' + opts.script);
				delete opts.script;
			}
			if (!opts.name)
				return log('template must have a name');
			if (!opts.template && !opts.script)
				return log('template must have either a template or a script.');
			if (!pathoschild.TemplateScript.IsEnabled(opts))
				return;

			/* set defaults */
			if (!opts.position)
				opts.position = (pathoschild.TemplateScript.Context.action === 'edit' ? 'cursor' : 'replace');
			if (!opts.editSummaryPosition)
				opts.editSummaryPosition = 'replace';
			if (!opts.headlinePosition)
				opts.headlinePosition = 'replace';

			/* add template */
			opts.id = this._templates.push(opts) - 1;
			this._CreateSidebarEntry(opts);
		},

		/**
		 * Add templates to the sidebar menu.
		 * @property {pathoschild.TemplateScript.Template} fields A Template-like object containing fields to merge into the templates.
		 * @param {pathoschild.TemplateScript.Template | pathoschild.TemplateScript.Template[]} templates The template(s) to add.
		 * @return {int} Returns the identifier of the added template (or the last added template if given an array), or -1 if the template could not be added.
		 */
		AddWith: function(fields, templates) {
			/* merge templates */
			if (!$.isArray(templates))
				templates = [templates];

			for (var i in templates) {
				for (var attr in fields)
					templates[i][attr] = fields[attr];
			}

			/* add templates */
			this.Add(templates);
		},

		/**
		 * Apply a template to the form.
		 * @param {int} id The identifier of the template to insert, as returned by Add().
		 */
		Apply: function(id) {
			/* get template */
			if (!(id in this._templates)) {
				pathoschild.util.Log('pathoschild.TemplateScript::Apply() failed, there is no template with ID "' + id + '".');
				return;
			}
			var opts = this._templates[id];

			/* validate target input box */
			if (!this.Context.$target.length) {
				pathoschild.util.Log('pathoschild.TemplateScript::Apply() failed, no recognized form found.');
				return;
			}

			/* insert template */
			if (opts.template) {
				this.InsertLiteral(this.Context.$target, opts.template, opts.position);
			}
			if (opts.editSummary && !this.Context.isSectionNew) {
				this.InsertLiteral(this.Context.$editSummary, opts.editSummary, opts.editSummaryPosition);
			}
			if (opts.headline && this.Context.isSectionNew) {
				this.InsertLiteral(this.Context.$editSummary, opts.headline, opts.headlinePosition);
			}
			if (opts.isMinorEdit) {
				$('#wpMinoredit').attr('checked', 'checked');
			}

			/* invoke script */
			if (opts.script) {
				opts.script(this.Context);
			}

			/* perform auto-submission */
			if (opts.autoSubmit) {
				this.Context.$target.parents('form').first().submit();
			}
		},

		/**
		 * Check whether the template is enabled for the current page context, based on its for* condition properties. This
		 * method also accepts an arbitrary object which exposes the for* property names from the Template interface.
		 * @param {pathoschild.TemplateScript.Template | Object} template
		 * @returns {boolean} Returns true if all for* conditions were met, or no conditions were found; else false.
		 */
		IsEnabled: function(template) {
			/* check enabled flag */
			if ('enabled' in template && template.enabled !== null && !template.enabled) {
				return false;
			}

			/* match context values */
			var context = pathoschild.TemplateScript.Context;
			var is = pathoschild.TemplateScript._IsEqualOrIn;
			if ('forNamespaces' in template && template.forNamespaces !== null && !is(context.namespace, template.forNamespaces)) {
				return false;
			}
			if ('forActions' in template && template.forActions !== null && !is(context.action, template.forActions)) {
				return false;
			}

			return true;
		},

		/**
		 * Set the header text for the default sidebar text.
		 * @param {string} text The text to use as the sidebar text.
		 */
		SetDefaultGroupHeader: function(text) {
			var id = this._GetSidebar();

			this._defaultHeaderText = text;
			this._menus[text] = id;
			$('#' + id + ' h5').text(text);
		},

		/*****
		** Framework
		*****/
		/**
		 * Insert a literal text into a field.
		 * @param {jQuery} $target The field into which to insert the template.
		 * @param {string} text The template text to insert, with template format values preparsed.
		 * @param {string} position The insertion position, matching a {pathoschild.TemplateScript.Position} value.
		 */
		InsertLiteral: function($target, text, position) {
			/* validate */
			if (!$target || !$target.length || !text || !text.length) {
				return; // nothing to do
			}
			try {
				position = pathoschild.util.ApplyEnumeration('Position', position, pathoschild.TemplateScript.Position);
			}
			catch (err) {
				pathoschild.util.Log('TemplateScript: InsertLiteral failed, discarding literal: ' + err);
			}

			/* perform insertion */
			switch (position) {
				case this.Position.before:
					$target.val(text + $target.val());
					break;

				case this.Position.after:
					$target.val($target.val() + text);
					break;

				case this.Position.replace:
					$target.val(text);
					break;

				case this.Position.cursor:
					var box = $target.get(0);
					box.focus();

					/* most browsers */
					if (box.selectionStart || box.selectionStart === '0') {
						var startPos = box.selectionStart;
						var endPos = box.selectionEnd;
						var scrollTop = box.scrollTop;

						box.value = box.value.substring(0, startPos) + text + box.value.substring(endPos, box.value.length);
						box.focus();

						box.selectionStart = startPos + text.length;
						box.selectionEnd = startPos + text.length;
						box.scrollTop = scrollTop;
					}

						/* Internet Explorer */
					else if (document.selection) {
						var selection = document.selection.createRange();
						selection.text = text;
						box.focus();
					}

							/* Unknown implementation */
					else {
						pathoschild.util.Log('TemplateScript: unknown browser cursor selection implementation, appending instead.');
						box.value += text;
						return;
					}
					break;

				default:
					pathoschild.util.Log('TemplateScript: insertion failed, unknown position "' + position + '".');
					return;
			}
		}
	};

	// initialize menu (and wait for Vector if needed)
	var init = function() { pathoschild.TemplateScript._Initialize(); };
	var vectorModules = mw.config.get('wgVectorEnabledModules');
	if (vectorModules && vectorModules.collapsiblenav)
		mw.loader.using(['ext.vector.collapsibleNav'], function() { $(init); });
	else
		$(init);

	pathoschild.TemplateScript.Add({
		name: 'Regex editor',
		script: function(context) {
			pathoschild.TemplateScript._LoadDependency('//tools.wmflabs.org/meta/scripts/pathoschild.regexeditor.js', pathoschild.RegexEditor, function() {
				pathoschild.RegexEditor.Create(context.$target);
			});
		},
		forActions: 'edit'
	});
}());
