/*jshint bitwise:true, eqeqeq:true, forin:false, immed:true, latedef:true, loopfunc:true, noarg:true, noempty:true, nonew:true, smarttabs:true, strict:true, trailing:true, undef:true*/
/*global mw */
var pathoschild = pathoschild || {};

(function() {
	"use strict";
	/**
	 * Extends the user interface for Wikimedia stewards' convenience.
	 * @see https://github.com/Pathoschild/Wikimedia-contrib#readme
	 */
	pathoschild.StewardScript = {
		version: '2.2.0',
	
		Initialize: function() {
			var _this = pathoschild.StewardScript;
			var articleUrl = mw.config.get('wgServer') + mw.config.get('wgArticlePath');

			mw.util.addCSS(
				  // generic
				  '#mw-panel.collapsible-nav #p-stewardscript .body { display:block; }'
				+ '.stewardscript-box { position:relative; margin-top:2em; padding:0.5em; border:1px solid #CCC; background:#EEE; }'
				+ '.stewardscript-box-title { position:absolute; top:-1em; left:0.5em; padding:0 0.5em; border:1px solid #CCC; border-bottom:0; background:#EEE; }'

				  // Special:CentralAuth
				+ '#mw-centralauth-admin-status-locked, #mw-centralauth-admin-status-hidden { color:gray; font-size:smaller; }'
				+ '.stewardscript-centralauth-merged-link-cell { font-size:smaller; }'

				  // Special:UserRights
				+ '#stewardscript-userrights-reasons { font-size:smaller; }'
			);

			/*****************
			** add steward menu
			*****************/
			/* menu */
			var $list;
			$('#column-one, #mw-panel').append(this
				.Make('div')
					.attr({ 'class':'portlet portal expanded', id:'p-stewardscript' })
					.append(this
						.Make('h5')
						.text('StewardScript')
					)
					.append(this
						.Make('div')
						.attr('class', 'pBody body')
						.append($list = this.Make('ul'))
					)
			);

			/* list items */
			var menu = [
				{name:'Steward handbook', page:'Steward_handbook', desc:'Help page for stewards'},
				{name:'user > account', page:'Special:CentralAuth', desc:'View/delete/lock a user\'s global account'},
				{name:'user > rights (local)', page:'Special:UserRights', desc:'Manage local user rights'},
				{name:'user > rights (global)', page:'Special:GlobalGroupMembership', desc:'Manage global user rights'},
				{name:'IP > global block', page:'Special:GlobalBlock', desc:'Globally block an IP'},
				{name:'IP > global unblock', page:'Special:GlobalUnblock', desc:'Globally unblock an IP'},
				{name:'global > groups', page:'Special:GlobalGroupPermissions', desc:'Manage global groups'},
				{name:'global > wikisets', page:'Special:EditWikiSets', desc:'Manage global groups'}
			];
			for (var i = 0, len = menu.length; i < len; i++) {
				$list.append(this
					.Make('li')
					.append(this
						.Make('a')
						.text(menu[i].name)
						.attr({ href: _this.articleUrl.replace('$1', menu[i].page), title: menu[i].desc })
					)
				);
			}
		
			/*****************
			** Page actions
			*****************/
			switch(mw.config.get('wgCanonicalSpecialPageName')) {
				case 'Block':
					// get preloaded user or IP
					var target = $('#mw-bi-target').val();
					if(!target)
						break;
					var $linkBox = $('.mw-ipb-conveniencelinks:first');

					// link to stalktoy
					$linkBox
						.append(' | ')
						.append(this
							.Make('a')
							.text('Stalktoy')
							.attr({ href:'//toolserver.org/~pathoschild/stalktoy?target=' + encodeURIComponent(target), title:'View details about the global user or IP address on all Wikimedia wikis.' })
						);

					// link to central auth (if not an IP)
					if(!target.match(/\d+\.\d+\.\d+\.\d+/)) {
						$linkBox
							.append(' | ')
							.append(this
								.Make('a')
								.text('CentralAuth')
								.attr({ href:_this.articleUrl.replace('$1', 'Special:CentralAuth/' + encodeURIComponent(target)), title:'Manage this user\'s global account.' })
							);
					}

					break;

					/*****************
					** Special:CentralAuth
					*****************/
				case 'CentralAuth':
					/* read-only? */
					if($('#delete-reason').length === 0)
						break;

					/* references & data */
					var user  = pathoschild.StewardScript.user = $('#bodyContent input[name="target"]').val();
					var token = $('#bodyContent input[name="wpEditToken"]')[0].value;

					var $caReason = $('#bodyContent input[name="wpReason"]');
					var $form   = $caReason.closest('form');
					var $submit = $form.find('input[type="submit"]');
				
					/*****************
					** See-also links
					*****************/
					$('#mw-centralauth-info')
						.append(this
							.Make('p')
							.text('See also: ')
							.append(this
								.Make('a')
								.text('stalktoy')
								.attr({ href:'//toolserver.org/~pathoschild/stalktoy?target=' + encodeURIComponent(user), title:'Pathoschild\'s Stalktoy (comprehensive information about the given user on all Wikimedia wikis)'})
							)
							.append(', ')
							.append(this
								.Make('a')
								.text('crosswiki edits')
								.attr({ href:'//toolserver.org/~luxo/contributions/contributions.php?blocks=true&user=' + encodeURIComponent(user), title:'Luxo\'s User Contributions (lists edits across all Wikimedia wikis)'})
							)
						);
				
					/*****************
					** Prefill reason
					*****************/
					$('#wpReasonList option[value="Cross-wiki abuse"]')
						.val('crosswiki abuse')
						.text('crosswiki abuse')
						.prop('selected', 1);
			
					/*****************
					** 'Quick access' section
					*****************/
					/* container */
					$form
						.addClass('stewardscript-box')
						.append(this
							.Make('div')
							.text('StewardScript')
							.attr({ 'class':'stewardscript-box-title' })
						);
				
					/* quick links */
					var shortcuts = {
						'lock': '#mw-centralauth-status-locked-yes, #mw-centralauth-status-hidden-no',
						'lock & hide': '#mw-centralauth-status-locked-yes, #mw-centralauth-status-hidden-list',
						'lock & oversight': '#mw-centralauth-status-locked-yes, #mw-centralauth-status-hidden-oversight'
					};
					var $cell;
					$('#mw-centralauth-admin-status-locked')
						.before(this
							.Make('tr')
							.attr({ id:'stewardscript-ca-shortcuts' })
							.append(this
								.Make('td')
								.text('Quick status:')
								.attr({ 'class':'mw-label' })
							)
							.append($cell = this
								.Make('td')
								.attr({ 'class':'mw-input' })
							)
						);
				
					for(var shortcutName in shortcuts) {
						$cell
							.append(this
								.Make('a')
								.text(shortcutName)
								.attr({ href:'#', 'data-toggles': shortcuts[shortcutName] })
								.click(function() {
									$($(this).attr('data-toggles')).prop('checked', 1);
									return false;
								})
							);
					}
					$cell.find('a').slice(1).before(' | ');

					/*****************
					** Add links to merged-accounts list
					*****************/
					$('#mw-centralauth-merged tbody tr').each(function(i, row) {
						var $row = $(row);
						var $link  = $row.find('a:first');
						var domain = $link.text();
						if(!domain)
							return;
						var $checkuserStatus = this.Make('span');
					
						$row.append(this
							.Make('td')
							.attr({ 'class':'stewardscript-centralauth-merged-link-cell' })
							.append(this
								.Make('a')
								.text('block')
								.attr({ href: '//' + domain + '/wiki/Special:BlockIP/' + encodeURIComponent(user) + '?wpExpiry=indefinite' })
							)
						);
					});
					break;
				
					/*****************
					** Special:UserRights
					*****************/
				case 'UserRights': // 1.18
				case 'Userrights': // 1.19+
					var $urReason = $('#wpReason');

					/* readonly? */
					if($urReason.length === 0)
						break;
				
					/*****************
					** Add quick reason menu
					*****************/
					var reasons = {
						'request': '[[Steward requests/Permissions|request]]',
						'bot policy': '[[m:standard bot policy|standard bot policy]]',
						'Ø': ''
					};
					var $span;
					$urReason.after($span = this
						.Make('span')
						.attr( {id:'stewardscript-userrights-reasons', 'class':'stewardscript-box' })
					);
				
					for(var k in reasons) {
						$span.append(this
							.Make('a')
							.text(k)
							.attr({ 'data-text': reasons[k], href: '#' })
							.click(function() {
								$urReason.val($(this).attr('data-text'));
								return false;
							})
						);
					}
					$span.find('a').slice(1).before(' | ');
					break;
			}
		},
		
		/*****************
		** Return formatted box to make StewardScript options stand out
		*****************/
		FormatBox: function(tag, id, hideTitle) {
			var $box = this.Make(tag)
				.attr({ id:id, 'class':'stewardscript-box' });
			if(!hideTitle) {
				$box.append(this
					.Make('div')
					.text('StewardScript')
					.attr({ 'class':'stewardscript-box-title' })
				);
			}
		},
	
		/*****************
		** Parse domain into database prefix
		*****************/
		GetPrefix: function(url) {
			/* get subdomain & domain */
			url = url.match(/([^\.]+)\.([^\.]+).org/);
			var lang = url[1];
			var dom  = url[2];

			/* exit if invalid */
			if(!lang || !dom)
				return null;

			/* normalize */
			lang = lang.replace(/-/g,'_');
			dom  = dom.replace(/wiki[mp]edia/, 'wiki');

			/* return */
			return lang + dom;
		},

		/**
		 * Construct a DOM element.
		 * @param {string} tag The name of the DOM element to construct.
		 */
		Make: function(tag) {
			return $(document.createElement(tag));
		}
	};

	$(function() { pathoschild.StewardScript.Initialize(); });
}());