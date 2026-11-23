=== Brew Log for Brewfather ===
Contributors: mythotec
Tags: brewfather, homebrew, beer, recipes, brewing
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.0
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

== Changelog ==

= 0.1.0 =
* Shortcodes [brewfather_recipes], [brewfather_batches] and [brewfather_stock].
* Recipe view with details, Ingredients and all its Batches; Batch view with measured values.
* Settings for the Brewfather API credentials (with connection test), cache timeout and hiding Ingredients from visitors or users.
* Brewer role: Brewers always see Ingredients and can see Stock.
* Batch notes for logged-in users.
* English and Dutch.
