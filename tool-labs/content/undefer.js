/**
 * This script removes `defer=1` URL parameters for non-automated users.
 *
 * URLs which trigger an expensive request can have a `defer=1` query argument, which tells the
 * server to show a confirmation prompt instead of processing it directly. That prevents crawl bots,
 * web scrapers, and other link-following bots from accidentally running expensive queries by
 * following tool links.
 *
 * This script detects when a link element with the `data-undefer` HTML attribute is clicked,
 * and strips the parameter so regular users don't see the challenge.
 *
 * If a user has JavaScript disabled, they'll just see the extra confirmation prompt when the new
 * page loads.
 */
(function () {
    "use strict";

    /**
     * Get a copy of a URL without the `defer=1` argument.
     * @param {string} href The URL to change.
     * @returns {string} The updated URL.
     */
    function stripDefer(href) {
        const url = new URL(href);
        url.searchParams.delete("defer");
        return url.toString();
    }

    /**
     * Handle a link element being activated, before the browser follows it.
     * @param {Event} event The event which activated the link.
     */
    function onActivate(event) {
        const target = event.target;
        if (!target?.closest)
            return;

        const link = target.closest("a[data-undefer]");
        if (link)
            link.href = stripDefer(link.href);
    }

    // hook DOM link events
    document.addEventListener("pointerdown", onActivate, true);
    document.addEventListener("keydown", event => {
        if (event.key === "Enter")
            onActivate(event);
    }, true);
    document.addEventListener("click", onActivate, true);
})();
