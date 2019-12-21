/**
 * This is a translation file for these scripts. See https://meta.wikimedia.org/wiki/TemplateScript#Translation
 * for instructions on using translation files.
 */

var pathoschild = pathoschild || {};
pathoschild.i18n = {
    templatescript: {
        defaultHeaderText: "TemplateScript", // the sidebar header text label for the default group
        regexEditor: "Editor regex" // the default 'regex editor' script
    },
    regexeditor: {
        header: "Editor d´expressions regulars",      // the header text shown in the form
        search: "Cercar",                             // the search input label
        replace: "Reemplaçar",                        // the replace input label
        nameSession: "Introdueixi un nom per aquesta sessió", // the prompt shown when saving the session
        loadSession: 'Carregar sessió "{name}"',      // tooltip shown for a saved session, where {name} is replaced with the session name
        deleteSession: 'Esborrar sessió "{name}"',    // tooltip shown for the delete icon on a saved session, where {name} is replaced with the session name
        closeEditor: "Tancar l´editor regex",         // tooltip shown for the close-editor icon
        addPatterns: "Afegeix patrons",               // button text
        addPatternsTooltip: "Afegeix caixes de cerca i reemplaçament", // button tooltip
        apply: "Aplica",                              // button text
        applyTooltip: "Aplica els patrons indicats",  // button tooltip
        undo: "Desfés darrer canvi",                  // button text
        undoTooltip: "Desfà el darrer canvi",         // button tooltip
        save: "Desa canvis",                          // button text
        saveTooltip: "Desa aquesta sessió per usar-la més endavant", // button tooltip        
        instructions: 'Introdueixi les expressions regulars a executar. El patró de cerca pot ser de com "{code|text=patró de cerca}" o "{code|text=/patró/modificadors}" i el patró de reemplaçament pot contenir grups de referència com "{code|text=$1}" (vegeu {helplink|text=tutorial|title=JavaScript regex tutorial|url=https://www.regular-expressions.info/javascript.html}).'
    }
};