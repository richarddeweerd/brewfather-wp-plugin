<?php
/**
 * Shortcodes and the Recipe and Batch views.
 *
 * @package BrewLogForBrewfather
 */

namespace BrewLogForBrewfather;

defined( 'ABSPATH' ) || exit;

/**
 * Renders Brewfather data on the site.
 *
 * Every plugin shortcode shows its list, unless the page was opened with
 * ?recipe=<id> or ?batch=<id>: then it shows that Recipe or Batch instead.
 * Links between lists and views stay on the current page, so one page with
 * one shortcode is enough.
 *
 * Only fields picked out explicitly are printed. Brewfather data also holds
 * things some viewers must not see, such as Stock in a Recipe's Ingredients.
 */
final class Frontend {

	/**
	 * Whether a shortcode on this page already showed the requested view.
	 *
	 * @var bool
	 */
	private static $view_shown = false;


	/**
	 * Query argument that opens a Recipe view.
	 */
	const RECIPE_ARG = 'recipe';

	/**
	 * Query argument that opens a Batch view.
	 */
	const BATCH_ARG = 'batch';

	/**
	 * Registers the shortcodes.
	 */
	public static function init() {
		add_shortcode( 'brewfather_recipes', array( __CLASS__, 'recipes_shortcode' ) );
		add_shortcode( 'brewfather_batches', array( __CLASS__, 'batches_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_style' ) );
	}

	/**
	 * Registers the stylesheet; it is enqueued only on pages with a shortcode.
	 */
	public static function register_style() {
		wp_register_style( 'blfb-frontend', plugins_url( 'assets/frontend.css', BLFB_PLUGIN_FILE ), array(), BLFB_VERSION );
	}

	/**
	 * The [brewfather_recipes] shortcode.
	 *
	 * @return string
	 */
	public static function recipes_shortcode() {
		if ( self::is_view_request() ) {
			return self::view_once();
		}

		return self::wrap( self::recipe_list() );
	}

	/**
	 * The [brewfather_batches status="fermenting"] shortcode.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function batches_shortcode( $atts ) {
		$atts = shortcode_atts( array( 'status' => '' ), $atts, 'brewfather_batches' );

		if ( self::is_view_request() ) {
			return self::view_once();
		}

		return self::wrap( self::batch_list( $atts['status'] ) );
	}

	/**
	 * Whether the page has ?recipe=<id> or ?batch=<id>.
	 *
	 * @return bool
	 */
	public static function is_view_request() {
		return '' !== self::id_from_request( self::RECIPE_ARG ) || '' !== self::id_from_request( self::BATCH_ARG );
	}

	/**
	 * The requested Recipe or Batch view for the first shortcode on the page,
	 * and nothing for the others, so a page with several lists shows it once.
	 *
	 * @return string
	 */
	private static function view_once() {
		if ( self::$view_shown ) {
			return '';
		}
		self::$view_shown = true;

		return self::wrap( (string) self::view_from_request() );
	}

	/**
	 * Forgets that the view was shown. For tests.
	 */
	public static function reset() {
		self::$view_shown = false;
	}

	/**
	 * The Recipe or Batch view the request asks for, or null for the list.
	 *
	 * @return string|null
	 */
	public static function view_from_request() {
		$recipe_id = self::id_from_request( self::RECIPE_ARG );
		if ( '' !== $recipe_id ) {
			return self::recipe_view( $recipe_id );
		}

		$batch_id = self::id_from_request( self::BATCH_ARG );
		if ( '' !== $batch_id ) {
			return self::batch_view( $batch_id );
		}

		return null;
	}

	/**
	 * The Recipe list.
	 *
	 * @return string
	 */
	public static function recipe_list() {
		$recipes = self::catalog()->recipes();
		if ( is_wp_error( $recipes ) ) {
			return self::error( $recipes );
		}
		if ( ! $recipes ) {
			return self::notice( __( 'There are no recipes yet.', 'brew-log-for-brewfather' ) );
		}

		$rows = '';
		foreach ( $recipes as $recipe ) {
			$rows .= '<tr>'
				. '<td><a href="' . esc_url( self::url( self::RECIPE_ARG, $recipe['_id'] ) ) . '">' . esc_html( self::field( $recipe, 'name' ) ) . '</a></td>'
				. '<td>' . esc_html( self::field( $recipe, 'style.name' ) ) . '</td>'
				. '<td>' . esc_html( self::percent( self::value( $recipe, 'abv' ) ) ) . '</td>'
				. '<td>' . esc_html( self::number( self::value( $recipe, 'ibu' ), 0 ) ) . '</td>'
				. '<td>' . esc_html( self::number( self::value( $recipe, 'color' ), 0 ) ) . '</td>'
				. '</tr>';
		}

		return self::table(
			array(
				__( 'Recipe', 'brew-log-for-brewfather' ),
				__( 'Style', 'brew-log-for-brewfather' ),
				__( 'ABV', 'brew-log-for-brewfather' ),
				__( 'IBU', 'brew-log-for-brewfather' ),
				__( 'Colour (EBC)', 'brew-log-for-brewfather' ),
			),
			$rows,
			'blfb-recipes'
		);
	}

