/**
 * Quick utility script to parse the ISO 639 datasets, extract native language names from the English Wikipedia articles,
 * and generate SQL statements to import them into the database. Obviously, these should be reviewed before they're executed.
 */
var pathoschild = pathoschild || {};
pathoschild.ParseIso639Codes = {
	/**
	 * Quote a value for a SQL string.
	 */
	Quote: function($value) {
		return "'" + $value.replace(/'/g, "\\'") + "'";
	},
	
	/**
	 * Import jQuery.
	 */
	ImportJquery: function() {
		var head = document.getElementsByTagName('head')[0];
		var script = document.createElement('script');
		script.setAttribute('src', 'http://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.js');
		script.setAttribute('type', 'text/javascript');
		head.appendChild(script);
	},
	
	//
	/**
	 * Parse the ISO 639-1 and ISO 639-2 codes.
	 * This should be run on <http://www.loc.gov/standards/iso639-2/php/English_list.php>.
	 */
	ParseCodes_Iso6392: function() {
		var _this = this;
		$('table:eq(1) tr:not(:eq(0))').each(function(i, row) {
			// parse
			var $row = $(row);
			var names = $.trim($row.find('td:eq(1)').text());
			var code_1 = $.trim($row.find('td:eq(4)').text());
			var code_2 = $.trim($row.find('td:eq(3)').text());
			var code_2b = '';
			if(code_2.indexOf('/') != -1) {
				var codes = code_2.split('/');
				code_2b = codes[0];
				code_2 = codes[1];
			}
			
			// output ISO 639-1 SQL
			if(code_1)
				console.log('INSERT INTO `u_pathoschild_iso639`.`codes` (`list`, `code`, `name`) VALUES(\'1\', ' + _this.Quote(code_1) + ', ' + _this.Quote(names) + ');');
			
			// output ISO 639-2 SQL
			console.log('INSERT INTO `u_pathoschild_iso639`.`codes` (`list`, `code`, `name`) VALUES(\'2\', ' + _this.Quote(code_2) + ', ' + _this.Quote(names) + ');');
			if(code_2b)
				console.log('INSERT INTO `u_pathoschild_iso639`.`codes` (`list`, `code`, `name`) VALUES(\'2/B\', ' + _this.Quote(code_2b) + ', ' + _this.Quote(names) + ');')
		});
	},
	
	/**
	 * Parse the ISO 639-3 codes.
	 * This should be run on the dataset downloaded from <http://www.sil.org/iso639-3/download.asp>.
	 */
	ParseCodes_Iso6393: function() {
		var text = $(document.body).text();
		var lines = text.split('\n');
		lines = lines.splice(1, lines.length - 1);
		
		for(var i = 0; i < lines.length; i++) {
			// parse
			lines[i] = lines[i].replace(/	/g, '|');
			var columns = lines[i].split('|');
			var code = columns[0];
			var name = columns[6];
			var scope = columns[4];
			var type = columns[5];
			var notes = columns[7];
			
			// convert acronyms
			switch(type) {
				case 'L': type = 'living'; break;
				case 'E': type = 'extinct'; break;
				case 'A': type = 'ancient'; break;
				case 'H': type = 'historic'; break;
				case 'C': type = 'constructed'; break;
				case 'S': type = 'special'; break;
				default:
					throw 'Unrecognized language type code "' + type + '".';
			}
			switch(scope) {
				case 'I': scope = 'individual'; break;
				case 'M': scope = 'macrolanguage'; break;
				case 'C': scope = 'collection'; break;
				case 'D': scope = 'dialect'; break;
				case 'R': scope = 'reserved'; break;
				case 'S': scope = 'special'; break;
				default:
					throw 'Unrecognized language scope code "' + scope + '".';
			}
			
			// output SQL
			console.log('INSERT INTO `u_pathoschild_iso639`.`codes` (`list`, `code`, `name`, `scope`, `type`, `notes`) VALUES(\'3\', ' + this.Quote(code) + ', ' + this.Quote(name) + ', ' + this.Quote(scope) + ', ' + this.Quote(type) + ', ' + this.Quote(notes) + ');');
		}
	},

	/**
	 * Parse the ISO 639 native language names and write SQL statements to the console.
	 * This should be run on <http://en.wikipedia.org/wiki/List_of_ISO_639-1_codes>,
	 * <http://en.wikipedia.org/wiki/List_of_ISO_639-2_codes>, or
	 * <http://en.wikipedia.org/wiki/Special:ExpandTemplates?input={{:ISO_639:a}}{{:ISO_639:b}}{{:ISO_639:c}}{{:ISO_639:d}}{{:ISO_639:e}}{{:ISO_639:f}}{{:ISO_639:g}}{{:ISO_639:h}}{{:ISO_639:i}}{{:ISO_639:j}}{{:ISO_639:k}}{{:ISO_639:l}}{{:ISO_639:m}}{{:ISO_639:n}}{{:ISO_639:o}}{{:ISO_639:p}}{{:ISO_639:q}}{{:ISO_639:r}}{{:ISO_639:s}}{{:ISO_639:t}}{{:ISO_639:u}}{{:ISO_639:v}}{{:ISO_639:w}}{{:ISO_639:x}}{{:ISO_639:y}}{{:ISO_639:z}}&removecomments=1>.
	 * 
	 * @param {int} code The ISO 639 article format to parse (1, 2, or 3).
	 */
	ParseNativeNames: function(code) {
		var _this = this;
		switch(code) {
			case 1:
				$('table.wikitable tr:not(:eq(0))').each(function(i, row) {
					// extract values
					var $row = $(row);
					var code = $.trim($row.find('td:eq(4)').text());
					var nativeName = $.trim($row.find('td:eq(3)').text());
					var nativeNameHtml = $.trim($row.find('td:eq(3)').html());

					// skip if no native name
					if(!nativeName)
						return;

					// write SQL
					console.log('UPDATE `u_pathoschild_iso639`.`codes` SET `native_name` = ' + _this.Quote(nativeName) + ', `native_name_html` = ' + _this.Quote(nativeNameHtml) + ' WHERE `list` = \'1\' AND `code` = ' + _this.Quote(code) + ';');
				});
				break;
				
			case 2:
				$('table.wikitable tr:not(:eq(0))').each(function(i, row) {
					// extract values
					var $row = $(row);
					var code = $.trim($row.find('td:eq(0)').text());
					var nativeName = $.trim($row.find('td:eq(3)').text());
					var nativeNameHtml = $.trim($row.find('td:eq(3)').html());

					// skip if no native name
					if(!nativeName)
						return;

					// write SQL
					console.log('UPDATE `u_pathoschild_iso639`.`codes` SET `native_name` = ' + _this.Quote(nativeName) + ', `native_name_html` = ' + _this.Quote(nativeNameHtml) + ' WHERE `list` IN (\'2\', \'2/B\') AND `code` = ' + _this.Quote(code) + ';');
				});
				break;
				
			case 3:
				$('table.wikitable tr:not(:eq(0))').each(function(i, row) {
					// extract values
					var $row = $(row);
					var code = $.trim($row.find('th:eq(0)').text());
					var nativeName = $.trim($row.find('td:eq(4)').text());
					var nativeNameHtml = $.trim($row.find('td:eq(4)').html());

					// skip if no native name
					if(!nativeName)$
						return;

					// write SQL
					console.log('UPDATE `u_pathoschild_iso639`.`codes` SET `native_name` = ' + _this.Quote(nativeName) + ', `native_name_html` = ' + _this.Quote(nativeNameHtml) + ' WHERE `list` = \'3\' AND `code` = ' + _this.Quote(code) + ';');
				});
				break;
		}
	}
};