<?php

use BrewLogForBrewfather\Access;
use BrewLogForBrewfather\Batch_Notes;
use BrewLogForBrewfather\Frontend;
use BrewLogForBrewfather\Options;
use PHPUnit\Framework\TestCase;

class BatchNotesTest extends TestCase {

	private $author;
	private $other;
	private $brewer;

	protected function setUp(): void {
		reset_wp();
		Access::install();
		update_option( Options::USER_ID, 'user' );
		update_option( Options::API_KEY, 'key' );
		update_option( 'date_format', 'Y-m-d' );
		update_option( 'time_format', 'H:i' );
		add_filter( 'blfb_batch_view_after', array( Batch_Notes::class, 'render' ) );

		$this->author = make_user( 'subscriber' );
		$this->other  = make_user( 'subscriber' );
		$this->brewer = make_user( Access::ROLE );
	}

	private function batch( $id = 'b1' ) {
		return array( '_id' => $id, 'batchNo' => 22, 'name' => 'Batch', 'status' => 'Fermenting', 'recipe' => array( 'name' => 'Doppelbock' ) );
	}

	private function add_note( $content = 'Dry hopped today.', $user = null ) {
		queue_response( 200, $this->batch() );
		return Batch_Notes::add( 'b1', $user ? $user : $this->author, $content );
	}

	public function test_user_adds_a_note_to_an_existing_batch() {
		$id = $this->add_note();

		$this->assertIsInt( $id );
		$notes = Batch_Notes::notes_for( 'b1' );
		$this->assertCount( 1, $notes );
		$this->assertSame( 'Dry hopped today.', $notes[0]->post_content );
		$this->assertSame( array(), Batch_Notes::notes_for( 'other' ) );
	}

	public function test_note_for_unknown_batch_is_rejected() {
		queue_response( 404 );

		$result = Batch_Notes::add( 'nope', $this->author, 'Hello' );

		$this->assertSame( 'unknown_batch', $result->get_error_code() );
		$this->assertSame( array(), $GLOBALS['blfb_test']['posts'] );
	}

	public function test_visitors_cannot_add_notes() {
		$this->assertSame( 'forbidden', Batch_Notes::add( 'b1', 0, 'Hello' )->get_error_code() );
	}

	public function test_empty_and_too_long_notes_are_rejected() {
		$this->assertSame( 'empty', Batch_Notes::add( 'b1', $this->author, "  \n " )->get_error_code() );
		$this->assertSame( 'too_long', Batch_Notes::add( 'b1', $this->author, str_repeat( 'a', 5001 ) )->get_error_code() );
	}

	public function test_only_the_author_can_edit() {
		$id = $this->add_note();

		$this->assertSame( 'forbidden', Batch_Notes::update( $id, $this->other, 'Changed' )->get_error_code() );
		$this->assertSame( 'forbidden', Batch_Notes::update( $id, $this->brewer, 'Changed' )->get_error_code() );
		$this->assertTrue( Batch_Notes::update( $id, $this->author, 'Changed' ) );
		$this->assertSame( 'Changed', get_post( $id )->post_content );
	}

	public function test_author_and_brewers_can_delete_others_cannot() {
		$first  = $this->add_note();
		$second = $this->add_note();

		$this->assertSame( 'forbidden', Batch_Notes::delete( $first, $this->other )->get_error_code() );
		$this->assertTrue( Batch_Notes::delete( $first, $this->author ) );
		$this->assertTrue( Batch_Notes::delete( $second, $this->brewer ) );
		$this->assertSame( array(), Batch_Notes::notes_for( 'b1' ) );
	}

	public function test_admin_can_delete_any_note() {
		$id = $this->add_note();

		$this->assertTrue( Batch_Notes::delete( $id, make_user( 'administrator' ) ) );
	}

	public function test_other_posts_cannot_be_edited_or_deleted_through_notes() {
		$page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_author' => $this->author, 'post_content' => 'About' ) );

		$this->assertSame( 'forbidden', Batch_Notes::update( $page, $this->author, 'Hacked' )->get_error_code() );
		$this->assertSame( 'forbidden', Batch_Notes::delete( $page, $this->author )->get_error_code() );
		$this->assertSame( 'About', get_post( $page )->post_content );
	}

	public function test_visitors_see_a_login_link_and_no_notes() {
		$this->add_note( 'Secret tasting note' );
		$_GET['batch'] = 'b1';
		queue_response( 200, $this->batch() );

		$html = Frontend::batches_shortcode( array() );

		$this->assertStringContainsString( 'Log in to read and add notes.', $html );
		$this->assertStringNotContainsString( 'Secret tasting note', $html );
		$this->assertStringNotContainsString( '<textarea', $html );
	}

	public function test_users_see_notes_escaped_with_author_and_an_add_form() {
		$this->add_note( "Line one\n<b>bold</b>", $this->other );
		$GLOBALS['blfb_test']['current'] = $this->author;
		$_GET['batch']                   = 'b1';
		queue_response( 200, $this->batch() );

		$html = Frontend::batches_shortcode( array() );

		$this->assertStringContainsString( 'User ' . $this->other, $html );
		$this->assertStringContainsString( "Line one<br />\n&lt;b&gt;bold&lt;/b&gt;", $html );
		$this->assertStringContainsString( 'name="action" value="blfb_add_note"', $html );
		$this->assertStringNotContainsString( 'Edit</a>', $html, 'Not their note' );
		$this->assertStringNotContainsString( 'blfb_delete_note', $html, 'Not their note' );
	}

	public function test_author_sees_edit_and_delete_brewer_sees_only_delete() {
		$this->add_note();
		$_GET['batch'] = 'b1';

		$GLOBALS['blfb_test']['current'] = $this->author;
		queue_response( 200, $this->batch() );
		$author_html = Frontend::batches_shortcode( array() );

		$GLOBALS['blfb_test']['current'] = $this->brewer;
		$brewer_html                     = Frontend::batches_shortcode( array() );

		$this->assertStringContainsString( 'Edit</a>', $author_html );
		$this->assertStringContainsString( 'blfb_delete_note', $author_html );
		$this->assertStringNotContainsString( 'Edit</a>', $brewer_html );
		$this->assertStringContainsString( 'blfb_delete_note', $brewer_html );
	}

	public function test_edit_mode_shows_the_note_in_a_form() {
		$id                              = $this->add_note( 'Original text' );
		$GLOBALS['blfb_test']['current'] = $this->author;
		$_GET['batch']                   = 'b1';
		$_GET['edit_note']               = (string) $id;
		queue_response( 200, $this->batch() );

		$html = Frontend::batches_shortcode( array() );

		$this->assertStringContainsString( '>Original text</textarea>', $html );
		$this->assertStringContainsString( 'value="blfb_edit_note"', $html );
		$this->assertStringNotContainsString( 'value="blfb_add_note"', $html );
	}

	public function test_error_from_the_url_is_shown() {
		$GLOBALS['blfb_test']['current'] = $this->author;
		$_GET['batch']                   = 'b1';
		$_GET['blfb_note_error']         = 'empty';
		queue_response( 200, $this->batch() );

		$this->assertStringContainsString( 'The note is empty.', Frontend::batches_shortcode( array() ) );
	}
}
