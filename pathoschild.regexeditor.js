/*


This regex editor lets the user define any number of arbitrary search & replace patterns using regex,
apply them sequentially to a textbox, and save them as sessions in local browser storage. This script
is bundled into TemplateScript.

For more information, see <https://github.com/Pathoschild/Wikimedia-contrib#readme>.


*/
/* global $ */
/* jshint eqeqeq: true, latedef: true, nocomma: true, undef: true */
var pathoschild = pathoschild || {};
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
		** Fields
		*********/
		self.version = '0.10';
		var state = {
			containerID: 'tsre', // unique ID of the regex editor container
			undoText: null,      // the original text before the last patterns were applied
			$target: null        // the text input element to which to apply regular expressions
		};
		self.config = $.extend({ alwaysVisible: false }, config);


		/*********
		** Private methods
		*********/
		/**
		 * Construct a DOM element.
		 * @param {string} tag The name of the DOM element to construct.
		 */
		var _make = function (tag) {
			return $(document.createElement(tag));
		};

		/**
		 * Load the scripts required by the regex editor.
		 * @param {function} callback The method to invoke (with no arguments) when the dependencies have been loaded.
		 */
		var _loadDependencies = function(callback) {
			var invokeCallback = function() { callback.call(pathoschild.RegexEditor); };
			if (pathoschild.util)
				invokeCallback();
			else
				$.ajax({ url:'//tools-static.wmflabs.org/meta/scripts/pathoschild.util.js', dataType:'script', crossDomain:true, cached:true, success:invokeCallback });
		};

		/**
		 * Add a pair of regular expression input boxes to the regex editor.
		 */
		var _addInputs = function() {
			var id = $('.tsre-pattern').length + 1;
			$('#' + state.containerID + ' ol:first')
				.append(
					_make('li')
					.attr('class', 'tsre-pattern')
					.append(
						_make('label')
						.attr('for', 'tsre-search-' + id)
						.text('Search:')
					)
					.append(
						_make('textarea')
						.attr({ 'name': 'tsre-search-' + id, 'tabindex': id + 100 })
					)
					.append(_make('br'))
					.append(
						_make('label')
						.attr('for', 'tsre-replace-' + id)
						.text('Replace:')
					)
					.append(
						_make('textarea')
						.attr({ 'name': 'tsre-replace-' + id, 'tabindex': id + 101 })
					)
				);
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
					'input': $item.find('textarea:eq(0)').val(),
					'replace': $item.find('textarea:eq(1)').val()
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
			var sessionName = prompt('Enter a name for this session:', '');
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
		var _loadSession = function (sessionName) {
			var patterns = pathoschild.util.storage.Read('tsre-sessions.' + sessionName);
			self.reset();
			for (var i = 1, len = patterns.length; i < len; i++) {
				_addInputs();
			}

			$('.tsre-pattern').each(function (i, item) {
				var $item = $(item);
				$item.find('textarea:eq(0)').val(patterns[i].input);
				$item.find('textarea:eq(1)').val(patterns[i].replace);
			});
		};

		/**
		 * Delete a previously saved set of patterns.
		 * @param {string} sessionName The unique name of the session to delete.
		 */
		var _deleteSession = function (sessionName) {
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

			var $box = $('.tsre-sessions').empty();
			for (var i = 0, len = sessions.length; i < len; i++) {
				$box
					.append(
						_make('span')
						.attr('class', 'tsre-session-tag')
						.append(
							_make('a')
							.text(sessions[i])
							.attr({ 'title': 'Load session "' + sessions[i] + '"', 'href': '#', 'data-key': sessions[i] })
							.click(function() { _loadSession($(this).attr('data-key')); return false; })
						)
						.append(' ')
						.append(
							_make('a')
							.text('x')
							.attr({ 'title': 'Delete session "' + sessions[i] + '"', 'href': '#', 'class': 'tsre-delete-session', 'data-key': sessions[i] })
							.click(function() { _deleteSession($(this).attr('data-key')); return false; })
						)
					);
			}
		};


		/*********
		** Public methods
		*********/
		/**
		 * Construct the regex editor and add it to the page.
		 * @param {jQuery} $target The text input element to which to apply regular expressions.
		 */
		self.create = function($target) {
			_loadDependencies(function() {
				// initialize state
				state.$target = $target;
				var $container = $('#' + state.containerID);
				var $warning = $('#' + state.containerID + ' .tsre-warning');

				// add CSS
				pathoschild.util.AddStyles(
					  '#tsre { position: relative; margin: 0.5em; padding: 0.5em; border: 1px solid #AAA; border-radius: 15px; line-height: normal; }\n'
					+ '.tsre-close { position: absolute; top: 10px; right: 10px; }\n'
					+ '.tsre-warning { color: red; }\n'
					+ '.tsre-sessions { color: #AAA; }\n'
					+ '.tsre-session-tag { border: 1px solid #057BAC; border-radius: 2px; background: #1DA1D8; padding: 0 2px; }\n'
					+ '.tsre-session-tag a { color: #FFF; }\n'
					+ 'a.tsre-delete-session { color: red; font-family: monospace; font-weight: bold; }'
				);

				// display reset warning if already open (unless it's already displayed)
				if ($container.length) {
					if (!$warning.length) {
						$warning = 
							_make('div')
							.attr('class', 'tsre-warning')
							.text('You are launching the regex editor tool, but it\'s already open. Do you want to ')
							.append(
								_make('a')
								.text('reset the form')
								.attr({ 'title': 'reset the form', 'class': 'tsre-reset', 'href': '#' })
								.click(function() { self.reset(); return false; })
							)
							.append(' or ')
							.append(
								_make('a')
								.text('cancel the new launch')
								.attr({ 'title': 'cancel the new launch', 'class': 'tsre-cancel', 'href': '#' })
								.click(function() { $warning.remove(); return false; })
							)
							.append('?')
							.prependTo($container);
					}
				}

					// build form
				else {
					// container
					$container =
						// form
						_make('div')
						.attr('id', state.containerID)
						.append(
							_make('h3')
							.text('Regex editor')
						)

						// instructions
						.append(self.createInstructions(_make('p')))

						// form
						.append(
							_make('form')
							.append(_make('ol')) // inputlist
							// exit button
							.append(
								_make('div')
								.attr('class', 'tsre-close')
								.append(
									_make('a')
									.attr({ 'title': 'Close the regex editor', href: '#' })
									.click(function() {
										if(self.config.alwaysVisible)
											self.reset();
										else
											self.remove();
										return false;
									})
									.append(
										_make('img')
										.attr('src', '//upload.wikimedia.org/wikipedia/commons/thumb/4/47/Noun_project_-_supprimer_round.svg/16px-Noun_project_-_supprimer_round.svg.png')
									)
								)
							)
							// field buttons
							.append(
								_make('div')
								.attr('class', 'tsre-buttons')
								.append(
									_make('a')
									.append(
										_make('img')
										.attr('src', '//upload.wikimedia.org/wikipedia/commons/thumb/0/0c/Noun_project_-_plus_round.svg/16px-Noun_project_-_plus_round.svg.png')
									)
									.append(' add patterns')
									.attr({ 'title': 'Add search & replace boxes', 'class': 'tsre-add', 'href': '#' })
									.click(function() { _addInputs(); return false; })
								)
								.append(' | ')
								.append(
									_make('a')
									.append(
										_make('img')
										.attr('src', '//upload.wikimedia.org/wikipedia/commons/thumb/5/57/Noun_project_-_crayon.svg/16px-Noun_project_-_crayon.svg.png')
									)
									.append(' apply')
									.attr({ 'title': 'Perform the above patterns', 'class': 'tsre-execute', 'href': '#' })
									.click(function() { self.execute(); return false; })
								)
								.append(
									_make('span')
									.attr('class', 'tsre-undo')
									.append(' | ')
									.append(
										_make('a')
										.append(
											_make('img')
											.attr('src', '//upload.wikimedia.org/wikipedia/commons/thumb/1/13/Noun_project_-_Undo.svg/16px-Noun_project_-_Undo.svg.png')
										)
										.append(' undo the last apply')
										.attr({ 'title': 'Undo the last apply', 'href': '#' })
										.click(function() { self.undo(); return false; })
									)
									.hide()
								)
								// session buttons
								.append(
									_make('span')
									.attr('class', 'tsre-session-buttons')
									.append(' | ')
									.append(
										_make('a')
										.append(
											_make('img')
											.attr('src', '//upload.wikimedia.org/wikipedia/commons/thumb/a/a0/Noun_project_-_USB.svg/16px-Noun_project_-_USB.svg.png')
										)
										.append(' save')
										.attr({ 'title': 'Save this session for later use', 'class': 'tsre-save', 'href': '#' })
										.click(function() { _saveSession(); return false; })
									)
									.append(' ')
									.append(
										_make('span')
										.attr('class', 'tsre-sessions')
									)
								)
							)
						)
						.insertBefore(state.$target);

					// add first pair of input boxes
					_addInputs();
					_populateSessionList();

					// hide sessions if browser doesn't support it
					if (!pathoschild.util.storage.IsAvailable()) {
						$('.tsre-session-buttons').hide();
					}
				}
			});
		};

		/**
		 * Populate a container with the regex tool instructions.
		 * @param {jQuery} $container The element to populate.
		 */
		self.createInstructions = function($container) {
			$container
				.attr('class', 'tsre-instructions')
				.empty()
				.append('Enter any number of regular expressions to execute. The search pattern can be like "')
				.append(_make('code').text('search pattern'))
				.append('" or "')
				.append(_make('code').text('/pattern/modifiers'))
				.append('", and the replace pattern can contain reference groups like "')
				.append(_make('code').text('$1'))
				.append('" (see a ')
				.append(
					_make('a')
					.text('tutorial')
					.attr({ 'title': 'JavaScript regex tutorial', 'class': 'external text', 'href': 'http://www.regular-expressions.info/javascript.html', 'target': '_blank' })
				)
				.append(').');
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
			var oldText = state.$target.val();

			// execute
			var patterns = _getPatterns();
			for (var i = 0, len = patterns.length; i < len; i++) {
				state.$target.val(state.$target.val().replace(patterns[i].search, patterns[i].replace));
			}

			if (state.$target.val() !== oldText) {
				state.undoText = oldText;
				$('.tsre-undo').show();
			}
		};

		/**
		 * Revert the text to its state before the regular expressions were last applied.
		 */
		self.undo = function() {
			if (state.$target.val() === state.undoText || state.undoText === null) {
				return;
			}

			state.$target.val(state.undoText);
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