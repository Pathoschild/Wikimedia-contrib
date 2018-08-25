/**
 * This is a translation file for these scripts. See https://meta.wikimedia.org/wiki/TemplateScript#Translation
 * for instructions on using translation files.
 */

var pathoschild = pathoschild || {};
pathoschild.i18n = {
    templatescript: {
        defaultHeaderText: "TemplateScript", // the sidebar header text label for the default group
        regexEditor: "Уређивач регуларних израза."  // the default 'regex editor' script
    },
    regexeditor: {
        header: "Уређивач регуларних израза",   // the header text shown in the form
        search: "Претражи",                           // the search input label
        replace: "Замени",                         // the replace input label
        nameSession: "Унеси име за ову сесију",  // the prompt shown when saving the session
        loadSession: "Учитај сесију «{name}»",  // tooltip shown for a saved session, where {name} is replaced with the session name
        deleteSession: "Уклони сесију «{name}»",  // tooltip shown for the delete icon on a saved session, where {name} is replaced with the session name
        closeEditor: "Затвори уређивач",           // tooltip shown for the close-editor icon
        addPatterns: "Додај шаблоне",            // button text
        addPatternsTooltip: "Додај поље за претрагу и замену", // button tooltip
        apply: "Примени",                        // button text
        applyTooltip: "Изведите горње шаблоне",  // button tooltip
        undo: "Поништи претходну измену",        // button text
        undoTooltip: "Поништи претходну измену", // button tooltip
        save: "Сачувај",                         // button text
        saveTooltip: "Сачувај ову сесију за касније коришћење", // button tooltip
        instructions: "Унесите било који број регуларних израза за извршавање. Шема за претрагу може бити попут «{code|text=претрага узорка}», било као «{code|text=/шаблон/застава}», и заменски шаблон може садржавати референтне групе као што су «{code|text=$1}» (види {helplink|text=tutorial|title=Туторијал|url=http://www.regular-expressions.info/javascript.html}).'"
    }
};
