<?php
require_once( '../backend/modules/Backend.php' );
$backend = new Backend(Array(
	'title'  => 'Regex toy',
	'blurb'  => '',
	'source' => array('index.php')
));

$backend->link( 'stylesheet.css' );
$backend->link( 'http://pathoschild.github.com/Wikimedia-contrib/pathoschild.regexeditor.css' );
$backend->link( 'https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.js' );
$backend->link( 'https://raw.github.com/Pathoschild/Wikimedia-contrib/master/pathoschild.regexeditor.js' );
$backend->addScript('
	$(function() {
		pathoschild.RegexEditor.config.alwaysVisible = true;
		pathoschild.RegexEditor.Create($("#editor"));
		pathoschild.RegexEditor.CreateInstructions($("#blurb"));
	});
');

$backend->header();
echo '<textarea id="editor"></textarea>';
$backend->footer();
