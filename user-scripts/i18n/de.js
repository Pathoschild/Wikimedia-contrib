/**
 * This is a translation file for these scripts. See https://meta.wikimedia.org/wiki/TemplateScript#Translation
 * for instructions on using translation files.
 */

var pathoschild = pathoschild || {};
pathoschild.i18n = {
    templatescript: {
        defaultHeaderText: "TemplateScript", // the sidebar header text label for the default group
        regexEditor: "Regex-Editor" // the default 'regex editor' script
    },
    regexeditor: {
        header: "Regex-Editor",                         // the header text shown in the form
        search: "Suchen",                               // the search input label
        replace: "Ersetzen",                            // the replace input label
        nameSession: "Namen für diese Sitzung angeben", // the prompt shown when saving the session
        loadSession: 'Sitzung "{name}" laden',          // tooltip shown for a saved session, where {name} is replaced with the session name
        deleteSession: 'Sitzung "{name}" löschen',      // tooltip shown for the delete icon on a saved session, where {name} is replaced with the session name
        closeEditor: "Regex-Editor schließen",          // tooltip shown for the close-editor icon
        addPatterns: "Weiteres Feld",                   // button text
        addPatternsTooltip: "Weiteres Feld für Such- und Ersetzungsmuster hinzufügen", // button tooltip
        apply: "Anwenden",                              // button text
        applyTooltip: "Obenstehende Muster anwenden",   // button tooltip
        undo: "Letzte Ersetzung zurücknehmen",          // button text
        undoTooltip: "Letzte Ersetzung rückgängig machen", // button tooltip
        save: "Speichern",                              // button text
        saveTooltip: "Diese Sitzung zur erneuten Verwendung speichern", // button tooltip
        instructions: 'Gib einen Regulären Ausdruck ein, der angewendet werden soll. Das Suchmuster kann eine einfache Folge sein wie "{code|text=Suchmuster}" oder ein Ausdruck "{code|text=/Muster/Modifikatoren}" und das Ersetzungsmuster kann Referenzgruppen enthalten wie "{code|text=$1}" (siehe {helplink|text=englisches Tutorial|title=JavaScript-Regex-Tutorial|url=http://www.regular-expressions.info/javascript.html}).'
    }
};
