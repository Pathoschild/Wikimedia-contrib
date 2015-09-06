/*


This regex editor lets the user define any number of arbitrary search & replace patterns using regex,
apply them sequentially to a textbox, and save them as sessions in local browser storage. This script
is bundled into TemplateScript.

For more information, see <https://github.com/Pathoschild/Wikimedia-contrib#readme>.


*/
/* global $, mw, pathoschild, rangy, RegexColorizer */
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
		self.version = '1.0-alpha';
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
			containerID: 'regex-editor', // unique ID of the regex editor container
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
			mw.loader.load('//tools-static.wmflabs.org/meta/scripts/dependencies/regex-colorizer.css', 'text/css');
			pathoschild.util.AddStyles(
				  '#regex-editor { position: relative; margin: 0.5em; padding: 0.5em; border: 1px solid #AAA; border-radius: 15px; line-height: normal; }\n'
				+ '.tsre-close { position: absolute; top: 10px; right: 10px; }\n'
				+ '.tsre-sessions { color: #AAA; }\n'
				+ '.tsre-session-tag { border: 1px solid #057BAC; border-radius: 2px; background: #1DA1D8; padding: 0 2px; }\n'
				+ '.tsre-session-tag a { color: #FFF; }\n'
				+ 'a.tsre-delete-session { color: red; font-family: monospace; font-weight: bold; }'
			);

			// load dependencies
			return state.initialisation = $.when(
				$.ajax('//tools-static.wmflabs.org/meta/scripts/pathoschild.util.js', { dataType:'script', crossDomain:true, cached:true }),
				$.ajax('//tools-static.wmflabs.org/meta/scripts/dependencies/regex-colorizer.js', { dataType:'script', crossDomain:true, cached:true }),
				$.ajax('//tools-static.wmflabs.org/cdnjs/ajax/libs/rangy/1.3.0/rangy-core.js', { dataType:'script', crossDomain:true, cached:true })
			).then(function() {
				return $.ajax('//tools-static.wmflabs.org/cdnjs/ajax/libs/rangy/1.3.0/rangy-textrange.js', { dataType:'script', crossDomain:true, cached:true });
			});
		};

		/**
		 * Add a pair of regular expression input boxes to the regex editor.
		 */
		var _addInputs = function() {
			var id = $('.tsre-pattern').length + 1;
			var searchID = 'tsre-search-' + id;
			var replaceID = 'tsre-replace-' + id;

			_make('li', {
				class: 'tsre-pattern',
				append: [
					// search
					_make('label', { for: searchID, text: self.strings.search + ':' }),
					_make('pre', {
						contenteditable: true,
						name: searchID,
						tabindex: id + 100,
						class: searchID + ' search regex',
						keyup: function() {
							var selection = rangy.getSelection().saveCharacterRanges(this);
							RegexColorizer.colorizeAll(searchID);
							rangy.getSelection().restoreCharacterRanges(this, selection);
						}
					}),

					// replace
					_make('br'),
					_make('label', { for: replaceID, text: self.strings.replace + ':' }),
					_make('pre', {
						class: 'replace',
						contenteditable: true,
						name: replaceID,
						tabindex: id + 101
					})
				],
				appendTo: '#' + state.containerID + ' ol:first'
			});
		};

		/**
		 * Get the regular expression patterns defined by the user.
		 */
		var _getPatterns = function() {
			var patterns = [];
			$('.tsre-pattern').each(function(i, item) {
				// extract input
				var $item = $(item);
				var pattern = {
					'input': $item.find('pre.search').text(),
					'replace': $item.find('pre.replace').text()
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
			self.reset();
			for (var i = 1, len = patterns.length; i < len; i++)
				_addInputs();

			$('.tsre-pattern').each(function(i, item) {
				var $item = $(item);
				var $search = $item.find('pre.search');
				var $replace = $item.find('pre.replace');

				$search.text(patterns[i].input);
				$replace.text(patterns[i].replace);
				RegexColorizer.colorizeAll($search.attr('id'));
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
			var container = $('.tsre-sessions').empty();
			$.each(sessions, function() {
				var session = this;

				// build layout
				_make('span', {
					class: 'tsre-session-tag',
					append: [
						// apply link
						_make('a', {
							text: session,
							title: self.strings.loadSession.replace(/\{name\}/g, session),
							href: '#',
							click: function() { _loadSession(session); return false; }
						}),
						' ',

						// delete link
						_make('a', {
							text: 'x',
							title: self.strings.deleteSession.replace(/\{name\}/g, session),
							href: '#',
							class: 'tsre-delete-session',
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
		 */
		self.create = function($target, editor) {
			_initialise().then(function() {
				// initialize state
				state.$target = $target;
				state.editor = editor || TemplateScriptShim($target);
				var $container = $('#' + state.containerID);
				if ($container.length)
					return; // already loaded

				// build form
				$container = _make('div', {
					id: state.containerID,
					append: [
						// header
						_make('h3', { text: self.strings.header }),
						self.createInstructions(_make('p')),

						// form
						_make('form', {
							append: [
								// input list
								_make('ol'),

								// exit button
								_make('div', {
									class: 'tsre-close',
									append: [
										_make('a', {
											title: self.strings.closeEditor,
											href: '#',
											click: function() {
												if(self.config.alwaysVisible)
													self.reset();
												else
													self.remove();
												return false;
											},
											append: [
												_make('img', { src: '//upload.wikimedia.org/wikipedia/commons/thumb/4/47/Noun_project_-_supprimer_round.svg/16px-Noun_project_-_supprimer_round.svg.png' })
											]
										})
									]
								}),

								// field buttons
								_make('div', {
									class: 'tsre-buttons',
									append: [
										// add button
										_make('a', {
											title: self.strings.addPatternsTooltip,
											class: 'tsre-add',
											'href': '#',
											click: function() { _addInputs(); return false; },
											append: [
												_make('img', { src: '//upload.wikimedia.org/wikipedia/commons/thumb/0/0c/Noun_project_-_plus_round.svg/16px-Noun_project_-_plus_round.svg.png' }),
												' ' + self.strings.addPatterns
											]
										}),
										' | ',

										// execute button
										_make('a', { 
											title: self.strings.applyTooltip,
											class: 'tsre-execute',
											href: '#',
											click: function() { self.execute(); return false; },
											append: [
												_make('img', { src: '//upload.wikimedia.org/wikipedia/commons/thumb/5/57/Noun_project_-_crayon.svg/16px-Noun_project_-_crayon.svg.png' }),
												' ' + self.strings.apply
											]
										}),

										// undo button
										_make('span', { 
											class: 'tsre-undo',
											append: [
												' | ',
												_make('a', {
													title: self.strings.undoTooltip,
													href: '#',
													click: function() { self.undo(); return false; },
													append: [
														_make('img', { src: '//upload.wikimedia.org/wikipedia/commons/thumb/1/13/Noun_project_-_Undo.svg/16px-Noun_project_-_Undo.svg.png' }),
														' ' + self.strings.undo
													]
												})
											]
										}).hide(),


										// session buttons
										_make('span', {
											class: 'tsre-session-buttons',
											append: [
												' | ',
												_make('a', {
													title: self.strings.saveTooltip,
													class: 'tsre-save',
													href: '#',
													click: function() { _saveSession(); return false; },
													append: [
														_make('img', { src: '//upload.wikimedia.org/wikipedia/commons/thumb/a/a0/Noun_project_-_USB.svg/16px-Noun_project_-_USB.svg.png' }),
														' ' + self.strings.save
													]
												})
											]
										}),
										' ',
										_make('span', { class: 'tsre-sessions' })
									]
								})
							]
						})
					]
				});
				$container.insertBefore(state.$target);

				// add first pair of input boxes
				_addInputs();
				_populateSessionList();

				// hide sessions if browser doesn't support it
				if (!pathoschild.util.storage.IsAvailable())
					$('.tsre-session-buttons').hide();
			});
		};

		/**
		 * Populate a container with the regex tool instructions.
		 * @param {jQuery} $container The element to populate.
		 */
		self.createInstructions = function($container) {
			// create instructions
			$container
				.attr('class', 'tsre-instructions')
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
		 */
		self.reset = function() {
			self.remove();
			self.create(state.$target);
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
				$('.tsre-undo').show();
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
			$('.tsre-undo').hide();
		};

		/**
		 * Remove the regex editor.
		 */
		self.remove = function() {
			$('#' + state.containerID).remove();
		};


		/*****
		** 0.9 compatibility
		*****/
		self.Create = self.create;
		self.Remove = self.remove;

		return self;
	};
}());