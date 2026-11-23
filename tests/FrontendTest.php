<?php

use BrewLogForBrewfather\Access;
use BrewLogForBrewfather\Catalog;
use BrewLogForBrewfather\Frontend;
use BrewLogForBrewfather\Options;
use PHPUnit\Framework\TestCase;

class FrontendTest extends TestCase {

	protected function setUp(): void {
		reset_wp();
		Access::install();
		update_option( Options::USER_ID, 'user' );
		update_option( Options::API_KEY, 'key' );
		update_option( 'date_format', 'Y-m-d' );
	}

	private function batch( $id, $no, $status, $brew_date, $recipe_id = 'r1', $recipe_name = 'Doppelbock' ) {
		return array(
			'_id'         => $id,
			'name'        => 'Batch',
			'batchNo'     => $no,
			'status'      => $status,
			'brewDate'    => $brew_date,
			'measuredAbv' => 6.6,
			'recipe'      => array( '_id' => $recipe_id, 'name' => $recipe_name ),
		);
	}

	private function recipe() {
		return array(
			'_id'          => 'r1',
			'name'         => 'Doppelbock',
			'style'        => array( 'name' => 'Doppelbock' ),
			'abv'          => 7.74,
			'ibu'          => 18.7,
			'color'        => 23.6,
			'batchSize'    => 26.04,
			'fermentables' => array( array( 'name' => 'BEST Munich', 'amount' => 2.81, 'percentage' => 32.15, 'inventory' => 150 ) ),
			'hops'         => array( array( 'name' => 'Hallertauer', 'amount' => 40, 'use' => 'Boil', 'time' => 60, 'inventory' => 300 ) ),
			'yeasts'       => array( array( 'name' => 'German Bock Lager', 'amount' => 1, 'unit' => 'pkg', 'laboratory' => 'White Labs', 'productId' => 'WLP833' ) ),
		);
	}

	private function log_in( $role ) {
		$GLOBALS['blfb_test']['current'] = make_user( $role );
	}

	public function test_recipe_list_is_newest_first_and_links_to_the_recipe_view() {
		queue_response(
			200,
			array(
				array( '_id' => 'old', 'name' => 'Old', '_created' => array( '_seconds' => 100 ) ),
				array( '_id' => 'new', 'name' => 'New', '_created' => array( '_seconds' => 200 ), 'abv' => 5.51 ),
			)
		);

		$html = Frontend::recipes_shortcode();

		$this->assertLessThan( strpos( $html, 'Old' ), strpos( $html, 'New' ) );
		$this->assertStringContainsString( 'href="https://example.test/brews/?recipe=new"', $html );
		$this->assertStringContainsString( '5.5%', $html );
	}

	public function test_batch_list_leaves_out_archived_and_sorts_by_brew_date() {
		queue_response(
			200,
			array(
				$this->batch( 'a', 14, 'Completed', 1000 ),
				$this->batch( 'b', 24, 'Fermenting', 3000 ),
				$this->batch( 'c', 2, 'Archived', 2000 ),
			)
		);

		$html = Frontend::batches_shortcode( array() );

		$this->assertStringNotContainsString( '#2 ', $html );
		$this->assertLessThan( strpos( $html, '#14' ), strpos( $html, '#24' ) );
		$this->assertStringContainsString( '?batch=b', $html );
	}

	public function test_batch_list_status_attribute_is_case_insensitive() {
		queue_response( 200, array( $this->batch( 'b', 24, 'Fermenting', 3000 ) ) );

		Frontend::batches_shortcode( array( 'status' => 'fermenting' ) );

		$this->assertStringContainsString( 'status=Fermenting', $GLOBALS['blfb_test']['requests'][0][0] );
	}

	public function test_archived_batches_can_be_listed_explicitly() {
		queue_response( 200, array( $this->batch( 'c', 2, 'Archived', 2000 ) ) );

		$html = Frontend::batches_shortcode( array( 'status' => 'Archived' ) );

		$this->assertStringContainsString( '#2', $html );
	}

	public function test_unknown_status_lists_all_non_archived() {
		$this->assertNull( Catalog::normalize_status( 'bubbling' ) );
		$this->assertSame( 'Conditioning', Catalog::normalize_status( ' CONDITIONING ' ) );
	}

