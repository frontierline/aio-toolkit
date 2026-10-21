=== Frontierline AIO Toolkit ===
Contributors: frontierline2015
Tags: ai, llms-txt, seo, schema, structured data
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Make your site AI-ready: auto llms.txt & llms-full.txt, allow major AI crawlers, and inject JSON-LD structured data.

== Description ==

Frontierline AIO Toolkit prepares your WordPress site for LLMs and AI-powered search. It focuses on the few things that actually decide whether an AI assistant can read, understand and cite your site — and does them automatically, from one simple settings screen.

**How this differs from other llms.txt plugins**

An `llms.txt` file on its own is only part of the picture. Being readable by an AI assistant takes three things working together, and this plugin is built to cover all three from a single screen:

1. **Content the model can read** — `llms.txt`, plus a full-text `llms-full.txt` that is cached and size-capped.
2. **Permission to fetch it** — an allow-list for 22 named AI crawlers appended to your virtual robots.txt, *after* other SEO plugins have written theirs, so nothing is overwritten.
3. **Context the model can parse** — schema.org JSON-LD for posts, pages, the front page and authors.

It is also built to sit next to an existing SEO plugin rather than fight it. The robots.txt rules are appended at filter priority 100000, after plugins such as Yoast SEO that rebuild the whole file late, so neither output is lost; and when Yoast SEO, Rank Math, All in One SEO or SEOPress is detected the settings screen says so, next to the checkbox that turns this plugin's JSON-LD off, so you can avoid duplicate structured data.

**Features**

* **llms.txt (auto-generated)** – A concise, machine-friendly overview of your site: summary, company info and key pages, served dynamically at `/llms.txt`.
* **llms-full.txt (auto-generated)** – A full-text aggregation of your published posts and pages for LLM ingestion, with caching and a configurable size cap, served at `/llms-full.txt`.
* **AI crawler allow-list** – Adds `Allow` rules for 22 major AI crawlers (GPTBot, OAI-SearchBot, ClaudeBot, PerplexityBot, Google-Extended, Applebot-Extended, CCBot, Amazonbot and more) to your virtual robots.txt with a single toggle.
* **JSON-LD structured data** – Injects schema.org markup automatically: Article for posts, WebPage for pages, Organization + WebSite for the front page, Person for authors and ImageObject for featured images.
* **Simple settings** – One tabbed screen under Settings → AIO Toolkit. Nothing to configure to get started; sensible defaults are on out of the box.

**Privacy first**

Password-protected, private and draft content is never included in the generated feeds. The plugin sends no data anywhere — everything is generated on your own server.

**Built for developers**

Lightweight, dependency-free, multisite-aware and translation-ready. Filters: `flaio_ai_crawlers`, `flaio_llms_txt`, `flaio_llms_full_txt`, `flaio_llms_full_post_types`, `flaio_llms_full_max_posts`, `flaio_schema_graph`.

The feed queries do not suppress query filters, so membership and access-control plugins can exclude their restricted content from `llms-full.txt` as they do elsewhere on the site.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/frontierline-aio-toolkit`, or install it through the WordPress Plugins screen.
2. Activate the plugin through the "Plugins" screen in WordPress.
3. Visit **Settings → AIO Toolkit** to review the options (all core features are enabled by default).
4. Confirm your feeds at `/llms.txt` and `/llms-full.txt`.

== Frequently Asked Questions ==

= Do I need to configure anything? =

No. After activation, llms.txt, llms-full.txt, the AI crawler allow-list and JSON-LD are all enabled with sensible defaults. You can refine the site summary, company info and toggles under Settings → AIO Toolkit.

= Where are llms.txt and llms-full.txt served? =

They are generated dynamically at `https://your-site/llms.txt` and `https://your-site/llms-full.txt`. Pretty permalinks are required (any structure other than "Plain" under Settings → Permalinks). If the feeds return 404 right after activation, visit Settings → Permalinks once to flush the rewrite rules.

= Is my private content exposed? =

No. Only published posts and pages are included. Drafts, private posts and password-protected posts are excluded from both feeds, and no JSON-LD is emitted for them either.

= Does it conflict with my SEO plugin? =

No. AIO Toolkit appends to the virtual robots.txt rather than replacing it, and it adds its own JSON-LD block. If your SEO plugin already outputs schema.org markup you can turn the JSON-LD tab off and keep only the llms.txt features.

= Does llms-full.txt affect performance? =

The output is cached in a transient, regenerated when your content changes and refreshed at most once a day. A configurable size cap (default 500 KB, maximum 10 MB) keeps the response small.

= How do I change the list of AI crawlers? =

Use the `flaio_ai_crawlers` filter to add or remove user agents:

`add_filter( 'flaio_ai_crawlers', function ( $agents ) { $agents[] = 'MyBot'; return $agents; } );`

= Does the plugin call any external service? =

No. Nothing is sent off your server, and no third-party API is contacted.

= Is multisite supported? =

Yes. Rewrite rules are registered per site on activation, and uninstalling cleans up every site in the network (on very large networks, the site it is run from).

== Screenshots ==

1. Settings → AIO Toolkit: site information used in the generated feeds.
2. llms.txt tab: enable the feeds and set the size cap.
3. robots.txt tab: allow all major AI crawlers with one toggle.
4. JSON-LD tab: organization name and logo for structured data.
5. The generated llms.txt served at the site root.

== Changelog ==

= 0.1.0 =
* Initial release: llms.txt, llms-full.txt, AI crawler allow-list, JSON-LD injection, tabbed settings screen.

== Upgrade Notice ==

= 0.1.0 =
Initial release.
