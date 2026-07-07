/**
 * Webpushr Service Worker
 * Minimal stub that imports the official Webpushr SW SDK from CDN.
 * This file MUST be served as application/javascript (not text/html).
 * It lives at /webpushr-sw.js (document root) so its scope covers the whole site.
 * Apache .htaccess excludes this path from the SPA catch-all rewrite.
 */

// Official Webpushr SW — pins to v10 stable release
importScripts('https://cdn.webpushr.com/sw-server.min.js');
