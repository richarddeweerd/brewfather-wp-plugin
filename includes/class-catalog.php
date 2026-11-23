<?php
/**
 * Recipes and Batches as the site shows them.
 *
 * @package BrewLogForBrewfather
 */

namespace BrewLogForBrewfather;

defined( 'ABSPATH' ) || exit;

/**
 * Wraps the API client with the filtering and sorting the lists need.
 *
 * Brewfather's own ordering is by ID, which is not chronological, so lists
 * are sorted here. Everything comes from cached API calls.
 */
class Catalog {

	/**
	 * Batch statuses Brewfather knows, in workflow order.
	 */
	const STATUSES = array( 'Planning', 'Brewing', 'Fermenting', 'Conditioning', 'Completed', 'Archived' );

	/**
	 * Extra fields fetched for the Recipe list.
	 */
	const RECIPE_LIST_FIELDS = array( 'abv', 'ibu', 'color', 'batchSize', '_created' );

	/**
	 * Extra fields fetched for Batch lists.
	 */
	const BATCH_LIST_FIELDS = array( 'recipe._id', 'measuredAbv', '_created' );

	/**
	 * API client.
	 *
	 * @var Api_Client
	 */
	private $client;

	/**
	 * Constructor.
	 *
	 * @param Api_Client $client API client.
	 */
	public function __construct( Api_Client $client ) {
		$this->client = $client;
	}

	/**
	 * All Recipes, newest first.
	 *
	 * @return array|\WP_Error
	 */
	public function recipes() {
		$recipes = $this->client->get_recipes( self::RECIPE_LIST_FIELDS );
		if ( is_wp_error( $recipes ) ) {
			return $recipes;
		}

		usort(
			$recipes,
			function ( $a, $b ) {
				return self::created( $b ) <=> self::created( $a );
			}
		);

		return $recipes;
	}

	/**
	 * Batches, newest brew date first. Without a status, Archived Batches are left out.
	 *
	 * @param string $status Brewfather status, any case. Empty for all non-archived.
	 * @return array|\WP_Error
	 */
	public function batches( $status = '' ) {
		$status = self::normalize_status( $status );

		$batches = $this->client->get_batches( self::BATCH_LIST_FIELDS, (string) $status );
		if ( is_wp_error( $batches ) ) {
			return $batches;
		}

		if ( null === $status ) {
			$batches = array_values(
				array_filter(
					$batches,
					function ( $batch ) {
						return ! isset( $batch['status'] ) || 'Archived' !== $batch['status'];
					}
				)
			);
		}

		return self::sort_batches( $batches );
	}

	/**
	 * All Batches brewed from one Recipe, archived ones included, newest first.
	 *
	 * Uses the same cached list call for every Recipe instead of one call each.
	 *
	 * @param string $recipe_id Recipe ID.
	 * @return array|\WP_Error
	 */
	public function batches_for_recipe( $recipe_id ) {
		$batches = $this->client->get_batches( self::BATCH_LIST_FIELDS );
		if ( is_wp_error( $batches ) ) {
			return $batches;
		}

		return self::sort_batches(
			array_values(
				array_filter(
					$batches,
					function ( $batch ) use ( $recipe_id ) {
						return isset( $batch['recipe']['_id'] ) && $batch['recipe']['_id'] === $recipe_id;
					}
				)
			)
		);
	}

	/**
	 * One Recipe with all its fields.
	 *
	 * @param string $id Recipe ID.
	 * @return array|\WP_Error
	 */
	public function recipe( $id ) {
		return $this->client->get_recipe( $id );
	}

	/**
	 * One Batch with all its fields.
	 *
	 * @param string $id Batch ID.
	 * @return array|\WP_Error
	 */
	public function batch( $id ) {
		return $this->client->get_batch( $id );
	}

	/**
	 * Maps a status in any case to Brewfather's spelling.
	 *
	 * @param string $status E.g. 'fermenting'.
	 * @return string|null Null for empty or unknown values.
	 */
	public static function normalize_status( $status ) {
		foreach ( self::STATUSES as $known ) {
			if ( 0 === strcasecmp( $known, trim( (string) $status ) ) ) {
				return $known;
			}
		}

		return null;
	}

	/**
	 * Sorts Batches by brew date, then Batch number, newest first.
	 *
	 * @param array $batches Batches.
	 * @return array
	 */
	private static function sort_batches( array $batches ) {
		usort(
			$batches,
			function ( $a, $b ) {
				$by_date = ( isset( $b['brewDate'] ) ? (int) $b['brewDate'] : 0 ) <=> ( isset( $a['brewDate'] ) ? (int) $a['brewDate'] : 0 );
				if ( 0 !== $by_date ) {
					return $by_date;
				}

				return ( isset( $b['batchNo'] ) ? (int) $b['batchNo'] : 0 ) <=> ( isset( $a['batchNo'] ) ? (int) $a['batchNo'] : 0 );
			}
		);

		return $batches;
	}

	/**
	 * Creation time of an item in seconds, 0 if unknown.
	 *
	 * @param array $item Recipe or Batch.
	 * @return int
	 */
	private static function created( array $item ) {
		return isset( $item['_created']['_seconds'] ) ? (int) $item['_created']['_seconds'] : 0;
	}
}
