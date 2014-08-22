// <source lang="javascript">
/*#############################################
### Ajax Sysop
###	by [[user:Pathoschild]] (Jesse Plamondon-Willard)
### 	see http://meta.wikimedia.org/wiki/User:Pathoschild/Scripts/Ajax_sysop#Installation
##############################################*/
importStylesheetURI('//tools.wmflabs.org/meta/scripts/pathoschild.ajaxsysop.css');

var pathoschild = {
	revision: 70,

	/*############################
	## AJAX Sysop
	## Enhances MediaWiki with AJAX features for sysops and stewards.
	############################*/
	ajax_mw: {
		/*############################
		## Properties & objects
		############################*/
		/*********
		** Properties
		*********/
		config: {
			ajaxPatrol: true,
			ajaxRollback: true,
			botModeRollback: true,
			specialDeleteHelpers: true
		},

		/*############################
		## Initialization methods
		############################*/
		/*********
		** Initialize
		*********/
		Initialize: function () {
			/* load interface enhancements */
			if (this.config.ajaxPatrol) {
				this.InitAjaxPatrol();
			}
			if (this.config.ajaxRollback) {
				this.InitAjaxRollback();
			}
			if (this.config.botModeRollback) {
				this.InitBotModeRollback();
			}
			if (this.config.specialDeleteHelpers) {
				this.InitSpecialDeleteHelpers();
			}
		},

		/*********
		** Initialize AJAX patrol
		*********/
		InitAjaxPatrol: function () {
			/* wrap links in containers */
			var links = $('#bodyContent a[href*="rcid="]');
			links.each(function (i, link) {
				/* get link & rcid */
				link = $(link);
				var rcid = link.attr('href').match(/rcid=(\d+)/)[1];

				/* add container */
				link.wrap(
					$(document.createElement('span'))
					.attr({
						'id': 'ajax-mediawiki-patrol-' + rcid,
						'class': 'ajax-mediawiki-patrol'
					})
				);

				/* add links */
				link.parent().append(
					$(document.createElement('sup'))
					.append(
						$(document.createElement('a'))
						.text('ajax')
						.attr('href', '#')
						.bind('click', function () { pathoschild.ajax_mw.OnPatrolClick(rcid); })
					).append(
						$(document.createElement('span'))
					)
				);
			});
		},

		/*********
		** Initialize AJAX rollback
		*********/
		InitAjaxRollback: function () {
			var links = $('#bodyContent .mw-rollback-link a[href*="from="]');
			links.each(function (i, link) {
				/* get link & values */
				link = $(link);
				var href = link.attr('href');

				var title = href.match(/title=([^&]+)/)[1];
				var user = href.match(/from=([^&]+)/)[1];
				title = decodeURIComponent(title);
				user = decodeURIComponent(user);

				var guid = pathoschild.GetID();

				/* add container */
				link.wrap(
					$(document.createElement('span'))
					.attr({
						'id': 'ajax-mediawiki-rollback-' + guid,
						'class': 'ajax-mediawiki-rollback'
					})
				);

				/* add links */
				link.parent().append(
					$(document.createElement('sup'))
					.append(
						$(document.createElement('a'))
						.text('ajax')
						.attr('href', '#')
						.bind('click', function () { pathoschild.ajax_mw.OnRollbackClick(guid, title, user); })
					).append(
						$(document.createElement('span'))
					)
				);
			});
		},

		/*********
		** Initialize bot-mode rollback (Special:Contributions)
		*********/
		InitBotModeRollback: function () {
			if (mw.config.get( 'wgCanonicalSpecialPageName' ) != 'Contributions') {
				return;
			}

			/* get elements */
			var form = $('#newbie').parents('form').first();
			var botField = $('input[name=bot]').first();

			/* if bot mode is already enabled, remove the hidden field but remember that it's enabled */
			var toggleTo = false;
			if (botField.length) {
				toggleTo = true;
				botField.remove();
			}

			/* add options box */
			form.append(
				this.BuildFormattedBox()
				.append(
					$(document.createElement('input'))
					.attr({
						'type': 'checkbox',
						'id': 'bot',
						'name': 'bot',
						'checked': toggleTo
					})
					.bind('change', function () {
						var enabled = ($('#bot').attr('checked') ? '1' : '0');
						$('.mw-rollback-link a[href*="from="]').each(function (i, link) {
							link = $(link);
							link.attr('href', link.attr('href') + '&bot=' + enabled);
						});
					})
				).append(
					$(document.createElement('label'))
					.attr('for', 'bot')
					.text('Bot rollback flag (hide rollbacks on watchlists and Special:RecentChanges).')
				)
			);
		},

		/*********
		** Initialize Special:Delete helpers
		*********/
		InitSpecialDeleteHelpers: function () {
			/*********
			** Initialize
			*********/
			if (mw.config.get( 'wgAction' ) != 'delete') {
				return;
			}

			/* prepare namespaces */
			var ns_main = mw.config.get( 'wgNamespaceNumber' );
			if (ns_main % 2) {
				ns_main--;
			}
			var ns_talk = ns_main + 1;

			/*********
			** Build layout
			*********/
			var container;
			$('#deleteconfirm, #mw-img-deleteconfirm').first().append(
			/* 'subpages' header */
				container = this.BuildFormattedBox().append(
					$(document.createElement('h2'))
					.text('Subpages')

			/* main subpages */
				).append(
					$(document.createElement('h3'))
					.text('main subpages')
				).append(
					$(document.createElement('ul'))
					.attr('id', 'ajax-mediawiki-subpages-main')
					.append(
						$(document.createElement('li'))
						.append(
							this.BuildLoadingIndicator()
						)
					)

			/* talk subpages */
				).append(
					$(document.createElement('h3'))
					.text('talk subpages')
				).append(
					$(document.createElement('ul'))
					.attr('id', 'ajax-mediawiki-subpages-talk')
					.append(
						$(document.createElement('li'))
						.append(
							this.BuildLoadingIndicator()
						)
					)
				)
			);

			/* prepare block log layout */
			var blockLog = null;
			if (ns_main == 2) { // user or user_talk page
				container.append(
					$(document.createElement('h2'))
					.text('Block log')
				).append(
					blockLog = $(document.createElement('ul'))
					.attr('id', 'ajax-mediawiki-block-log')
					.append(
						$(document.createElement('li'))
						.append(
							this.BuildLoadingIndicator()
						)
					)
				);
			}

			/*********
			** Fetch subpages
			*********/
			function _GetSubpages(ul, namespace, prefix) {
				pathoschild.ajax.FetchPrefixIndex({
					'namespaceNumber': namespace,
					'prefix': prefix,
					'callback': function (pages, query) {
						/* error */
						if (query.Error()) {
							ul.find('li').empty()
							.append(
								$(document.createElement('li'))
								.append(
									$(document.createElement('span'))
									.text(query.Error())
									.addClass('ajax-mediawiki-error-inline')
								)
							);
							return;
						}

						/* list pages */
						ul.empty();
						if (!pages.length) {
							ul
							.addClass('ajax-mediawiki-subpages-none')
							.append(
								$(document.createElement('li'))
								.text('none.')
							);
						}
						else {
							for (var i = 0, len = pages.length; i < len; i++) {
								ul.append(
									$(document.createElement('li'))
									.append(
										$(document.createElement('a'))
										.attr({
											'href': mw.config.get( 'wgServer' ) + mw.config.get( 'wgArticlePath' ).replace('$1', encodeURIComponent(pages[i].title)),
											'alt': pages[i].title
										})
										.text(pages[i].title)
									)
								);
							}
						}
					}
				});
			}
			_GetSubpages($('#ajax-mediawiki-subpages-main'), ns_main, mw.config.get( 'wgTitle' ) + '/');
			_GetSubpages($('#ajax-mediawiki-subpages-talk'), ns_talk, mw.config.get( 'wgTitle' ) + '/');

			/*********
			** Fetch whatlinkshere
			*********/


			/*********
			** Fetch block log
			*********/
			if (blockLog !== null) {
				pathoschild.ajax.FetchBlockLog({
					'callback': function (entries, query) {
						/* error */
						if (query.Error()) {
							blockLog.empty().append(
								$(document.createElement('li'))
								.append(
									$(document.createElement('span'))
									.text(query.Error())
									.addClass('ajax-mediawiki-error-inline')
								)
							);
							return;
						}

						/* success! */
						blockLog.empty();
						for (var i = 0, len = entries.length; i < len; i++) {
							var item = entries[i];
							console.log(i + '/' + len, entries[i]);

							/* add entry */
							var blockDetails, blockFlags;
							blockLog.append(
								$(document.createElement('li'))
							/* date */
								.append(
									$(document.createElement('small'))
									.text(item.timestamp.replace(/(\d+-\d+-\d+)T(\d+:\d+).+/, '$1 $2') + ' ')
								)

							/* blocker */
								.append(
									$(document.createElement('a'))
									.attr({
										'href': pathoschild.parser.BuildLocalUrl(item.title),
										'title': item.title
									})
									.text(item.title)
								)
								.append(' ' + item.action + 'ed ')

							/* block details */
								.append(
									blockDetails = $(document.createElement('span'))
								)

							/* comment */
								.append(' &mdash; ' + pathoschild.parser.ParseWikiLinksIntoHtml(item.comment))

							/* flags */
								.append(' ')
								.append(
									blockFlags = $(document.createElement('small'))
								)
							);
							console.log('	adding block details...');
							if (item.action == 'block') {
								blockDetails.append(
									$(document.createElement('span'))
									.attr({
										'title': item.block.expiry,
										'style': 'border-bottom:1px dotted gray;'
									})
									.text(item.block.duration)
								);
								if (item.block.flags) {
									blockFlags.append('[' + item.block.flags + ']');
								}
							}
						}
					}
				});
			}
		},

		/*############################
		## Helper methods
		############################*/
		BuildFormattedBox: function () {
			return $(document.createElement('div'))
			.addClass('ajax-mediawiki-box')
			.append(
				$(document.createElement('span'))
				.addClass('ajax-mediawiki-box-title')
				.text('Ajax sysop')
			);
		},

		BuildLoadingIndicator: function () {
			return $(document.createElement('img'))
			.attr({
				'src': stylepath + '/common/images/spinner.gif',
				'alt': 'loading...'
			});
		},

		/*############################
		## Event handlers
		############################*/
		/*********
		** AJAX patrol link clicked
		*********/
		OnPatrolClick: function (rcid) {
			/* fetch elements */
			var container = $('#ajax-mediawiki-patrol-' + rcid);
			var span = container.find('sup span').first();

			/* patrol through API */
			span.text(' > loading...');
			pathoschild.ajax.Patrol({
				'rcid': rcid,
				'callback': function (success, query) {
					if (!success) {
						span.text(' > ').append(
							$(document.createElement('span'))
							.addClass('ajax-mediawiki-error-inline')
							.text('\u2718' + query.Error())
						);
					}
					else {
						container.addClass('.ajax-mediawiki-patrol-done');
						span.parent().text(' \u2713');
					}
				}
			});
		},

		/*********
		** AJAX rollback link clicked
		*********/
		OnRollbackClick: function (guid, title, user) {
			/* decode values & fetch elements */
			title = decodeURIComponent(title);
			user = decodeURIComponent(user);

			var container = $('#ajax-mediawiki-rollback-' + guid);
			var span = container.find('sup span');

			/* check bot mode */
			var markAsBot = false;
			if ($('#bot').length) {
				markAsBot = $('#bot').attr('checked');
				alert(markAsBot);
			}

			/* rollback through API */
			span.text(' > loading... ');
			pathoschild.ajax.Rollback({
				'title': title,
				'user': user,
				'markAsBot': markAsBot,
				'callback': function (success, query) {
					if (!success) {
						span.text(' > ').append(
							$(document.createElement('span'))
							.addClass('ajax-mediawiki-error-inline')
							.text('\u2718' + query.Error())
						);
					}
					else {
						container.addClass('.ajax-mediawiki-rollback-done');
						span.parent().text(' \u2713');
					}
				}
			});
		}
	},

	/*###############################################
	### Generic methods
	###############################################*/
	revision: 27, // revision number of this code
	verbose: true, // trace all function calls to console?

	/*********
	* Enforce a schema defining valid arguments and default values on a key:value object.
	* 
	* @param {object} args An argument object to conform to the schema.
	* @param {object} schema An argument schema to apply. Every argument key must have an
	*                 equivalent key in the schema. If a schema key is missing from the args
	*                 object, the default value is assigned.
	* @param {bool}   throwInvalid Indicates whether to throw an error if an invalid argument is
	*                 found in args. (By default, it will silently remove invalid arguments.)
	* @returns {object} The schema-conforming object.
	* @throws  Error    An exception indicating that some arguments were invalid.
	*********/
	ApplyArgumentSchema: function (args, schema, throwInvalid) {
		/* check key validity */
		for (var i in args) {
			if (typeof (schema[i]) == typeof (undefined)) {
				if (throwInvalid) {
					var valid_args = [];
					for (var x in schema) {
						valid_args.push(x);
					}
					throw new Error('Invalid argument "' + i + '"; valid arguments are [' + valid_args.toString() + '].');
				}
				delete args[i];
			}
		}

		/* enforce default values */
		for (var n in schema) {
			if (typeof (args[n]) == typeof (undefined) || args[n] === null) {
				args[n] = schema[n];
			}
		}

		/* return schema-conformant object */
		return args;
	},

	/*********
	* Deep-copy the properties of an object into a new object.
	* 
	* @param {object} obj An object to copy properties from.
	* @returns {object} A duplicate of the object given.
	*********/
	DuplicateObject: function (obj) {
		return obj; // good idea, but implement later
		// /* array */
		// if( obj instanceof Array ) {
		// var out = [];
		// for( var i = 0, len = obj.length; i < len; i++ )
		// out.push( this.DuplicateObject(obj[i]) );
		// return out;
		// }

		// /* object */
		// else if( obj instanceof Object ) {
		// var out = {};
		// for ( var i in obj )
		// out[i] = this.DuplicateObject(obj[i]);
		// return out;
		// }

		// /* scalar */
		// else
		// return obj;
	},

	/*********
	* Adopt the properties of an object.
	* 
	* @param {object} host An object to copy properties into.
	* @param {object} source An object to copy properties from.
	* @returns {object} the modified host object.
	*********/
	AdoptProperties: function (host, source) {
		var _source = this.DuplicateObject(source);
		for (var i in _source) {
			host[i] = _source[i];
		}
		return host;
	},

	/*********
	* Gets a sequential ID, used when a unique ID is needed within a controlled context.
	* 
	* @returns {Number} The next unused ID, starting at 0.
	*********/
	_guid: -1,
	GetID: function () {
		return ++this._guid;
	},

	/*###############################################
	### Text and parse methods
	###############################################*/
	parser: {
		/*********
		* Perform a strictly literal search.
		* 
		* @param {string} text The text that will be searched for a match.
		* @param {string} search The string to find in the text.
		* 
		* @returns {string} The first literal match.
		*********/
		LiteralSearch: function (text, search) {
			var index = text.indexOf(search);
			var length = search.length;
			return text.substr(index, index + length);
		},

		/*********
		* Perform a strictly literal replace of one match.
		* 
		* @param {string} text The text that will be modified.
		* @param {string} search The string to find in the text.
		* @param {string} replace The string to substitute for the match.
		* 
		* @returns {string} The modified string.
		*********/
		LiteralReplace: function (text, search, replace) {
			var index = text.indexOf(search);
			if (index == -1) {
				return text;
			}
			var length = search.length;
			return text.substr(0, index) + replace + text.substr(index + length);
		},

		/*********
		* Build a local URL.
		* 
		* @param {string} targetTitle The title of the page to link to.
		* 
		* @returns {string} The equivalent string with HTML links.
		*********/
		BuildLocalUrl: function (targetTitle) {
			return mw.config.get( 'wgServer' ) + mw.config.get( 'wgScript' ) + '?title=' + encodeURIComponent(targetTitle);
		},

		/*********
		* Parse MediaWiki links into HTML links (eg, in edit summaries or block reasons)
		* 
		* @param {string} text The 
		* 
		* @returns {string} The equivalent string with HTML links.
		*********/
		ParseWikiLinksIntoHtml: function (text) {
			/* extract links */
			var links = text.match(/\[\[[^\]]+\]\]/g);
			if (!links || !links.length) {
				return text;
			}

			/* parse each link */
			for (var i = 0, len = links.length; i < len; i++) {
				/* extract link target & text */
				var parts = links[i].toString().match(/\[\[([^\]]+?)(?:\|([^\]]+))?\]\]/);
				var link_title = parts[1];
				var link_text = parts[2] || link_title;

				/* build link */
				var link = '<a' + ' href="' + this.BuildLocalUrl(link_title) + '"' + ' title="' + link_title.replace('"', '\\"') + '"' + '>' + link_text.replace(/^User:/, '') + '</a>';

				/* replace link */
				text = this.LiteralReplace(text, links[i], link);
			}

			return text;
		}
	},

	/*###############################################
	### AJAX methods
	###############################################*/
	ajax: {
		/*###############################################
		### Query class
		###############################################*/
		/*********
		* Generic class instantiated to encapsulate a single generic API query, with relevant
		* parsing and error-handling. Executes the query immediately upon instantiation, with the
		* properties passed in the args object.
		* 
		* @params {object} args An argument object containing any of the following keys:
		*    (function) callback [= null]
		*    The function to invoke when the query completes. This callback will be passed two
		*    arguments, response and query. The response will be a preparsed representation of
		*    the response text, normally a JSON object. The query will be this Query instance.
		* 
		*    (object) context [= null]
		*    An arbitrary object accessible to the callback as query.context; default null.
		* 
		*    (str) method [= 'GET']
		*	  The HTTP method to use when submitting the data.
		* 
		*    (str) url [= mw.config.get( 'wgServer' ) + mw.config.get( 'wgScriptPath' ) + '/api.php']
		*    The URL of the page to query.
		* 
		*    (object) data [= {}]
		*    The query data to submit to the URL, as a key:value object.
		* 
		*    (string) format [= 'json']
		*    The API format to request. The result will be parsed automatically if known.
		* 
		*    (bool) deadQuery [= false]
		*    Indicates whether to prevent dispatching the query. This should only be used when
		*    you need a query object, but don't need to execute a query.
		* 
		* @returns Query instance.
		* @notes Upon query completion, the following additional properties will be populated:
		*    (object) response
		*    The parsed representation of the query response.
		* 
		*    (object) xhr
		*    The XmlHttpRequest object representing the query state, or the equivalent vendor
		*    object for the client's browser.
		**********/
		Query: function (args) {
			/* set properties */
			pathoschild.ApplyArgumentSchema(
				args,
				{
					'callback': null,
					'context': null,

					'url': mw.config.get( 'wgServer' ) + mw.config.get( 'wgScriptPath' ) + '/api.php',
					'data': {},
					'method': 'GET',
					'format': 'json',
					'deadQuery': false
				},
				true
			);
			pathoschild.AdoptProperties(this, args);

			this.data.format = this.format;
			this.response = null;
			this.xhr = null;
			this._error = null;

			/* set methods */
			this.Callback = pathoschild.ajax._Query_Callback;
			this.Query = pathoschild.ajax._Query_Query;
			this.Error = pathoschild.ajax._Query_Error;

			/* launch query */
			if (!this.deadQuery) {
				this.Query();
			}
		},

		/*********
		** Callback
		** Invokes the callback, if one is defined.
		*********/
		_Query_Callback: function () {
			if (this.callback) {
				this.callback(this.response, this);
			}
			return this;
		},

		/*********
		** executes a request to the API.
		*********/
		_Query_Query: function () {
			var _this = this;
			$.ajax({
				type: this.method,
				url: this.url,
				data: this.data,
				error: function (xhr, textStatus, error) {
					_this.xhr = xhr;
					_this.response = null;
					_this._error = error;
					_this.Callback();
				},
				success: function (response, textStatus, xhr) {
					_this.xhr = xhr;
					_this.response = response;
					_this._error = null;
					_this.Callback();
				}
			});
			return this;
		},

		/*********
		** Get a human-readable error message.
		*********/
		_Query_Error: function () {
			if (this._error === null) {
				if (this.xhr && this.xhr.status != 200) { // HTTP error
					this._error = this.xhr.status + ': ' + this.xhr.statusText;
				}
				else if (this.response && this.response.error) { // API error
					this._error = this.response.error.code + ': ' + this.response.error.info;
				}
				else {
					this._error = '';
				}
			}
			return this._error;
		},


		/*############################
		## Generic queries
		############################*/
		/*********
		* Get a token from the API for a write action.
		* 
		* @params {object} args An argument object containing any of the following keys:
		*    (str) type [= 'edit']
		*    The type of token to request from the toolserver.
		* 
		*    (str) title [= 'Sandbox']
		*    The name of the page to get a token for (not relevant for editing).
		* 
		*    (function) callback [= null]
		*    The function to invoke when the query completes, with the signature
		*    callback( token, query ).
		* 
		*    (object) context [= null]
		*    An arbitrary object accessible to the callback as query.context; default null.
		*********/
		_token_cache: {},
		tokenTypes: {
			EDIT: 'edit',
			PATROL: 'patrol',
			ROLLBACK: 'rollback'
		},
		GetToken: function (args) {
			/* get arguments */
			pathoschild.ApplyArgumentSchema(
				args,
				{
					'type': this.tokenTypes.EDIT,
					'title': 'Sandbox',
					'callback': null,
					'context': null
				},
				true
			);
			if (args.type == this.tokenTypes.EDIT) {
				args.title = 'Sandbox'; // optimize, edit tokens are title-independent
			}

			/* get token from cache */
			if (this._token_cache[args.type] && this._token_cache[args.type][args.title]) {
				args.callback(this._token_cache[args.type][args.title], new pathoschild.ajax.Query({ 'context': args.context, 'deadQuery': true }));
				return;
			}

			/* query API */
			var _this = this;
			if (!this._token_cache[args.type]) {
				this._token_cache[args.type] = {};
			}

			if (args.type == this.tokenTypes.ROLLBACK) {
				new pathoschild.ajax.Query({
					'data': {
						'action': 'query',
						'prop': 'revisions',
						'titles': args.title,
						'rvtoken': 'rollback',
						'rvprop': ''
					},
					'callback': function (data, query) {
						if (!query.Error()) {
							for (var i in data.query.pages) {
								_this._token_cache[args.type][args.title] = data.query.pages[i].revisions[0].rollbacktoken;
								break;
							}
						}
						if (args.callback) {
							args.callback(_this._token_cache[args.type][args.title], query);
						}
					},
					'context': args.context
				});
			}
			else {
				new pathoschild.ajax.Query({
					'data': {
						'action': 'query',
						'prop': 'info',
						'indexpageids': '1',
						'intoken': 'edit',
						'titles': args.title
					},
					'callback': function (data, query) {
						if (!query.Error()) {
							var pageId = data.query.pageids[0];
							_this._token_cache[args.type][args.title] = data.query.pages[pageId].edittoken;
						}
						if (args.callback) {
							args.callback(_this._token_cache[args.type][args.title], query);
						}
					},
					'context': args.context
				});
			}
		},

		/*********
		* Patrol a revision.
		* 
		* @params {object} args An argument object containing any of the following keys:
		*    (str) rcid [= null]
		*    The RCID of the revision to patrol.
		* 
		*    (function) callback [= null]
		*    The function to invoke when the query completes, with the signature
		*    callback( success_bool, query ).
		* 
		*    (object) context [= null]
		*    An arbitrary object accessible to the callback as query.context; default null.
		*********/
		Patrol: function (args) {
			/* get arguments */
			pathoschild.ApplyArgumentSchema(
				args,
				{
					'rcid': null,
					'callback': null,
					'context': null
				},
				true
			);

			/* query API */
			this.GetToken({
				'type': pathoschild.ajax.tokenTypes.EDIT,
				'callback': function (token, query) {
					new pathoschild.ajax.Query({
						'data': {
							'action': 'patrol',
							'token': token,
							'rcid': args.rcid
						},
						'callback': function (data, query) {
							if (args.callback) {
								args.callback(!query.Error(), query);
							}
						},
						'context': args.context
					});
				}
			});
		},

		/*********
		* Rollback latest revisions to a page by a user.
		* 
		* @params {object} args An argument object containing any of the following keys:
		*    (str) title [= null]
		*    The title of the page to rollback.
		* 
		*    (str) user [= null]
		*    The name of the user to rollback.
		* 
		*    (bool) markAsBot [= false]
		*    Indicates whether to hide the rollback on recentchanges and watchlists.
		* 
		*    (function) callback [= null]
		*    The function to invoke when the query completes, with the signature
		*    callback( success_bool, query ).
		* 
		*    (object) context [= null]
		*    An arbitrary object accessible to the callback as query.context; default null.
		*********/
		Rollback: function (args) {
			/* get arguments */
			pathoschild.ApplyArgumentSchema(
				args,
				{
					'title': null,
					'user': null,
					'markAsBot': false,
					'callback': null,
					'context': null
				},
				true
			);

			/* query API */
			this.GetToken({
				'type': pathoschild.ajax.tokenTypes.ROLLBACK,
				'title': args.title,
				'callback': function (token, query) {
					new pathoschild.ajax.Query({
						'method': 'POST',
						'data': {
							'action': 'rollback',
							'token': token,
							'title': args.title,
							'user': args.user,
							'markbot': (args.markAsBot ? '1' : '0')
						},
						'callback': function (data, query) {
							if (args.callback) {
								args.callback(!query.Error(), query);
							}
						},
						'context': args.context
					});
				}
			});
		},

		/*********
		* Fetch a list of pages matching a prefix.
		* 
		* @params {object} args An argument object containing any of the following keys:
		*    (Number) namespaceNumber [= null]
		*    The numeric ID of the namespace to get the pages from.
		* 
		*    (str) prefix [= null]
		*    The prefix matched against page titles.
		* 
		*    (function) callback [= null]
		*    The function to invoke when the query completes, with the signature
		*    callback( success_bool, query ).
		* 
		*    (object) context [= null]
		*    An arbitrary object accessible to the callback as query.context; default null.
		*********/
		FetchPrefixIndex: function (args) {
			/* get arguments */
			pathoschild.ApplyArgumentSchema(
				args,
				{
					'namespaceNumber': mw.config.get( 'wgNamespaceNumber' ),
					'prefix': mw.config.get( 'wgTitle' ),
					'callback': null,
					'context': null
				},
				true
			);

			/* collect pages */
			var pages = [];
			new pathoschild.ajax.Query({
				'data': {
					'action': 'query',
					'list': 'allpages',
					'apprefix': args.prefix,
					'aplimit': 500,
					'apnamespace': args.namespaceNumber
				},
				'callback': function (data, query) {
					if (!query.Error()) {
						data = data.query.allpages;
					}
					if (args.callback) {
						args.callback(data, query);
					}
				},
				'context': args.context
			});
		},

		/*********
		* Fetch a user's block history.
		* 
		* @params {object} args An argument object containing any of the following keys:
		*    (str) user [= null]
		*    The name of the user (including 'User:' prefix) to match against page titles.
		* 
		*    (function) callback [= null]
		*    The function to invoke when the query completes, with the signature
		*    callback( success_bool, query ).
		* 
		*    (object) context [= null]
		*    An arbitrary object accessible to the callback as query.context; default null.
		*********/
		FetchBlockLog: function (args) {
			/* get arguments */
			pathoschild.ApplyArgumentSchema(
				args,
				{
					'user': mw.config.get( 'wgPageName' ).match(/[^\/]+/, '').toString(),
					'callback': null,
					'context': null
				},
				true
			);

			/* get log entries */
			new pathoschild.ajax.Query({
				'data': {
					'action': 'query',
					'list': 'logevents',
					'letype': 'block',
					'letitle': args.user,
					'lelimit': 500
				},
				'callback': function (data, query) {
					/* parse */
					if (!query.Error()) {
						data = data.query.logevents;
					}
					if (args.callback) {
						args.callback(data, query);
					}
				},
				'context': args.context
			});
		}
	},

	/*###############################################
	### Debug methods
	###############################################*/
	debug: {
		/*********
		** Return a string representation of an object
		*********/
		GetObjectSchema: function (obj) {
			var str = '{\n';
			for (var i in obj) {
				str += '	' + i + ' => ' + obj[i] + ',\n';
			}
			return str + '}';
		}
	}
};
pathoschild.ajax_mw.Initialize();
// </source>