=== SEO Engine ===
Contributors: TigrouMeow
Tags: seo, ai, google, search, optimization
Donate link: https://www.patreon.com/meowapps
Requires at least: 6.0
Tested up to: 6.8
Stable tag: 0.4.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The modern, lightweight SEO solution for WordPress.

== Description ==

Tired of bloated SEO plugins like Bloast SEO and friends that promise everything while turning your WordPress into a slower experience? Welcome to SEO Engine! We believe SEO should be elegant, not exhausting! ☀️

We built SEO Engine for people who want powerful SEO without the complexity. No 500+ option screens, no confusing features, just straightforward tools that help you rank better. We focus on what actually matters in modern SEO.

=== Content Optimization ===
Write content, click a button, and watch SEO Engine work its magic. Our **AI-powered Magic Fix** instantly optimizes titles and descriptions, while our **SEO scoring system** gives you clear A-F grades. We analyze readability, keywords, and structure, then actually fix the problems instead of just complaining about them.

=== Website Health & Speed ===
Your site's performance affects rankings more than any meta tag ever will. SEO Engine integrates **Google PageSpeed Insights** to monitor your **Core Web Vitals**, accessibility, and loading times.

=== Tracking & Analytics ===
Connect **directly to Google Cloud**, no expensive third-party services acting as middlemen with your data. Get your **Google Analytics** metrics, track keyword rankings, and monitor performance straight from the source.

=== Technical SEO ===
Generate **clean sitemaps** that search engines actually want to read. Edit your **robots.txt** without breaking everything. Handle redirects, canonical URLs, and multi-language sites... without installing five different plugins! It just works.

=== AI ===
When paired with AI Engine, SEO Engine becomes your content optimization assistant. Generate meta descriptions, suggest keywords, and bulk-optimize your entire site. This isn't "AI" slapped on as a marketing term. It's genuinely useful automation that saves hours.

Stop letting your SEO plugin slow down your site. Join the rebellion against bloat! 🏝️

== Installation ==

1. Upload `seo-engine` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Visit SEO Engine in your admin menu to get started

== Upgrade Notice ==

Replace all the files. Nothing else to do.

== Frequently Asked Questions ==

= Is SEO Engine really faster than other SEO plugins? =
Yes. We've built SEO Engine from the ground up with performance in mind. No legacy code, no feature bloat. Just clean, modern code that does SEO right.

= Can I import my data from other SEO plugins? =
Absolutely! SEO Engine can import your SEO data from Yoast and RankMath, making the switch seamless.

= Do I need AI Engine for all features? =
No, SEO Engine works great on its own. AI Engine unlocks additional AI-powered features like Magic Fix and content generation, but core SEO functionality works without it.

= Is this suitable for large websites? =
Yes! SEO Engine is designed to scale. Our bulk operations and efficient code make it perfect for sites of any size.

== Changelog ==

= 0.4.0 (2025/06/19) =
* Add: Model Context Protocol (MCP) integration for AI assistants
* Add: Comprehensive website health monitoring with PageSpeed Insights
* Add: Advanced analytics with Core Web Vitals tracking
* Add: Bulk SEO operations for efficient management
* Add: SEO statistics dashboard
* Add: Duplicate title detection
* Add: Posts missing SEO finder
* Fix: Readability score calculation method
* Fix: Insights API integration
* Update: Modernized UI with better organization

= 0.3.9 (2025/05/11) =
* Update: Reorganized the dashboard with new modules for easier navigation.
* Add: Import option for Yoast and Rank Math SEO data, including SEO title, description, and keywords.

= 0.3.8 (2025/05/01) =
* Fix: Allowed saving even when no posts are selected.
* Fix: Resolved an issue where search was interfering with selected posts.
* Add: Excluded posts from sitemaps are now properly marked as noindex; also added a post selector.
* Update: Improved language selection by displaying language names in SEO settings.
* Add: Added compatibility with Polylang, including post filtering and automatic AI language setting.
* Add: Introduced a legend for SE Search icons to improve usability.

