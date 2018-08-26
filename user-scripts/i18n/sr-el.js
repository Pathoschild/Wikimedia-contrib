/**
 * This is a translation file for these scripts. See https://meta.wikimedia.org/wiki/TemplateScript#Translation
 * for instructions on using translation files.
 */

var pathoschild = pathoschild || {};
pathoschild.i18n = {
    templatescript: {
        defaultHeaderText: "TemplateScript", // the sidebar header text label for the default group
        regexEditor: "Uređivač regularnih izraza."  // the default 'regex editor' script
    },
    regexeditor: {
        header: "Uređivač regularnih izraza",   // the header text shown in the form
        search: "Pretraži",                           // the search input label
        replace: "Zameni",                         // the replace input label
        nameSession: "Unesi ime za ovu sesiju",  // the prompt shown when saving the session
        loadSession: "Učitaj sesiju «{name}»",  // tooltip shown for a saved session, where {name} is replaced with the session name
        deleteSession: "Ukloni sesiju «{name}»",  // tooltip shown for the delete icon on a saved session, where {name} is replaced with the session name
        closeEditor: "Zatvori uređivač",           // tooltip shown for the close-editor icon
        addPatterns: "Dodaj šablone",            // button text
        addPatternsTooltip: "Dodaj polje za pretragu i zamenu", // button tooltip
        apply: "Primeni",                        // button text
        applyTooltip: "Izvedite gornje šablone",  // button tooltip
        undo: "Poništi prethodnu izmenu",        // button text
        undoTooltip: "Poništi prethodnu izmenu", // button tooltip
        save: "Sačuvaj",                         // button text
        saveTooltip: "Sačuvaj ovu sesiju za kasnije korišćenje", // button tooltip
        instructions: "Unesite bilo koji broj regularnih izraza za izvršavanje. Šema za pretragu može biti poput «{code|text=pretraga uzorka}», bilo kao «{code|text=/šablon/zastava}», i zamenski šablon može sadržavati referentne grupe kao što su «{code|text=$1}» (vidi {helplink|text=tutorial|title=Tutorijal|url=http://www.regular-expressions.info/javascript.html}).'"
    }
};
