<?php
#################################################
## 
## Form class
## Provides static methods for generating form elements.
## 
#################################################
error_reporting( E_ALL );
class Form {
	const SELF_CLOSING = 1;


	#############################
	## Generic element constructor
	#############################
	static function Element( $tag, $attrs, $text, $options = NULL ) {
		$out = "<{$tag}";
		
		foreach( $attrs as $field => $value )
			$out .= " {$field}='{$value}'";
		
		if( $options & self::SELF_CLOSING )
			$out .= " />";
		else
			$out .= ">{$text}</{$tag}>";
		
		return $out;
	}
	

	#############################
	## Checkbox
	#############################
	static function Checkbox( $name, $checked, $attrs = Array(), $options = NULL ) {
		$attrs['type']  = 'checkbox';
		$attrs['name']  = $name;
		$attrs['id']    = $name;
		if( $checked )
			$attrs['checked'] = 'checked';
	
		return self::Element( 'input', $attrs, NULL, $options | self::SELF_CLOSING );
	}
	
	#############################
	## Select (drop-down menu)
	#############################
	static function Select( $name, $selected_index, $select_options, $attrs = Array(), $options = NULL ) {
		$attrs['name'] = $name;
		$attrs['id']   = $name;
		
		/* generate <option> tags */
		$opt_tags = '';
		foreach( $select_options as $index => $value ) {
			$opt_attrs = Array('value' => $index);
			if( $index == $selected_index )
				$opt_attrs['selected'] = 'selected';
			$opt_tags .= self::Element( 'option', $opt_attrs, $value );
		}
		
		/* generate <select> */
		return self::Element( 'select', $attrs, $opt_tags, $options );
	}
	
	static function SelectWiki( $name, $selected_index, $wikis ) {
		function filter($var) {
			return ($var != ''); // remove wikis with no domain
		}
		return Form::Select( $name, $selected_index, array_filter($wikis, 'filter') );
	}
}
