# Frontierline AIO Toolkit

Make a WordPress site readable by LLMs and AI search: auto-generated `llms.txt` / `llms-full.txt`, an allow-list for major AI crawlers, and schema.org JSON-LD — from one settings screen.

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/frontierline-aio-toolkit.svg)](https://wordpress.org/plugins/frontierline-aio-toolkit/)
[![WordPress Plugin: Tested WP Version](https://img.shields.io/wordpress/plugin/tested/frontierline-aio-toolkit.svg)](https://wordpress.org/plugins/frontierline-aio-toolkit/)
[![License: GPL v2](https://img.shields.io/badge/License-GPLv2-blue.svg)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html)

**Published on the WordPress.org plugin directory:** https://wordpress.org/plugins/frontierline-aio-toolkit/
Japanese listing: https://ja.wordpress.org/plugins/frontierline-aio-toolkit/

---

## Why this exists

An `llms.txt` file on its own does not make a site citable by an AI assistant. Three things have to hold at the same time:

1. **Content the model can read** — a concise `llms.txt`, plus a cached, size-capped full-text `llms-full.txt`.
2. **Permission to fetch it** — an allow-list for named AI crawlers in `robots.txt`.
3. **Context the model can parse** — schema.org JSON-LD describing what each page actually is.

Most plugins in this space cover only the first. This one covers all three, and is built to sit *next to* an existing SEO plugin rather than fight it.

## Features

| Feature | Detail |
|---|---|
| `llms.txt` | Site summary, company info and key pages, served dynamically at `/llms.txt` |
| `llms-full.txt` | Full-text aggregation of published posts and pages, cached, with a configurable size cap (default 500 KB, max 10 MB) |
| AI crawler allow-list | `Allow` rules for 22 named crawlers (GPTBot, OAI-SearchBot, ClaudeBot, PerplexityBot, Google-Extended, Applebot-Extended, CCBot, Amazonbot, …) |
| JSON-LD | Article / WebPage / Organization + WebSite / Person / ImageObject |
| Settings | One tabbed screen under **Settings → AIO Toolkit**. Works with defaults; nothing required to get started |
| Privacy | Drafts, private and password-protected content are excluded from both feeds and emit no JSON-LD. Nothing is sent off the server; no third-party API is contacted |

## Design decisions worth reading

These are the non-obvious parts — each one exists because the naive implementation breaks in production.

**robots.txt is appended at filter priority `100000`.**
Plugins such as Yoast SEO rebuild the entire virtual robots.txt late in the request. Appending at a normal priority means your rules are silently discarded. Running last keeps both outputs intact.

**Duplicate structured data is surfaced, not silently merged.**
When Yoast SEO, Rank Math, All in One SEO or SEOPress is detected, the settings screen says so, right next to the toggle that turns this plugin's JSON-LD off — so the site owner makes the call instead of shipping two competing graphs.

**Multisite activation never calls `flush_rewrite_rules()` inside `switch_to_blog()`.**
`$wp_rewrite` is not re-initialised per site, so flushing inside the loop writes the permalink structure of the *current* request's site onto every other site in the network. The plugin deletes the `rewrite_rules` option per site instead and lets each site rebuild its own rules on its next request. Large networks (`wp_is_large_network()`) are skipped entirely to avoid activation timeouts.

**`llms-full.txt` is cached in a transient**, regenerated when content changes and refreshed at most once a day, with a hard size cap — so a large site does not turn a crawler request into a full table scan.

**Feed queries do not suppress query filters.**
Membership and access-control plugins already exclude their restricted content from normal queries; by leaving filters active, that exclusion carries over to `llms-full.txt` automatically instead of having to be re-implemented here.

**No dependencies.** A ~20-line `spl_autoload_register` maps `FrontierLine\AIO\Llms_Generator` to `includes/class-llms-generator.php`. No Composer runtime requirement, no vendor directory in the distributed plugin.

## Architecture

```
frontierline-aio-toolkit.php   Bootstrap, constants, autoloader, activation/deactivation
includes/
  class-plugin.php             Singleton bootstrap, hook registration
  class-settings.php           Options, defaults, admin screen wiring
  class-rewrite.php            /llms.txt and /llms-full.txt endpoints
  class-llms-generator.php     Feed generation, caching, size cap
  class-robots-handler.php     AI crawler allow-list (filter priority 100000)
  class-schema-injector.php    JSON-LD graph construction
admin/
  partials/settings-page.php   Tabbed settings UI
  assets/css/admin.css
languages/                     .pot for translation
uninstall.php                  Option cleanup, multisite-aware
```

## Extending

```php
add_filter( 'flaio_ai_crawlers', function ( $agents ) {
    $agents[] = 'MyBot';
    return $agents;
} );
```

Available filters: `flaio_ai_crawlers`, `flaio_llms_txt`, `flaio_llms_full_txt`, `flaio_llms_full_post_types`, `flaio_llms_full_max_posts`, `flaio_schema_graph`.

## Quality

- Passed the **WordPress.org plugin directory review** and is published in the official directory.
- **Plugin Check: 0 errors, 0 warnings.**
- Verified on **PHP 7.4+**, **WordPress 6.0+**, tested up to 7.1.
- Multisite-aware activation, deactivation and uninstall.
- Internationalised; the Japanese translation was submitted through the official GlotPress workflow and **approved**, so a `ja` language pack is distributed by WordPress.org.

## Development workflow

Built solo — planning, architecture, implementation, review submission and maintenance.

Development is AI-assisted: generative AI is used for implementation, investigation, code review and documentation, under a fixed process. The scope a model is allowed to touch, the naming conventions and the things it must not do are written down before work starts; generated diffs are read before they are applied; and the judgment calls that decide correctness — impact analysis, anything that changes stored data, and whether something ships — stay with me.

## Requirements

- WordPress 6.0+
- PHP 7.4+
- Pretty permalinks (any structure other than "Plain")

## Installation

Install **Frontierline AIO Toolkit** from the WordPress plugin directory, or clone this repository into `wp-content/plugins/frontierline-aio-toolkit` and activate it. Then visit **Settings → AIO Toolkit**.

If `/llms.txt` returns 404 immediately after activation, open **Settings → Permalinks** once to flush rewrite rules.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

---

## 日本語

WordPress サイトを LLM・AI 検索から読み取れる状態にするプラグインです。`llms.txt` と `llms-full.txt` の自動生成、主要 AI クローラー 22 種の robots.txt 許可制御、schema.org JSON-LD の出力を、設定画面 1 枚で行います。

`llms.txt` を置くだけでは AI に引用されません。「読めるコンテンツ」「取得の許可」「解釈できる文脈」の 3 つが同時に成立する必要があり、本プラグインはその 3 つをまとめて扱います。

既存 SEO プラグインと競合しない設計です。robots.txt はフィルタ優先度 100000 で追記するため、Yoast SEO のように robots.txt を後から再構築するプラグインと併用しても双方の出力が残ります。Yoast SEO / Rank Math / All in One SEO / SEOPress を検出した場合は設定画面にその旨を表示し、JSON-LD を無効化する選択肢を同じ画面に置いています。

WordPress 公式ディレクトリの審査を通過して公開中。Plugin Check は ERROR 0 / WARNING 0、PHP 7.4 以上で動作検証済み。日本語訳は公式 GlotPress 経由で承認され、言語パックが配信されています。

企画・設計・実装・審査申請・保守まで単独で担当。開発では生成AIを併用し、変更してよい範囲と禁止事項を先に明文化してから着手する、生成された差分を読んでから反映する、影響範囲の判断と本番反映の可否は自分で行う、という手順を固定しています。

**作者**: [株式会社フロンティアライン](https://frontierline.co.jp/)
**関連**: [AI検索改善レポートツール](https://aiseo.frontierline.work/) — サイトの構造・記述が AI からどう読み取られるかを診断するツール