	public function test_recipe_view_shows_details_ingredients_and_its_batches() {
		$_GET['recipe'] = 'r1';
		queue_response( 200, $this->recipe() );
		queue_response(
			200,
			array(
				$this->batch( 'a', 14, 'Completed', 1000 ),
				$this->batch( 'x', 9, 'Completed', 1500, 'other', 'Other' ),
				$this->batch( 'c', 2, 'Archived', 2000 ),
			)
		);

		$html = Frontend::batches_shortcode( array() );

		$this->assertStringContainsString( '<h2 class="blfb-title">Doppelbock</h2>', $html );
		$this->assertStringContainsString( '7.7%', $html );
		$this->assertStringContainsString( '26.0 L', $html );
		$this->assertStringContainsString( 'BEST Munich', $html );
		$this->assertStringContainsString( '2.81 kg', $html );
		$this->assertStringContainsString( 'White Labs WLP833', $html );
		$this->assertStringContainsString( '#14', $html );
		$this->assertStringContainsString( '#2', $html, 'Archived Batches of the Recipe are shown' );
		$this->assertStringNotContainsString( '#9', $html, 'Batches of other Recipes are not shown' );
		$this->assertStringContainsString( 'Back to the list', $html );
	}

	public function test_recipe_view_never_prints_stock() {
		$_GET['recipe'] = 'r1';
		queue_response( 200, $this->recipe() );
		queue_response( 200, array() );

		$html = Frontend::recipes_shortcode();

		$this->assertStringNotContainsString( '150', $html );
		$this->assertStringNotContainsString( '300', $html );
	}

	public function test_recipe_view_hides_ingredients_from_visitors_when_set() {
		update_option( Options::HIDE_INGREDIENTS_VISITORS, true );
		$_GET['recipe'] = 'r1';
		queue_response( 200, $this->recipe() );
		queue_response( 200, array() );

		$html = Frontend::recipes_shortcode();

		$this->assertStringNotContainsString( 'BEST Munich', $html );
		$this->assertStringContainsString( 'Doppelbock', $html );
	}

	public function test_brewer_sees_ingredients_even_when_hidden_from_users() {
		update_option( Options::HIDE_INGREDIENTS_USERS, true );
		$this->log_in( Access::ROLE );
		$_GET['recipe'] = 'r1';
		queue_response( 200, $this->recipe() );
		queue_response( 200, array() );

		$this->assertStringContainsString( 'BEST Munich', Frontend::recipes_shortcode() );
	}

	public function test_batch_view() {
		$_GET['batch'] = 'b';
		queue_response( 200, $this->batch( 'b', 22, 'Conditioning', 1792620000000 ) + array( 'measuredOg' => 1.06, 'measuredFg' => 1.034 ) );

		$html = Frontend::recipes_shortcode();

		$this->assertStringContainsString( '#22 · Doppelbock', $html );
		$this->assertStringContainsString( 'Conditioning', $html );
		$this->assertStringContainsString( '1.060', $html );
		$this->assertStringContainsString( '1.034', $html );
		$this->assertStringContainsString( '2026-10-21', $html );
		$this->assertStringContainsString( '?recipe=r1', $html );
	}

	public function test_batch_view_has_a_hook_for_batch_notes() {
		$_GET['batch'] = 'b';
		queue_response( 200, $this->batch( 'b', 22, 'Conditioning', 1000 ) );
		add_filter(
			'blfb_batch_view_after',
			function ( $html, $batch ) {
				return $html . '<p>notes for ' . $batch['_id'] . '</p>';
			}
		);

		$this->assertStringContainsString( '<p>notes for b</p>', Frontend::batches_shortcode( array() ) );
	}

	public function test_unknown_item_shows_an_error_and_a_back_link() {
		$_GET['batch'] = 'missing';
		queue_response( 404 );

		$html = Frontend::batches_shortcode( array() );

		$this->assertStringContainsString( 'blfb-error', $html );
		$this->assertStringContainsString( 'Back to the list', $html );
	}

	public function test_ids_from_the_url_are_sanitized() {
		$this->assertSame( 'abc_D-1script', Frontend::sanitize_id( 'abc_D-1"><script>' ) );
	}

	public function test_batch_title_skips_the_generic_name() {
		$this->assertSame( '#22 · Doppelbock', Frontend::batch_title( $this->batch( 'b', 22, 'Planning', 0 ) ) );
		$this->assertSame( '#3 · Doppelbock · Christmas', Frontend::batch_title( array( 'batchNo' => 3, 'name' => 'Christmas', 'recipe' => array( 'name' => 'Doppelbock' ) ) ) );
	}

	public function test_without_credentials_shows_the_not_configured_message() {
		delete_option( Options::API_KEY );

		$this->assertStringContainsString( 'not set up yet', Frontend::recipes_shortcode() );
	}
}
