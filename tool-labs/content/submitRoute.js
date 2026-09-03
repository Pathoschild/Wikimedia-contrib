/**
 * This script rewrites form submissions into route URLs.
 *
 * Forms submit their values in query strings by default; this script intercepts the form submit,
 * parses the route template from the form's `data-submit-route` attribute, and moves matching query
 * arguments into the submitted route. Any remaining query arguments are left in the query string.
 *
 * A route template is the absolute path to submit, with token placeholders to replace:
 *   - `{token}`: replace with the matching form field value, with standard URL-encoding (except ':'
 *                which is kept as-is).
 *   - `{*token}`: same as `{token}`, but also keeps slashes ('/') non-URL-encoded.
 *
 * For example, given a `target` value of `127.0.0.1/16`:
 *   route template   | submitted to
 *   ---------------- | --------------------------
 *   `/for/{target}`  | `/for/127.0.0.1%2F16`
 *   `/for/{*target}` | `/for/127.0.0.1/16`
 */
(function () {
    "use strict";

    /**
     * Build the route URL for a form's current values.
     *
     * @param {HTMLFormElement} form The form being submitted.
     * @returns {string|null} The new URL which should be submitted to, or null to keep the default behavior.
     */
    function getRouteUrl(form) {
        // get template
        const template = form.getAttribute("data-submit-route");
        if (!template)
            return null;

        // check format
        if (template.includes("//")) {
            console.warn(`Invalid route template: "${template}": can't contain empty segments.`);
            return null;
        }
        if (!template.startsWith("/")) {
            console.warn(`Invalid route template: "${template}": must be an absolute path.`);
            return null;
        }

        // build route
        const values = new FormData(form);
        const appliedFieldNames = [];
        let route = template.replace(/\{(\*?)([^{}]+)\}/g, (_, decodeSlashes, fieldName) => {
            appliedFieldNames.push(fieldName);

            const value = values.get(fieldName);
            if (value === null)
                return "";

            let encoded = encodeURIComponent(value).replace(/%3A/g, ":"); // ':' is valid in route values (e.g. an IPv6 address for Stalktoy)
            if (decodeSlashes)
                encoded = encoded.replace(/%2F/g, "/");
            return encoded;
        });

        // trim empty trailing segments
        while (route.endsWith("/"))
            route = route.slice(0, -1);

        // skip if invalid
        if (route.includes("//"))
            return null; // empty (non-trailing) route segments
        if (/(^|\/)\.\.?(\/|$)/.test(route))
            return null; // directory climbing

        // add any remaining fields
        const url = new URL(route, window.location.href);
        for (const [field, value] of values) {
            if (value !== "" && !appliedFieldNames.includes(field))
                url.searchParams.append(field, value);
        }

        return url.toString();
    }

    /**
     * Handle a form being submitted, before the browser sends it.
     * @param {Event} event The form submission event.
     */
    function onSubmit(event) {
        const form = event.target;
        if (form.method && form.method.toLowerCase() !== "get")
            return;

        const url = getRouteUrl(form);
        if (!url)
            return;

        event.preventDefault();
        window.location.assign(url);
    }

    // hook DOM form events
    document.addEventListener("submit", onSubmit, true);
})();
