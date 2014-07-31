/*


This regex editor lets the user define any number of arbitrary search & replace patterns using regex,
apply them sequentially to a textbox, and save them as sessions in local browser storage. This script
is bundled into TemplateScript.

For more information, see <https://github.com/Pathoschild/Wikimedia-contrib#readme>.


*/
/// <reference path="pathoschild.util.js" />
var pathoschild = pathoschild || {};
(function () {
	'use strict';

	/**
	 * Singleton that lets the user define custom regular expressions using a dynamic form and execute them against the text.
	 * @author Pathoschild
	 * @version 0.9.14
	 * @class
	 * @property {string} ContainerID The unique ID of the regex editor container.
	 * @property {string} UndoText The original text before the last patterns were applied.
	 * @property {jQuery} $target The text input element to which to apply regular expressions.
	 * @property {object} config Configuration settings primarily intended for usage outside MediaWiki.
	*/
	pathoschild.RegexEditor = {
		/*********
		** Properties
		*********/
		_version: '0.9.14',
		ContainerID: 'tsre',
		UndoText: null,
		$target: null,
		config: {
			alwaysVisible: false
		},


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
		 * Load the scripts required by the regex editor.
		 * @param {function} callback The method to invoke (with no arguments) when the dependencies have been loaded.
		 */
		LoadDependencies: function(callback) {
			var invokeCallback = function() { callback.call(pathoschild.RegexEditor); };
			if (pathoschild.util)
				invokeCallback();
			else
				$.ajax({ url:'//tools.wmflabs.org/meta/scripts/pathoschild.util.js', dataType:'script', crossDomain:true, cached:true, success:invokeCallback });
		},

		/**
		 * Construct the regex editor and add it to the page.
		 * @param {jQuery} $target The text input element to which to apply regular expressions.
		 */
		Create: function($target) {
			this.LoadDependencies(function () {
				// initialize state
				var _this = this;
				this.$target = $target;
				var $container = $('#' + this.ContainerID);
				var $warning = $('#' + this.ContainerID + ' .tsre-warning');

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
						.append(this.CreateInstructions(this.Make('p')))

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
									.click(function() {
										if(_this.config.alwaysVisible)
											_this.Reset();
										else
											_this.Remove();
										return false;
									})
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
						.insertBefore(this.$target);

					// add first pair of input boxes
					this.AddInputs();
					this.PopulateSessionList();

					// hide sessions if browser doesn't support it
					if (!pathoschild.util.storage.IsAvailable()) {
						$('.tsre-session-buttons').hide();
					}
				}
			});
		},

		/**
		 * Populate a container with the regex tool instructions.
		 * @param {jQuery} $container The element to populate.
		 */
		CreateInstructions: function($container) {
			$container
				.attr('class', 'tsre-instructions')
				.empty()
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
				.append(').');
			return $container;
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
			if (index === -1)
				return;

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
}());
