# Brew Log for Brewfather

A WordPress plugin that shows your [Brewfather](https://brewfather.app) recipes, batches and stock on your website. It is written by R. de Weerd and is not affiliated with Brewfather.

- Lists of recipes and batches, and a page for each recipe and batch
- Ingredients that you can hide from visitors or from logged-in users
- Stock that only brewers can see
- Notes on batches, added by logged-in users
- English and Dutch

Requires WordPress 6.4+ and PHP 7.4+.

## How to

### 1. Install

Download `brew-log-for-brewfather-<version>.zip` from the [releases](https://github.com/richarddeweerd/brewfather-wp-plugin/releases). In WordPress, upload it under **Plugins → Add New → Upload Plugin** and activate it.

### 2. Connect Brewfather

1. In Brewfather, go to **Settings → API keys** and create a key with the scopes `recipes.read`, `batches.read` and `inventory.read`. Note your user ID and the key.
2. In WordPress, go to **Settings → Brew Log for Brewfather**, fill in the user ID and API key, and click **Save Changes**.
3. Click **Test connection**. You should see "Connected to Brewfather."

### 3. Add a page

Create a page (for example "Our beers") and add a shortcode block with:

```
[brewfather_recipes]
```

Publish the page. Visitors see the list and can click through to each recipe and its batches.

### 4. Set up brewers

On **Users**, give the people who brew with you the **Brewer** role. Brewers always see the ingredients and can see the stock. Administrators can do everything brewers can.

### 5. Choose who sees ingredients

Under **Settings → Brew Log for Brewfather → Ingredients**, you can hide the ingredients of your recipes:

| Setting | Visitors | Logged-in users | Brewers and admins |
|---|---|---|---|
| Nothing checked | see them | see them | see them |
| Hide from visitors | hidden | see them | see them |
| Hide from logged-in users | hidden | hidden | see them |

## Examples

**All recipes, newest first:**

```
[brewfather_recipes]
```

**All batches, newest brew date first.** Archived batches are left out:

```
[brewfather_batches]
```

**What's in the fermenter right now:**

```
[brewfather_batches status="fermenting"]
```

The status can be `planning`, `brewing`, `fermenting`, `conditioning`, `completed` or `archived`.

**A "ready to drink" page:**

```
<h2>Conditioning</h2>
[brewfather_batches status="conditioning"]

<h2>Ready</h2>
[brewfather_batches status="completed"]
```

**The archive of old batches:**

```
[brewfather_batches status="archived"]
```

**A stock page for brewers.** Put it on a private or members-only page. Anyone who is not a brewer sees nothing:

```
[brewfather_stock]
```

**Linking straight to one recipe or batch.** Any page with one of the shortcodes shows a single recipe or batch when you add its Brewfather ID to the address. To find the ID, open the recipe or batch from a list on your site and copy the address:

```
https://example.com/our-beers/?recipe=<recipe-id>
https://example.com/our-beers/?batch=<batch-id>
```

The recipe page shows the style, ABV, IBU, colour, batch size, ingredients and every batch brewed from it. The batch page shows its recipe, status, brew date, measured OG, FG and ABV, and the batch notes.

More examples, including a full site setup, styling and PHP filters, are in [EXAMPLES.md](EXAMPLES.md).

## Batch notes

Logged-in users can add notes at the bottom of a batch page, for example tasting notes or what happened on brew day. They can edit and delete their own notes. Brewers and administrators can delete any note. Visitors see a link to log in instead.

Notes are stored in WordPress, not in Brewfather.

## Caching

Brewfather allows a few hundred API calls per hour, so the plugin stores what it fetches. Changes you make in Brewfather show up on the site after the **cache timeout** (60 minutes by default). To see them right away, click **Clear cache now** on the settings page.

If Brewfather is busy or unreachable, the plugin keeps showing the last data it fetched (up to a week old).

## Development

The checks run in GitLab CI. With PHP and Composer installed:

```
composer install
composer test      # PHPUnit
composer phpcs     # WordPress coding standards
```

After changing any text, run `bin/update-translations.sh` and fill in the Dutch translations in `languages/brew-log-for-brewfather-nl_NL.po`.

## License

GPL-2.0-or-later
