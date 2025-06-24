**Wikimedia-contrib** is a collection of user scripts and Toolforge tools intended for users of Wikimedia Foundation wikis.

## For users
### Tools

[Toolforge](https://toolforge.org/) is part of the Wikimedia Cloud infrastructure hosted by the Wikimedia Foundation for community-developed tools and bots. These tools provide analysis and data to support wiki editors and functionaries.

* **[Account Eligibility](https://meta.toolforge.org/accounteligibility/)** analyzes a user account to determine whether it's eligible to vote in the specified event.
* **[Category Analysis](https://meta.toolforge.org/catanalysis/)** analyzes edits to pages in the category tree rooted at the specified category (or pages rooted at a prefix). This is primarily intended for test project analysis by the Wikimedia Foundation [language committee](https://meta.wikimedia.org/wiki/Language_committee).
* **[Crosswiki Activity](https://meta.toolforge.org/crossactivity/)** measures a user's latest edit, bureaucrat, or sysop activity on all wikis.
* **[Global Groups](https://meta.toolforge.org/globalgroups/)** shows a live review of extra permissions assigned to [global groups](https://meta.wikimedia.org/wiki/Steward_handbook#Globally_and_wiki_sets) on Wikimedia Foundation wikis.
* **[Global User Search](https://meta.toolforge.org/gusersearch/)** provides searching and filtering of global users on Wikimedia wikis.
* **[ISO 639 Database](https://meta.toolforge.org/iso639db/)** is a searchable database of languages and ISO 639 codes augmented by native language names from Wikipedia.
* **[Magic Redirect](https://meta.toolforge.org/magicredirect/)** redirects to an arbitrary URL with tokens based on user and wiki filled in. This is primarily intended for Wikimedia templates ([see example](https://meta.toolforge.org/magicredirect/?url=//{wiki.domain}/wiki/Special:UserRights/{user.name}@{wiki.name}&wiki=metawiki&user=Pathoschild)).
* **[Stalktoy](https://meta.toolforge.org/stalktoy/)** shows global details about a user across all Wikimedia wikis. You can provide an account name (like `Pathoschild`), an IPv4 address (like `127.0.0.1`), an IPv6 address (like `2001:db8:1234::`), or a CIDR block (like `212.75.0.1/16` or `2600:3C00::/48`).
* **[Stewardry](https://meta.toolforge.org/stewardry/)** estimates which users in a group are available based on their last edit or action.
* **[Synchbot](https://meta.wikimedia.org/wiki/Synchbot)** synchronises user pages across Wikimedia projects in every language. This allows users to create user pages on every wiki, or to have global JavaScript and CSS. (Due to the potential for misuse, this bot is not open-source.)
* **[User Pages](https://meta.toolforge.org/userpages/)** finds your user pages on all wikis (or finds wikis where you don't have user pages).

### User scripts

These user scripts extend the wiki interface seen by a user, and they're sometimes available to all users as gadgets (particularly TemplateScript). See _[Gadget kitchen](https://www.mediawiki.org/wiki/Gadget_kitchen)_ for an introduction to user scripts & gadgets.

* **[ForceLTR](https://meta.wikimedia.org/wiki/Force_ltr)** enforces left-to-right layout and editing on right-to-left wikis. This resolves editing glitches in many browsers when one's preferred language is left-to-right, and corrects display when the interface language is not right-to-left.
* **[StewardScript](https://meta.wikimedia.org/wiki/StewardScript)** extends the user interface for [Wikimedia stewards](https://meta.wikimedia.org/wiki/Stewards)' convenience. It extends the sidebar (with links to steward pages), [Special:Block](https://meta.wikimedia.org/wiki/Special:Block) (with links to [stalktoy](https://toolserver.org/~pathoschild/stalktoy/) and [Special:CentralAuth](https://meta.wikimedia.org/wiki/Special:CentralAuth) if preloaded with a target), [Special:CentralAuth](https://meta.wikimedia.org/wiki/Special:CentralAuth) (with links to external tools, one-click status selection, a preselected template reason, and convenient links in the 'local accounts' list), global renaming and [Special:UserRights](https://meta.wikimedia.org/wiki/Special:UserRights) (with template summaries).
* **[TemplateScript](https://meta.wikimedia.org/wiki/TemplateScript)** adds a menu of configurable templates and scripts to the sidebar. It automatically handles templates for various forms (from editing to protection), edit summaries, auto-submission, and filtering which templates are shown based on namespace, form, or arbitrary conditions. Templates can be inserted at the cursor position or at a preconfigured position, and scripts can be invoked when a sidebar link is activated. TemplateScript is also used as a framework for other scripts, and includes a [fully-featured regex editor](https://meta.wikimedia.org/wiki/User:Pathoschild/Scripts/TemplateScript#Regex_editor).
* **[UseJS](https://meta.wikimedia.org/wiki/UseJS)** imports JavaScript for the current page when the URL contains a parameter like `&usejs=MediaWiki:Common.js`. It only accepts scripts in the protected `MediaWiki:` namespace.

## For maintainers
### Deploy to Toolforge
This repo uses three Toolforge accounts by default:

account | tools
------- | -----
`meta`  | most tools
`meta2` | crossactivity
`meta3` | stalktoy

They redirect as needed, so all tools can be accessed through eitherhostname. To use different
accounts, edit the `tool-labs/backend/modules/__config__.php` and `tool-labs/.lighttpd*` files
accordingly.

To deploy from scratch:

1. [Connect to Toolforge via SSH](https://wikitech.wikimedia.org/wiki/Portal:Toolforge/Tool_Accounts).
2. Run this script:
   ```sh
   ##########
   ## Set up meta
   ##########
   # switch to the meta project
   become meta

   ## set up tool files
   git clone https://github.com/Pathoschild/Wikimedia-contrib.git git/wikimedia-contrib
   mkdir cache
   mkdir public_html
   ln -s git/wikimedia-contrib/tool-labs/.lighttpd.meta.conf .lighttpd.conf
   cd public_html
   for TARGET in backend content 'toolinfo.json' accounteligibility catanalysis globalgroups gusersearch iso639db magicredirect pgkbot regextoy stewardry userpages
   do
      ln -s "../git/wikimedia-contrib/tool-labs/$TARGET"
   done

   # set up script CDN (accessed via https://tools-static.wmflabs.org/meta/scripts/*.js).
   mkdir ~/www
   mkdir ~/www/static
   cd ~/www/static
   ln -s ~/git/wikimedia-contrib/user-scripts scripts

   ## launch server
   toolforge webservice php8.2 start


   ##########
   ## Set up meta2
   ##########
   ## switch to the meta2 project
   exit
   become meta2

   ## set up files
   git clone https://github.com/Pathoschild/Wikimedia-contrib.git git/wikimedia-contrib
   mkdir cache
   mkdir public_html
   ln -s git/wikimedia-contrib/tool-labs/.lighttpd.meta2.conf .lighttpd.conf
   cd public_html
   for TARGET in backend content crossactivity
   do
      ln -s "../git/wikimedia-contrib/tool-labs/$TARGET"
   done

   ## launch server
   toolforge webservice php8.2 start


   ##########
   ## Set up meta3
   ##########
   ## switch to the meta3 project
   exit
   become meta3

   ## set up files
   git clone https://github.com/Pathoschild/Wikimedia-contrib.git git/wikimedia-contrib
   mkdir cache
   mkdir public_html
   ln -s git/wikimedia-contrib/tool-labs/.lighttpd.meta3.conf .lighttpd.conf
   cd public_html
   for TARGET in backend content stalktoy
   do
      ln -s "../git/wikimedia-contrib/tool-labs/$TARGET"
   done

   ## launch server
   toolforge webservice php8.2 start
   ```

That's it! The new tools should now be running at https://meta.toolforge.org. To update an account
later, just login and run these commands:

```sh
git -C git/wikimedia-contrib pull
webservice restart
```
