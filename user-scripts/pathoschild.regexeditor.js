/*


This regex editor lets the user define any number of arbitrary search & replace patterns using regex,
apply them sequentially to a textbox, and save them as sessions in local browser storage. This script
is bundled into TemplateScript.

For more information, see <https://github.com/Pathoschild/Wikimedia-contrib#readme>.


*/
/* global $, mw, pathoschild, RegexColorizer */
/* jshint eqeqeq: true, latedef: true, nocomma: true, undef: true */
window.pathoschild = window.pathoschild || {}; // use window for ResourceLoader compatibility
(function() {
	'use strict';

	/**
	 * Singleton that lets the user define custom regular expressions using a dynamic form and execute them against the text.
	 * @author Pathoschild
	 * @class
	 * @param {object} config Configuration settings primarily intended for usage outside MediaWiki.
	 * @property {string} version The unique version number for debug purposes.
	*/
	pathoschild.RegexEditor = function(config) {
		var self = {};


		/*********
		** Objects
		*********/
		/**
		 * The regex editor is primarily meant as a TemplateScript script, but it can be run
		 * directly on Tool Labs as a standalone script. This object replicates the TemplateScript
		 * interface for the Tool Labs page, so the regex tool has a consistent interface to code
		 * against.
		 */
		var TemplateScriptShim = function($target) {
			var context = {};

			/**
			 * Get the value of the target element.
			 */
			context.get = function() {
				return $target.val();
			};

			/**
			 * Set the value of the target element.
			 * @param {string} text The text to set.
			 */
			context.set = function(text) {
				$target.val(text);
			};

			return context;
		};



		/*********
		** Fields
		*********/
		self.version = '0.12';
		self.strings = {
			header: 'Regex editor', // the header text shown in the form
			search: 'Search',       // the search input label
			replace: 'Replace',     // the replace input label
			nameSession: 'Enter a name for this session', // the prompt shown when saving the session
			loadSession: 'Load session "{name}"',         // tooltip shown for a saved session, where {name} is replaced with the session name
			deleteSession: 'Delete session "{name}"',     // tooltip shown for the delete icon on a saved session, where {name} is replaced with the session name
			closeEditor: 'Close the regex editor',        // tooltip shown for the close-editor icon
			addPatterns: 'add patterns',                  // button text
			addPatternsTooltip: 'Add search & replace boxes', // button tooltip
			apply: 'apply',                               // button text
			applyTooltip: 'Perform the above patterns',   // button tooltip
			undo: 'undo the last apply',                  // button text
			undoTooltip: 'Undo the last apply',           // button tooltip
			save: 'save',                                 // button text
			saveTooltip: 'Save this session for later use', // button tooltip
			instructions: 'Enter any number of regular expressions to execute. The search pattern can be like "{code|text=search pattern}" or "{code|text=/pattern/modifiers}", and the replace pattern can contain reference groups like "{code|text=$1}" (see {helplink|text=tutorial|title=JavaScript regex tutorial|url=http://www.regular-expressions.info/javascript.html}).'
		};
		var state = {
			undoText: null,      // the original text before the last patterns were applied
			$target: null,       // the DOM elements before which to insert the regex editor UI
			editor: null,        // the TemplateScript editor
			initialisation: null // a promise completed when initialisation is done
		};
		self.config = $.extend({ alwaysVisible: false }, config);


		/*********
		** Private methods
		*********/
		/**
		 * Construct a DOM element.
		 * @param {string} tag The name of the DOM element to construct.
		 * @param {object} attr (optional) The attributes to set on the DOM element.
		 */
		var _make = function(tag, attr) {
			// Convert the tag to jQuery creation syntax. Using document.createElement would be cleaner,
			// but the jQuery attr argument only works for elements created this way.
			return $('<' + tag + '></' + tag + '>', attr);
		};

		/**
		 * Load the scripts required by the regex editor.
		 * @returns A promise completed when the dependencies have been loaded.
		 */
		var _initialise = function() {
			// already initialising or initialised
			if(state.initialisation)
				return state.initialisation;

			// apply localisation
			if(pathoschild.i18n && pathoschild.i18n.regexeditor)
				$.extend(self.strings, pathoschild.i18n.regexeditor);

			// add CSS
			mw.loader.load('//tools-static.wmflabs.org/meta/scripts/pathoschild.regexeditor.css', 'text/css');
			mw.loader.load('//tools-static.wmflabs.org/meta/scripts/dependencies/regex-colorizer.css', 'text/css');

			// load dependencies
			return state.initialisation = $.when(
				$.ajax('//tools-static.wmflabs.org/meta/scripts/pathoschild.util.js', { dataType:'script', crossDomain:true, cached:true }),
				$.ajax('//tools-static.wmflabs.org/meta/scripts/dependencies/regex-colorizer.js', { dataType:'script', crossDomain:true, cached:true })
			);
		};

		/**
		 * Add a pair of regular expression input boxes to the regex editor.
		 * @param {string|RegExp} search The search text to preload (if any).
		 * @param {string} replace The replace text to preload (if any).
		 */
		var _addInputs = function(search, replace) {
			var id = $('.re-pattern').length + 1;

			// stringify regex
			if(search && search instanceof RegExp)
				search = search.toString();

			// create layout
			var $searchContainer, $search, $preview;
			_make('li', {
				'class': 're-pattern',
				append: [
					// search
					_make('label', { 'for': 're-search-' + id, text: self.strings.search + ':' }),
					$searchContainer = _make('div', {
						'class': 're-syntax-highlighted',
						append: [
							$preview = _make('pre', {
								'class': 'preview regex',
								text: search
							}),
							$search = _make('textarea', {
								name: 're-search-' + id,
								tabindex: id + 100,
								'class': 'search',
								text: search
							})
						]
					}),

					// replace
					_make('br'),
					_make('label', { 'for': 're-replace-' + id, text: self.strings.replace + ':' }),
					_make('textarea', {
						'class': 'replace',
						text: replace,
						contenteditable: true,
						name: 're-replace-' + id,
						tabindex: id + 101
					})
				],
				appendTo: '#regex-editor ol:first'
			});

			// add search formatting
			var updateFormatting = function() {
				// update syntax highlighting
				var formatted = RegexColorizer.colorizeText($search.val())
					+ '&nbsp;'; // height:auto is calculated incorrectly if the last line is blank
				$preview.html(formatted);

				// resize search to fit contents
				var previewHeight = $preview.height();
				$searchContainer.height(previewHeight);
				$search.height(previewHeight);
			};
			$search.on('keyup', updateFormatting);

			// format initial text
			updateFormatting();
		};

		/**
		 * Get the regular expression patterns defined by the user.
		 */
		var _getPatterns = function() {
			var patterns = [];
			$('.re-pattern').each(function(i, item) {
				// extract input
				var $item = $(item);
				var pattern = {
					'input': $item.find('.search').val(),
					'replace': $item.find('.replace').val()
				};

				// parse search expression
				if (!pattern.input.match(/^\s*\/[\s\S]*\/[a-z]*\s*$/i))
					pattern.search = new RegExp(pattern.input);
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
		};

		/**
		 * Save the regex editor patterns for later reuse.
		 */
		var _saveSession = function() {
			// get session name
			var sessionName = prompt(self.strings.nameSession + ':', '');
			if (!sessionName)
				return;

			// save patterns
			var patterns = _getPatterns();
			var sessions = pathoschild.util.storage.Read('tsre-sessions') || [];
			sessions.push(sessionName);
			sessions.sort();
			pathoschild.util.storage.Write('tsre-sessions', sessions);
			pathoschild.util.storage.Write('tsre-sessions.' + sessionName, patterns);

			// update list
			_populateSessionList();
		};

		/**
		 * Load a previously saved set of patterns.
		 * @param {string} sessionName The unique name of the session to load.
		 */
		var _loadSession = function(sessionName) {
			var patterns = pathoschild.util.storage.Read('tsre-sessions.' + sessionName);
			self.reset(false/* don't add empty inputs */);
			$.each(patterns, function(i, pattern) {
				_addInputs(pattern.input, pattern.replace);
			});
		};

		/**
		 * Delete a previously saved set of patterns.
		 * @param {string} sessionName The unique name of the session to delete.
		 */
		var _deleteSession = function(sessionName) {
			var sessions = pathoschild.util.storage.Read('tsre-sessions') || [];
			var index = $.inArray(sessionName, sessions);
			if (index === -1)
				return;

			sessions.splice(index, 1);

			pathoschild.util.storage.Write('tsre-sessions', sessions);
			pathoschild.util.storage.Delete(sessionName);

			_populateSessionList();
		};

		/**
		 * Populate the list of sessions.
		 */
		var _populateSessionList = function() {
			var sessions = pathoschild.util.storage.Read('tsre-sessions') || [];
			var container = $('#re-sessions');
			container.find('.re-session-apply, .re-session-delete').remove();
			$.each(sessions, function() {
				var session = this;

				// build layout
				_make('span', {
					'class': 're-session',
					append: [
						// apply link
						_make('button', {
							'class': 're-session-apply',
							text: session,
							title: self.strings.loadSession.replace(/\{name\}/g, session),
							click: function() { _loadSession(session); return false; }
						}),

						// delete link
						_make('button', {
							'class': 're-session-delete',
							text: 'x',
							title: self.strings.deleteSession.replace(/\{name\}/g, session),
							click: function() { _deleteSession(session); return false; }
						})
					],
					appendTo: container
				});
			});
		};

		/*********
		** Public methods
		*********/
		/**
		 * Construct the regex editor and add it to the page.
		 * @param {jQuery} $target The DOM elements before which to insert the regex editor UI.
		 * @param {object} editor The TemplateScript editor (if available).
		 * @param {bool} addInputs Whether to add empty inputs for the first pattern.
		 */
		self.create = function($target, editor, addInputs) {
			_initialise().then(function() {
				// initialize state
				state.$target = $target;
				state.editor = editor || TemplateScriptShim($target);
				var $container = $('#regex-editor');
				if ($container.length)
					return; // already loaded

				// build form
				$container = _make('div', {
					id: 'regex-editor',
					append: [
						// header
						_make('h3', { text: self.strings.header }),
						self.createInstructions(_make('p')),

						// form
						_make('form', {
							submit: function() { return false; }, // suppress default submit behaviour
							append: [
								// input list
								_make('ol'),

								// exit button
								_make('button', {
									'class': 're-close',
									title: self.strings.closeEditor,
									click: function() {
										if(self.config.alwaysVisible)
											self.reset();
										else
											self.remove();
										return false;
									}
								}),

								// field buttons
								_make('div', {
									'class': 're-buttons',
									append: [
										// add button
										_make('button', {
											'class': 're-add',
											text: self.strings.addPatterns,
											title: self.strings.addPatternsTooltip,
											click: function() { _addInputs(); }
										}),

										// execute button
										_make('button', {
											'class': 're-execute',
											text: self.strings.apply,
											title: self.strings.applyTooltip,
											click: self.execute
										}),

										// undo button
										_make('button', {
											'class': 're-undo',
											text: self.strings.undo,
											title: self.strings.undoTooltip,
											click: self.undo
										}).hide(),

										// save button & session list
										_make('span', {
											id: 're-sessions',
											append: _make('button', {
												'class': 're-save',
												text: self.strings.save,
												title: self.strings.saveTooltip,
												click: _saveSession
											})
										})
									]
								})
							]
						})
					]
				});
				$container.insertBefore(state.$target);

				// add first pair of input boxes
				if(addInputs !== false)
					_addInputs();

				// add sessions
				_populateSessionList();
				if (!pathoschild.util.storage.IsAvailable())
					$('#re-sessions').hide();
			});
		};

		/**
		 * Populate a container with the regex tool instructions.
		 * @param {jQuery} $container The element to populate.
		 */
		self.createInstructions = function($container) {
			// create instructions
			$container
				.attr('class', 're-instructions')
				.empty()
				.text(self.strings.instructions);

			// inject form elements
			$container.html($container.html()
				.replace(/\{code\|text=(.+?)\}/g, '<code>$1</code>')
				.replace(/\{helplink\|text=(.+?)\|title=(.+)?\|url=(.+)?\}/g, function(match, text, title, url) {
					var link = _make('a')
						.text(text || '')
						.attr({ title: title, 'class': 'external text', href: url, target: '_blank' });
					return _make('div').append(link).html();
				})
			);
			
			return $container;
		};

		/**
		 * Reset the regex editor.
		 * @param {bool} addInputs Whether to add empty inputs for the first pattern.
		 */
		self.reset = function(addInputs) {
			self.remove();
			self.create(state.$target, null, addInputs);
		};

		/**
		 * Apply the defined regular expressions to the text.
		 */
		self.execute = function() {
			// enable undo
			var oldText = state.editor.get();

			// apply patterns
			var newText = oldText;
			var patterns = _getPatterns();
			for (var i = 0, len = patterns.length; i < len; i++)
				newText = newText.replace(patterns[i].search, patterns[i].replace);

			// update UI
			if (newText !== oldText) {
				state.editor.set(newText);
				state.undoText = oldText;
				$('.re-undo').show();
			}
		};

		/**
		 * Revert the text to its state before the regular expressions were last applied.
		 */
		self.undo = function() {
			if (state.undoText === null || state.editor.get() === state.undoText)
				return;

			state.editor.set(state.undoText);
			state.undoText = null;
			$('.re-undo').hide();
		};

		/**
		 * Remove the regex editor.
		 */
		self.remove = function() {
			$('#regex-editor').remove();
		};


		/*****
		** 0.9 compatibility
		*****/
		self.Create = self.create;
		self.Remove = self.remove;

		return self;
	};
}());