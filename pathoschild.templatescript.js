/*


TemplateScript adds a menu of configurable templates and scripts to the sidebar.
For more information, see <https://github.com/Pathoschild/Wikimedia-contrib#readme>.


*/
/*jshint bitwise:true, eqeqeq:true, forin:false, immed:true, latedef:true, loopfunc:true, noarg:true, noempty:true, nonew:true, smarttabs:true, strict:true, trailing:true, undef:true*/
/*global $:true, mw:true, pathoschild:true*/
$.getScript('https://raw.github.com/pathoschild/wikimedia-contrib/master/pathoschild.util.js?v=0.9.8', function () {
	"use strict";
	if (pathoschild.TemplateScript) {
		return; // already initialized, don't overwrite
	}

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
	 * @property {boolean} _isInitialized Whether the singleton has been initialized and hooked into the DOM.
	 * @property {string} _revision The unique revision number, for debug purposes.
	 */
	pathoschild.TemplateScript = {
		_version: '0.9.8-alpha',

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
		_isInitialized: false,
		_templates: [],
		_defaultHeaderText: 'TemplateScript',
		_menus: {},
		_menuCount: 0,

		/*********
		** Private methods
		*********/
		/**
		 * Initialize the template script.
		 */
		_Initialize: function () {
			var _this = pathoschild.TemplateScript;

			_this.Context.singleton = _this;
			_this.Context.$target = $('#wpTextbox1, #wpReason, #wpComment, #mwProtect-reason, #mw-bi-reason').first();
			_this.Context.$editSummary = $('#wpSummary:first');

			for (var t = 0; t < _this._templates.length; t++) {
				_this._CreateSidebarEntry(_this._templates[t]);
			}
			_this._isInitialized = true;
		},

		/**
		 * Get the unique ID for a TemplateScript sidebar portlet, creating it if necessary.
		 * @param {string} [name=null] The display name of the header to retrieve, or null to get the default sidebar.
		 * @returns {string} Returns the unique ID of the sidebar.
		 * @private
		 */
		_GetSidebar: function (name) {
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
		_CreateSidebarEntry: function (template) {
			var id = this._GetSidebar(template.category);
			pathoschild.util.mediawiki.AddPortletLink(id, template.name, function () { pathoschild.TemplateScript.Apply(template.id); });
		},

		/*
		 * Check whether the value is equal to the scalar haystack or in the array haystack.
		 * @param {Object} value The search value.
		 * @param {Object | Object[]} haystack The object to compare against, or array to search.
		 * @returns {boolean} Returns whether the value is equal to or in the haystack.
		 */
		_IsEqualOrIn: function (value, haystack) {
			if ($.isArray(haystack)) {
				return $.inArray(value, haystack) !== -1;
			}
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
		 * @return {int} Returns the identifier of the added template (or the last added template if given an array), or -1 if the template could not be added.
		 */
		Add: function (opts) {
			/* handle multiple templates */
			if ($.isArray(opts)) {
				var id = -1;
				for (var t = 0; t < opts.length; t++) {
					id = this.Add(opts[t]);
				}
				return id;
			}

			/* normalize option types */
			try {
				opts = pathoschild.util.ApplyArgumentSchema("AddTemplate", opts, this.Template);
				opts.position = pathoschild.util.ApplyEnumeration('Position', opts.position, pathoschild.TemplateScript.Position);
				opts.editSummaryPosition = pathoschild.util.ApplyEnumeration('Position', opts.editSummaryPosition, pathoschild.TemplateScript.Position);
				opts.headlinePosition = pathoschild.util.ApplyEnumeration('Position', opts.headlinePosition, pathoschild.TemplateScript.Position);
			}
			catch (err) {
				pathoschild.util.Log('TemplateScript::Add() failed: normalization error: ' + err);
				return -1;
			}

			/* validate */
			if (!opts.name) {
				pathoschild.util.Log('TemplateScript::Add() failed: template must have a name.');
				return -1;
			}
			if (!opts.template && !opts.script) {
				pathoschild.util.Log('TemplateScript::Add() failed, template "' + opts.name + '" must have either a template or a script.');
				return -1;
			}
			if (!pathoschild.TemplateScript.IsEnabled(opts)) {
				return -1;
			}

			/* set defaults */
			if (!opts.position) {
				opts.position = (pathoschild.TemplateScript.Context.action === 'edit' ? 'cursor' : 'replace');
			}
			if (!opts.editSummaryPosition) {
				opts.editSummaryPosition = 'replace';
			}
			if (!opts.headlinePosition) {
				opts.headlinePosition = 'replace';
			}

			/* add template */
			opts.id = this._templates.push(opts) - 1;
			if (this._isInitialized) {
				this._CreateSidebarEntry(opts);
			}
			return opts.id;
		},

		/**
		 * Add templates to the sidebar menu.
		 * @property {pathoschild.TemplateScript.Template} fields A Template-like object containing fields to merge into the templates.
		 * @param {pathoschild.TemplateScript.Template | pathoschild.TemplateScript.Template[]} templates The template(s) to add.
		 * @return {int} Returns the identifier of the added template (or the last added template if given an array), or -1 if the template could not be added.
		 */
		AddWith: function (fields, templates) {
			/* merge templates */
			if (!$.isArray(templates)) {
				templates = [templates];
			}
			for (var i in templates) {
				for (var attr in fields) {
					templates[i][attr] = fields[attr];
				}
			}

			/* add templates */
			return this.Add(templates);
		},

		/**
		 * Apply a template to the form.
		 * @param {int} id The identifier of the template to insert, as returned by Add().
		 */
		Apply: function (id) {
			/* get template */
			if (!(id in this._templates)) {
				pathoschild.util.Log('TemplateScript::Apply() failed, there is no template with ID "' + id + '".');
				return;
			}
			var opts = this._templates[id];

			/* validate target input box */
			if (!this.Context.$target.length) {
				pathoschild.util.Log('TemplateScript::Apply() failed, no recognized form found.');
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
		IsEnabled: function (template) {
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
		SetDefaultGroupHeader: function (text) {
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
		InsertLiteral: function ($target, text, position) {
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
	var vectorModules = mw.config.get('wgVectorEnabledModules');
	if (vectorModules && vectorModules.collapsiblenav) {
		mw.loader.using(['ext.vector.collapsibleNav'], function () { $(pathoschild.TemplateScript._Initialize); });
	}
	else {
		$(pathoschild.TemplateScript._Initialize);
	}

	/*********
	** Regex editor tool for TemplateScript
	*********/
	/**
	 * Singleton that lets the user define custom regular expressions using a dynamic form and execute them against the text.
	 * @author Pathoschild
	 * @version 0.1-alpha
	 * @class
	 * @property {string} ContainerID The unique ID of the regex editor container.
	 * @property {string} UndoText The original text before the last patterns were applied.
	 * @property {jQuery} $target The text input element to which to apply regular expressions.
	*/
	pathoschild.TemplateScript.RegexEditor = {
		/*********
		** Properties
		*********/
		ContainerID: 'templatescript-regex-editor',
		UndoText: null,
		$target: null,


		/*********
		** Methods
		*********/
		/**
		 * Construct a DOM element.
		 * @param {string} tag The name of the DOM element to construct.
		 */
		Make: function (tag) {
			return $(document.createElement(tag));
		},

		/**
		 * Construct the regex editor and add it to the page.
		 * @param {jQuery} $target The text input element to which to apply regular expressions.
		 */
		Create: function ($target) {
			// initialize state
			var _this = this;
			this.$target = $target;
			var $container = $('#' + this.ContainerID);
			var $warning = $('#' + this.ContainerID + ' .tsre-warning');

			// add CSS
			mw.loader.load('https://raw.github.com/Pathoschild/Wikimedia-contrib/master/pathoschild.templatescript.css', 'text/css');

			// display reset warning if already open (unless it's already displayed)
			if ($container.length) {
				if (!$warning.length) {
					$warning = this
						.Make('div')
						.attr('class', 'tsre-warning')
						.text('You are launching the regex editor tool, but it\'s already open. Do you want to ')
						.append(this
							.Make('a')
							.text('reset the form')
							.attr({ 'title': 'reset the form', 'class': 'tsre-reset', 'href': '#' })
							.click(function () { _this.Reset(); return false; })
						)
						.append(' or ')
						.append(this
							.Make('a')
							.text('cancel the new launch')
							.attr({ 'title': 'cancel the new launch', 'class': 'tsre-cancel', 'href': '#' })
							.click(function () { $warning.remove(); return false; })
						)
						.append('?')
						.prependTo($container);
				}
			}

				// build form
			else {
				// container
				$container = this
					// form
					.Make('div')
					.attr('id', this.ContainerID)
					.append(this
						.Make('h3')
						.text('Regex editor')
					)

					// instructions
					.append(this
						.Make('p')
						.attr('class', 'tsre-instructions')
						.append('Enter any number of regular expressions to execute. The search pattern can be like "')
						.append(this.Make('code').text('search pattern'))
						.append('" or "')
						.append(this.Make('code').text('/pattern/modifiers'))
						.append('", and the replace pattern can contain reference groups like "')
						.append(this.Make('code').text('$1'))
						.append('" (see a ')
						.append(this
							.Make('a')
							.text('tutorial')
							.attr({ 'title': 'JavaScript regex tutorial', 'class': 'external text', 'href': 'http://www.regular-expressions.info/javascript.html', 'target': '_blank' })
						)
						.append(').')
					)

					// form
					.append(this
						.Make('form')
						.append(this
							.Make('ol') // inputlist
						)
						// exit button
						.append(this
							.Make('div')
							.attr('class', 'tsre-close')
							.append(this
								.Make('a')
								.attr({ 'title': 'Close the regex editor', href: '#' })
								.click(function () { _this.Remove(); return false; })
								.append(this
									.Make('img')
									.attr('src', '//upload.wikimedia.org/wikipedia/commons/thumb/4/47/Noun_project_-_supprimer_round.svg/16px-Noun_project_-_supprimer_round.svg.png')
								)
							)
						)
						// field buttons
						.append(this
							.Make('div')
							.attr('class', 'tsre-buttons')
							.append(this
								.Make('a')
								.append(this
									.Make('img')
									.attr('src', '//upload.wikimedia.org/wikipedia/commons/thumb/0/0c/Noun_project_-_plus_round.svg/16px-Noun_project_-_plus_round.svg.png')
								)
								.append(' add patterns')
								.attr({ 'title': 'Add search & replace boxes', 'class': 'tsre-add', 'href': '#' })
								.click(function () { _this.AddInputs(); return false; })
							)
							.append(' | ')
							.append(this
								.Make('a')
								.append(this
									.Make('img')
									.attr('src', '//upload.wikimedia.org/wikipedia/commons/thumb/5/57/Noun_project_-_crayon.svg/16px-Noun_project_-_crayon.svg.png')
								)
								.append(' apply')
								.attr({ 'title': 'Perform the above patterns', 'class': 'tsre-execute', 'href': '#' })
								.click(function () { _this.Execute(); return false; })
							)
							.append(this
								.Make('span')
								.attr('class', 'tsre-undo')
								.append(' | ')
								.append(this
									.Make('a')
									.append(this
										.Make('img')
										.attr('src', '//upload.wikimedia.org/wikipedia/commons/thumb/1/13/Noun_project_-_Undo.svg/16px-Noun_project_-_Undo.svg.png')
									)
									.append(' undo the last apply')
									.attr({ 'title': 'Undo the last apply', 'href': '#' })
									.click(function () { _this.Undo(); return false; })
								)
								.hide()
							)
							// session buttons
							.append(this
								.Make('span')
								.attr('class', 'tsre-session-buttons')
								.append(' | ')
								.append(this
									.Make('a')
									.append(this
										.Make('img')
										.attr('src', '//upload.wikimedia.org/wikipedia/commons/thumb/a/a0/Noun_project_-_USB.svg/16px-Noun_project_-_USB.svg.png')
									)
									.append(' save')
									.attr({ 'title': 'Save this session for later use', 'class': 'tsre-save', 'href': '#' })
									.click(function () { _this.SaveSession(); return false; })
								)
								.append(' ')
								.append(this
									.Make('span')
									.attr('class', 'tsre-sessions')
								)
							)
						)
					)
					.prependTo(_this.$target.parent());

				// add first pair of input boxes
				this.AddInputs();
				this.PopulateSessionList();

				// hide sessions if browser doesn't support it
				if (!pathoschild.util.storage.IsAvailable()) {
					$('.tsre-session-buttons').hide();
				}
			}
		},

		/**
		 * Reset the regex editor.
		 */
		Reset: function () {
			this.Remove();
			this.Create(this.$target);
		},

		/**
		 * Add a pair of regular expression input boxes to the regex editor.
		 */
		AddInputs: function () {
			var id = $('.tsre-pattern').length + 1;
			$('#' + this.ContainerID + ' ol:first')
				.append(this
					.Make('li')
					.attr('class', 'tsre-pattern')
					.append(this
						.Make('label')
						.attr('for', 'tsre-search-' + id)
						.text('Search:')
					)
					.append(this
						.Make('textarea')
						.attr({ 'name': 'tsre-search-' + id, 'tabindex': id + 100 })
					)
					.append(this.Make('br'))
					.append(this
						.Make('label')
						.attr('for', 'tsre-replace-' + id)
						.text('Replace:')
					)
					.append(this
						.Make('textarea')
						.attr({ 'name': 'tsre-replace-' + id, 'tabindex': id + 101 })
					)
				);
		},

		/**
		 * Get the regular expression patterns defined by the user.
		 */
		GetPatterns: function () {
			var patterns = [];
			$('.tsre-pattern').each(function (i, item) {
				// extract input
				var $item = $(item);
				var pattern = {
					'input': $item.find('textarea:eq(0)').val(),
					'replace': $item.find('textarea:eq(1)').val()
				};

				// parse search expression
				if (!pattern.input.match(/^\s*\/[\s\S]*\/[a-z]*\s*$/i)) {
					pattern.search = new RegExp(pattern.input);
				}
				else {
					var search = pattern.input.replace(/^\s*\/([\s\S]*)\/[a-z]*\s*$/i, '$1');
					var modifiers = pattern.input.replace(/^\s*\/[\s\S]*\/([a-z]*)\s*$/, '$1');
					modifiers = modifiers.replace(/[^gim]/ig, '');
					pattern.search = new RegExp(search, modifiers);
				}

				// store
				patterns.push(pattern);
			});

			return patterns;
		},

		/**
		 * Apply the defined regular expressions to the text.
		 */
		Execute: function () {
			// enable undo
			var oldText = this.$target.val();

			// execute
			var patterns = this.GetPatterns();
			for (var i = 0, len = patterns.length; i < len; i++) {
				this.$target.val(this.$target.val().replace(patterns[i].search, patterns[i].replace));
			}

			if (this.$target.val() !== oldText) {
				this.UndoText = oldText;
				$('.tsre-undo').show();
			}
		},

		/**
		 * Revert the text to its state before the regular expressions were last applied.
		 */
		Undo: function () {
			if (this.$target.val() === this.UndoText || this.UndoText === null) {
				return;
			}

			this.$target.val(this.UndoText);
			this.UndoText = null;
			$('.tsre-undo').hide();
		},

		/**
		 * Remove the regex editor.
		 */
		Remove: function () {
			$('#' + this.ContainerID).remove();
		},

		/**
		 * Save the regex editor patterns for later reuse.
		 */
		SaveSession: function () {
			// get session name
			var sessionName = prompt('Enter a name for this session:', '');
			if (!sessionName) {
				return;
			}

			// save patterns
			var patterns = this.GetPatterns();
			var sessions = pathoschild.util.storage.Read('tsre-sessions') || [];
			sessions.push(sessionName);
			sessions.sort();
			pathoschild.util.storage.Write('tsre-sessions', sessions);
			pathoschild.util.storage.Write('tsre-sessions.' + sessionName, patterns);

			// update list
			var $list = $('.tsre-session-buttons select:first');
			this.PopulateSessionList();
		},

		/**
		 * Load a previously saved set of patterns.
		 * @param {string} sessionName The unique name of the session to load.
		 */
		LoadSession: function (sessionName) {
			var patterns = pathoschild.util.storage.Read('tsre-sessions.' + sessionName);
			this.Reset();
			for (var i = 1, len = patterns.length; i < len; i++) {
				this.AddInputs();
			}

			$('.tsre-pattern').each(function (i, item) {
				var $item = $(item);
				$item.find('textarea:eq(0)').val(patterns[i].input);
				$item.find('textarea:eq(1)').val(patterns[i].replace);
			});
		},

		/**
		 * Delete a previously saved set of patterns.
		 * @param {string} sessionName The unique name of the session to delete.
		 */
		DeleteSession: function (sessionName) {
			var sessions = pathoschild.util.storage.Read('tsre-sessions') || [];
			var index = $.inArray(sessionName, sessions);
			if (index === -1) {
				return;
			}

			sessions.splice(index, 1);

			pathoschild.util.storage.Write('tsre-sessions', sessions);
			pathoschild.util.storage.Delete(sessionName);

			this.PopulateSessionList();
		},

		/**
		 * Populate the list of sessions.
		 */
		PopulateSessionList: function () {
			var _this = this;
			var sessions = pathoschild.util.storage.Read('tsre-sessions') || [];

			var $box = $('.tsre-sessions').empty();
			for (var i = 0, len = sessions.length; i < len; i++) {
				$box
					.append(this
						.Make('span')
						.attr('class', 'tsre-session-tag')
						.append(this
							.Make('a')
							.text(sessions[i])
							.attr({ 'title': 'Load session "' + sessions[i] + '"', 'href': '#', 'data-key': sessions[i] })
							.click(function () { _this.LoadSession($(this).attr('data-key')); return false; })
						)
						.append(' ')
						.append(this
							.Make('a')
							.text('x')
							.attr({ 'title': 'Delete session "' + sessions[i] + '"', 'href': '#', 'class': 'tsre-delete-session', 'data-key': sessions[i] })
							.click(function () { _this.DeleteSession($(this).attr('data-key')); return false; })
						)
					);
			}
		}
	};

	pathoschild.TemplateScript.Add({
		name: 'Regex editor',
		script: function ($target) { pathoschild.TemplateScript.RegexEditor.Create($target.$target); },
		forActions: 'edit'
	});
});