/**
 * This is a translation file for these scripts. See https://meta.wikimedia.org/wiki/TemplateScript#Translation
 * for instructions on using translation files.
 */

var pathoschild = pathoschild || {};
pathoschild.i18n = {
	templatescript: {
		defaultHeaderText: 'TemplateScript', // the sidebar header text label for the default group
		regexEditor: 'Editor de regex' // the default 'regex editor' script
	},
	regexeditor: {
		header: 'Editor de expressões regulares (regex)', // the header text shown in the form
		search: 'Localizar',       // the search input label
		replace: 'Substituir',     // the replace input label
		nameSession: 'Forneça um nome para esta sessão', // the prompt shown when saving the session
		loadSession: 'Carregar esta sessão "{name}"',         // tooltip shown for a saved session, where {name} is replaced with the session name
		deleteSession: 'Apagar sessão "{name}"',     // tooltip shown for the delete icon on a saved session, where {name} is replaced with the session name
		launchConflict: 'Você está carregando a ferramenta para editar expressões regulares, mas ela já está aberta. Deseja {resetForm|text=restaurar o formulário} ou {cancelForm|text=cancelar o formulário}?', // the message shown when relaunching the form, where {resetForm} and {cancelForm} will be replaced with appropriate form elements.
		closeEditor: 'Fechar o editor de expressões regulares',        // tooltip shown for the close-editor icon
		addPatterns: 'incluir padrões',                  // button text
		addPatternsTooltip: 'Incluir caixas de localizar e substituir', // button tooltip
		apply: 'aplicar',                               // button text
		applyTooltip: 'Executar os padrões acima',   // button tooltip
		undo: 'desfazer a última aplicação',                  // button text
		undoTooltip: 'Desfazer a última aplicação',           // button tooltip
		save: 'salvar',                                 // button text
		saveTooltip: 'Salvar esta sessão para uso posterior', // button tooltip
		instructions: 'Insira qualquer número de expressõres regulares a serem executadas. O padrão de busca pode ser como "{code|text=padrão de busca}" ou "{code|text=/padrão/modificadores}", e o padrão de substituição pode conter grupos de referência como "{code|text=$1}" (ver {helplink|text=um tutorial|title=Tutorial sobre expressões regulares em JavaScript|url=http://www.regular-expressions.info/javascript.html}).'
	}
};
