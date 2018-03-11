/**
 * This is a translation file for these scripts. See https://meta.wikimedia.org/wiki/TemplateScript#Translation
 * for instructions on using translation files.
 */

var pathoschild = pathoschild || {};
pathoschild.i18n = {
    templatescript: {
        defaultHeaderText: "テンプレートスクリプト", // the sidebar header text label for the default group
        regexEditor: "正規表現エディタ" // the default 'regex editor' script
    },
    regexeditor: {
        header: "正規表現エディタ",                         // the header text shown in the form
        search: "検索",                                     // the search input label
        replace: "置換",                                    // the replace input label
        nameSession: "セッション名を入力する",              // the prompt shown when saving the session
        loadSession: 'セッション"{name}"を開く',            // tooltip shown for a saved session, where {name} is replaced with the session name
        deleteSession: 'セッション"{name}"を削除する',      // tooltip shown for the delete icon on a saved session, where {name} is replaced with the session name
        closeEditor: "正規表現エディタを終了する",          // tooltip shown for the close-editor icon
        addPatterns: "パターンを追加する",                  // button text
        addPatternsTooltip: "検索・置換ボックスを追加する", // button tooltip
        apply: "適用する",                                  // button text
        applyTooltip: "上記のパターンを適用する",           // button tooltip
        undo: "最後の適用を取り消す",                       // button text
        undoTooltip: "最後の適用を取り消す",                // button tooltip
        save: "保存する",                                   // button text
        saveTooltip: "後で使うためにセッションを保存する",  // button tooltip
        instructions: '任意の数の正規表現を入力できます。検索パターンは"{code|text=検索パターン}"または"{code|text=/パターン/修飾子}"のように指定します。置換パターンには"{code|text=$1}"のように参照グループを含めることができます（{helplink|text=チュートリアル|title=JavaScript regex tutorial|url=http://www.regular-expressions.info/javascript.html}を参照してください）。'
    }
};
