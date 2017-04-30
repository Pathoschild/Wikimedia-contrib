/**
 * This is a translation file for these scripts. See https://meta.wikimedia.org/wiki/TemplateScript#Translation
 * for instructions on using translation files.
 */
var pathoschild = pathoschild || {};
pathoschild.i18n = {
    templatescript: {
        defaultHeaderText: "TemplateScript", // the sidebar header text label for the default group
        regexEditor: "Editor Regex" // the default 'regex editor' script
    },
    regexeditor: {
        header: "Editor Regex",                                 // the header text shown in the form
        search: "Cerca",                                        // the search input label
        replace: "Sostituisci",                                 // the replace input label
        nameSession: "Inserisci un nome per questa sessione",   // the prompt shown when saving the session
        loadSession: 'Carica la sessione "{name}"',             // tooltip shown for a saved session, where {name} is replaced with the session name
        deleteSession: 'Elimina la sessione "{name}"',          // tooltip shown for the delete icon on a saved session, where {name} is replaced with the session name
        closeEditor: "Chiudi l'editor di regex",               // tooltip shown for the close-editor icon
        addPatterns: "aggiungi modello",                        // button text
        addPatternsTooltip: "Aggiungi caselle cerca & sostituisci", // button tooltip
        apply: "applica",                                       // button text
        applyTooltip: "Esegui il modello",                      // button tooltip
        undo: "annulla l'ultimo applica",                      // button text
        undoTooltip: "Annulla l'ultimo applica",               // button tooltip
        save: "salva",                                          // button text
        saveTooltip: "Salva questa sessione per utilizzarla più tardi", // button tooltip
        instructions: 'Inserisci qualsiasi numero di espressioni regolari da eseguire. Il modello può essere ad esempio "{code|text=modello di cerca}" o "{code|text=/pattern/modifiers}", e il modello sostituito può contenere riferimenti a gruppi, ad esempio "{code|text=$1}" (vedi il {helplink|text=tutorial|title=JavaScript regex tutorial|url=http://www.regular-expressions.info/javascript.html}).'
    }
};