= 0.3.7 (2025/02/17) =
* Add: Added documentation and refactored settings tabs for better organization.
* Fix: Corrected language input handling in the WooCommerce helper.
* ✨ Please leave a nice review for [SEO Engine on WordPress](https://wordpress.org/support/plugin/seo-engine/reviews/#new-post). That will help a lot, thank you! 💖

= 0.3.6 (2025/01/15) =
* Add: Better sitemap than the core WordPress one.
* Add: New features in the UI to ease the SEO process.
* Fix: Minor issues.

= 0.3.5 (2025/01/04) =
* Update: Enhanced the UI.
* Fix: Minor issues.
* Add: Search Engine Ranking in the Pro Version.

= 0.3.4 (2024/10/17) =
* Fix: Excluded posts should not appear in the sitemap.

= 0.3.3 (2024/09/18) =
* Fix: Works better with WooCommerce products.

= 0.3.2 (2024/08/01) =
* Add: Logging.
* Update: Enhanced the UI for small screens.

= 0.3.1 (2024/07/07) =
* Update: Various fixes and enhancements related to sitemaps.
* Update: Enhancements related to Social Posts.

= 0.3.0 (2024/06/08) =
* Add: Clear logs functionality.
* Fix: Issue with Common Dashboard visibility when only SEO Engine is used.
* Update: Removed NekoTheme and adjusted styles.
* Update: Overridden thumbnail filter for OG.

= 0.2.8 (2024/05/14) =
* Update: Each tab now has its own module settings on the dashboard.
* Add: Collapsible setup for future search engines integration.
* Add: Prepared search form for compatibility with multiple search engines.
* Add: New setting to disable Sitemap generation on updates.

= 0.2.6 (2024/05/09) =
* Add: Introduced Reverse Search functionality utilizing Google API for enhanced query accuracy.
* Update: Improved search engine ranking and re-analysis post-optimization processes.
* Add: New suggestion feature for image alt text and enhanced dashboard logging for better tracking.
* Fix: Various bug fixes including typo corrections, post-analysis adjustments, and sanitation of input data for security.
* Remove: Deprecated chart options to streamline user interface.

= 0.2.4 (2024/04/27) =
* Add: WooCommerce Assistant for enhanced e-commerce functionality.
* Add: Social Card previews for SNS and Open Graph integration.
* Update: Improved sitemap styling and added robots.txt filter.
* Update: Enhanced analytics with sorting by score, and added "ok" status to clean up warnings.
* Fix: Corrected post fetching for analytics charts.
* Add: Expanded post type selection for broader analytics scope.
* Update: Tab persistence on page reload for better user experience.
* Update: Refined issue tracking, replacing "issues" with "analyzed" and distinguishing between issues and major issues.

= 0.2.3 (2024/04/06) =
* Add: Social Cards. 
* Fix: Sitemap generation issue.
* Update: Re-organized the tabs for better usability.

= 0.2.2 (2024/03/23) =
* Add: Introduced the Analyze feature and core context for enhanced functionality.
* Add: Added an experimental section to explore new features.
* Update: Sanitized options and fixed issues with setting routes, batch "Analyze" operations, and stats display. Improved AI Engine status visibility and error handling.
* Update: Improved initialization with current options, split post table into components, and refined handling of empty documents.
* Update: Removed "Auto Analyze" setting and unnecessary Context and Options for a cleaner codebase.
* Fix: Addressed problems in stats, pagination, search, and "skip" status. Corrected direction of status icons and issues with the Magic Fix button.

= 0.2.1 (2024/02/09) =
* Update: It is now SEO Engine!

= 0.1.8 (2024/02/02) =
* Add: Maintenance Import / Export options for enhanced data management.
* Add: NekoPill indicator for AI Engine status, providing visual feedback on operational status.
* Fix: Addressed issues with undefined length and competitors for more reliable analytics.
* Fix: Resolved language update reset issue, ensuring persistent settings across updates.
* Fix: Eliminated warning log spam when no Google key is present, for a cleaner log experience.

= 0.1.7 (2023/12/25) =
* Update: UX enhancements with improved charts and loading indicators.
* Add: Settings for Refresh Interval, Search Depth, Track Points.
* Add: Force refresh button for immediate data update.
* Add: Links for viewing competitors' websites.
* Add: Integration of data fetching from Google API and completion of SearchCard.
* Add: CRUD operations for Searches.
* Add: New Search Card component.
* Add: Google Ranking Tab for better insights.
* Update: Replaced regular options with an advanced component for better query handling.

= 0.1.6 (2023/11/29) =
* Add: Missing alt text images support.
* Update: Code cleanup.

= 0.1.5 (2023/10/23) =
* Fix: Refresh dynamic values upon refreshing settings, including custom post types and language.
* Update: Replaced deprecated NekoModal buttons for compatibility.
* Misc: Removed testing logs for cleaner codebase.

= 0.1.4 (2023/10/03) =
* Add: New feature to block the GPTBot, which is the OpenAI bot. This way, you can make sure your content is not used by OpenAI to train their models.

= 0.1.3 (2023/10/02) =
* Add: Keywords for Posts. They will be reused by the AI for various purposes.

= 0.1.2 (2023/09/22) =
* Add: Sitemap feature.
* Update: Optimized the bundles.

= 0.1.1 (2023/09/16) =
* Update: Many tiny fixes and improvements, as well as a cleanup of the UI.

= 0.1.0 (2023/09/11) =
* Add: Magic Fix is now working! 🎉

= 0.0.9 (2023/08/17) =
* Add: Support for AI for generating titles, descriptions, etc.
* Add: SEO Score.
* Add: Readability Treshold.

= 0.0.8 (2023/04/06) =
* Update: Improved UI.
* Fix: Handle better the tags, categories, blog page, etc.

= 0.0.5 (2023/03/28) =
* Update: Better UI.
* Update: Renamed in SEO Engine, as the goal of this plugin is to help you adapt to the AI era.

= 0.0.4 (2023/02/16) =
* Add: We can now ignore/skip certain posts.

= 0.0.2 (2022/11/13) =
* Fix: Links.
* Update: Compatibility.

= 0.0.1 =
* First release.