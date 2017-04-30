var pathoschild = pathoschild || {};

/**
 * Usejs imports JavaScript for the current page when the URL contains a parameter like &usejs=MediaWiki:Common.js. It only accepts scripts in the protected MediaWiki: namespace (so these are all equivalent: &usejs=MediaWiki:Common.js, &usejs=Common.js, &usejs=common).
 * @see https://github.com/Pathoschild/Wikimedia-contrib#user-scripts
 * @update-token [[File:Pathoschild/usejs.js]]
 */
pathoschild.usejs = function() {
    /*********
    ** Public methods
    *********/
    /**
     * Initialise the script and load the script if requested.
     */
    this.initialise = function() {
        var self = this;

        // get script info
        var scriptName = self.getScriptName();
        if (!scriptName)
            return;
        var scriptUrl = self.getScriptUrl(scriptName);
        var scriptLink = '<a href="' + mw.util.getUrl(scriptName) + '">' + scriptName + "</a>";
        // load script
        self.notify("↻ loading " + scriptName + "...");
        $.getScript(scriptUrl)
            .done(function(data) {
                if (!data)
                    self.notify("Couldn't load " + scriptLink + " because it doesn't exist.", "error");
                else
                    self.notify("Loaded " + scriptLink + "!");
            })
            .fail(function(xhr, settings, err) {
                self.notify("Couldn't load " + scriptLink + ": " + err, "error");
            });
    };

    /*********
    ** Private methods
    *********/
    /**
     * Get the normalised name of the script to load (like 'MediaWiki:Common.js').
     */
    this.getScriptName = function() {
        var script = mw.util.getParamValue("usejs");
        return script && "MediaWiki:" + script.replace(/^MediaWiki:|\.js$/ig, "") + ".js";
    };

    /**
     * Get the executable relative script URL.
     * @param name The page name of the script to load (like 'MediaWiki:Common.js').
     */
    this.getScriptUrl = function(name) {
        return mw.config.get("wgScript") + "?title=" + name + "&action=raw&ctype=text/javascript";
    };

    /**
     * Show a notification message to the user.
     * @param content the text or jQuery element to show to the user.
     * @param cssClass a CSS class with which to wrap the content (default is 'warning').
     */
    this.notify = function(content, cssClass) {
        var message = '<span class="' + (cssClass || "warning") + '">' + content + "</span>";
        var infoBlurb = '<small>You see this because <a href="' + mw.util.getUrl("User:Pathoschild/Scripts/Usejs") + '">usejs</a> is enabled and the URL has &usejs.</small>';
        mw.notify($(message + "<br />" + infoBlurb), { tag: "usejs" });
    };

    this.initialise();
};
$(function() { pathoschild.usejs(); });
