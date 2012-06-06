<?php
require_once( '../backend/modules/Backend.php' );
$backend = Backend::create('Regex toy', '')
	->link( 'stylesheet.css' )
	->link( 'http://pathoschild.github.com/Wikimedia-contrib/pathoschild.regexeditor.css' )
	->link( 'https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.js' )
	->link( 'https://raw.github.com/Pathoschild/Wikimedia-contrib/master/pathoschild.regexeditor.js' )
	->addScript('
		$(function() {
			pathoschild.RegexEditor.config.alwaysVisible = true;
			pathoschild.RegexEditor.Create($("#editor"));
			pathoschild.RegexEditor.CreateInstructions($("#blurb"));
		});
	')
	->header();
echo '<textarea id="editor"></textarea>';
$backend->footer();
