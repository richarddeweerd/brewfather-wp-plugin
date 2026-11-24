# Examples

Longer examples for Brew Log for Brewfather. The basics are in the [README](README.md).

## 1. A small brewery site

A typical setup has four pages. Each one only needs a shortcode block.

| Page | Visibility | Content |
|---|---|---|
| Our beers | Public | `[brewfather_recipes]` |
| On tap | Public | Conditioning and completed batches |
| Brew log | Public | Every batch except the archive |
| Brewery | Private | Stock and current batches, for brewers |

**On tap.** Put each heading in its own Heading block:

```
<h2>Almost ready</h2>
[brewfather_batches status="conditioning"]

<h2>On tap</h2>
[brewfather_batches status="completed"]
```

**Brew log.** All batches except the archived ones, newest first:

```
[brewfather_batches]
```

Put the archive on its own page with `[brewfather_batches status="archived"]`.

Clicking a batch opens it on the same page, and the back link returns to the list. On a page with several shortcodes, such as "On tap", the batch shows once, in place of the first list, and the other lists are hidden.

**Brewery.** Set the page to **Private** (or use a members plugin). Brewers see everything; anyone else sees nothing from `[brewfather_stock]`:

```
<h2>What's in the fermenter</h2>
[brewfather_batches status="fermenting"]

<h2>Stock</h2>
[brewfather_stock]
```

Recipes opened from a brewer's page also show an **In stock** column next to each ingredient, so you can see at a glance whether you can brew it again.

## 2. A members club

Say your homebrew club wants members to swap tasting notes, but keep recipes to themselves.

1. Under **Settings → Brew Log for Brewfather → Ingredients**, check **Hide from visitors**. Visitors still see the recipes with style, ABV, IBU and colour, but not what's in them.
2. Give every member a WordPress account with the **Subscriber** role. They now see the ingredients and can add **Batch notes**.
3. Give the people who run the brew days the **Brewer** role. They see the stock and can remove any note.

| Who | Recipes | Ingredients | Stock | Notes |
|---|---|---|---|---|
| Visitor | yes | no | no | read |
| Member (Subscriber) | yes | yes | no | read, add, edit own |
| Brewer | yes | yes | yes | read, add, edit own, delete any |
| Administrator | yes | yes | yes | read, add, edit own, delete any |

To keep ingredients for brewers only, check **Hide from logged-in users** as well.

## 3. Sharing one batch

After a brew day, link straight to the batch in a blog post or on social media:

```
https://example.com/brew-log/?batch=<batch-id>
```

Open the batch from a list on your site and copy the address to get the ID. The link works on any page with one of the shortcodes, so you can also use the "On tap" page:

```
https://example.com/on-tap/?batch=<batch-id>
```

The same goes for recipes with `?recipe=<recipe-id>`. A recipe page lists every batch brewed from it, which works well as a "this beer over the years" page.

## 4. Styling

All output sits inside `<div class="blfb">`, so you can style it from **Appearance → Customize → Additional CSS** without touching the rest of your theme.

| Class | What it is |
|---|---|
| `.blfb-table` | Every table, with `.blfb-recipes`, `.blfb-batches` or `.blfb-ingredients` |
| `.blfb-title` | The heading of a recipe or batch |
| `.blfb-details` | The list of style, ABV, dates and so on |
| `.blfb-back` | The back link |
| `.blfb-note`, `.blfb-note-meta` | One batch note and its author and date |
| `.blfb-error`, `.blfb-notice` | Messages, e.g. when Brewfather is unreachable |

A darker, compact look:

```css
.blfb .blfb-table {
	font-size: 0.9em;
}
.blfb .blfb-table th {
	background: #2b1d0e;
	color: #f5deb3;
}
.blfb .blfb-table tbody tr:nth-child(even) {
	background: rgba(0, 0, 0, 0.04);
}
.blfb .blfb-title {
	border-bottom: 3px solid #c98a2b;
	padding-bottom: 0.25em;
}
```

Hide the yeast laboratory column on narrow screens:

```css
@media (max-width: 600px) {
	.blfb .blfb-ingredients td:nth-child(3),
	.blfb .blfb-ingredients th:nth-child(3) {
		display: none;
	}
}
```

## 5. Extending with PHP

The plugin has three filters. Put the code in your theme's `functions.php` or in a small plugin of your own. Brewfather fields are documented in the [Brewfather API](https://docs.brewfather.app/api).

### Add a column to the ingredient tables

`blfb_ingredient_columns` gets the column headings after the name, and `blfb_ingredient_cells` gets the cell texts for one ingredient. The type is `fermentables`, `hops`, `miscs` or `yeasts`. Always add the same number of headings and cells.

Show the alpha acid of each hop:

```php
add_filter( 'blfb_ingredient_columns', function ( $columns, $type ) {
	if ( 'hops' === $type ) {
		$columns[] = __( 'Alpha', 'my-theme' );
	}
	return $columns;
}, 10, 2 );

add_filter( 'blfb_ingredient_cells', function ( $cells, $type, $item ) {
	if ( 'hops' === $type ) {
		$cells[] = isset( $item['alpha'] ) ? $item['alpha'] . '%' : '';
	}
	return $cells;
}, 10, 3 );
```

Cells are plain text; the plugin escapes them, so HTML shows up as text.

Show the colour of each fermentable:

```php
add_filter( 'blfb_ingredient_columns', function ( $columns, $type ) {
	if ( 'fermentables' === $type ) {
		$columns[] = 'EBC';
	}
	return $columns;
}, 10, 2 );

add_filter( 'blfb_ingredient_cells', function ( $cells, $type, $item ) {
	if ( 'fermentables' === $type ) {
		$cells[] = isset( $item['color'] ) ? round( $item['color'] * 1.97 ) : '';
	}
	return $cells;
}, 10, 3 );
```

Brewfather stores fermentable colour in Lovibond; 1.97 converts it to EBC.

Don't print `inventory` from `$item`. It is the stock at the time the recipe was saved, it is usually out of date, and visitors would see it.

### Add something below a batch

`blfb_batch_view_after` gets the HTML below a batch and the batch itself. Batch notes are added through the same filter, so append to `$html` instead of replacing it. Unlike the ingredient cells, this is HTML, so escape what you print.

A "days in the fermenter" line for fermenting batches:

```php
add_filter( 'blfb_batch_view_after', function ( $html, $batch ) {
	if ( 'Fermenting' !== ( $batch['status'] ?? '' ) || empty( $batch['brewDate'] ) ) {
		return $html;
	}

	// Brewfather dates are in milliseconds.
	$days = floor( ( time() - $batch['brewDate'] / 1000 ) / DAY_IN_SECONDS );

	$line = '<p class="my-days">' . esc_html(
		sprintf( _n( '%d day in the fermenter', '%d days in the fermenter', $days, 'my-theme' ), $days )
	) . '</p>';

	return $line . $html;
}, 5, 2 );
```

Priority 5 puts the line above the batch notes; use a higher number to put it below.

A "brewed by" credit for brewers only:

```php
add_filter( 'blfb_batch_view_after', function ( $html, $batch ) {
	if ( ! current_user_can( 'blfb_brewer' ) || empty( $batch['brewer'] ) ) {
		return $html;
	}
	return '<p>' . esc_html( 'Brewer: ' . $batch['brewer'] ) . '</p>' . $html;
}, 5, 2 );
```

`blfb_brewer` is the capability that brewers and administrators have. Check it instead of the role name.
