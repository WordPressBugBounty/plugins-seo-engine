=== SEO Engine - Smart SEO with AI, Schema & Redirection for WordPress ===
Contributors: TigrouMeow
Tags: seo, redirection, sitemap, schema, analytics
Donate link: https://www.patreon.com/meowapps
Requires at least: 6.0
Tested up to: 7.1
Stable tag: 0.9.1
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Made it through the SEO plugin wasteland? You've earned a coffee ☺️ Quietly powerful AI SEO that actually works. No bloat, just results. Enjoy! 💕

== Description ==

**SEO Engine is the lightweight, intelligent SEO solution for WordPress.** Built from scratch for modern websites, it combines powerful AI with clean code to help you rank better without slowing down your site. 

Stop fighting bloated SEO plugins that promise everything while turning your WordPress into a slower experience! 😘

SEO Engine focuses on what actually matters: **Content Quality** and **Technical Excellence**. It implements AI in its free version and provides full MCP support. Explore [our official site](https://meowapps.com/seo-engine) and check out [the docs](https://seo.thehiddendocs.com/) to get started.

== Core Modules ==

🏝️ **Content SEO**
Analyze and optimize your content with AI-powered insights. Get real-time scores, actionable suggestions, and automated fixes—all from one clean dashboard.

🛠️ **Technical SEO**
All the SEO basics, straight to the point. No endless settings for features you don't need—just what actually matters.

🪄 **Magic Fix**
Detected issues come with a quiet "fix" affordance — AI generates a concrete suggestion, you review it, you apply it. Internal links, ALT text, grammar, titles, excerpts, featured images. No bulk "fix everything" that rewrites your site behind your back.

📡 **AI Visibility**
See how your brand or product shows up when people ask ChatGPT, Gemini and Claude. Track your rank on each assistant, who gets recommended alongside you, and how it all trends over time — running on your own AI Engine providers, no extra subscription.

🚦 **Redirections & 404**
A clean redirect manager paired with a 404 monitor. Spot broken links, convert any 404 into a redirect with one click, and let SEO Engine create 301s automatically when slugs change.

🧠 **Intelligence Features**
Full MCP support means your entire site's SEO data is queryable via ChatGPT or Claude. Ask anything, get insights, automate workflows. That's all in the free version.

🎯 **Analytics & Tracking**
Connect to Google Analytics, Plausible Analytics, or use built-in privacy-friendly tracking. Monitor AI bot visits, Core Web Vitals, and performance, all in one place.

== 🏝️ Content SEO ==

Your content is your SEO foundation. SEO Engine helps you write better, rank higher, and engage readers.

**Smart Analysis:**

* AI-Powered Scoring
* Readability Checks
* Topic Completeness
* Structure Quality
* Originality & Personality

**Bulk Operations:**

* Analyze hundreds of posts at once
* Filter by score, status, or post type
* Export reports for team collaboration
* Import from Yoast or RankMath seamlessly

**Multi-Language:**

* Works with Polylang, WPML and Bogo
* Automatic language detection for AI suggestions
* Per-language SEO optimization

== 🛠️ Technical SEO ==

All the basics you need, without the bloat. Most SEO plugins bury essential features under endless settings. SEO Engine gets straight to the point—giving you settings only for what actually matters.

* Smart Sitemaps
* IndexNow (Bing, Yandex and Naver are pinged the moment you publish)
* Robots.txt Editor
* Canonical URLs
* Meta Tags
* Structured Data

== 🪄 Magic Fix ==

Most plugins flag SEO issues; very few actually fix them. Magic Fix is SEO Engine's AI-powered fixer — it sits next to each detected issue as a quiet, in-context affordance. Click it, and AI generates a concrete suggestion you can review before anything is written to your post. No bulk "fix everything" button that rewrites your site behind your back; every change is shown to you as a search/replace diff first.

Powered by AI Engine — bring your own provider (OpenAI, Anthropic, Google, etc.). All suggestions are generated in the post's language (Polylang, WPML and Bogo supported).

**Internal Links — section-aware suggestions:**

* Extracts keywords from the current post, then builds a candidate pool from the same category, same tags, and keyword search
* AI ranks the most relevant related posts and proposes link placements split by section: Introduction, Body, Conclusion
* Multiple placement strategies per target — link existing text (preferred), add a short parenthetical, or insert a new sentence
* You pick which option (or none) to apply per suggested target post
* Polylang, WPML and Bogo aware: only candidates in the same language; translations of the current post are excluded

**ALT Text — contextual generation:**

* Scans post content for `<img>` tags missing or with empty `alt` attributes
* Generates ALT using post title, content excerpt, image filename, and attachment title/caption as context
* Constrained to short, plain functional descriptions (no "stunning", "tranquil", or "Image of...")
* Updates the existing `<img>` tag in place — preserves all other attributes
* Up to 5 images per pass to keep the review surface manageable

**Grammar & Typos — HTML-aware:**

* AI returns precise search/replace pairs with 3-5 words of surrounding context
* Inline HTML inside the corrected phrase (`<strong>`, `<em>`, links) is preserved exactly
* Each correction is shown before it's applied; no blind rewrite of your prose

**Title & Excerpt:**

* Rewrite titles to your configured length range (default 30-75 chars) without losing the meaning
* Generate missing excerpts from content, or rewrite existing ones to a healthy 80-160 chars

**External Links:**

* Suggests authoritative external references (Wikipedia and similar) for relevant in-content terms
* Wraps existing phrases as links — never invents text

**Featured Image:**

* Generates a featured image with AI when a post is missing one
* Uploaded to your media library with proper title, description, and ALT text, then set as the post thumbnail

== 📡 AI Visibility ==

Search is moving into AI assistants. AI Visibility shows how your brand, product or site is recommended when people ask ChatGPT, Gemini and Claude the questions your buyers actually ask — and it runs entirely on your own AI Engine providers, with no extra subscription or credits.

Add a brand, describe it, and let AI draft the buyer-intent questions to track (or write your own, in any language). SEO Engine then asks each AI assistant and reads the answers back for you.

**What you see:**

* Your rank on each AI service, side by side (OpenAI, Anthropic, Google...)
* An overall AI Visibility score, trended over time
* The competitors recommended alongside you, so you know who you're up against
* Sentiment — how positively each assistant talks about you
* The full transcripts behind every result, so nothing is a black box

**How it works:**

* Bring your own providers through AI Engine — your keys, your models, no metered add-on
* Pick one model per provider (for example GPT, Claude and Gemini) and scan across all of them
* Scans run from your browser, one question per provider at a time, so nothing times out
* Every result records the exact model used, and the whole history is queryable over MCP

Scans ask each provider directly, so they measure what the models already know about you from training rather than a live web search — which is what an assistant answers from memory.

== 🚦 Redirections & 404 ==

A focused redirect manager and 404 monitor that pair naturally: most 404s should become redirects, and the workflow is built around that.

**Redirect Manager:**

* Exact-path redirects with 301, 302, 307, 308, and 410 Gone
* Hit counter and last-hit timestamp on every rule, so you know which ones are useful
* Sub-millisecond lookup on the front-end (object-cached, hashed, deferred writes)
* Static assets (images, CSS, JS, fonts) skipped automatically — no log bloat
* Optional auto 301 when a published post or page slug changes (off by default)
* Regex match type with capture groups for advanced rewrites

**404 Monitor:**

* Aggregated log — one row per unique URL, with hit count, first/last seen, and last referer
* One-click "Convert to Redirect" from any 404 entry
* Crawler filter so the log stays focused on real visitors
* Path exclusions with wildcards (e.g. `/feed*`, `/wp-admin/*`)
* Daily auto-prune with configurable retention

== 🧠 Intelligence Features ==

The entire SEO Engine has full MCP (Model Context Protocol) support, so everything can be queried via ChatGPT or Claude. Intelligence features are included in the Free version.

**What You Can Do:**

* Get automated daily summaries
* Generate meta descriptions automatically
* Suggest relevant keywords
* Rewrite content for better readability
* Bulk-optimize your entire site
* Multi-language content generation
* Query your SEO data via ChatGPT or Claude using MCP

== 🎯 Analytics & Tracking ==

Understand your traffic, monitor performance, all from your WordPress dashboard.

**Multiple Data Sources:**

* Google Analytics: Connect directly via Google Cloud
* Plausible Analytics: Privacy-friendly alternative
* Private Analytics: Built-in tracking with full data ownership
* Switch between sources or... combine them!

**What You Can Track:**

* Visits, unique visitors, bounce rates
* Top performing posts and pages
* Traffic by country and source
* Google Search Console: clicks, impressions and positions per post
* Core Web Vitals from PageSpeed Insights
* Real-time performance monitoring

Referral spam and junk analytics rows are filtered out automatically, so the numbers you see are real humans.

**AI Bot Tracking:**

* OpenAI (GPTBot, ChatGPT)
* Anthropic (Claude)
* Google (Gemini)
* Perplexity, Meta, Microsoft, and more!

== Pro Features ==

* Magic Fix
* Search Console Insights (per-post search data, quick wins, opportunity views)
* Content Intelligence (AI checks for originality, completeness, readability)
* WooCommerce AI
* Advanced Analytics
* Priority Support

== Why SEO Engine? ==

**Performance First**
Built from the ground up with modern code. No legacy bloat, no unnecessary features. Just clean, fast SEO, with a modern UI.

**AI-Powered**
Smart suggestions that actually help. Not "AI" slapped on as marketing, genuinely useful automation.

**Privacy-Friendly**
Choose between Google Analytics, Plausible, or fully private tracking. You control your data.

**Developer Friendly**
Clean APIs, WordPress hooks. Extend it your way.

**Constantly Evolving**
Weekly updates based on real user feedback. We listen, we improve.

== Installation ==

1. Upload `seo-engine` to `/wp-content/plugins/`
2. Activate through the 'Plugins' menu
3. Visit SEO Engine in your admin menu
4. Start optimizing! 🚀

For AI features, install [AI Engine](https://wordpress.org/plugins/ai-engine/).

== Frequently Asked Questions ==

= Is SEO Engine really faster than other SEO plugins? =

Yes. We've built SEO Engine from scratch with performance as a priority. No legacy code, no feature bloat. Just modern, efficient code.

= Can I import my data from Yoast or RankMath? =

Absolutely! SEO Engine imports your SEO titles, descriptions, and keywords seamlessly.

= Do I need AI Engine for all features? =

No. SEO Engine works great standalone. AI Engine unlocks Magic Fix, content generation, and advanced AI features, but core SEO functionality works without it.

= Which analytics should I use? =

* **Google Analytics**: If you're already using it and want detailed insights
* **Plausible**: If you want privacy-friendly analytics with a clean interface
* **Private Analytics**: If you want full data ownership and simplicity

You can switch between them or compare data from multiple sources.

= Does this work with WooCommerce? =

Yes! SEO Engine has special features for WooCommerce products (Pro version).

= Is this suitable for large websites? =

Absolutely. Our bulk operations and efficient code make it perfect for sites of any size.

= Can I track AI bots visiting my site? =

Yes! SEO Engine can track visits from GPTBot, Claude, Gemini, Perplexity, and many other AI crawlers.

= Do I need to do anything special to rank in AI Overviews or AI Mode? =

No. Google's official AI Optimization Guide (May 2026) confirms that AI Overviews and AI Mode run on the same index and ranking as normal Search — there is no separate AI ranking, no AI-specific markup, and no llms.txt that helps. What works is the same thing that has always worked: original first-hand content with a clear point of view, crawlable pages, no JavaScript-only main content, and writing for humans (not for AI parsers). SEO Engine focuses on exactly those signals, and we surface AI Overview CTR cannibalization in the Search Console tab so you can see when summaries are eating your clicks.

= What about multilingual sites? =

SEO Engine works with Polylang, WPML and Bogo, including post filtering and automatic AI language detection.

= Do I still need a separate Redirection plugin? =

No. The Redirections & 404 module handles URL redirects (301, 302, 307, 308, 410) and tracks 404s in one place. You can convert any 404 into a redirect with one click, and slug changes can be auto-redirected for you. Regex redirects are available in the Pro version.

= Does SEO Engine support IndexNow? =

Yes, and it is fully automatic. When you publish or update a post, SEO Engine instantly notifies Bing, Yandex, Naver and every IndexNow-compatible engine, so your content gets crawled right away instead of waiting. The verification key is generated and served for you; there is nothing to configure. Note that Google does not use IndexNow and keeps reading your sitemap instead.

== Changelog ==

= 0.9.1 (2026/08/29) =
* Add: AI cache reset in Maintenance settings.
* Update: Private Analytics and Plausible options are no longer Pro-only.
* Update: Refined the topic completeness prompt for better scoring accuracy.

= 0.9.0 (2026/08/21) =
* Add: WooCommerce custom instructions setting.
* Fix: Posts endpoint rebuilt in three passes to stop out-of-memory crashes on 128M hosts.
* Fix: GA4 reports are now scoped and fail-safe, and the analytics cache key matches correctly.
* Update: Content SEO filters respond instantly with loading feedback, a proper error state, and paging resets when filters change.
* Update: Tested up to WordPress 7.1.

= 0.8.9 (2026/08/14) =
* Add: AI Engine integration that feeds its SEO block with AI bot visits, AI visibility and top AI bots.
* Update: Shared dashboard now syncs with the new Board and AI site analysis.
* Update: Minimum required PHP version is now 8.1.
* Fix: Orphan page check now works with relative URLs and uses a static cache.
* Fix: Slug structure test returns 100 for the home page, which has no slug.
* Fix: Readability check now uses the rendered HTML content.
* 🎵 Discuss with others about Seo Engine on [the Discord](https://discord.gg/bHDGh38).
* 🌴 Keep us motivated with [a little review here](https://wordpress.org/support/plugin/seo-engine/reviews/). Thank you!
* 🥰 If you want to help us, check our [Patreon](https://www.patreon.com/meowapps). Thank you!

= 0.8.8 (2026/07/30) =
* Update: Mentioned Bogo alongside Polylang and WPML in the readme.
* Update: Reworked post type detection to use WordPress core functions instead of direct database queries.

= 0.8.7 (2026/07/27) =
* Fix: Bogo no longer limits queries to the site language, restoring the language filter, sitemap page count and internal link suggestions.
* Update: WooCommerce Assistant is now part of the Content SEO posts table, with bulk actions.

= 0.8.6 (2026/07/25) =
* Fix: MCP bulk tools no longer crash on large sites, and their SEO length checks are now consistent.
* Fix: mwseo_get_seo_statistics no longer crashes on large sites; the scan is now chunked and skips the content filter stack.
* Update: Google property and authentication checks are now handled separately.
* Update: Removed an unnecessary log entry.

= 0.8.5 (2026/07/23) =
* Add: Filter to override the language used for AI suggestions.
* Add: Bogo support for multilingual sites and centralised language handling.

= 0.8.4 (2026/07/22) =
* Add: custom instructions support for Magic Fix.
* Update: AI Visibility module is now available in the Pro version only.

= 0.8.3 (2026/07/22) =
* Add: AI Visibility module to track how brands rank across AI assistants.
* Update: Reworked the AI Visibility interface and made the scan safer and more reliable.
* Update: Turned the mentions count into a small coverage meter.
* Update: Extended SEO plugin compatibility mode to schema output and added a notice when another plugin renders the meta tags.

= 0.8.2 (2026/07/18) =
* Add: SEO plugin compatibility mode.
* Fix: JS-rendering false positive on content-dense pages.
* Add: Import redirections from Rank Math.
* Fix: Bulk tasks no longer stop on a failed entry, and only entries with a proposal value greater than zero are auto-selected.
* Update: Moved Quick Edit Suggest and undo buttons inside their fields.
* Update: WooCommerce AI assistant now works with any AI model.
* Fix: Ensure log file exists before opening to prevent warnings.

= 0.8.1 (2026/06/26) =
* Add: Quick Edit button on posts and new NekoMoreMenu for card actions.
* Update: Embedded Content SEO screens renamed to "All Posts (SEO)" and "All Pages (SEO)", with their header removed.
* Add: Parsers for third-party themes and builders (e.g., Divi) as a fallback when Live Content is unavailable.
* Update: Live Content user agent and SSL settings for broader compatibility.
* Fix: Check for empty parsers option.
* Update: MCP tools now accept ID, post_id, or id interchangeably for the post identifier, matching AI Engine's wp_* tools.

= 0.8.0 (2026/06/14) =
* Fix: Dashboard Search Visibility and Movers now query the active Search Console property, so multi-property sites no longer show empty stats.

= 0.7.9 (2026/06/14) =
* Update: Removed the Search Visibility ranking tracker and its Reverse Search companion, as Google closed the underlying API to new customers.
* Add: IndexNow support — publishing or updating a post now instantly notifies Bing, Yandex, and Naver, with a single toggle under Technical SEO (on by default).
* Add: Opportunity filters (Quick Wins, Low CTR, Invisible) and audience sorting (impressions, clicks, position, visitors, AI bots) to Content SEO.
* Fix: Google Search Console no longer auto-selects an unrelated property on agency accounts; it now only picks one matching the site's own domain.
* Fix: Analytics now filters out referral spam and drops GA4's "(not set)" and empty-host rows that inflated reports with fake visits.
* Add: A bot_type filter (ai, search, all) to bot traffic queries and the MCP tool, so a single call can answer how much AI traffic a site gets.
* Update: Published posts are now quietly re-scanned a few seconds after each save so scores no longer go stale, and the bulk scan MCP tool runs fast quick scans capped at 20 posts.
* Add: New mwseo_suggest_seo_excerpt MCP tool lets AI agents batch-fill missing meta descriptions.
* Update: Redesigned the dashboard with a Movers block, compact AI bots snapshot with analytics modal, Search Console visibility chart, and moved module toggles into Settings.
* Update: Rebuilt the Search Console tab around a weekly workflow with a performance chart, switchable opportunities workspace, and compact top-queries list.
* Update: Condensed the Content SEO toolbar and header into a single calm toolbar with overflow menu, and moved filter views and opportunity chips to the bottom bar.
* Update: Refreshed the readme to replace the retired Search Engine Ranking with Search Console Insights and Content Intelligence, and documented IndexNow and referral-spam filtering.
* 🎵 Discuss with others about Seo Engine on [the Discord](https://discord.gg/bHDGh38).
* 🌴 Keep us motivated with [a little review here](https://wordpress.org/support/plugin/seo-engine/reviews/). Thank you!
* 🥰 If you want to help us, check our [Patreon](https://www.patreon.com/meowapps). Thank you!

= 0.7.8 (2026/06/10) =
* Fix: Visitors panel now shows "No visits in the last 30 days" instead of empty bars on posts with no traffic.

= 0.7.7 (2026/06/10) =
* Add: Per-post Google Search Console data throughout Content SEO, including row metrics, a Search pulse in the post editor, and a Search Visibility block on the dashboard.
* Add: Redesigned post card with an audience panel showing AI bots, Search Console, and a 30-day visitors chart, plus a to-do issue list with in-place score spinner and Reset issues action.
* Add: Language filter (Polylang/WPML) to Bulk SEO, matching the existing Content SEO filter.
* Add: "Last insight" timestamp next to the Daily Insights refresh button.
* Update: AI Content Intelligence checks are now gated behind Pro.
* Update: Search Console tab reworked into actionable sections: pages with issues, top queries with landing pages, and most-visited pages with their top query.
* Fix: Thin Content bulk list incorrectly showed "Will be hidden" on every row; unselected posts now correctly show as kept in search.
* Fix: Bulk SEO "Generate all" and "Apply selected" actions could not be stopped once started; the progress bar stop button now works.
* Fix: Reserved-height spinners added to the post card so Search and Visitors data no longer cause layout jumps while loading.

= 0.7.6 (2026/06/03) =
* Fix: Bulk SEO proposals not visible due to broken style variables.
* Update: Issue tooltips now include a resolve hint.
* Add: Expandable diff preview for grammar and alt proposals.
* Update: Thin-content noindex is now opt-in for well-scored posts.

= 0.7.5 (2026/06/02) =
* Add: Bulk SEO tab for fixing SEO issues by type across multiple posts at once.
* Update: Content SEO redesigned as a full posts manager with a shared analysis toolbar.
* Update: Cleaner card layout in Content SEO.
* Update: Simpler, insights-first dashboard.

= 0.7.4 (2026/05/30) =
* Update: Improved logging for live content processing.
* Fix: Removed unused excluded taxonomies and tax query from post sitemap.
* Fix: Bulk Scan button now checks for AI Engine availability before proceeding.
* Add: Sitemap Types and Taxonomies clean up.

= 0.7.3 (2026/05/20) =
* Update: Deprecated the llms.txt editor and removed the chunkability signal, following Google's May 2026 AI Optimization Guide.
* Add: First-hand originality check has been strengthened.
* Add: JavaScript-rendered content detector.
* Add: AI Overview cannibalization view in Search Console.

= 0.7.2 (2026/05/17) =
* Update: Redesigned the LLMs tab with live-status indicator, size and links stats, AI-generation and delete actions, and an explanatory notice about llms.txt.
* Update: The llms.txt template now seeds from real pages and recent posts instead of placeholder text.

= 0.7.1 (2026/05/16) =
* Add: Google Search Console integration with OAuth, multi-property support (including Polylang/WPML multi-domain), and a new Search Console dashboard tab showing overview stats, quick wins, top pages, and top queries.
* Add: Several new MCP tools — `gsc_site_pulse`, `gsc_weekly_digest`, `quick_wins`, `post_pulse`, `status`, `suggest_seo_title`, `suggest_internal_links`, `generate_internal_link_placements`, and `get_orphan_pages`.
* Update: Reworked the Readability check as "Content Clarity," now scoring how easy a post is to skim and extract by AI bots, with concrete suggestions instead of a Flesch number. The check is off by default; existing internal data keys are preserved for API and MCP consumers.
* Fix: `og:type` no longer incorrectly emits "article" on the homepage when a static front page is set; it now correctly uses "website."
* Fix: MCP tools now perform real text search, support site-wide aggregate for `get_issues`, track Googlebot variants, and fall back to CrUX when PageSpeed is unavailable.

= 0.7.0 (2026/05/10) =
* Add: Redirections + 404 module — manage URL redirects, monitor 404 errors, and convert any 404 into a redirect with one click.
* Add: Optional auto 301 when a published post or page slug changes.
* Add: Regex match type for redirect rules.

= 0.6.5 (2026/04/25) =
* Add: Google Setup documentation link.
* Add: Canonical URL support in the post editor.
* Fix: AI suggestions now use the post's actual language instead of the global language on Polylang/WPML sites.
* Update: Better UI/UX.

= 0.6.4 (2026/04/15) =
* Fix: Downgraded XSL version from 2.0 to 1.0 in sitemap stylesheets for broader compatibility.
* Update: Improved sitemap file path handling and file writing logic.
* Update: Refactored robots.txt and llms.txt handling to use dynamic paths and improved file writing logic.
* Update: Empty LLMs now use a template instead of an error message.
* Fix: Corrected NekoMessage variant.
* Update: Switched to AI Engine's new hasAI() and hasMCP() helpers; MCP no longer requires an API key.
* Add: UTF-8 BOM for llms.txt.
* 🎵 Discuss with others about Seo Engine on [the Discord](https://discord.gg/bHDGh38).
* 🌴 Keep us motivated with [a little review here](https://wordpress.org/support/plugin/seo-engine/reviews/). Thank you!
* 🥰 If you want to help us, check our [Patreon](https://www.patreon.com/meowapps). Thank you!

= 0.6.3 (2026/03/10) =
* Fix: Corrected sitemap last modified dates so search engines receive accurate update information.
* Update: Improved sitemap generation to avoid unnecessary repeated processing.
* Update: Extended category handling to work with all relevant taxonomies.
* Add: Introduced bulk actions for categories to manage SEO settings more quickly.
* Update: Automatically disable dependent child features when the Technical SEO module is inactive to prevent confusion.

= 0.6.2 (2026/02/25) =
* Add: Allow editing categories within the Technical SEO.
* Update: Improve sitemap generation response handling.
* Add: Introduce options to exclude specific content via custom sitemap exclude providers.
* Update: Change sitemap file path handling to store the site URL.
* Add: Include aggregate rating and reviews in product schema.
* Add: Show AI Engine status in a pill tooltip.
* Update: Define clear read/write access levels for all MCP tool definitions.

= 0.6.1 (2026/02/13) =
* Add: Enhanced "Internal Links" suggestions with section-aware recommendations.
* Fix: Adjusted the Edit SEO modal to respect configured title and excerpt length.
* Fix: Reduced false positives in the structure quality check.
* Fix: Improved internal link detection for Polylang multi-domain setups.

= 0.6.0 (2026/02/12) =
* Update: Relaxed SEO title length scoring to better support titles between 30–75 characters.
* Fix: Improved handling of non-English characters in AI-powered SEO suggestion.
* Update: Made grammar and originality checks less strict.
* Update: Reduced the penalty for missing internal links to avoid overly harsh SEO scores on shorter content.
* Fix: Skipped schema validation when the schema module is disabled.
* Update: Removed Archives, Category, and similar prefixes from archive pages.
* Fix: Corrected a field event from onChange to onBlur in the bot instructions.
* Add: Introduced new post selection options in the Settings for Content SEO.
* Add: Added a "no index" status option in the Post Editor.
* 🎵 Discuss with others about Seo Engine on [the Discord](https://discord.gg/bHDGh38).
* 🌴 Keep us motivated with [a little review here](https://wordpress.org/support/plugin/seo-engine/reviews/). Thank you!
* 🥰 If you want to help us, check our [Patreon](https://www.patreon.com/meowapps). Thank you!

= 0.5.8 (2026/01/30) =
* Add: New "Page Title" section in Technical SEO settings with an option to disable automatic title generation.
* Add: New MCP tool `mwseo_get_post_analytics` to retrieve per-post analytics data from Google Analytics, Plausible, and Private Analytics.

= 0.5.7 (2026/01/27) =
* Update: Unified all SEO title and description length checks to use a display‑width approach that better reflects Google’s pixel-based limits, especially for CJK characters.
* Add: Introduced a new developer MCP tool, `mwseo_get_posts_needing_seo`, and updated statistics with a `posts_needing_attention` count so AI agents and advanced workflows can reliably identify posts with truly problematic SEO lengths.
* Fix: Ensure Google Analytics token refresh correctly updates all instances so MCP analytics no longer fail with expired tokens.  
* Add: Introduce an llms.txt editor to manage and customize your LifterLMS configuration file from the dashboard.  
* Add: Enable searching posts by meta fields so you can find content using additional metadata criteria.

= 0.5.5 (2026/01/05) =
* Add: Option to enable or disable checking for missing ALT texts.
* Add: Warning in the SEO editor when the site name length may impact Google.

= 0.5.4 (2025/12/03) =
* Add: Option to enable or disable the title and description metadata.   
* Update: Delete logs larger than 1MB and remove unnecessary log entries. 
* Update: Better explain AI Engine's role and capabilities.
* Fix: Resolved a TypeError in the Insights tab.  
* Fix: Corrected the analyze-on-update behavior.
* 🎵 Discuss with others about Seo Engine on [the Discord](https://discord.gg/bHDGh38).
* 🌴 Keep us motivated with [a little review here](https://wordpress.org/support/plugin/seo-engine/reviews/). Thank you!
* 🥰 If you want to help us, check our [Patreon](https://www.patreon.com/meowapps). Thank you!

= 0.5.3 (2025/11/10) =
* Add: Magic Fix for Featured Image with enforced 'seo' scope for AI requests.
* Add: Animated scoring and progressive penalty display.
* Add: "Semantic Alignment" option in Settings.
* Add: Sorted issues are now prioritized by impact.
* Add: Mouse wheel navigation between issues.
* Update: MeowCommon is now MeowKit. 
* Update: Pillar bullets reset to gray during analysis; skipped checks now shown as gray.
* Update: REST used for Insights instead of Settings storage.
* Fix: Resolved concurrent analysis issues.
* Fix: Better page title validation.
* Fix: Duplicate issue reporting removed.
* Fix: Custom sitemap post types and taxonomies restored.
* Fix: Minor UI tweaks for Edit Post tabs (“Basic” / “SEO”).
* Fix: Resolved an issue with the Magic Fix for Internal Links.  
* Fix: Corrected the detection of enabled checks.

= 0.5.1 (2025/10/24) =
* 🥳 This is the biggest update in SEO Engine’s history! A complete redesign from the ground up! Every part of the plugin has been improved: UI, performance, intelligence, analytics, and the overall experience.
* Add: Magic Fix 2.0 with smarter ALT-text generation, internal-link suggestions, and a new “Find All Solutions” button.
* Fix: Grammar & typo fixer rebuilt with HTML-aware context matching and full-content caching.
* Info: Unified AI engine replaces the old Magic Fix system.
* Add: AI Agent instructions.
* Add: Plausible Analytics integration alongside Google Analytics and Private Analytics.
* Add: Daily Insights feature summarizing your SEO and traffic performance.
* Add: AI Bot Tracking to monitor visits from GPTBot, Claude, Gemini, and more.
* Add: New AI-powered checks for readability, topic completeness, structure quality, and originality.
* Add: Smart caching, in-place Quick & Full Analysis, and Clear AI Cache tool.
* Update: Revamped charts, improved filters, faster UI, and clearer scoring.
* Fix: Non-ASCII permalink encoding and analysis buffer issues resolved.
* Add: Meta Robots Tag validation and improved schema integrity checks.
* Fix: Canonical URL and structured-data handling across post types.
* Update: Simplified Maintenance section, lowered thresholds, and removed PHP warnings.
* Add: Autosave support for AI-generated product content plus improved Vision AI for WooCommerce.
* Fix: WooCommerce AI field mapping and error handling improved.
* Info: WooCommerce AI remains available for free following feedback.
* Add: Complete UI redesign for Content SEO and Scoring System — faster, cleaner, and modular.
* Update: Dashboard layout, icons, color system, and filters refined.
* Fix: Major performance boost with smarter caching and fewer API calls.
* Add: Smarter penalty-based scoring system with post-type thresholds.
* Update: Entire codebase modernized for stability, memory use, and Meow Apps architecture alignment.
* Add: CJK language support to improve SEO scoring accuracy for Chinese, Japanese, and Korean content.
* Update: Enhanced and consolidated MCP tools for a better user experience and streamlined functionality.

= 0.4.3 (2025/10/10) =
* Add: Vision and product details features to WooCommerce AI.
* Add: Plausible Analytics integration (cloud and self-hosted).
* Update: Enhanced NekoHeader with dynamic section names.
* Fix: Post status dropdown now syncs correctly with internal filters.

= 0.4.2 (2025/09/29) =
* Add: Status filter in Content SEO tab.
* Update: Replaced post type checkboxes with cleaner selectors.
* Add: Settings to customize content scan batch sizes.
* Fix: Issues with Content SEO status handling.

= 0.4.1 (2025/08/27) =
* Update: Reworked AI prompts to exclude header/footer for clearer outputs.
* Add: "Live Content" option to parse HTML content.
* Fix: Language selection for AI suggestions.
* Fix: Top Posts filtered by "All Countries" now show cumulative stats.

= 0.4.0 (2025/06/19) =
* Add: Model Context Protocol (MCP) integration for AI assistants.
* Add: Comprehensive website health monitoring with PageSpeed Insights.
* Add: Advanced analytics with Core Web Vitals tracking.
* Add: Bulk SEO operations for efficient management.
* Add: SEO statistics dashboard with daily insights.
* Add: Duplicate title detection and missing SEO finder.
* Add: AI bot/agent tracking (GPTBot, Claude, Gemini, etc).
* Fix: Readability score calculation using Flesch Reading Ease.
* Fix: Insights API integration.
* Update: Complete UI modernization with better organization.

= 0.3.9 (2025/05/11) =
* Update: Reorganized dashboard with modular navigation.
* Add: Import from Yoast and RankMath (titles, descriptions, keywords).
* Add: New scoring system with penalty-based calculation.
* Add: AI-powered checks (readability, topic completeness, personality).

= 0.3.8 (2025/05/01) =
* Add: Polylang compatibility with post filtering and language detection.
* Add: Proper noindex marking for sitemap exclusions.
* Fix: Search interference with post selection.
* Update: Language selection displays actual language names.

= 0.3.7 (2025/02/17) =
* Add: Enhanced documentation and refactored settings tabs.
* Add: Magic Fix for ALT text, internal links, grammar fixes.
* Fix: Language handling in WooCommerce helper.

= 0.3.6 (2025/01/15) =
* Add: Better sitemap generation (faster than WordPress core).
* Add: Content depth analysis and structure quality checks.
* Fix: Various UI improvements and bug fixes.

= 0.3.5 (2025/01/04) =
* Add: Search Engine Ranking.
* Update: Enhanced UI with better filtering and sorting.
* Fix: Minor stability improvements.

= 0.3.4 (2024/10/17) =
* Fix: Excluded posts properly removed from sitemaps.

= 0.3.3 (2024/09/18) =
* Fix: Better WooCommerce product support.

= 0.3.2 (2024/08/01) =
* Add: Comprehensive logging system.
* Update: Mobile-responsive UI improvements.

= 0.3.1 (2024/07/07) =
* Update: Sitemap enhancements and fixes.
* Update: Social post improvements.

= 0.3.0 (2024/06/08) =
* Add: Clear logs functionality.
* Fix: Common Dashboard visibility.
* Update: Style improvements and theme cleanup.

= 0.2.8 (2024/05/14) =
* Update: Module-based settings organization.
* Add: Multi-search engine preparation.
* Add: Sitemap generation optimization settings.

= 0.2.6 (2024/05/09) =
* Add: Reverse Search with Google API.
* Add: Image ALT text suggestions.
* Fix: Security improvements and input sanitization.

= 0.2.4 (2024/04/27) =
* Add: WooCommerce Assistant.
* Add: Social Card previews (Open Graph, Twitter).
* Update: Analytics sorting by score.
* Fix: Post fetching for analytics charts.

= 0.2.3 (2024/04/06) =
* Add: Social Cards feature.
* Fix: Sitemap generation.
* Update: Tab reorganization.

= 0.2.2 (2024/03/23) =
* Add: Analyze feature with batch operations.
* Update: Sanitized options and improved error handling.

= 0.2.1 (2024/02/09) =
* Update: Renamed to SEO Engine!

= 0.1.8 (2024/02/02) =
* Add: Import/Export for data management.
* Add: AI Engine status indicator.
* Fix: Various stability improvements.

= 0.1.0 (2023/09/11) =
* Add: Magic Fix is working! 🎉

= 0.0.9 (2023/08/17) =
* Add: AI support for titles, descriptions, keywords.
* Add: SEO Score system.

= 0.0.1 =
* First release.
