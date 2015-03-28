// <pre><nowiki>
/*************
*** AJAX transclusion table <http://meta.wikimedia.org/wiki/User:Pathoschild/Scripts/AJAX_transclusion_table>
*** by [[m:user:Pathoschild]]
*************/
 
// Documentation:
//    - uses tables with class="attable"
function attIntialize() {
	/* namespace */
	var att = {};
 
	/* find AJAX tables */
	att.alltables = document.getElementsByTagName('table'); // all tables
	att.tables = new Array(); // AJAX tables
	att.count = att.alltables.length; // count all tables
	var x = 0; // count AJAX tables
	for(var i=0; i<att.count; i++) {
		if(att.alltables[i].getAttribute('class')) {
			if(att.alltables[i].getAttribute('class').match(/\battable\b/)) {
				att.tables[x] = att.alltables[i];
				x++;
			}
		}
	}
	if(att.tables.length) {
		/* array of all att rows */
		att.rows = new Array;
 
		/* rebuild tables with tbodies */
		att.countTables = att.tables.length;
		for(var i=0; i<att.countTables; i++) {
			/* get elements */
			att.table = att.tables[i];
			att.trows = att.table.getElementsByTagName('tr');
			att.countRows = att.trows.length;
 
			/* rebuild and fill row array */
			att.tbody = document.createElement('tbody');
			for(var x=0; x<att.countRows; x++) {
				att.countTemp = att.rows.length;
				att.rows[att.countTemp] = att.trows[x].cloneNode(true);
				att.rows[att.countTemp].setAttribute('id','oldrow_'+att.countTemp);
				att.tbody.appendChild(att.rows[att.countTemp]);
			}
			att.table.innerHTML = '';
			att.table.appendChild(att.tbody);
		}
 
		/* create template add/show link */
		att.shlink = document.createElement('a');
		att.shlink.setAttribute('style','font-size:0.9em; cursor:pointer;');
		att.shlink.appendChild(document.createTextNode('[show] '));
 
		/* add show/hide links */
		att.countRows = att.rows.length;
		for(var i=0; i<att.countRows; i++) {
			att.row = att.rows[i];
			att.firstCell = att.row.getElementsByTagName('td')[0];
			// skip rows with no links or cells
			att.link = att.row.getElementsByTagName('a')[0];
			if(att.link && att.link.getAttribute('href').match(/^\/wiki/) && att.firstCell) {
				att.row.setAttribute('id','oldrow_'+i);
				att.newlink = att.shlink.cloneNode(true);
				att.newlink.setAttribute('OnClick','attShow('+i+');');
				att.newlink.setAttribute('id','attlink_'+i);
				att.firstCell.insertBefore(att.newlink,att.firstCell.firstChild);
			}
		}
	}
}
 
function attShow(id) {
	/* get elements */
	var att = {};	
	att.row = document.getElementById('oldrow_'+id);
	att.tbody = att.row.parentNode;
 
	/* build display row */	
	att.colspan = att.row.getElementsByTagName('td').length;
	att.newrow = document.createElement('tr');
	att.newrow.setAttribute('id','newrow_'+id);
	att.newcell = document.createElement('td');
	att.newcell.setAttribute('colspan',att.colspan);
	att.newbox = document.createElement('div');
	att.newbox.setAttribute('style','margin:0.5em; padding:0.5em; border:2px solid gray;');
	att.loader = document.createElement('img');
	att.loader.setAttribute('src','//upload.wikimedia.org/wikipedia/commons/d/d2/Spinning_wheel_throbber.gif');
	att.newbox.appendChild(att.loader);
	att.newcell.appendChild(att.newbox);
	att.newrow.appendChild(att.newcell);
	att.tbody.insertBefore(att.newrow,att.row.nextSibling);
 
	/* get title */
	att.links = att.row.getElementsByTagName('a');
	att.count = att.links.length;
	for(var i=0; i<att.count; i++) {
		if(att.links[i].getAttribute('title')) {
			att.title = att.links[i].getAttribute('title');
			break;
		}
	}
	att.query = sajax_init_object();
	att.url = wgServer+'/wiki/'+att.title+'?action=render';
	att.query.open('GET',att.url,true);
	att.query.send('');
	att.query.onreadystatechange = function() {
		if(att.query.readyState==4) {
			if(att.query.status==200) {
				att.newbox.innerHTML = att.query.responseText;
			}
			else {
				att.loader.setAttribute('img','//upload.wikimedia.org/wikipedia/commons/thumb/d/dd/Achtung.svg/32px-Achtung.svg.png');
			}
		}
	}
	/* update show/hide link */
	att.shlink = document.getElementById('attlink_'+id);
	att.shlink.innerHTML = '[hide] ';
	att.shlink.setAttribute('OnClick','attHide('+id+');');
}
 
function attHide(id) {
	/* get elements */
	var att = {};
	att.row = document.getElementById('newrow_'+[id]);
	att.shlink = document.getElementById('attlink_'+id);
 
	/* remove display row */
	att.row.parentNode.removeChild(att.row);
 
	/* update show/hide link */
	att.shlink.innerHTML = '[show] ';
	att.shlink.setAttribute('OnClick','attShow('+id+');');
}
 
if(!location.href.match('disableatt=true')) {
	attIntialize();
}
// </nowiki></pre>
