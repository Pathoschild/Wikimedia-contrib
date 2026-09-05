**Wikimedia-contrib** is a collection of user scripts and Toolforge tools intended for users of Wikimedia Foundation wikis.

## For users
### Tools
[Toolforge](https://toolforge.org/) is part of the Wikimedia Cloud infrastructure hosted by the Wikimedia Foundation for community-developed tools and bots. These tools provide analysis and data to support wiki editors and functionaries.

* **[Account Eligibility](https://accounteligibility.toolforge.org/)** analyzes a user account to determine whether it's eligible to vote in the specified event.
* **[Category Analysis](https://catanalysis.toolforge.org/)** analyzes edits to pages in the category tree rooted at the specified category (or pages rooted at a prefix). This is primarily intended for test project analysis by the Wikimedia Foundation [language committee](https://meta.wikimedia.org/wiki/Language_committee).
* **[Crosswiki Activity](https://crossactivity.toolforge.org/)** measures a user's latest edit, bureaucrat, or sysop activity across all wikis.
* **[Global Groups](https://globalgroups.toolforge.org/)** shows a live review of extra permissions assigned to [global groups](https://meta.wikimedia.org/wiki/Steward_handbook#Globally_and_wiki_sets) on Wikimedia Foundation wikis.
* **[Global User Search](https://gusersearch.toolforge.org/)** searches and filters global users on Wikimedia wikis.
* **[Magic Redirect](https://magicredirect.toolforge.org/)** redirects to an arbitrary URL with tokens based on user and wiki filled in. This is primarily intended for Wikimedia templates ([see example](https://magicredirect.toolforge.org/?url=//{wiki.domain}/wiki/Special:UserRights/{user.name}@{wiki.name}&wiki=metawiki&user=Pathoschild)).
* **[Stalktoy](https://stalktoy.toolforge.org/)** shows global details about a user across all Wikimedia wikis. You can provide an account name (like `Pathoschild`), an IPv4 address (like `127.0.0.1`), an IPv6 address (like `2001:db8:1234::`), or a CIDR block (like `212.75.0.1/16` or `2600:3C00::/48`).
* **[Stewardry](https://stewardry.toolforge.org/)** estimates which users in a group are available based on their last edit or action.
* **[Synchbot](https://meta.wikimedia.org/wiki/Synchbot)** synchronises user pages across Wikimedia projects in every language. This allows users to create user pages on every wiki, or to have global JavaScript and CSS. (Due to the potential for misuse, this bot is not open-source.)
* **[User Pages](https://userpages.toolforge.org/)** shows a user's pages on all wikis (or finds wikis where they don't have user pages).

### User scripts
These user scripts extend the wiki interface seen by a user, and they're sometimes available to all users as gadgets (particularly TemplateScript). See _[Gadget kitchen](https://www.mediawiki.org/wiki/Gadget_kitchen)_ for an introduction to user scripts & gadgets.

* **[ForceLTR](https://meta.wikimedia.org/wiki/Force_ltr)** enforces left-to-right layout and editing on right-to-left wikis. This resolves editing glitches in many browsers when one's preferred language is left-to-right, and corrects display when the interface language is not right-to-left.
* **[StewardScript](https://meta.wikimedia.org/wiki/StewardScript)** extends the user interface for [Wikimedia stewards](https://meta.wikimedia.org/wiki/Stewards)' convenience. It extends the sidebar (with links to steward pages), [Special:Block](https://meta.wikimedia.org/wiki/Special:Block) (with links to [stalktoy](https://stalktoy.toolforge.org/) and [Special:CentralAuth](https://meta.wikimedia.org/wiki/Special:CentralAuth) if preloaded with a target), [Special:CentralAuth](https://meta.wikimedia.org/wiki/Special:CentralAuth) (with links to external tools, one-click status selection, a preselected template reason, and convenient links in the 'local accounts' list), global renaming and [Special:UserRights](https://meta.wikimedia.org/wiki/Special:UserRights) (with template summaries).
* **[TemplateScript](https://meta.wikimedia.org/wiki/TemplateScript)** adds a menu of configurable templates and scripts to the sidebar. It automatically handles templates for various forms (from editing to protection), edit summaries, auto-submission, and filtering which templates are shown based on namespace, form, or arbitrary conditions. Templates can be inserted at the cursor position or at a preconfigured position, and scripts can be invoked when a sidebar link is activated. TemplateScript is also used as a framework for other scripts, and includes a [fully-featured regex editor](https://meta.wikimedia.org/wiki/User:Pathoschild/Scripts/TemplateScript#Regex_editor).
* **[UseJS](https://meta.wikimedia.org/wiki/UseJS)** imports JavaScript for the current page when the URL contains a parameter like `&usejs=MediaWiki:Common.js`. It only accepts scripts in the protected `MediaWiki:` namespace.

## Deploy to Toolforge
### Tool accounts
Each tool has its own account, with a subdomain matching its folder name. For example, Stalktoy is at [stalktoy.toolforge.org](https://stalktoy.toolforge.org).

To deploy a tool from scratch:
1. [Connect to Toolforge via SSH](https://wikitech.wikimedia.org/wiki/Portal:Toolforge/Tool_Accounts).
2. Run this script (editing the `# configure` section as needed):
   ```sh
   # configure
   become stalktoy
   toolName=stalktoy

   # add required folders
   mkdir -p bin cache logs public_html

   # add tool files
   git clone https://github.com/Pathoschild/Wikimedia-contrib.git git/wikimedia-contrib
   ln -s git/wikimedia-contrib/tool-labs/.lighttpd.conf .lighttpd.conf
   ln -s git/wikimedia-contrib/tool-labs/backend public_html/backend --relative
   ln -s git/wikimedia-contrib/tool-labs/content public_html/content --relative
   ln -s git/wikimedia-contrib/tool-labs/$toolName public_html/tool --relative
   cp /usr/bin/kubectl bin/kubectl # scheduled jobs (jobs.yaml) can only access home folder

   # launch server
   toolforge webservice php8.2 start --cpu 2 --mem 2Gi

   # start scheduled jobs (e.g. log rotation)
   toolforge jobs load ~/git/wikimedia-contrib/tool-labs/_scheduledJobs/jobs.yaml
   ```

To deploy an update:
1. [Connect to Toolforge via SSH](https://wikitech.wikimedia.org/wiki/Portal:Toolforge/Tool_Accounts).
2. Run this script (editing the `# configure` section as needed):
   ```sh
   # configure
   become stalktoy

   # update tool
   git -C git/wikimedia-contrib pull
   cp --update /usr/bin/kubectl bin/kubectl
   toolforge jobs load ~/git/wikimedia-contrib/tool-labs/_scheduledJobs/jobs.yaml

   # (optional) restart service to bypass caching, or if .lighttpd.conf changed
   webservice restart
   ```

### User scripts
The user scripts are deployed to the shared [`meta`](https://meta.toolforge.org) account, and made available through the Toolforge static CDN (via `https://tools-static.wmflabs.org/meta/scripts/*.js`).

To deploy the `meta` account from scratch:
1. [Connect to Toolforge via SSH](https://wikitech.wikimedia.org/wiki/Portal:Toolforge/Tool_Accounts).
2. Run this script:
   ```sh
   # switch to the project
   become meta

   # add required folders
   mkdir -p bin logs

   ## add tool files
   git clone https://github.com/Pathoschild/Wikimedia-contrib.git git/wikimedia-contrib
   ln -s git/wikimedia-contrib/tool-labs/.lighttpd.conf .lighttpd.conf
   cp /usr/bin/kubectl bin/kubectl # scheduled jobs (jobs.yaml) can only access home folder

   # set up script CDN
   mkdir -p www/static
   ln -s git/wikimedia-contrib/user-scripts www/static/scripts --relative

   ## launch server
   toolforge webservice php8.2 start --cpu 2 --mem 2Gi

   ## start scheduled jobs (e.g. log rotation)
   toolforge jobs load ~/git/wikimedia-contrib/tool-labs/_scheduledJobs/jobs.yaml
   ```

To deploy an update, follow the [same process as other tools](#tool-accounts).

### Legacy redirects
The tools were previously hosted in three shared tool accounts (`meta`, `meta2`, and `meta3`). These still exist to redirect requests to the new per-tool accounts.

To deploy `meta`, see [_user scripts_](#user-scripts-1) above.

To deploy `meta2` or `meta3` from scratch:
1. [Connect to Toolforge via SSH](https://wikitech.wikimedia.org/wiki/Portal:Toolforge/Tool_Accounts).
2. Run this script (editing the `# configure` section as needed):
   ```sh
   # switch to the project
   become meta2

   ## set up tool files
   git clone https://github.com/Pathoschild/Wikimedia-contrib.git git/wikimedia-contrib
   mkdir -p bin logs public_html
   ln -s git/wikimedia-contrib/tool-labs/.lighttpd.conf .lighttpd.conf
   cp /usr/bin/kubectl bin/kubectl # scheduled jobs (jobs.yaml) can only access home folder

   ## launch server
   toolforge webservice php8.2 start --cpu 2 --mem 2Gi

   ## start scheduled jobs (e.g. log rotation)
   toolforge jobs load ~/git/wikimedia-contrib/tool-labs/_scheduledJobs/jobs.yaml
   ```

To deploy an update, follow the [same process as other tools](#tool-accounts).

## Web logs
With the above setup, these logs are created automatically on each tool account:
- `~/error.log` logs Lighttpd errors (enabled by default).
- `~/logs/access.log` logs each incoming request via Lighttpd (configured via `~/.lighttpd.conf`).
- `~/logs/job-*.log` + `~/logs/job-*.err` logs output from scheduled jobs.

Two jobs run on each tool (set in `tool-labs/_scheduledJobs/jobs.yaml`):
- The log files are rotated daily, with one week of backups in `~/logs`. (Toolforge runs `@daily` tasks at a randomized
  time of day for each tool, so logs likely don't switch at midnight.)
- If messages were written to `error.log`, a daily task sends a summary email for the last 24 hours to the tool
  maintainers (i.e. `tools.<tool>@toolforge.org`).
