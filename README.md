**Wikimedia-contrib** is a collection of user scripts and Toolforge tools intended for users of Wikimedia Foundation wikis.

## For users
### Tools

[Toolforge](https://tools.wmflabs.org/) is part of the Wikimedia Cloud infrastructure hosted by the Wikimedia Foundation for community-developed tools and bots. These tools provide analysis and data to support wiki editors and functionaries.

* **[Account Eligibility](https://tools.wmflabs.org/meta/accounteligibility/)** analyzes a user account to determine whether it's eligible to vote in the specified event.
* **[Category Analysis](https://tools.wmflabs.org/meta/catanalysis/)** analyzes edits to pages in the category tree rooted at the specified category (or pages rooted at a prefix). This is primarily intended for test project analysis by the Wikimedia Foundation [language committee](https://meta.wikimedia.org/wiki/Language_committee).
* **[Crosswiki Activity](https://tools.wmflabs.org/meta/crossactivity/)** measures a user's latest edit, bureaucrat, or sysop activity on all wikis.
* **[Global Groups](https://tools.wmflabs.org/meta/globalgroups/)** shows a live review of extra permissions assigned to [global groups](https://meta.wikimedia.org/wiki/Steward_handbook#Globally_and_wiki_sets) on Wikimedia Foundation wikis.
* **[Global User Search](https://tools.wmflabs.org/meta/gusersearch/)** provides searching and filtering of global users on Wikimedia wikis.
* **[ISO 639 Database](https://tools.wmflabs.org/meta/iso639db/)** is a searchable database of languages and ISO 639 codes augmented by native language names from Wikipedia.
* **[Magic Redirect](https://tools.wmflabs.org/meta/magicredirect/)** redirects to an arbitrary URL with tokens based on user and wiki filled in. This is primarily intended for Wikimedia templates ([see example](https://tools.wmflabs.org/meta/magicredirect/?url=//{wiki.domain}/wiki/Special:UserRights/{user.name}@{wiki.name}&wiki=metawiki&user=Pathoschild)).
* **[Stalktoy](https://tools.wmflabs.org/meta/stalktoy/)** shows global details about a user across all Wikimedia wikis. You can provide an account name (like `Pathoschild`), an IPv4 address (like `127.0.0.1`), an IPv6 address (like `2001:db8:1234::`), or a CIDR block (like `212.75.0.1/16` or `2600:3C00::/48`).
* **[Stewardry](https://tools.wmflabs.org/meta/stewardry/)** estimates which users in a group are available based on their last edit or action.
* **[Synchbot](https://meta.wikimedia.org/wiki/User:Pathoschild/Scripts/Synchbot)** synchronises user pages across Wikimedia projects in every language. This allows users to create user pages on every wiki, or to have global JavaScript and CSS. (Due to the potential for misuse, this bot is not open-source.)
* **[User Pages](https://tools.wmflabs.org/meta/userpages/)** finds your user pages on all wikis (or finds wikis where you don't have user pages).

### User scripts

These user scripts extend the wiki interface seen by a user, and they're sometimes available to all users as gadgets (particularly TemplateScript). See _[Gadget kitchen](https://www.mediawiki.org/wiki/Gadget_kitchen)_ for an introduction to user scripts & gadgets.

* **[ForceLTR](https://meta.wikimedia.org/wiki/Force_ltr)** enforces left-to-right layout and editing on right-to-left wikis. This resolves editing glitches in many browsers when one's preferred language is left-to-right, and corrects display when the interface language is not right-to-left.
* **[StewardScript](https://meta.wikimedia.org/wiki/StewardScript)** extends the user interface for [Wikimedia stewards](https://meta.wikimedia.org/wiki/Stewards)' convenience. It extends the sidebar (with links to steward pages), [Special:Block](https://meta.wikimedia.org/wiki/Special:Block) (with links to [stalktoy](https://toolserver.org/~pathoschild/stalktoy/) and [Special:CentralAuth](https://meta.wikimedia.org/wiki/Special:CentralAuth) if preloaded with a target), [Special:CentralAuth](https://meta.wikimedia.org/wiki/Special:CentralAuth) (with links to external tools, one-click status selection, a preselected template reason, and convenient links in the 'local accounts' list), global renaming and [Special:UserRights](https://meta.wikimedia.org/wiki/Special:UserRights) (with template summaries).
* **[TemplateScript](https://meta.wikimedia.org/wiki/TemplateScript)** adds a menu of configurable templates and scripts to the sidebar. It automatically handles templates for various forms (from editing to protection), edit summaries, auto-submission, and filtering which templates are shown based on namespace, form, or arbitrary conditions. Templates can be inserted at the cursor position or at a preconfigured position, and scripts can be invoked when a sidebar link is activated. TemplateScript is also used as a framework for other scripts, and includes a [fully-featured regex editor](https://meta.wikimedia.org/wiki/User:Pathoschild/Scripts/TemplateScript#Regex_editor).
* **[UseJS](https://meta.wikimedia.org/wiki/UseJS)** imports JavaScript for the current page when the URL contains a parameter like `&usejs=MediaWiki:Common.js`. It only accepts scripts in the protected `MediaWiki:` namespace.

## For maintainers
### Deploy one tool to Toolforge
Ideally each tool should be deployed to Toolforge as a separate tool account, both to avoid hitting connection limits and to minimise impact on other tools when an expensive tool is overloaded.

To deploy a tool:

1. [Create a Toolforge tool account](https://wikitech.wikimedia.org/wiki/Portal:Toolforge/Tool_Accounts). Make sure the name matches the tool directory in the `tool-forge` folder (or edit the tool's `tool.lighttpd.conf` file to specify the account name).
2. Connect to the account via SSH.
3. Run these commands from the home directory to set up the tool files (replace `$TOOLNAME` with the tool name being deployed):

   ```sh
   git clone https://github.com/Pathoschild/Wikimedia-contrib.git git/wikimedia-contrib
   mkdir cache
   mkdir public_html

   ln -s git/wikimedia-contrib/tool-labs/.lighttpd.conf
   ln -s git/wikimedia-contrib/tool-labs/$TOOLNAME/.lighttpd.tool.conf

   cd public_html
   for TARGET in backend content $TOOLNAME
   do
      ln -s "../git/wikimedia-contrib/tool-labs/$TARGET"
   done
   ```

4. Change the URLs in `public_html/backend/modules/__config.php` if different from the default.
5. Launch the server:
   ```sh
   webservice --backend=kubernetes start
   ```

That's it! The new tool should now be running at https://tools.wmflabs.org/$TOOLNAME.

### Deploy all tools to one Toolforge account
All tools can be deployed as part of the same Toolforge account, though keep in mind they'll share
the same usage quotas.

To deploy all tools to the same account:

1. [Create a Toolforge tool account](https://wikitech.wikimedia.org/wiki/Portal:Toolforge/Tool_Accounts).
2. Connect to the account via SSH.
3. Run these commands from the home directory to set up the tool files:

   ```sh
   git clone https://github.com/Pathoschild/Wikimedia-contrib.git git/wikimedia-contrib
   mkdir cache
   mkdir public_html

   ln -s git/wikimedia-contrib/tool-labs/.lighttpd.meta.conf .lighttpd.conf

   cd public_html
   for TARGET in backend content script 'toolinfo.json' accounteligibility catanalysis globalgroups gusersearch iso639db magicredirect pgkbot regextoy stalktoy stewardry userpages
   do
      ln -s "../git/wikimedia-contrib/tool-labs/$TARGET"
   done
   ```

4. Change the URLs in `public_html/backend/modules/__config.php` and `.lighttpd.conf` if different
   from the default.
5. Launch the server:
   ```sh
   webservice --backend=kubernetes start
   ```

That's it! The new tools should now be running at https://tools.wmflabs.org/$ACCOUNTNAME.