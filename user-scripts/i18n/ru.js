/**
 * This is a translation file for these scripts. See https://meta.wikimedia.org/wiki/TemplateScript#Translation
 * for instructions on using translation files.
 */

var pathoschild = pathoschild || {};
pathoschild.i18n = {
	templatescript: {
		defaultHeaderText: 'TemplateScript', // the sidebar header text label for the default group
		regexEditor: 'Редактор рег. выраж.'  // the default 'regex editor' script
	},
	regexeditor: {
		header: 'Редактор регулярных выражений',   // the header text shown in the form
		search: 'Поиск',                           // the search input label
		replace: 'Замена',                         // the replace input label
		nameSession: 'Укажите имя данной сессии',  // the prompt shown when saving the session
		loadSession: 'Загрузить сессию «{name}»',  // tooltip shown for a saved session, where {name} is replaced with the session name
		deleteSession: 'Удалить сессию «{name}»',  // tooltip shown for the delete icon on a saved session, where {name} is replaced with the session name
		closeEditor: 'Закрыть редактор',           // tooltip shown for the close-editor icon
		addPatterns: 'Добавить шаблон',            // button text
		addPatternsTooltip: 'Добавить поле поиска и замены', // button tooltip
		apply: 'Применить',                        // button text
		applyTooltip: 'Выполнить данные шаблоны',  // button tooltip
		undo: 'Отмена последнего действия',        // button text
		undoTooltip: 'Отмена последнего действия', // button tooltip
		save: 'Сохранить',                         // button text
		saveTooltip: 'Сохранение сессии для применения в будущем', // button tooltip
		instructions: 'Вы можете указать любое количество шаблонов для использования. Шаблон поиска может выглядеть как «{code|text=шаблон поиска}», либо как «{code|text=/шаблон/флаги}», а шаблон замены может содержать ссылки на группы вида «{code|text=$1}» (см. {helplink|text=справку|title=Справка по регулярным выражениям JavaScript|url=https://learn.javascript.ru/regular-expressions-javascript}).'
	}
};