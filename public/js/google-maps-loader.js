/*
 * Single-flight loader for the Google Maps JavaScript API.
 *
 * Uses Google's recommended dynamic library import bootstrap. The API key is
 * read from <meta name="google-maps-key"> (filled by Laravel config, never
 * hard-coded here). The script is injected exactly once per page, however
 * many maps ask for it; every caller shares the same promise.
 *
 *   GoogleMapsLoader.load(['maps', 'visualization']).then(google => { ... });
 *   GoogleMapsLoader.hasKey()   // false when the key is not configured
 */
(function (global) {
    'use strict';

    let bootstrapped = false;
    let loadPromise = null;
    const loadedLibraries = new Set();

    function apiKey() {
        const meta = document.querySelector('meta[name="google-maps-key"]');
        return meta ? String(meta.getAttribute('content') || '').trim() : '';
    }

    // Official bootstrap (https://developers.google.com/maps/documentation/javascript/load-maps-js-api)
    function bootstrap(key) {
        if (bootstrapped || (global.google && global.google.maps && global.google.maps.importLibrary)) { bootstrapped = true; return; }
        bootstrapped = true;
        (g => { var h, a, k, p = "The Google Maps JavaScript API", c = "google", l = "importLibrary", q = "__ib__", m = document, b = global; b = b[c] || (b[c] = {}); var d = b.maps || (b.maps = {}), r = new Set, e = new URLSearchParams, u = () => h || (h = new Promise(async (f, n) => { await (a = m.createElement("script")); e.set("libraries", [...r] + ""); for (k in g) e.set(k.replace(/[A-Z]/g, t => "_" + t[0].toLowerCase()), g[k]); e.set("callback", c + ".maps." + q); a.src = `https://maps.${c}apis.com/maps/api/js?` + e; d[q] = f; a.onerror = () => h = n(Error(p + " could not load.")); a.nonce = m.querySelector("script[nonce]")?.nonce || ""; m.head.append(a) })); d[l] ? console.warn(p + " only loads once. Ignoring:", g) : d[l] = (f, ...n) => r.add(f) && u().then(() => d[l](f, ...n)) })({
            key: key,
            v: 'weekly',
        });
    }

    function load(libraries) {
        const libs = Array.isArray(libraries) && libraries.length ? libraries : ['maps'];
        const key = apiKey();
        if (!key) return Promise.reject(new Error('Google Maps API key is not configured (GOOGLE_MAPS_API_KEY).'));
        bootstrap(key);

        const need = libs.filter(l => !loadedLibraries.has(l));
        const chain = (loadPromise || Promise.resolve()).then(() =>
            Promise.all(need.map(l => global.google.maps.importLibrary(l).then(() => loadedLibraries.add(l))))
        ).then(() => global.google);
        loadPromise = chain.catch(() => {});   // keep the chain alive for later callers
        return chain;
    }

    global.GoogleMapsLoader = { load: load, hasKey: () => apiKey() !== '' };
})(window);
