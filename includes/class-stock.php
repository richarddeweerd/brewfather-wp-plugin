<?php
/**
 * Stock: the Ingredients on hand, for Brewers.
 *
 * @package BrewLogForBrewfather
 */

namespace BrewLogForBrewfather;

defined( 'ABSPATH' ) || exit;

/**
 * The [brewfather_stock] list and the Stock column on Recipe views.
 *
 * Only Brewers and Admins see Stock. It comes from Brewfather's inventory
 * lists, not from the copy embedded in Recipes, which is out of date.
 */
final class Stock {

	/**
	 * Stock per item ID for the current request, or null until loaded.
	 *
	 * @var array|null
	 */
	private static $by_id = null;

	/**
	 * Registers the shortcode and the Recipe view filters.
	 */
	public static function init() {
		add_shortcode( 'brewfather_stock', array( __CLASS__, 'shortcode' ) );
		add_filter( 'blfb_ingredient_columns', array( __CLASS__, 'add_column' ) );
		add_filter( 'blfb_ingredient_cells', array( __CLASS__, 'add_cell' ), 10, 3 );
	}

	/**
	 * Forgets the loaded Stock. For tests.
	 */
	public static function reset() {
		self::$by_id = null;
	}

	/**
	 * The [brewfather_stock] shortcode. Empty for anyone below Brewer.
	 *
	 * @return string
	 */
	public static function shortcode() {
		if ( ! Access::at_least( Access::BREWER ) ) {
			return '';
		}

		$titles = array(
			'fermentables' => __( 'Fermentables', 'brew-log-for-brewfather' ),
			'hops'         => __( 'Hops', 'brew-log-for-brewfather' ),
			'miscs'        => __( 'Other ingredients', 'brew-log-for-brewfather' ),
			'yeasts'       => __( 'Yeasts', 'brew-log-for-brewfather' ),
		);

		$client = Api_Client::from_settings();
		$html   = '';
		foreach ( $titles as $type => $title ) {
			$items = $client->get_inventory( $type, array( 'unit' ) );
			if ( is_wp_error( $items ) ) {
				return Frontend::wrap( Frontend::error( $items ) );
			}

			usort(
				$items,
				function ( $a, $b ) {
					return strcasecmp( Frontend::field( $a, 'name' ), Frontend::field( $b, 'name' ) );
				}
			);

			$rows = '';
			foreach ( $items as $item ) {
				if ( '' === Frontend::field( $item, 'name' ) ) {
					continue;
				}
				$amount = self::amount( $type, $item );
				$class  = is_numeric( Frontend::value( $item, 'inventory' ) ) && $item['inventory'] > 0 ? '' : ' class="blfb-empty"';
				$rows  .= '<tr' . $class . '>'
					. '<td>' . esc_html( Frontend::field( $item, 'name' ) ) . '</td>'
					. '<td>' . esc_html( trim( Frontend::field( $item, 'type' ) . ' ' . self::supplier( $item ) ) ) . '</td>'
					. '<td>' . esc_html( $amount ) . '</td>'
					. '</tr>';
			}
			if ( '' === $rows ) {
				continue;
			}

			$html .= '<h3>' . esc_html( $title ) . '</h3>'
				. Frontend::table(
					array(
						__( 'Name', 'brew-log-for-brewfather' ),
						__( 'Type', 'brew-log-for-brewfather' ),
						__( 'In stock', 'brew-log-for-brewfather' ),
					),
					$rows,
					'blfb-stock'
				);
		}

		if ( '' === $html ) {
			$html = Frontend::notice( __( 'There is nothing in stock yet.', 'brew-log-for-brewfather' ) );
		}

		return Frontend::wrap( $html );
	}

	/**
	 * Adds a Stock column to the Ingredient tables for Brewers.
	 *
	 * @param string[] $columns Column headings.
	 * @return string[]
	 */
	public static function add_column( $columns ) {
		if ( Access::at_least( Access::BREWER ) ) {
			$columns[] = __( 'In stock', 'brew-log-for-brewfather' );
		}

		return $columns;
	}

	/**
	 * Adds the Stock of one Ingredient to its row for Brewers.
	 *
	 * @param string[] $cells Cell texts.
	 * @param string   $type  Ingredient type.
	 * @param array    $item  Ingredient from the Recipe.
	 * @return string[]
	 */
	public static function add_cell( $cells, $type, $item ) {
		if ( ! Access::at_least( Access::BREWER ) ) {
			return $cells;
		}

		$stock = self::by_id();
		$id    = Frontend::field( $item, '_id' );
		if ( isset( $stock[ $type ][ $id ] ) ) {
			$cells[] = self::amount( $type, $stock[ $type ][ $id ] );
		} else {
			$cells[] = '–';
		}

		return $cells;
	}

	/**
	 * All inventory items by type and ID, loaded once per request.
	 * Types that fail to load are left empty.
	 *
	 * @return array Type => ID => item.
	 */
	private static function by_id() {
		if ( null !== self::$by_id ) {
			return self::$by_id;
		}

		$client      = Api_Client::from_settings();
		self::$by_id = array();
		foreach ( Api_Client::INVENTORY_TYPES as $type ) {
			self::$by_id[ $type ] = array();
			$items                = $client->get_inventory( $type, array( 'unit' ) );
			if ( is_wp_error( $items ) ) {
				continue;
			}
			foreach ( $items as $item ) {
				if ( isset( $item['_id'] ) ) {
					self::$by_id[ $type ][ $item['_id'] ] = $item;
				}
			}
		}

		return self::$by_id;
	}

	/**
	 * The amount on hand with its unit.
	 *
	 * @param string $type Inventory type.
	 * @param array  $item Inventory item.
	 * @return string
	 */
	private static function amount( $type, array $item ) {
		$units = array(
			'fermentables' => array( 'kg', 2 ),
			'hops'         => array( 'g', 0 ),
		);

		list( $unit, $decimals ) = isset( $units[ $type ] ) ? $units[ $type ] : array( Frontend::field( $item, 'unit' ), 1 );

		return Frontend::unit( Frontend::value( $item, 'inventory' ), $unit, $decimals );
	}

	/**
	 * The supplier or laboratory, in parentheses, or ''.
	 *
	 * @param array $item Inventory item.
	 * @return string
	 */
	private static function supplier( array $item ) {
		$supplier = Frontend::field( $item, 'supplier' );
		if ( '' === $supplier ) {
			$supplier = Frontend::field( $item, 'laboratory' );
		}

		return '' === $supplier ? '' : '(' . $supplier . ')';
	}
}
