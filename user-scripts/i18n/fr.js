/**
 * This is a translation file for these scripts. See https://meta.wikimedia.org/wiki/TemplateScript#Translation
 * for instructions on using translation files.
 */

var pathoschild = pathoschild || {};
pathoschild.i18n = {
	templatescript: {
		defaultHeaderText: 'TemplateScript', // the sidebar header text label for the default group
		regexEditor: 'éditeur regex' // the default 'regex editor' script
	},
	regexeditor: {
		header: 'éditeur regex',     // the header text shown in the form
		search: 'Recherche',         // the search input label
		replace: 'Remplacement',     // the replace input label
		nameSession: 'Entrez un nom pour cette session', // the prompt shown when saving the session
		loadSession: 'Charger la session "{name}"',      // tooltip shown for a saved session, where {name} is replaced with the session name
		deleteSession: 'Supprimer la session "{name}"',  // tooltip shown for the delete icon on a saved session, where {name} is replaced with the session name
		closeEditor: 'Fermer l\'éditeur de regex',      // tooltip shown for the close-editor icon
		addPatterns: 'ajouter des motifs',              // button text
		addPatternsTooltip: 'Ajouter des motifs',       // button tooltip
		apply: 'appliquer',                             // button text
		applyTooltip: 'Effectuer les motifs ci-dessus', // button tooltip
		undo: 'annuler la dernière application',        // button text
		undoTooltip: 'Annuler la dernière application', // button tooltip
		save: 'sauvegarder',                            // button text
		saveTooltip: 'Enregistrer cette session pour une utilisation ultérieure', // button tooltip
		instructions: 'Entrez un nombre quelconque d\'expressions régulières à exécuter. Le motif de recherche peut être « {code|text=texte simple} » ou un motif regex tel que « {code|text=/motif/modificateurs} », et le remplacement peut contenir des groupes de référence tel que « {code|text=$1} » (voir {helplink|text=un tutoriel (en anglais)|title=tutoriel de regex JavaScript|url=http://www.regular-expressions.info/javascript.html}).'
	}
};