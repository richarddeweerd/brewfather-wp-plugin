<?php

use BrewLogForBrewfather\Access;
use BrewLogForBrewfather\Frontend;
use BrewLogForBrewfather\Options;
use BrewLogForBrewfather\Stock;
use PHPUnit\Framework\TestCase;

class StockTest extends TestCase {

	protected function setUp(): void {
		reset_wp();
		Stock::reset();
		Access::install();
		update_option( Options::USER_ID, 'user' );
		update_option( Options::API_KEY, 'key' );
		add_filter( 'blfb_ingredient_columns', array( Stock::class, 'add_column' ) );
		add_filter( 'blfb_ingredient_cells', array( Stock::class, 'add_cell' ) );
	}

	private function log_in( $role ) {
		$GLOBALS['blfb_test']['current'] = make_user( $role );
	}

	/**
	 * Queues the four inventory lists, in the order the plugin fetches them.
	 */
	private function queue_inventory() {
		queue_response(
			200,
			array(
				array( '_id' => 'f1', 'name' => 'Swaen Vienna', 'type' => 'Grain', 'supplier' => 'The Swaen', 'inventory' => 6.68 ),
				array( '_id' => 'f2', 'name' => 'BEST Munich', 'type' => 'Grain', 'supplier' => 'BESTMALZ', 'inventory' => 0 ),
			)
		);
		queue_response( 200, array( array( '_id' => 'h1', 'name' => 'Hallertauer', 'type' => 'Pellet', 'inventory' => 300 ) ) );
		queue_response( 200, array() );
		queue_response( 200, array( array( '_id' => 'y1', 'name' => 'German Bock Lager', 'laboratory' => 'White Labs', 'inventory' => 2, 'unit' => 'pkg' ) ) );
	}

	private function recipe() {
		return array(
			'_id'          => 'r1',
			'name'         => 'Doppelbock',
			'fermentables' => array( array( '_id' => 'f1', 'name' => 'Swaen Vienna', 'amount' => 2.81, 'inventory' => 999 ) ),
			'hops'         => array( array( '_id' => 'unknown', 'name' => 'Mystery hop', 'amount' => 10 ) ),
		);
	}

	public function test_stock_list_is_empty_for_visitors_and_users() {
		$this->assertSame( '', Stock::shortcode() );

		$this->log_in( 'subscriber' );
		$this->assertSame( '', Stock::shortcode() );
		$this->assertCount( 0, $GLOBALS['blfb_test']['requests'] );
	}

	public function test_brewer_sees_the_stock_list() {
		$this->log_in( Access::ROLE );
		$this->queue_inventory();

		$html = Stock::shortcode();

		$this->assertStringContainsString( 'Swaen Vienna', $html );
		$this->assertStringContainsString( '6.68 kg', $html );
		$this->assertStringContainsString( '300 g', $html );
		$this->assertStringContainsString( '2.0 pkg', $html );
		$this->assertStringContainsString( 'Grain (The Swaen)', $html );
		$this->assertLessThan( strpos( $html, 'Swaen Vienna' ), strpos( $html, 'BEST Munich' ), 'Sorted by name' );
		$this->assertStringContainsString( 'class="blfb-empty"', $html, 'Items without stock are marked' );
		$this->assertStringContainsString( 'include=unit', $GLOBALS['blfb_test']['requests'][0][0] );
	}

	public function test_admin_sees_the_stock_list() {
		$this->log_in( 'administrator' );
		$this->queue_inventory();

		$this->assertStringContainsString( 'Swaen Vienna', Stock::shortcode() );
	}

	public function test_brewer_sees_live_stock_next_to_each_ingredient() {
		$this->log_in( Access::ROLE );
		$_GET['recipe'] = 'r1';
		queue_response( 200, $this->recipe() );
		$this->queue_inventory();
		queue_response( 200, array() );

		$html = Frontend::recipes_shortcode();

		$this->assertStringContainsString( '<th scope="col">In stock</th>', $html );
		$this->assertStringContainsString( '<td>6.68 kg</td>', $html, 'Live Stock, not the copy in the Recipe' );
		$this->assertStringNotContainsString( '999', $html );
		$this->assertStringContainsString( '<td>Mystery hop</td><td>10 g</td><td></td><td>–</td><td>–</td>', $html );
	}

	public function test_users_do_not_see_the_stock_column() {
		$this->log_in( 'subscriber' );
		$_GET['recipe'] = 'r1';
		queue_response( 200, $this->recipe() );
		queue_response( 200, array() );

		$html = Frontend::recipes_shortcode();

		$this->assertStringNotContainsString( 'In stock', $html );
		$this->assertStringNotContainsString( '6.68', $html );
	}
}
