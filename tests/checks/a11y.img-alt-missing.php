<?php
// phpcs:ignoreFile
// The routing rule's branches live here and in a11y.link-empty: one fixture per branch.
$about = function ( $t, $content, $acf = null ) {
	$args = array( 'post_type' => 'page', 'post_name' => 'about', 'post_title' => 'About', 'post_content' => $content );
	if ( null !== $acf ) { $args['acf'] = $acf; omens_test_enable_acf(); }
	return omens_test_add_post( $args );
};
$attachment = function ( $t, $id, $file, $alt = '' ) {
	omens_test_add_post( array( 'ID' => $id, 'post_type' => 'attachment', 'post_name' => 'att-' . $id, 'guid' => home_url( '/wp-content/uploads/2026/11/' . $file ), 'meta' => array( '_wp_attachment_image_alt' => $alt ) ) );
};
return array(
	// Rule 1: no alt attribute at all -> code.
	'fires' => array(
		'setup'  => function ( $t ) use ( $about ) {
			define( 'OMENS_STARTER_VERSION', '1.12.0' ); 
			$id = $about( $t, '<p>Hello</p>' );
			$t->page( $t->doc( '<header><img src="/wp-content/themes/x/images/logo.svg" class="site-logo"></header><main>About</main>' ), get_permalink( $id ) );
			$t->page( $t->doc( '<main>home</main>' ) );
		},
		'expect' => array( 'count' => 1, 'severity' => 'serious', 'route' => 'code', 'confidence' => 'high', 'criterion' => 'WCAG 2.2 1.1.1', 'where_contains' => 'header' ),
	),
	// Rule 2, media library branch: empty alt inside a link, the attachment is in post_content -> content, Media Library.
	'fires_empty_alt_in_link_is_content' => array(
		'setup'  => function ( $t ) use ( $about, $attachment ) {
			$attachment( $t, 412, 'team-photo.jpg', '' );
			$id = $about( $t, '<figure class="wp-block-image"><a href="/team/"><img src="https://example.test/wp-content/uploads/2026/11/team-photo-1024x768.jpg" alt="" class="wp-image-412"/></a></figure>' );
			$t->page( $t->doc( '<main><a href="/team/"><img src="https://example.test/wp-content/uploads/2026/11/team-photo-1024x768.jpg" alt="" class="wp-image-412"></a></main>' ), get_permalink( $id ) );
		},
		'expect' => array( 'count' => 1, 'route' => 'content', 'confidence' => 'high', 'where_contains' => 'Media Library alt for attachment 412', 'subject' => '/wp-content/uploads/2026/11/team-photo.jpg|empty' ),
	),
	// The library HAS alt text and the template dropped it -> code.
	'fires_library_alt_dropped_by_template' => array(
		'setup'  => function ( $t ) use ( $about, $attachment ) {
			define( 'OMENS_STARTER_VERSION', '1.12.0' ); 
			$attachment( $t, 413, 'hero.jpg', 'The Omens team in the studio' );
			$id = $about( $t, '<p>no images in content</p>' );
			$t->page( $t->doc( '<main><figure><img src="/wp-content/uploads/2026/11/hero.jpg" alt="" class="hero wp-image-413"></figure></main>' ), get_permalink( $id ) );
		},
		'expect' => array( 'count' => 1, 'route' => 'code', 'confidence' => 'high', 'summary_contains' => 'Media Library holds alt text' ),
	),
	// Rule 2, ACF branch: image chosen in an ACF field, empty alt, alone in a figure -> content with the field named.
	'fires_acf_image_field' => array(
		'setup'  => function ( $t ) use ( $about, $attachment ) {
			$attachment( $t, 414, 'office.jpg', '' );
			$id = $about( $t, '', array( 'hero' => array( 'image' => 414, 'heading' => 'Welcome' ) ) );
			$t->page( $t->doc( '<main><figure><img src="/wp-content/uploads/2026/11/office.jpg" alt=""></figure></main>' ), get_permalink( $id ) );
		},
		'expect' => array( 'count' => 1, 'route' => 'content', 'where_contains' => 'ACF field `hero.image' ),
	),
	// Decorative: empty alt, not in a link, not alone in a figure, no library alt -> nothing.
	'clean' => array(
		'setup'  => function ( $t ) use ( $about, $attachment ) {
			$attachment( $t, 415, 'divider.png', '' );
			$id = $about( $t, '<p>x</p>' );
			$t->page( $t->doc( '<main><img src="/wp-content/uploads/2026/11/divider.png" alt="" class="wp-image-415"><p>Text</p><figure><img src="/a.jpg" alt=""><figcaption>Caption</figcaption></figure><a href="/x/"><img src="/i.svg" alt=""> Go</a><img src="/t.gif" alt="" aria-hidden="true"></main>' ), get_permalink( $id ) );
			$t->page( $t->doc( '<main><img src="/wp-content/themes/x/logo.svg" alt="Example Co"></main>' ) );
		},
		'expect' => array( 'count' => 0 ),
	),
	// The same defect on every page is one finding.
	'fires_dedupes_across_pages' => array(
		'setup'  => function ( $t ) use ( $about ) {
			$id = $about( $t, '' );
			$html = $t->doc( '<header><img src="/logo.svg"></header><main>x</main>' );
			$t->page( $html ); $t->page( $html, get_permalink( $id ) );
		},
		'expect' => array( 'count' => 1 ),
	),
	// Rule 5: on a purchased site the code route becomes human.
	'fires_purchased_routes_human' => array(
		'setup'  => function ( $t ) { $t->state()['theme']['author'] = 'ThemeForest Vendor'; $t->page( $t->doc( '<main><img src="/x.jpg"></main>' ) ); },
		'expect' => array( 'count' => 1, 'route' => 'human', 'where_contains' => 'no repo' ),
	),
);