	/**
	 * The Batch list.
	 *
	 * @param string $status Status filter from the shortcode, any case.
	 * @return string
	 */
	public static function batch_list( $status = '' ) {
		$batches = self::catalog()->batches( $status );
		if ( is_wp_error( $batches ) ) {
			return self::error( $batches );
		}
		if ( ! $batches ) {
			return self::notice( __( 'There are no batches yet.', 'brew-log-for-brewfather' ) );
		}

		return self::batch_table( $batches );
	}

	/**
	 * The view of one Recipe, with its Ingredients (if the viewer may see
	 * them) and its Batches.
	 *
	 * @param string $id Recipe ID.
	 * @return string
	 */
	public static function recipe_view( $id ) {
		$recipe = self::catalog()->recipe( $id );
		if ( is_wp_error( $recipe ) ) {
			return self::back_link() . self::error( $recipe );
		}

		$html  = self::back_link();
		$html .= '<h2 class="blfb-title">' . esc_html( self::field( $recipe, 'name' ) ) . '</h2>';
		$html .= self::details(
			array(
				array( __( 'Style', 'brew-log-for-brewfather' ), self::field( $recipe, 'style.name' ) ),
				array( __( 'ABV', 'brew-log-for-brewfather' ), self::percent( self::value( $recipe, 'abv' ) ) ),
				array( __( 'IBU', 'brew-log-for-brewfather' ), self::number( self::value( $recipe, 'ibu' ), 0 ) ),
				array( __( 'Colour (EBC)', 'brew-log-for-brewfather' ), self::number( self::value( $recipe, 'color' ), 0 ) ),
				array( __( 'Batch size', 'brew-log-for-brewfather' ), self::unit( self::value( $recipe, 'batchSize' ), 'L', 1 ) ),
			)
		);

		if ( Access::can_see_ingredients( Access::current_level() ) ) {
			$html .= self::ingredients( $recipe );
		}

		$html   .= '<h3>' . esc_html__( 'Batches', 'brew-log-for-brewfather' ) . '</h3>';
		$batches = self::catalog()->batches_for_recipe( $id );
		if ( is_wp_error( $batches ) ) {
			$html .= self::error( $batches );
		} elseif ( ! $batches ) {
			$html .= self::notice( __( 'This recipe has not been brewed yet.', 'brew-log-for-brewfather' ) );
		} else {
			$html .= self::batch_table( $batches );
		}

		return $html;
	}

	/**
	 * The view of one Batch.
	 *
	 * @param string $id Batch ID.
	 * @return string
	 */
	public static function batch_view( $id ) {
		$batch = self::catalog()->batch( $id );
		if ( is_wp_error( $batch ) ) {
			return self::back_link() . self::error( $batch );
		}

		$recipe_id = self::field( $batch, 'recipe._id' );
		$recipe    = esc_html( self::field( $batch, 'recipe.name' ) );
		if ( '' !== $recipe_id ) {
			$recipe = '<a href="' . esc_url( self::url( self::RECIPE_ARG, $recipe_id ) ) . '">' . $recipe . '</a>';
		}

		$html  = self::back_link();
		$html .= '<h2 class="blfb-title">' . esc_html( self::batch_title( $batch ) ) . '</h2>';
		$html .= self::details(
			array(
				array( __( 'Recipe', 'brew-log-for-brewfather' ), array( 'html' => $recipe ) ),
				array( __( 'Status', 'brew-log-for-brewfather' ), self::status_label( self::field( $batch, 'status' ) ) ),
				array( __( 'Brew date', 'brew-log-for-brewfather' ), self::date( self::value( $batch, 'brewDate' ) ) ),
				array( __( 'Original gravity', 'brew-log-for-brewfather' ), self::number( self::value( $batch, 'measuredOg' ), 3 ) ),
				array( __( 'Final gravity', 'brew-log-for-brewfather' ), self::number( self::value( $batch, 'measuredFg' ), 3 ) ),
				array( __( 'ABV', 'brew-log-for-brewfather' ), self::percent( self::value( $batch, 'measuredAbv' ) ) ),
			)
		);

		/**
		 * Filters the HTML shown below a Batch view, e.g. to add Batch notes.
		 *
		 * @param string $html  HTML, empty by default.
		 * @param array  $batch The Batch as returned by Brewfather.
		 */
		$after = apply_filters( 'blfb_batch_view_after', '', $batch );

		return $html . $after;
	}

