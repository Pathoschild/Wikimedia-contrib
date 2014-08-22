// <source lang="javascript">
/*#############################################
### Ajax sysop
### 	DO NOT COPY AND PASTE, instead see http://meta.wikimedia.org/wiki/User:Pathoschild/Scripts/Ajax_sysop#Installation
###	(it is occasionally updated with bug fixes and new features).
###	By [[user:Pathoschild]] (Jesse Plamondon-Willard)
##############################################*/
/*************
*** import required libraries
*************/
mw.loader.load('//tools.wmflabs.org/meta/scripts/pathoschild.mootools.js');

/*************
*** initialize
*************/
// configuration options:
// 	disable_patrol:true
if(!ajax_sysop_config)
	var ajax_sysop_config = {};

// global for calling functions on user action
var ajax_sysop = {};

/*************
*** Debug function (never called by code)
*************/
function ajax_sysop_debug() {
	return 'Ajax sysop version: 0.2.10';
}

/*************
*** Main (run each page load)
*************/
function ajax_sysop_main() {
	/*****************
	** Error-checking
	*****************/
	// WikiMooTools not loaded yet
	if(!window.$chk) {
		setTimeout('ajax_sysop_main()', 500);
		return;
	}

	// AJAX not supported
	if(!wfSupportsAjax())
		return;
		
	/*************
	*** Add CSS
	*************/
	appendCSS(
		// formatted box
		  '.as_box { position:relative; margin-top:2em; padding:0.5em; border:1px solid #CCC; background:#EEE; }\n'
		+ '.as_box_title { position:absolute; top:-1em; left:0.5em; padding:0 0.5em; border:1px solid #CCC; border-bottom:0; background:#EEE; }\n'
		// deletion form
		+ '.as_del_sublist li { font-weight:bold; }\n'
		+ '.as_del_sublist li ul { font-weight:normal; }\n'
		+ '.as_del_empty { color:gray; }\n'
		// fix shifting line-heights in lists
		+ '.smallloader img { height:16px; width:16px; }\n'
	);

	/*****************
	** variables
	*****************/
	var body = $('bodyContent');
	var path = mw.config.get('wgServer') + '/wiki/';

	// create trackers
	ajax_sysop.src_fail = '//upload.wikimedia.org/wikipedia/commons/thumb/d/dd/Achtung.svg/32px-Achtung.svg.png';

	/*****************
	** AJAX patrolling
	** 	- one-click patrol link (on Special:Watchlist, Special:Recentchanges, and diff/newpage view with rcid)
	*****************/
	if(!ajax_sysop_config.disable_patrol) {
		/* get list of potential patrol links */
		var text;
		var links = [];
		if(mw.config.get('wgCanonicalSpecialPageName')=='Watchlist' || mw.config.get('wgCanonicalSpecialPageName')=='Recentchanges') {
			text  = 'patrol';
			links = body.getElements('a');
		}
		else if(location.href.match('rcid')) {
			text = 'AJAX';
			var box = body.getElements('.patrollink');
			if(box.length>0)
				links = box[0].getElements('a');
		}

		/* add ajax if patrol links */
		for(var len=links.length, i=0; i<len; i++) {
			var href = links[i].getProperty('href');
			if(href && href.match('rcid')) {
				var rcid = href.replace(/^.*rcid=(\d+).*$/, '$1');

				// add ajax patrol link
				var box = new Element('sup', {'id':'as_patrol_'+rcid});
				box.appendChild(
					new Element('a', {
						'html':'(' + text + ')',
						'href':'javascript:ajax_sysop.patrol("' + rcid + '");',
						'id':'as_patrol_link'
						}
					)
				);
				box.inject(links[i], 'after');
			}
		}

		/* patrol page on click of AJAX link */
		ajax_sysop.patrol = function(rcid) {
			/* get references */
			var span = $('as_patrol_'+rcid);

			// display error message
			function asp_error(text) {
				// re-add AJAX link
				span.text += '(';
				span.adopt(link);
				span.text += ')';

				// display error message
				span.adopt(new Element('br'));
				span.adopt(
					new Element('img', {
						'src':ajax_sysop.src_fail,
						'height':'16px',
						'width':'16px'
					})
				);
				span.text += 'AJAX patrol failed: ' + api.status + ', "' + api.statusText + '".';
			}

			/* show loading indicator */
			span.empty();
			span.adopt(wmt_loader({'class':'smallloader'}));

			/* get edit token */
			if(ajax_sysop.token)
				cPatrol();
			else
				wmt_token({onSuccess:cToken});
			function cToken(token) {
				ajax_sysop.token = token;
				cPatrol();
			}

			/* patrol page */
			function cPatrol() {
				var request = new Request({
					url: mw.config.get('wgServer')+mw.config.get('wgScriptPath')+'/api.php?format=xml&action=patrol&token='+encodeURIComponent(ajax_sysop.token)+'&rcid='+rcid,
					method: 'GET',
					onSuccess: function(responseText, responseXML) {
						// if API errors
						var errors = responseXML.getElementsByTagName('error');
						if(errors.length)
							asp_error('AJAX patrol error: "' + errors[0].getAttribute('info') + '".');
						// else success!
						else {
							span.empty();
							span.adopt(document.createTextNode(' (patrolled)'));
						}
					},
					onFailure: function(xhr) {
						asp_error('AJAX patrol error: ' + xhr.status + ' (' + xhr.statusText + ')');
					}
				});
				request.send();
			}
		}
	}

	/*****************
	** AJAX rollback
	** 	- one-click rollback (on Special:Contributions, action=history)
	*****************/
	if(mw.config.get('wgCanonicalSpecialPageName')=='Contributions' || mw.config.get('wgAction')=='history') {
		/* add links */
		var rbspans = ajax_sysop.rbspans = $$('#bodyContent .mw-rollback-link');
		for(var i=0; i<rbspans.length; i++) {
			rbspans[i].adopt(
				new Element('sup', {
					'id':'ass_rb_' + i
				}).adopt(
					new Element('a', {
						'text':'(AJAX)',
						'href':'javascript:ajax_sysop.rollback(' + i + ');'
					})
				)
			);
		}

		/* rollback functions */
		ajax_sysop.rollback = function(i) {
			/* generate query URL */
			var href  = ajax_sysop.rbspans[i].getElement('a').get('href');
			var query = mw.config.get('wgServer') + mw.config.get('wgScriptPath') + '/api.php?format=xml&action=rollback'
			          + '&title='   + href.match(/title=([^&]+)/)[1]
			          + '&user='    + href.match(/from=([^&]+)/)[1]
			          + '&token='   + href.match(/token=([^&]+)/)[1]
			          + '&markbot=' + ($('bot') && $('bot').get('value') ? 1 : 0);

			/* add placeholder */
			var span = $('ass_rb_' + i);
			span.empty();
			span.adopt(wmt_loader({'class':'smallloader'}));

			/* send query */
			new Request({
				'url':query,
				'method':'POST',
				'onSuccess':function(responseText, responseXML) {
					span.empty();

					// API errors
					var errors = responseXML.getElementsByTagName('error');
					if(errors.length)
						span.set('text', 'AJAX patrol error: "' + errors[0].getAttribute('info') + '".');

					// success
					else
						span.set('text', '(rollbacked)');
				},
				onFailure: function(xhr) {
					span.empty();
					span.set('text', 'AJAX patrol error: ' + xhr.status + ' (' + xhr.statusText + ')');
				}
			}).send();
		}
	}

	/*****************
	** Special contributions
	**	- link to enable/disable bot mode
	*****************/
	if(mw.config.get('wgCanonicalSpecialPageName')=='Contributions') {
		var toggleTo = false;
		var form = $('newbie').getParent('form');

		/* if bot=1, note and remove hidden field */
		if(document.getElementsByName('bot').length) {
			toggleTo = true;
			var field = document.getElementsByName('bot')[0];
			$(field).getParent().removeChild(field);
		}

		/* add options box */
		var box;
		form.adopt(
			// main box
			box = ajax_sysop.formattedBox().adopt(
				// bot mode checkbox
				new Element('input', {
					'type':'checkbox',
					'id':'bot',
					'name':'bot',
					'checked':toggleTo,
					'events':{
						'click':function() {
							var enable = $('bot').get('checked');
							$$('#bodyContent .mw-rollback-link').each(function(link) {
								link.getElementsByTagName('a')[0].href += '&bot=' + (enable?1:0);
							})
						}
					}
				})
			).adopt(
				// bot mode label
				new Element('label', {
					'for':'bot',
					'text':'Bot rollback flag (hide rollbacks on watchlists and Special:RecentChanges)'
				})
			)
		);
	}

	/*****************
	** deletion form
	**	- list of subpages
	**	- block log (on user & user talk pages)
	*****************/
	if(mw.config.get('wgAction')=='delete') {
		var form;
		if(mw.config.get('wgNamespaceNumber')==6)
			form = $('mw-img-deleteconfirm');
		else
			form = $('deleteconfirm').getParent();

		/**********
		* Prepare output
		**********/
		var box = ajax_sysop.formattedBox();
		form.adopt(box);

		/**********
		* determine namespace
		**********/
		if((mw.config.get('wgNamespaceNumber')%2)==0)
			var ns_main = mw.config.get('wgNamespaceNumber');
		else
			var ns_main = mw.config.get('wgNamespaceNumber')-1;
		var ns_talk = ns_main+1;

		/**********
		* List subpages
		**********/
		// prepare output
		var e = {
			/* headers */
			// 'Subpages'
			'h_subpages':new Element('h2', {
				'text':'Subpages'
			}),
			// 'main pages'
			'sh_main':new Element('li', {
				'text':'main pages'
			}),
			// 'talk pages'
			'sh_talk':new Element('li', {
				'text':'talk pages'
			}),

			/* lists */
			// main list
			'ul':new Element('ul', {
				'class':'as_del_sublist'
			}),
			// main pages
			'ul_main':new Element('ul'),
			// talk pages
			'ul_talk':new Element('ul')
		};

		e.ul_main.adopt(wmt_loader({container:new Element('li')}));
		e.ul_talk.adopt(wmt_loader({container:new Element('li')}));
		e.ul.adopt(e.sh_main);
		e.ul.adopt(e.ul_main);
		e.ul.adopt(e.sh_talk);
		e.ul.adopt(e.ul_talk);

		box.adopt(e.box_label);
		box.adopt(e.h_subpages);
		box.adopt(e.ul);

		var list_main = e.ul_main;
		var list_talk = e.ul_talk;
		delete e;

		/* page list */
		function cListPages(titles, box) {
			box.empty();
			var length = titles.length;

			if(length==0)
				box.adopt(new Element('li', {'text':'None', 'class':'as_del_empty'}));
			else {
				for(var i=0; i<length; i++) {
					box.adopt(
						new Element('li').adopt(
							new Element('a', {
								'href':mw.config.get('wgServer') + mw.config.get('wgArticlePath').replace('$1', titles[i]), text:titles[i]
							})
						)
					);
				}
			}
		}
		function cListFailed(xhr, box) {
			box.empty();
			box.adopt(new Element('li', {'text':'error ' + xhr.status + ' (' + xhr.statusText + ').'}));
		}

		// send queries
		wmt_prefixindex({'namespace':ns_main, 'onSuccess':cListPages, 'onFailure':cListFailed, 'subpagesOnly':true, 'passOn':list_main});
		wmt_prefixindex({'namespace':ns_talk, 'onSuccess':cListPages, 'onFailure':cListFailed, 'subpagesOnly':true, 'passOn':list_talk});

		/**********
		* Block log
		**********/
		if(mw.config.get('wgNamespaceNumber')==2 || mw.config.get('wgNamespaceNumber')==3) {
			// prepare output
			box.adopt(new Element('h2', {'text':'Block log'}));
			var log = new Element('ul');
			box.adopt(log);
			log.adopt(new Element('li').adopt(wmt_loader()));

			// add block log after query
			function cBlockLog(responseText, responseXML) {
				log.empty();

				var entries = responseXML.getElementsByTagName('item');
				var length = entries.length;

				if(length==0)
					log.adopt(new Element('li', {'text':'Never blocked', 'class':'as_del_empty'}));

				else {
					// list entries
					var entry;
					for(var i=0; i<length; i++) {
						// alias values
						var blocker   = entries[i].getAttribute('user');
						var timestamp = entries[i].getAttribute('timestamp');
						var action    = entries[i].getAttribute('action');
						var reason    = entries[i].getAttribute('comment');
						if(action=='block') {
							var duration = entries[i].getElementsByTagName('block')[0].getAttribute('duration');
							var flags    = entries[i].getElementsByTagName('block')[0].getAttribute('flags');
						}

						// block details string
						var date = timestamp.replace(/^(\d+-\d+-\d+)T(\d+:\d+).+$/, '$1 $2') + ' ';
						var block_details = '';
						if(action=='block') {
							// block length
							if(duration=='indefinite')
								block_details += ' blocked indefinitely ';
							else
								block_details += ' blocked for ' + duration + ' ';

							// block flags
							if(flags!='')
								block_details += '[' + flags + '] ';
							else
								block_details += ' unblocked ';

							// output
							var item = new Element('li');
							item.adopt(document.createTextNode(' '));
							item.adopt(
								new Element('a', {
									'href':mw.config.get('wgServer') + mw.config.get('wgArticlePath') + 'user:' + blocker,
									'text':blocker
								})
							);
							item.adopt(document.createTextNode(block_details + '('));
							item.adopt(new Element('small', {'text':reason}));
							item.adopt(document.createTextNode(')'));

							log.adopt(item);
						}
					}
				}
			}
			function cBlockLogFailure(xhr) {
				log.empty();
				log.adopt(new Element('li', {'text':'error ' + xhr.status + ' (' + xhr.statusText + ').'}));
			}

			// send query
			new Request({
				'url':mw.config.get('wgServer')+mw.config.get('wgScriptPath')+'/api.php?format=xml&lelimit=500&action=query&list=logevents&letype=block&letitle=User:' + mw.config.get('wgTitle').match(/^[^\/]+/),
				'onSuccess':cBlockLog,
				'onFailure':cBlockLogFailure
			}).send();
		}
	}
}

/*****************
** Return formatted box to make ajax sysop options stand out
*****************/
ajax_sysop.formattedBox = function(o) {
	/* defaults */
	if(!o) var o = {};
	if(!o.id) o.id = '';
	if(!o.tag) o.tag = 'div';
	if(!o.box_styles) o.box_styles = {};
	
	/* create & return element */
	return new Element(o.tag, {
		'id':o.id,
		'class':'as_box',
		'styles':o.box_styles
	}).adopt(
		new Element('div', {
			'text':'Ajax sysop',
			'class':'as_box_title'
		})
	);
}
addOnloadHook(ajax_sysop_main);
// </source>