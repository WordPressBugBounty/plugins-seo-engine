=== SEO Engine ===
Contributors: TigrouMeow
Tags: seo, ai, analytics, google, optimization
Donate link: https://www.patreon.com/meowapps
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 0.5.8
Requires PHP: 7.4
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

* Works with Polylang and WPML
* Automatic language detection for AI suggestions
* Per-language SEO optimization

**Magic Fix:**

* Generate optimized titles and meta descriptions
* Fix grammar and typos with HTML-aware context
* Add internal links with smart suggestions
* Optimize ALT text for images
* Improve readability and structure

== 🛠️ Technical SEO ==

All the basics you need, without the bloat. Most SEO plugins bury essential features under endless settings. SEO Engine gets straight to the point—giving you settings only for what actually matters.

* Smart Sitemaps
* Robots.txt Editor
* Canonical URLs
* Meta Tags
* Structured Data

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
* Core Web Vitals from PageSpeed Insights
* Real-time performance monitoring

**AI Bot Tracking:**

* OpenAI (GPTBot, ChatGPT)
* Anthropic (Claude)
* Google (Gemini)
* Perplexity, Meta, Microsoft, and more!

== Pro Features ==

* Magic Fix
* WooCommerce AI
* Search Engine Ranking
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

= What about multilingual sites? =

SEO Engine works with Polylang, including post filtering and automatic AI language detection.

== Changelog ==

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
