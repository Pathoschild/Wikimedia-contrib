/**
 * This is a translation file for these scripts. See https://meta.wikimedia.org/wiki/TemplateScript#Translation
 * for instructions on using translation files.
 */

var pathoschild = pathoschild || {};
pathoschild.i18n = {
    templatescript: {
        defaultHeaderText: "TemplateScript", // the sidebar header text label for the default group
        regexEditor: "Editor de regex" // the default 'regex editor' script
    },
    regexeditor: {
        header: "Editor de regex",                // the header text shown in the form
        search: "Buscar",                         // the search input label
        replace: "Reemplazar",                    // the replace input label
        nameSession: "Introduzca un nombre para esta sesión", // the prompt shown when saving the session
        loadSession: 'Cargar sesión "{name}"',    // tooltip shown for a saved session, where {name} is replaced with the session name
        deleteSession: 'Borrar sesión "{name}"',  // tooltip shown for the delete icon on a saved session, where {name} is replaced with the session name
        closeEditor: "Cerrar el editor de regex", // tooltip shown for the close-editor icon
        addPatterns: "añadir patrones",           // button text
        addPatternsTooltip: "Añadir botones de búsqueda y reemplazo", // button tooltip
        apply: "aplicar",                         // button text
        applyTooltip: "Realizar los patrones designados más arriba", // button tooltip
        undo: "deshacer lo último",               // button text
        undoTooltip: "Deshacer lo último",        // button tooltip
        save: "guardar",                          // button text
        saveTooltip: "Guardar esta sesión para uso posterior", // button tooltip
        instructions: 'Introduce cualquier número de expresiones regulares a ejecutar. El patrón de búsqueda puede ser tipo "{code|text=patrón de búsqueda}" o "{code|text=/patrón/modificadores}", y el patrón de reemplazos puede contener referencias a grupos como "{code|text=$1}" (ver {helplink|text=tutorial|title=Tutorial JavaScript regex|url=https://www.regular-expressions.info/javascript.html}).'
    }
};
