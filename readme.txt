=== Brew Log for Brewfather ===
Contributors: richarddeweerd
Tags: brewfather, homebrew, beer, recipes, brewing
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Show your Brewfather recipes, batches and stock on your WordPress site.

== Description ==

Brew Log for Brewfather connects to your Brewfather account and shows your recipes and batches on your site. Brewers on your site can also see your ingredient stock, and logged-in users can add notes to batches.

This plugin is not affiliated with or endorsed by Brewfather.

= External service =

This plugin reads data from the Brewfather API (https://api.brewfather.app) using the API credentials you enter in the settings. Only read requests are made. See Brewfather's terms (https://brewfather.app/terms) and privacy policy (https://brewfather.app/privacy).

== Installation ==

1. Install and activate the plugin.
2. Go to Settings → Brew Log for Brewfather, enter your Brewfather user ID and API key, and click Test connection.
3. Add `[brewfather_recipes]` or `[brewfather_batches]` to a page. Brewers can be shown `[brewfather_stock]`.
4. Give brewing members the Brewer role on the Users screen.

== Frequently Asked Questions ==

= Which shortcodes are there? =

* `[brewfather_recipes]` lists all recipes, newest first.
* `[brewfather_batches]` lists all batches except archived ones, newest brew date first.
* `[brewfather_batches status="fermenting"]` lists only batches with that status: planning, brewing, fermenting, conditioning, completed or archived.
* `[brewfather_stock]` lists your ingredient stock. Only brewers and administrators see it.

A page with any of these shortcodes also shows a single recipe or batch when opened with `?recipe=<id>` or `?batch=<id>`. The lists link there automatically.

= Who sees what? =

Visitors and logged-in users see recipes and batches. You can hide the ingredients from visitors, or from everyone who is not a brewer, in the settings. Users with the Brewer role always see the ingredients and can see the stock. Logged-in users can read and add batch notes.

= Why don't my changes in Brewfather show up? =

Brewfather data is cached for the cache timeout (60 minutes by default). Click "Clear cache now" in the settings to fetch it again right away.

== Changelog ==

= 0.1.4 =
* Translations now come from translate.wordpress.org instead of being bundled.

= 0.1.3 =
* A page with several shortcodes now shows an opened recipe or batch once instead of once per shortcode.

= 0.1.2 =
* Tested with WordPress 7.1.
* Readme updated for the WordPress.org plugin directory.

= 0.1.1 =
* The plugin author is now shown as R. de Weerd.
* Usage examples in the FAQ.

= 0.1.0 =
* Shortcodes [brewfather_recipes], [brewfather_batches] and [brewfather_stock].
* Recipe view with details, Ingredients and all its Batches; Batch view with measured values.
* Settings for the Brewfather API credentials (with connection test), cache timeout and hiding Ingredients from visitors or users.
* Brewer role: Brewers always see Ingredients and can see Stock.
* Batch notes for logged-in users.
* English and Dutch.