	/**
	 * The title of a Batch: its number and Recipe, e.g. "#22 · Doppelbock".
	 * Brewfather names most Batches just "Batch", so the name alone says little.
	 *
	 * @param array $batch Batch.
	 * @return string
	 */
	public static function batch_title( array $batch ) {
		$parts = array();
		if ( null !== self::value( $batch, 'batchNo' ) ) {
			$parts[] = '#' . self::value( $batch, 'batchNo' );
		}

		$recipe = self::field( $batch, 'recipe.name' );
		$name   = self::field( $batch, 'name' );
		if ( '' !== $recipe ) {
			$parts[] = $recipe;
		}
		if ( '' !== $name && 'Batch' !== $name && $name !== $recipe ) {
			$parts[] = $name;
		}

		return implode( ' · ', $parts );
	}

	/**
	 * The translated label for a Brewfather status.
	 *
	 * @param string $status Brewfather status.
	 * @return string
	 */
	public static function status_label( $status ) {
		$labels = array(
			'Planning'     => _x( 'Planning', 'batch status', 'brew-log-for-brewfather' ),
			'Brewing'      => _x( 'Brewing', 'batch status', 'brew-log-for-brewfather' ),
			'Fermenting'   => _x( 'Fermenting', 'batch status', 'brew-log-for-brewfather' ),
			'Conditioning' => _x( 'Conditioning', 'batch status', 'brew-log-for-brewfather' ),
			'Completed'    => _x( 'Completed', 'batch status', 'brew-log-for-brewfather' ),
			'Archived'     => _x( 'Archived', 'batch status', 'brew-log-for-brewfather' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : (string) $status;
	}

	/**
	 * The Ingredient tables of a Recipe.
	 *
	 * @param array $recipe Full Recipe.
	 * @return string
	 */
	private static function ingredients( array $recipe ) {
		$sections = array(
			'fermentables' => array(
				'title'   => __( 'Fermentables', 'brew-log-for-brewfather' ),
				'columns' => array( __( 'Amount', 'brew-log-for-brewfather' ), '%' ),
				'cells'   => function ( $item ) {
					return array( self::unit( self::value( $item, 'amount' ), 'kg', 2 ), self::percent( self::value( $item, 'percentage' ) ) );
				},
			),
			'hops'         => array(
				'title'   => __( 'Hops', 'brew-log-for-brewfather' ),
				'columns' => array( __( 'Amount', 'brew-log-for-brewfather' ), __( 'Use', 'brew-log-for-brewfather' ), __( 'Time', 'brew-log-for-brewfather' ) ),
				'cells'   => function ( $item ) {
					return array( self::unit( self::value( $item, 'amount' ), 'g', 0 ), self::field( $item, 'use' ), self::unit( self::value( $item, 'time' ), 'min', 0 ) );
				},
			),
			'miscs'        => array(
				'title'   => __( 'Other ingredients', 'brew-log-for-brewfather' ),
				'columns' => array( __( 'Amount', 'brew-log-for-brewfather' ), __( 'Use', 'brew-log-for-brewfather' ) ),
				'cells'   => function ( $item ) {
					return array( self::unit( self::value( $item, 'amount' ), self::field( $item, 'unit' ), 1 ), self::field( $item, 'use' ) );
				},
			),
			'yeasts'       => array(
				'title'   => __( 'Yeasts', 'brew-log-for-brewfather' ),
				'columns' => array( __( 'Amount', 'brew-log-for-brewfather' ), __( 'Laboratory', 'brew-log-for-brewfather' ) ),
				'cells'   => function ( $item ) {
					return array( self::unit( self::value( $item, 'amount' ), self::field( $item, 'unit' ), 1 ), trim( self::field( $item, 'laboratory' ) . ' ' . self::field( $item, 'productId' ) ) );
				},
			),
		);

		$html = '';
		foreach ( $sections as $key => $section ) {
			if ( empty( $recipe[ $key ] ) || ! is_array( $recipe[ $key ] ) ) {
				continue;
			}

			$rows = '';
			foreach ( $recipe[ $key ] as $item ) {
				$cells = call_user_func( $section['cells'], $item );
				$cells = self::filter_ingredient_cells( $cells, $key, $item );

				$rows .= '<tr><td>' . esc_html( self::field( $item, 'name' ) ) . '</td>';
				foreach ( $cells as $cell ) {
					$rows .= '<td>' . esc_html( $cell ) . '</td>';
				}
				$rows .= '</tr>';
			}

			$columns = self::filter_ingredient_columns( $section['columns'], $key );
			$html   .= '<h4>' . esc_html( $section['title'] ) . '</h4>'
				. self::table( array_merge( array( __( 'Name', 'brew-log-for-brewfather' ) ), $columns ), $rows, 'blfb-ingredients' );
		}

		if ( '' === $html ) {
			return '';
		}

		return '<h3>' . esc_html__( 'Ingredients', 'brew-log-for-brewfather' ) . '</h3>' . $html;
	}

	/**
	 * Lets other parts of the plugin add columns to the Ingredient tables.
	 *
	 * @param string[] $columns Column headings after the name.
	 * @param string   $type    Ingredient type, e.g. 'hops'.
	 * @return string[]
	 */
	private static function filter_ingredient_columns( array $columns, $type ) {
		return (array) apply_filters( 'blfb_ingredient_columns', $columns, $type );
	}

	/**
	 * Lets other parts of the plugin add cells to an Ingredient row.
	 *
	 * @param string[] $cells Cell texts after the name.
	 * @param string   $type  Ingredient type, e.g. 'hops'.
	 * @param array    $item  The Ingredient from the Recipe.
	 * @return string[]
	 */
	private static function filter_ingredient_cells( array $cells, $type, array $item ) {
		return (array) apply_filters( 'blfb_ingredient_cells', $cells, $type, $item );
	}

	/**
	 * A table of Batches.
	 *
	 * @param array $batches Batches.
	 * @return string
	 */
	private static function batch_table( array $batches ) {
		$rows = '';
		foreach ( $batches as $batch ) {
			$rows .= '<tr>'
				. '<td><a href="' . esc_url( self::url( self::BATCH_ARG, $batch['_id'] ) ) . '">' . esc_html( self::batch_title( $batch ) ) . '</a></td>'
				. '<td>' . esc_html( self::status_label( self::field( $batch, 'status' ) ) ) . '</td>'
				. '<td>' . esc_html( self::date( self::value( $batch, 'brewDate' ) ) ) . '</td>'
				. '<td>' . esc_html( self::percent( self::value( $batch, 'measuredAbv' ) ) ) . '</td>'
				. '</tr>';
		}

		return self::table(
			array(
				__( 'Batch', 'brew-log-for-brewfather' ),
				__( 'Status', 'brew-log-for-brewfather' ),
				__( 'Brew date', 'brew-log-for-brewfather' ),
				__( 'ABV', 'brew-log-for-brewfather' ),
			),
			$rows,
			'blfb-batches'
		);
	}

	/**
	 * A table with escaped headings and pre-escaped rows.
	 *
	 * @param string[] $headings Column headings.
	 * @param string   $rows     Escaped <tr> HTML.
	 * @param string   $css_class Extra class.
	 * @return string
	 */
	public static function table( array $headings, $rows, $css_class ) {
		$head = '';
		foreach ( $headings as $heading ) {
			$head .= '<th scope="col">' . esc_html( $heading ) . '</th>';
		}

		return '<table class="blfb-table ' . esc_attr( $css_class ) . '"><thead><tr>' . $head . '</tr></thead><tbody>' . $rows . '</tbody></table>';
	}

	/**
	 * A definition list. Empty values are left out. A value of
	 * array( 'html' => ... ) is printed as already-escaped HTML.
	 *
	 * @param array $items List of array( label, value ).
	 * @return string
	 */
	private static function details( array $items ) {
		$html = '';
		foreach ( $items as list( $label, $value ) ) {
			$value_html = is_array( $value ) ? $value['html'] : esc_html( $value );
			if ( '' === $value_html || '–' === $value_html ) {
				continue;
			}
			$html .= '<dt>' . esc_html( $label ) . '</dt><dd>' . $value_html . '</dd>';
		}

		return '<dl class="blfb-details">' . $html . '</dl>';
	}

	/**
	 * Wraps output and enqueues the stylesheet.
	 *
	 * @param string $html Inner HTML.
	 * @return string
	 */
	public static function wrap( $html ) {
		wp_enqueue_style( 'blfb-frontend' );

		return '<div class="blfb">' . $html . '</div>';
	}

	/**
	 * A link back to the list on the current page.
	 *
	 * @return string
	 */
	private static function back_link() {
		$url = remove_query_arg( array( self::RECIPE_ARG, self::BATCH_ARG ) );

		return '<p class="blfb-back"><a href="' . esc_url( $url ) . '">' . esc_html__( '← Back to the list', 'brew-log-for-brewfather' ) . '</a></p>';
	}

	/**
	 * The current page's URL opening one Recipe or Batch.
	 *
	 * @param string $arg RECIPE_ARG or BATCH_ARG.
	 * @param string $id  Item ID.
	 * @return string
	 */
	private static function url( $arg, $id ) {
		return add_query_arg( $arg, rawurlencode( $id ), remove_query_arg( array( self::RECIPE_ARG, self::BATCH_ARG ) ) );
	}

	/**
	 * A Brewfather ID from the query string, or ''.
	 *
	 * @param string $arg Query argument.
	 * @return string
	 */
	private static function id_from_request( $arg ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view selection.
		if ( ! isset( $_GET[ $arg ] ) || ! is_string( $_GET[ $arg ] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view selection.
		return self::sanitize_id( sanitize_text_field( wp_unslash( $_GET[ $arg ] ) ) );
	}

	/**
	 * Keeps only the characters Brewfather IDs use.
	 *
	 * @param string $id Raw ID.
	 * @return string
	 */
	public static function sanitize_id( $id ) {
		return substr( preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $id ), 0, 64 );
	}

	/**
	 * An error message.
	 *
	 * @param \WP_Error $error Error from the API client.
	 * @return string
	 */
	public static function error( $error ) {
		return '<p class="blfb-error">' . esc_html( $error->get_error_message() ) . '</p>';
	}

	/**
	 * An informational message.
	 *
	 * @param string $message Message.
	 * @return string
	 */
	public static function notice( $message ) {
		return '<p class="blfb-notice">' . esc_html( $message ) . '</p>';
	}

	/**
	 * A catalog using the saved settings.
	 *
	 * @return Catalog
	 */
	private static function catalog() {
		return new Catalog( Api_Client::from_settings() );
	}

	/**
	 * A nested value like 'style.name', or null.
	 *
	 * @param array  $item Data.
	 * @param string $path Dot-separated keys.
	 * @return mixed|null
	 */
	public static function value( array $item, $path ) {
		foreach ( explode( '.', $path ) as $key ) {
			if ( ! is_array( $item ) || ! isset( $item[ $key ] ) ) {
				return null;
			}
			$item = $item[ $key ];
		}

		return $item;
	}

	/**
	 * A nested value as a string, '' when missing.
	 *
	 * @param array  $item Data.
	 * @param string $path Dot-separated keys.
	 * @return string
	 */
	public static function field( array $item, $path ) {
		$value = self::value( $item, $path );

		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * A localized number, '–' when missing.
	 *
	 * @param mixed $value    Number.
	 * @param int   $decimals Decimals.
	 * @return string
	 */
	public static function number( $value, $decimals ) {
		return is_numeric( $value ) ? number_format_i18n( (float) $value, $decimals ) : '–';
	}

	/**
	 * A localized percentage with one decimal, '–' when missing.
	 *
	 * @param mixed $value Number.
	 * @return string
	 */
	public static function percent( $value ) {
		return is_numeric( $value ) ? number_format_i18n( (float) $value, 1 ) . '%' : '–';
	}

	/**
	 * A localized number with a unit, '–' when missing.
	 *
	 * @param mixed  $value    Number.
	 * @param string $unit     Unit, e.g. 'kg'.
	 * @param int    $decimals Decimals.
	 * @return string
	 */
	public static function unit( $value, $unit, $decimals ) {
		return is_numeric( $value ) ? trim( number_format_i18n( (float) $value, $decimals ) . ' ' . $unit ) : '–';
	}

	/**
	 * A date in the site's format from a Brewfather timestamp in milliseconds.
	 *
	 * @param mixed $milliseconds Timestamp.
	 * @return string
	 */
	public static function date( $milliseconds ) {
		return is_numeric( $milliseconds ) ? (string) wp_date( get_option( 'date_format' ), (int) ( $milliseconds / 1000 ) ) : '–';
	}
}
