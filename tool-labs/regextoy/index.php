<?php
require_once('../backend/modules/Backend.php');
$backend = Backend::create('Regex toy', '')
    ->link('/regextoy/stylesheet.css')
    ->link('/scripts/pathoschild.regexeditor.js')
    ->addScript('
		$(function() {
			var regexEditor = new pathoschild.RegexEditor({ editor: "#editor", instructions: "#blurb", alwaysVisible: true });
			regexEditor.create($("#editor"));
			regexEditor.createInstructions($("#blurb"));
		});
	')
    ->header();

echo '<textarea id="editor"></textarea>';
$backend->footer();
