[![Build Status](https://travis-ci.org/Pathoschild/Wikimedia-contrib.png)](https://travis-ci.org/Pathoschild/Wikimedia-contrib)
# Wikimedia-contrib
**Wikimedia-contrib** is a collection of scripts intended for users of Wikimedia Foundation wikis.

## TemplateScript
[TemplateScript](https://meta.wikimedia.org/wiki/User:Pathoschild/Scripts/TemplateScript) adds a menu of configurable templates and scripts to the sidebar. It automatically handles templates for various forms (from editing to protection), edit summaries, auto-submission, and filtering which templates are shown based on namespace, form, or arbitrary conditions. Templates can be inserted at the cursor position or at a preconfigured position, and scripts can be invoked when a sidebar link is activated.

TemplateScript is also used as a framework for other scripts, and includes a [fully-featured regex editor](https://meta.wikimedia.org/wiki/User:Pathoschild/Scripts/TemplateScript#Regex_editor) (which has a [live demo](https://toolserver.org/~pathoschild/regextoy/)) as an example.

## ForceLTR
[ForceLTR](https://meta.wikimedia.org/wiki/User:Pathoschild/Scripts/Force_ltr) forces MediaWiki into displaying text in left-to-right format, even if the wiki's primary language is right-to-left.

## StewardScript
[StewardScript](https://meta.wikimedia.org/wiki/User:Pathoschild/Scripts/StewardScript) extends the user interface for [Wikimedia stewards](https://meta.wikimedia.org/wiki/Stewards)' convenience. It extends the sidebar (with links to steward pages), [Special:Block](https://meta.wikimedia.org/wiki/Special:Block) (with links to [stalktoy](https://toolserver.org/~pathoschild/stalktoy/) and [Special:CentralAuth](https://meta.wikimedia.org/wiki/Special:CentralAuth) if preloaded with a target), [Special:CentralAuth](https://meta.wikimedia.org/wiki/Special:CentralAuth) (with links to external tools, one-click status selection, a preselected template reason, and convenience links in the 'local accounts' list), and [Special:UserRights](https://meta.wikimedia.org/wiki/Special:UserRights) (with template summaries).

## Synchbot
[Synchbot](https://meta.wikimedia.org/wiki/User:Pathoschild/Scripts/Synchbot) synchronises user pages across Wikimedia projects in every language. This allows users to create user pages on every wiki, or to have global JavaScript and CSS. (Due to the potential for misuse, this bot is not open-source.)
