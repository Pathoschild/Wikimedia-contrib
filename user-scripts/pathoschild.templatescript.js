/*


TemplateScript adds a menu of configurable templates and scripts to the sidebar.
For more information, see <https://github.com/Pathoschild/Wikimedia-contrib#readme>.


*/
/* global $, mw */
/* jshint eqeqeq: true, latedef: true, nocomma: true, undef: true */
var pathoschild = pathoschild || {};
(function() {
	'use strict';

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
		self.version = '1.12.0';
		self.strings = {
			defaultHeaderText: 'TemplateScript', // the sidebar header text label for the default group
			regexEditor: 'Regex editor' // the default 'regex editor' script
		};
		var state = {
			dependencies: [], // internal lookup used to manage asynchronous script dependencies
			isReady: false,   // whether TemplateScript has been initialised and hooked into the DOM
			templates: [],    // the registered template objects
			queue: [],        // the template objects to add to the DOM when it's ready
			sidebarCount: 0,  // number of rendered sidebars (excluding the default sidebar)
			sidebars: {},     // hash of rendered sidebars by name
			renderers: {}     // the plugins which render template/script links
		};


		/*********
		** Objects
		*********/
		/**
		 * Represents an insertable template schema.
		 * @property {string} name The name displayed as the sidebar link text.
		 * @property {boolean} enabled Whether this template is available.
		 * @property {string} category An arbitrary category name (for grouping templates into multiple sidebars). The default is `self.strings.defaultHeaderText`.
		 * @property {string[]} forActions The wgAction values for which the template is enabled, or null to enable for all actions.
		 * @property {int[]} forNamespaces The namespaces in which the template is enabled, or null to enable in all namespaces.
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
		 * @property {boolean} autoSubmit Whether to submit the form automatically after insertion.
		 * @property {string} scriptUrl A script URL (or page name on the current wiki) to fetch before adding the template.
		 * @property {function} script An arbitrary JavaScript function that is called after the template and edit summary are applied, but before autoSubmit is applied (if true). It is passed a reference to the context object.
		 *
		 * @property {int} id The internal template ID. (Modifying this value may cause unexpected behaviour.)
		 * @class
		 */
		self.Template = {
			/* UI options */
			name: null,
			enabled: true,
			category: null,
			forActions: null,
			forNamespaces: null,
			accessKey: null,
			tooltip: null,
			renderer: 'sidebar',

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
			scriptUrl: null,
			script: null,

			/* internal */
			id: null
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
			before: 'before',
			after: 'after',
			cursor: 'cursor',
			replace: 'replace'
		};

		/**
		 * Provides convenient access to singleton properties about the current page. (Changing the values may cause unexpected behaviour.)
		 * @property {int} namespace The number of the current MediaWiki namespace.
		 * @property {string} name The canonical name of the current MediaWiki namespace.
		 * @property {string} action The string representing the current MediaWiki action.
		 * @property {pathoschild.TemplateScript} singleton The TemplateScript instance for the page.
		 * @property {jQuery} $target The primary input element (e.g., the edit textarea) for the current form.
		 * @property {jQuery} $editSummary The edit summary input element (if relevant to the current form).
		 * @property {object} helper Provides shortcut methods for common operations.
		 */
		self.Context = (function() {
			/*********
			** Fields
			*********/
			var context = {
				namespace: mw.config.get('wgNamespaceNumber'),
				namespaceName: mw.config.get('wgCanonicalNamespace'),
				pageName: mw.config.get('wgPageName'),
				action: (function() {
					var action = mw.config.get('wgAction');
					var specialPage = mw.config.get('wgCanonicalSpecialPageName');
					switch(action) {
						case 'submit':
							return 'edit';

						case 'view':
							if($('#movepage').length)
								return 'move';
							if(specialPage === 'Block')
								return 'block';
							if(specialPage === 'Emailuser')
								return 'emailuser';

						default:
							return action;
					}
				})(),
				isSectionNew: $('#wpTextbox1, #wpSummary').first().attr('id') === 'wpSummary', // if #wpSummary is first, it's not the edit summary (MediaWiki reused ID)
				singleton: null,
				$target: null,
				$editSummary: null
			};
			
			
			/*********
			** Public methods
			*********/
			context.helper = {
				/**
				 * Perform a search & replace in the target element.
				 * @param {string|regexp} search The search string or regular expression.
				 * @param {string} replace The replace pattern.
				 * @returns The helper instance for chaining.
				 */
				replace: function(search, replace) {
					context.$target.val(function(i, val) { return val.replace(search, replace); });
					return context;
				},

				/**
				 * Set the value of the target element.
				 * @param {string} text The text to set.
				 */
				set: function(text) {
					context.$target.val(text);
					return context;
				},

				/**
				 * Append text to the target element. This is equivalent to insertLiteral(text, 'after').
				 * @param {string} text The text to append.
				 */
				append: function(text) {
					self.insertLiteral(context.$target, text, 'after');
					return context;
				},

				/**
				 * Escape the matching substrings in the target element to avoid conflicts. This returns a state used to unescape.
				 * @param {string|regexp} search The search string or regular expression.
				 */
				escape: function(search) {
					var $text = context.$target;
					var text = $text.val();


					// generate token format
					var uniqueStamp = (new Date()).getTime();
					var format = '~' + uniqueStamp + '.$1~';
					var formatPattern = new RegExp('~' + uniqueStamp + '\\.(\\d+)~', 'g');

					// escape
					var state = {
						search: search,
						token: formatPattern,
						values: []
					};
					var i = 0;
					text = text.replace(search, function(match) {
						state.values.push(match);
						return format.replace('$1', i++);
					});

					$text.val(text);
					return state;
				},

				/**
				 * Restore substrings in the target element escaped by the escape(search) method.
				 * @param {object} state The escape state returned by the escape(search) method.
				 */
				unescape: function(state) {
					var $text = context.$target;
					var text = $text.val();

					text = text.replace(state.token, function(match, id) {
						return state.values[id];
					});

					$text.val(text);
				},

				/**
				 * Insert a literal text into the target field.
				 * @param {string} text The template text to insert, with template format values preparsed.
				 * @param {string} position The insertion position, matching a {Position} value.
				 */
				insertLiteral: function(text, position) {
					self.insertLiteral(context.$target, text, position);
					return context;
				},

				/**
				 * Replace the selected text in the target field.
				 * @param {string|function} text The new text with which to overwrite the selection (with any template format values preparsed), or a function which takes the selected text and returns the new text. If no text is selected, the function is passed an empty value and its return value is added to the end.
				 */
				replaceSelection: function(text) {
					self.replaceSelection(context.$target, text);
					return context;
				},

				/**
				 * Append text to the edit summary (with a ', ' separator) if editing a page.
				 * @param {string} summary The edit summary.
				 * @returns The helper instance for chaining.
				 */
				appendEditSummary: function(summary) {
					// get edit summary box
					var $summary = context.$editSummary;
					if(!$summary || $summary.val().indexOf(summary) !== -1)
						return context;

					// append summary
					var text = $summary.val().replace(/\s*$/, '');
					if(text.match(/\*\/$/))
						$summary.val(text + ' ' + summary); // "/* section */ reason"
					else if(text.match(/[^\s]/))
						$summary.val(text + ', ' + summary); // old summary, new summary
					else
						$summary.val(summary); // new summary

					return context;
				},

				/**
				 * Overwrite the edit summary if editing a page.
				 * @param {string} summary The edit summary.
				 * @returns The helper instance for chaining.
				 */
				setEditSummary: function(summary) {
					// get edit summary box
					var $summary = context.$editSummary;
					if(!$summary)
						return context;

					// overwrite summary
					$summary.val(summary);
					return context;
				},

				/**
				 * Click the 'show changes' button if editing a page.
				 */
				clickDiff: function() {
					$('#wpDiff').click();
				},

				/**
				 * Click the 'show preview' button if editing a page.
				 */
				clickPreview: function() {
					$('#wpPreview').click();
				}
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
		 * Add a sidebar entry for a template.
		 * @param {Template} template The template for which to create an entry.
		 * @param {TemplateScript} instance The script instance.
		 * @returns the generated item.
		 */
		var _renderSidebar = function(template, instance) {
			// build the sidebar
			var category = template.category;
			if (!(category in state.sidebars)) {
				var id = state.sidebars[category] = 'p-templatescript-' + state.sidebarCount;
				pathoschild.util.mediawiki.AddPortlet(id, category);
				++state.sidebarCount;
			}
			var sidebarID = state.sidebars[category];

			// add link
			var $item = pathoschild.util.mediawiki.AddPortletLink(sidebarID, template.name, 'ts-link-' + template.id, template.tooltip, template.accessKey, function() { instance.apply(template.id); });
			if(template.accessKey) {
				$item.append(
					$('<small>')
						.addClass('ts-shortcut')
						.append(template.accessKey)
				);
			}
			return $item;
		};


		/*********
		** Private methods
		*********/
		/**
		 * Bootstrap TemplateScript and hook into the UI. This method should only be called once the DOM is ready.
		 */
		var _initialise = function() {
			if (self.Context.singleton)
				return;

			// init context
			self.Context.singleton = self;
			self.Context.$target = $('#wpTextbox1, #wpReason, #wpComment, #mwProtect-reason, #mw-bi-reason').first();
			self.Context.$editSummary = $('#wpSummary:first');

			// init localisation
			if(pathoschild.i18n && pathoschild.i18n.templatescript)
				$.extend(self.strings, pathoschild.i18n.templatescript);

			// init plugins
			self.addRenderer('sidebar', _renderSidebar);

			// init UI
			mw.util.addCSS('.ts-shortcut { margin-left:.5em; color:#CCC; }');
			_loadDependency('//tools-static.wmflabs.org/meta/scripts/pathoschild.util.js', pathoschild.util, function() {
				state.isReady = true;
				for (var i = 0; i < state.queue.length; i++)
					self.add(state.queue[i]);
			});
		};

		/**
		 * Asynchronously load a script and invoke the callback when loaded. This method is used to bootstrap TemplateScript and shouldn't be called directly.
		 * @param {string} url The URL of the script to load.
		 * @param {bool} test Indicates whether the dependency is already loaded.
		 * @param {function} callback The method to invoke (with no arguments) when the dependencies have been loaded.
		 */
		var _loadDependency = function(url, test, callback) {
			var invokeCallback = function() { callback.call(self); };
			if (test)
				invokeCallback();
			else
				$.ajax({ url:url, dataType:'script', crossDomain:true, cached:true, success:invokeCallback });
		};

		/**
		 * Write a warning to the debug console, if it's available.
		 * @param {string} message The warning message to write.
		 */
		var _warn = function(message) {
			if(console && console.log)
				console.log('[TemplateScript] ' + message);
		};

		/**
		 * Create a tool link that triggers the template.
		 * @param {Template} template The template for which to create an entry.
		 */
		var _renderEntry = function(template) {
			// get renderer
			var rendererKey = template.renderer;
			if(!(rendererKey in state.renderers)) {
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
				throw 'must have a name';
			if (opts.script && !$.isFunction(opts.script))
				throw 'script must be a function';
			if (!opts.template && !opts.script)
				throw 'must have either a template or a script';

			// normalise schema
			opts = pathoschild.util.ApplyArgumentSchema('pathoschild.TemplateScript::add(name:' + (opts.name || 'unnamed') + ')', opts, self.Template);
			opts.position = pathoschild.util.ApplyEnumeration('Position', opts.position, self.Position);
			opts.editSummaryPosition = pathoschild.util.ApplyEnumeration('Position', opts.editSummaryPosition, self.Position);
			opts.headlinePosition = pathoschild.util.ApplyEnumeration('Position', opts.headlinePosition, self.Position);

			// normalise script URL
			if(opts.scriptUrl && !opts.scriptUrl.match(/^(?:http:|https:)?\/\//))
				opts.scriptUrl = mw.config.get('wgServer') + mw.config.get('wgScriptPath') + '/index.php?title=' + encodeURIComponent(opts.scriptUrl) + '&action=raw&ctype=text/javascript';

			// normalise actions
			if(opts.forActions) {
				// cast to array
				if(!$.isArray(opts.forActions))
					opts.forActions = [opts.forActions];

				// normalise values
				opts.forActions = $.map(opts.forActions, function(value) { return value.toLowerCase(); });
			}

			// normalise namespaces
			if(opts.forNamespaces) {
				// cast to array
				if(!$.isArray(opts.forNamespaces))
					opts.forNamespaces = [opts.forNamespaces];

				// normalise values
				opts.forNamespaces = $.map(opts.forNamespaces, function(value) {
					// parse numeric value
					var numeric = parseInt(value);
					if(!isNaN(numeric))
						return numeric;

					// convert canonical namespace
					var key = value.toLowerCase().replace(/ /g, '_');
					numeric = mw.config.get('wgNamespaceIds')[key];
					if(numeric || numeric === 0)
						return numeric;

					// invalid value
					_warn('ignored unknown namespace "' + value + '"');
					return null;
				});
			}

			// normalise defaults
			opts.category = opts.category || self.strings.defaultHeaderText;
			opts.position = opts.position || (self.Context.action === 'edit' ? 'cursor' : 'replace');
			opts.editSummaryPosition = opts.editSummaryPosition || 'replace';
			opts.headlinePosition = opts.headlinePosition || 'replace';
			opts.renderer = opts.renderer || 'sidebar';
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
			if(common)
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
			catch(error) {
				_warn('template "' + (opts && opts.name || 'unnamed') + '" couldn\'t be normalised: ' + error);
				return; // invalid template
			}

			// add template
			if (self.isEnabled(opts)) {
				// add to UI
				opts.id = state.templates.push(opts) - 1;
				var $entry = _renderEntry(opts);

				/* load dependency */
				if(opts.scriptUrl) {
					$entry.hide();
					if(!state.dependencies[opts.scriptUrl])
						state.dependencies[opts.scriptUrl] = $.ajax(opts.scriptUrl, { cache: true, dataType: 'script' });
					state.dependencies[opts.scriptUrl].done(function() { $entry.show(); });
				}
			}
		};

		/**
		 * Add a plugin responsible for creating the link UI that activates a template. You can add multiple renderers, and choose how each template is rendered by adding "renderer: rendererKey" to its options.
		 * @param {string} key The unique key for the renderer.
		 * @param {function} renderer The function will accepts a template object, and returns a jQuery reference to the created entry.
		 */
		self.addRenderer = function(key, renderer) {
			if(key in state.renderers) {
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
			/* get template */
			if (!(id in state.templates)) {
				_warn('can\'t apply template #' + id + ' because there\'s no template with that ID; there\'s something wrong with TemplateScript\'s internal state');
				return;
			}
			var opts = state.templates[id];

			/* validate target input box */
			if (!self.Context.$target.length) {
				_warn('can\'t apply template because the current page has no recognisable form.');
				return;
			}

			/* insert template */
			if (opts.template)
				self.insertLiteral(self.Context.$target, opts.template, opts.position);
			if (opts.editSummary && !self.Context.isSectionNew)
				self.insertLiteral(self.Context.$editSummary, opts.editSummary, opts.editSummaryPosition);
			if (opts.headline && self.Context.isSectionNew)
				self.insertLiteral(self.Context.$editSummary, opts.headline, opts.headlinePosition);
			if (opts.isMinorEdit)
				$('#wpMinoredit').attr('checked', 'checked');

			/* invoke script */
			if (opts.script)
				opts.script(self.Context);

			/* perform auto-submission */
			if (opts.autoSubmit)
				self.Context.$target.parents('form').first().submit();
		};

		/**
		 * Check whether the template is enabled for the current page context, based on its for* condition properties. This
		 * method also accepts an arbitrary object which exposes the for* property names from the Template interface.
		 * @param {Template | object} template
		 * @returns {boolean} Returns true if all for* conditions were met, or no conditions were found; else false.
		 */
		self.isEnabled = function(template) {
			/* check enabled flag */
			if ('enabled' in template && template.enabled !== null && !template.enabled)
				return false;

			/* match context values */
			var context = self.Context;
			if ('forNamespaces' in template && template.forNamespaces !== null && !_isEqualOrIn(context.namespace, template.forNamespaces))
				return false;
			if ('forActions' in template && template.forActions !== null && !_isEqualOrIn(context.action, template.forActions))
				return false;

			return true;
		};


		/*****
		** Framework
		*****/
		/**
		 * Insert a literal text into a field.
		 * @param {jQuery} $target The field into which to insert the template.
		 * @param {string} text The template text to insert, with template format values preparsed.
		 * @param {string} position The insertion position, matching a {Position} value.
		 */
		self.insertLiteral = function($target, text, position) {
			/* validate */
			if (!$target || !$target.length || !text || !text.length) {
				return; // nothing to do
			}
			try {
				position = pathoschild.util.ApplyEnumeration('Position', position, self.Position);
			}
			catch (err) {
				_warn('can\'t insert literal text: ' + err);
			}

			/* perform insertion */
			switch (position) {
				case self.Position.before:
					$target.val(text + $target.val());
					break;

				case self.Position.after:
					$target.val($target.val() + text);
					break;

				case self.Position.replace:
					$target.val(text);
					break;

				case self.Position.cursor:
					self.replaceSelection($target, text);
					break;

				default:
					_warn('can\'t insert literal text: unknown position "' + position + '"');
					return;
			}
		};

		/**
		 * Replace the selected text in a field.
		 * @param {jQuery} $target The field whose selected text to replace.
		 * @param {string|function} text The new text with which to overwrite the selection (with any template format values preparsed), or a function which takes the selected text and returns the new text. If no text is selected, the function is passed an empty value and its return value is added to the end.
		 */
		self.replaceSelection = function($target, text) {
			var box = $target.get(0);
			box.focus();

			// standardise input
			if(!$.isFunction(text)) {
				var _t = text;
				text = function() { return _t; };
			}

			/* most browsers */
			if (box.selectionStart || box.selectionStart === false || box.selectionStart === '0' || box.selectionStart === 0) {
				var startPos = box.selectionStart;
				var endPos = box.selectionEnd;
				var scrollTop = box.scrollTop;

				var newText = text(box.value.substring(startPos, endPos));
				box.value = box.value.substring(0, startPos) + newText + box.value.substring(endPos - 1 + text.length, box.value.length);
				box.focus();

				box.selectionStart = startPos + text.length;
				box.selectionEnd = startPos + text.length;
				box.scrollTop = scrollTop;
			}

			/* older browsers */
			else if (document.selection) {
				var selection = document.selection.createRange();
				selection.text = text(selection.text);
				box.focus();
			}

			/* Unknown implementation */
			else {
				_warn('can\'t figure out the browser\'s cursor selection implementation, appending instead.');
				box.value += text('');
				return;
			}
		};

		/*****
		** 1.4 compatibility
		*****/
		self.Add = function(opts, common) { return self.add(opts, common); };
		self.AddWith = function(fields, templates) { return self.add(templates, fields); };
		self.Apply = function(id) { return self.apply(id); };
		self.IsEnabled = function(template) { return self.isEnabled(template); };
		self.InsertLiteral = function($target, text, position) { return self.insertLiteral($target, text, position); };


		/*****
		** Bootstrap TemplateScript
		*****/
		// init regex editor
		self.add({
			name: self.strings.regexEditor,
			scriptUrl: '//tools-static.wmflabs.org/meta/scripts/pathoschild.regexeditor.js',
			script: function(context) {
				var regexEditor = new pathoschild.RegexEditor();
				regexEditor.create(context.$target);
			},
			forActions: 'edit'
		});
		$(_initialise);
		return self;
	})();
}());
