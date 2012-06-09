// <source lang="javascript">
/**
 * StewardScript
 * dynamically loads information and modifies pages for quick stewarding
 * See http://meta.wikimedia.org/wiki/User:Pathoschild/Scripts/StewardScript#Installation
 * by [[user:Pathoschild]] (Jesse Plamondon-Willard)
 */
/*global Element, mw */
mw.loader.load('//meta.wikimedia.org/w/index.php?title=User:Pathoschild/Scripts/StewardScript.css&action=raw&ctype=text/css&r=7', 'text/css');

var pathoschild = pathoschild || {};
pathoschild.StewardScript = {
	version: '2.1.1',
	articleUrl: mw.config.get('wgServer') + mw.config.get('wgArticlePath'),

	Initialize: function() {
		var _this = pathoschild.StewardScript;

		/*****************
		** add steward menu
		*****************/
		/* menu */
		var $list;
		$('#column-one, #mw-panel').append(
			$(document.createElement('div'))
				.addClass('portlet portal expanded')
				.attr('id', 'p-stewardscript')
				.append(
					$(document.createElement('h5')).text('StewardScript')
				)
				.append(
					$(document.createElement('div'))
						.addClass('pBody body')
						//.css({'display': 'block'}) // MediaWiki helpfully auto-collapses with inline CSS
						.append(
							$list = $(document.createElement('ul'))
						)
				)
		);

		/* list items */
		var menuItems = {
			'Steward handbook': ['Steward_handbook', 'Help page for stewards'],
			'user > account': ['Special:CentralAuth', 'View/delete/lock a user\'s global account'],
			'user > rights (local)': ['Special:UserRights', 'Manage local user rights'],
			'user > rights (global)': ['Special:GlobalGroupMembership', 'Manage global user rights'],
			'IP > global block': ['Special:GlobalBlock', 'Globally block an IP'],
			'IP > global unblock': ['Special:GlobalUnblock', 'Globally unblock an IP'],
			'global > groups': ['Special:GlobalGroupPermissions', 'Manage global groups'],
			'global > wikisets': ['Special:EditWikiSets', 'Manage global groups']
		};

		for(var menuName in menuItems) {
			var href = menuItems[menuName][0];
			var title = menuItems[menuName][1];
		
			$list.append(
				$(document.createElement('li'))
					.append(
						$(document.createElement('a'))
							.attr({
								'href': _this.articleUrl.replace('$1', href.replace(' ', '_')),
								'title': title
							})
							.text(menuName)
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
				$linkBox = $('.mw-ipb-conveniencelinks:first');

				// link to stalktoy
				$linkBox
					.append(' | ')
					.append(
						$(document.createElement('a'))
							.text('Stalktoy')
							.attr({
								'href': '//toolserver.org/~pathoschild/stalktoy?target=' + encodeURIComponent(target),
								'title': 'View details about the global user or IP address on all Wikimedia wikis.'
							})
					);

				// link to central auth (if not an IP)
				if(!target.match(/\d+\.\d+\.\d+\.\d+/)) {
					$linkBox
						.append(' | ')
						.append(
							$(document.createElement('a'))
								.text('CentralAuth')
								.attr({
									'href': _this.articleUrl.replace('$1', 'Special:CentralAuth/' + encodeURIComponent(target)),
									'title': 'Manage this user\'s global account.'
								})
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
					.append(
						$(document.createElement('p'))
							.text('See also: ')
							.append(
								$(document.createElement('a'))
									.text('stalktoy')
									.attr({
										'href': '//toolserver.org/~pathoschild/stalktoy?target=' + encodeURIComponent(user),
										'title': 'Pathoschild\'s Stalktoy (comprehensive information about the given user on all Wikimedia wikis)'
									})
							)
							.append(', ')
							.append(
								$(document.createElement('a'))
									.text('crosswiki edits')
									.attr({
										'href': '//toolserver.org/~luxo/contributions/contributions.php?blocks=true&user=' + encodeURIComponent(user),
										'title': 'Luxo\'s User Contributions (lists edits across all Wikimedia wikis)'
									})
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
					.append(
						$(document.createElement('div'))
							.text('StewardScript')
							.addClass('stewardscript-box-title')
					);
				
				/* quick links */
				var shortcuts = {
					'lock': '#mw-centralauth-status-locked-yes, #mw-centralauth-status-hidden-no',
					'lock & hide': '#mw-centralauth-status-locked-yes, #mw-centralauth-status-hidden-list',
					'lock & oversight': '#mw-centralauth-status-locked-yes, #mw-centralauth-status-hidden-oversight'
				};
				var $cell;
				$('#mw-centralauth-admin-status-locked')
					.before(
						$(document.createElement('tr'))
							.attr('id', 'stewardscript-ca-shortcuts')
							.append(
								$(document.createElement('td'))
									.text('Quick status:')
									.addClass('mw-label')
							)
							.append(
								$cell = $(document.createElement('td'))
									.addClass('mw-input')
							)
					);
				
				for(var shortcutName in shortcuts) {
					$cell
						.append(
							$(document.createElement('a'))
								.text(shortcutName)
								.attr({
									'href': '#',
									'data-toggles': shortcuts[shortcutName]
								})
								.click(function(e) {
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
					if(!domain) {
						return;
					}
					var $checkuserStatus = $(document.createElement('span'));
					
					$row.append(
						$(document.createElement('td'))
							.addClass('stewardscript-centralauth-merged-link-cell')
							.append(
								$(document.createElement('a'))
									.text('block')
									.attr('href', '//' + domain + '/wiki/Special:BlockIP/' + encodeURIComponent(user) + '?wpExpiry=indefinite')
							)
							/*// InstantCheckuser no longer works since Wikimedia set X-Frame-Options DENY
							.append(' | ')
							.append(
								$(document.createElement('a'))
									.text('checkuser')
									.attr('href', '#')
									.click(function() {
										pathoschild.StewardScript.InstantCheckuser(domain, user, $checkuserStatus);
										return false;
									})
							)
							.append($checkuserStatus)
							*/
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
				$urReason.after(
					$span = $(document.createElement('span'))
						.attr('id', 'stewardscript-userrights-reasons')
						.addClass('stewardscript-box')
				);
				
				for(var k in reasons) {
					$span.append(
						$(document.createElement('a'))
							.text(k)
							.attr({
								'data-text': reasons[k],
								'href': '#'
							})
							.click(function() {
								$urReason.val($(this).attr('data-text'));
								return false;
							})
					);
				}
				$span.find('a').slice(1).before(' | ');
				break;
				
			/*****************
			** Category:Steward requests
			*****************/
			//case 'Category:Steward_requests':
				//	/*****************
				//	** Display backlog statuses
				//	*****************/
				//	/* configuration */
				//	var labels  = ['', 'attention', 'backlogged'];
				//	var borders = ['#0F0', '#CCC', '#F00'];
				//	var backs   = ['#EFE', '#EEE', '#FEE'];
				//
				//	/* get list of images with sort key '0' */
				//	var req = new Request({
				//		url:wgServer + wgScriptPath + '/api.php?format=xml&action=query&list=categorymembers&cmprop=title|sortkey&cmlimit=50&cmnamespace=0&cmtitle=' + encodeURIComponent(mw.config.get('wgPageName')),
				//		method:'GET',
				//		onSuccess:function(text, xml) {
				//			// extract list
				//			var titles = [];
				//			var keys   = [];
				//			var results = xml.getElementsByTagName('cm');
				//			for(var i=0, key; i<results.length; i++) {
				//				key = results[i].getAttribute('sortkey').match(/(\d|\?)$/);
				//				if(key) {
				//					titles.push(results[i].getAttribute('title'));
				//					keys.push(key[0]);
				//				}
				//
				//			}
				//
				//			/* highlight matching titles */
				//			if(titles.length) {
				//				var items = $('mw-pages').getElementsByTagName('li');
				//				for(var i=0, len=items.length, index, key; i<len && titles.length; i++) {
				//					index = titles.indexOf(items[i].getElementsByTagName('a')[0].title);
				//					key   = keys[index]
				//					if(index>-1) {
				//						items[i].set({'styles':{'background':backs[key], 'border-left':'3px solid ' + borders[key]}});
				//						items[i].adopt(
				//							new Element('span', {
				//								'styles':{'color':'red'},
				//								'text':' ' + labels[key]
				//							})
				//						);
				//						titles.splice(index, 1);
				//					}
				//				}
				//			}
				//		},
				//		onFailure:function(xhr) {
				//			console.log('highlightUnarticled error: ' + xhr.status + ' (' + xhr.statusText + ')');
				//		}
				//	});
				//	req.send();
				//}
		}
	},
	
	/*****************
	** Instant checkuser
	** (this no longer works since Wikimedia set X-Frame-Options DENY)
	*****************/
	/*
	InstantCheckuser: function(domain, user, $span, set) {
		// get data
		var _this = pathoschild.StewardScript;
		var urlUser = (domain == 'meta.wikimedia.org') ? mw.user.name() : (mw.user.name() + '@' + _this.GetPrefix(domain));

		// add loading indicator
		injectSpinner($span[0]);

		// function to update page
		var onSuccess = function() {
			$span.empty();
			if(set) {
				$span
					.append(': ')
					.append(
						$(document.createElement('a'))
							.text('view')
							.attr('href', _this.articleUrl.replace('$1', 'Special:Checkuser?user=' + encodeURIComponent(user) + '&reason=crosswiki+abuse'))
					)
					.append(', ')
					.append(
						$(document.createElement('a'))
							.text('remove')
							.click(function() {
								_this.InstantCheckuser(domain, user, $span, false);
							})
					);
			}
		};
		
		// set user rights
		_this.SetGroups(urlUser, {'checkuser': set}, '[[User:Pathoschild/Scripts/StewardScript|automated]]: ' + (set ? 'checking crosswiki abuse' : 'done'), onSuccess);
	},*/
	
	/*****************
	** Return formatted box to make StewardScript options stand out
	*****************/
	FormatBox: function(tag, id, hideTitle) {
		var $box = $(document.createElement(tag))
			.attr('id', id)
			.addClass('stewardscript-box');
		if(!hideTitle) {
			$box.append(
				$(document.createElement('div'))
					.text('StewardScript')
					.addClass('stewardscript-box-title')
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
	}
	
	/*****************
	** Set user groups remotely
	** (this is a hack; brion doesn't want a crosswiki userrights API)
	** (this no longer works since Wikimedia set X-Frame-Options DENY)
	*****************/
	/*SetGroups: function(user, groups, reason, Handler) {
		// open form
		var _this = pathoschild.StewardScript;
		var step = 0;
		var $frame = $(document.createElement('iframe'))
			.attr({
				'src': _this.articleUrl.replace('$1', 'Special:UserRights?user=' + encodeURIComponent(user))
			})
			.css({'display': 'none'})
			.load(function() {
				switch(step) {
					// make relevant changes, submit
					case 0:
						for(group in groups)
							$frame.contents().find('#wpGroup-' + group).prop('checked', groups[group]);
						$frame.contents().find('#wpReason').val(reason);
						$frame.contents().find('input[name="saveusergroups"]').click();
						break;
						
					// done, call handler
					case 1:
						$frame.remove();
						Handler();
						break;
				}
				step++;
			});
		$('#bodyContent').append($frame);
	}*/
};

$(pathoschild.StewardScript.Initialize);
// </source>
