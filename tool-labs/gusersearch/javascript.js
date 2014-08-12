var script = {
	/**********
	** Methods
	**********/ 
	'toggleVisibility':function( control, visible ) {
		control.setAttribute(
			'style',
			( visible ? 'display:inline;' : 'display:none;' )
		);
	},
	
	'toggleRegex':function( use_regex ) {
		this.toggleVisibility( document.getElementById('tips-regex'), use_regex );
		this.toggleVisibility( document.getElementById('tips-like'), !use_regex );
	}
};
