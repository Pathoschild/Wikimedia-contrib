/**
 * This is a translation file for these scripts. See https://meta.wikimedia.org/wiki/TemplateScript#Translation
 * for instructions on using translation files.
 */

var pathoschild = pathoschild || {};
pathoschild.i18n = {
    templatescript: {
        defaultHeaderText: "TemplateScript", // the sidebar header text label for the default group
        regexEditor: "正则编辑器" // the default 'regex editor' script
    },
    regexeditor: {
        header: "正则编辑器",                // the header text shown in the form
        search: "查找",                         // the search input label
        replace: "替换",                    // the replace input label
        nameSession: "输入该会话的名称：", // the prompt shown when saving the session
        loadSession: '载入会话"{name}"',    // tooltip shown for a saved session, where {name} is replaced with the session name
        deleteSession: '删除会话"{name}"',  // tooltip shown for the delete icon on a saved session, where {name} is replaced with the session name
        closeEditor: "关闭正则编辑器", // tooltip shown for the close-editor icon
        addPatterns: "添加新模式",           // button text
        addPatternsTooltip: "添加一个新的查找替换框", // button tooltip
        apply: "应用",                         // button text
        applyTooltip: "应用以上模式", // button tooltip
        undo: "撤销上一步应用",               // button text
        undoTooltip: "撤销上一步应用",        // button tooltip
        save: "保存方案",                          // button text
        saveTooltip: "保存该会话以便下次使用", // button tooltip
        instructions: '本工具能够输入并执行任意数量的正则表达式。查找功能的模式（pattern）可以直接使用 {code|text=模式}，也可以使用 {code|text=/模式/修饰符}，搜索功能的模式中可包含捕获组，例如 {code|text=$1}（参见{helplink|text=教程|title=Tutorial JavaScript regex|url=https://www.regular-expressions.info/javascript.html}）。'
    }
};
