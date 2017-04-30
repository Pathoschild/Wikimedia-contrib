/**
 * This is a sample translation file for these scripts. See https://meta.wikimedia.org/wiki/TemplateScript#Translation
 * for instructions on using translation files.
 * 
 * To translate these scripts:
 *   1. Copy this to a *new* file named with your language code.
 *   2. Translate the text inside 'quotes'. Be careful with the special tokens that look like
 *      {variable}; these are placeholders. Only translate the text right of the '=' symbol. For example,
 *      only "reset the form" should be translated for the "{resetForm|text=reset the form}" token.
 *   3. Submit a pull request to the Git repository, or simply post it to https://meta.wikimedia.org/wiki/User_talk:Pathoschild
 *      and I'll do the rest.
 */

var pathoschild = pathoschild || {};
pathoschild.i18n = {
    templatescript: {
        defaultHeaderText: "TemplateScript", // the sidebar header text label for the default group
        regexEditor: "Regex editor" // the default 'regex editor' script
    },
    regexeditor: {
        header: "Regex editor",                       // the header text shown in the form
        search: "Search",                             // the search input label
        replace: "Replace",                           // the replace input label
        nameSession: "Enter a name for this session", // the prompt shown when saving the session
        loadSession: 'Load session "{name}"',         // tooltip shown for a saved session, where {name} is replaced with the session name
        deleteSession: 'Delete session "{name}"',     // tooltip shown for the delete icon on a saved session, where {name} is replaced with the session name
        closeEditor: "Close the regex editor",        // tooltip shown for the close-editor icon
        addPatterns: "add patterns",                  // button text
        addPatternsTooltip: "Add search & replace boxes", // button tooltip
        apply: "apply",                               // button text
        applyTooltip: "Perform the above patterns",   // button tooltip
        undo: "undo the last apply",                  // button text
        undoTooltip: "Undo the last apply",           // button tooltip
        save: "save",                                 // button text
        saveTooltip: "Save this session for later use", // button tooltip
        instructions: 'Enter any number of regular expressions to execute. The search pattern can be like "{code|text=search pattern}" or "{code|text=/pattern/modifiers}", and the replace pattern can contain reference groups like "{code|text=$1}" (see {helplink|text=tutorial|title=JavaScript regex tutorial|url=http://www.regular-expressions.info/javascript.html}).'
    }
};
