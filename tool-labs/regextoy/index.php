<?php
require_once( '../backend/modules/Backend.php' );
$backend = Backend::create('Regex toy', '')
	->link( '/regextoy/stylesheet.css' )
	->link( '/scripts/pathoschild.regexeditor.js' )
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
