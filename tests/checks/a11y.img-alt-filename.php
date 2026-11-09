<?php
// phpcs:ignoreFile
$attachment = function ( $t, $id, $file, $alt ) {
	omens_test_add_post( array( 'ID' => $id, 'post_type' => 'attachment', 'post_name' => 'att-' . $id, 'guid' => home_url( '/wp-content/uploads/2026/11/' . $file ), 'meta' => array( '_wp_attachment_image_alt' => $alt ) ) );
};
return array(
	// Media library branch: the alt is the library's, so content with the attachment named.
	'fires' => array(
		'setup'  => function ( $t ) use ( $attachment ) {
			$attachment( $t, 501, 'IMG_4523.jpg', 'IMG_4523.jpg' );
			$t->page( $t->doc( '<main><img src="/wp-content/uploads/2026/11/IMG_4523.jpg" alt="IMG_4523.jpg" class="wp-image-501"></main>' ) );
		},
		'expect' => array( 'count' => 1, 'severity' => 'moderate', 'route' => 'content', 'where_contains' => 'Media Library alt for attachment 501', 'criterion' => 'WCAG 2.2 1.1.1' ),
	),
	'fires_meaningless_stem_without_extension' => array(
		'setup'  => function ( $t ) use ( $attachment ) {
			$attachment( $t, 502, 'DSC01234.jpg', 'DSC01234' );
			$t->page( $t->doc( '<main><img src="/wp-content/uploads/2026/11/DSC01234-scaled.jpg" alt="DSC01234" class="wp-image-502"></main>' ) );
		},
		'expect' => array( 'count' => 1, 'summary_contains' => 'meaningless filename' ),
	),
	// Hard-coded literal alt in the theme, no attachment -> code naming the file.
	'fires_literal_in_theme_is_code' => array(
		'setup'  => function ( $t ) {
			define( 'OMENS_STARTER_VERSION', '1.12.0' ); 
			$t->state()['theme']['dir'] = $t->tmp . '/theme';
			$t->file( 'theme/header.php', '<img src="<?php echo $logo; ?>" alt="logo">' );
			$t->page( $t->doc( '<header><img src="/wp-content/themes/x/logo.svg" alt="logo"></header><main>x</main>' ) );
		},
		'expect' => array( 'count' => 1, 'route' => 'code', 'where_contains' => 'header.php', 'summary_contains' => 'kind of thing' ),
	),
	// A one-letter alt on a glyph icon (Blackbird's AddToAny X share button, x.png, alt "X") is the right word.
	'clean_single_letter_icon' => array(
		'setup'  => function ( $t ) use ( $attachment ) {
			$attachment( $t, 504, 'x.png', 'X' );
			$t->page( $t->doc( '<main><a href="https://x.com/share"><img src="/wp-content/uploads/2026/11/x.png" alt="X" class="wp-image-504"></a><img src="/wp-content/themes/t/f.svg" alt="f"></main>' ) );
		},
		'expect' => array( 'count' => 0 ),
	),
	'clean' => array(
		'setup'  => function ( $t ) use ( $attachment ) {
			$attachment( $t, 503, 'IMG_4523.jpg', 'Jake and Ian at the Omens studio' );
			$t->page( $t->doc( '<main><img src="/wp-content/uploads/2026/11/IMG_4523.jpg" alt="Jake and Ian at the Omens studio" class="wp-image-503"><img src="/x.png" alt=""><img src="/la-jolla-sunset.jpg" alt="La Jolla sunset"><img src="/2.png" alt="Step 2 of 3"></main>' ) );
		},
		'expect' => array( 'count' => 0 ),
	),
);
