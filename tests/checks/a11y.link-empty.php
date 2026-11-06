<?php
// phpcs:ignoreFile
$about = function ( $t, $content, $acf = null ) {
	$args = array( 'post_type' => 'page', 'post_name' => 'about', 'post_title' => 'About', 'post_content' => $content );
	if ( null !== $acf ) { $args['acf'] = $acf; omens_test_enable_acf(); }
	return omens_test_add_post( $args );
};
$theme = function ( $t, $files ) {
	$t->state()['theme']['dir'] = $t->tmp . '/theme';
	foreach ( $files as $f => $c ) { $t->file( 'theme/' . $f, $c ); }
};
return array(
	// Rule 3: href found nowhere in editor content, found in the theme -> code, naming the file.
	'fires' => array(
		'setup'  => function ( $t ) use ( $about, $theme ) {
			define( 'OMENS_STARTER_VERSION', '1.12.0' ); 
			$theme( $t, array( 'footer.php' => '<a class="icon" href="https://www.instagram.com/omens/"><svg></svg></a>' ) );
			$id = $about( $t, '<p>Plain text</p>' );
			$t->page( $t->doc( '<main>x</main><footer><a class="icon" href="https://www.instagram.com/omens/"><svg></svg></a></footer>' ), get_permalink( $id ) );
		},
		'expect' => array( 'count' => 1, 'severity' => 'serious', 'route' => 'code', 'confidence' => 'high', 'where_contains' => 'footer.php', 'criterion' => 'WCAG 2.2 2.4.4, 4.1.2' ),
	),
	// Rule 2: href in post_content -> content, high.
	'fires_href_in_post_content' => array(
		'setup'  => function ( $t ) use ( $about, $theme ) {
			$theme( $t, array( 'footer.php' => '<footer>nothing</footer>' ) );
			$id = $about( $t, '<p><a href="/contact/"></a></p>' );
			$t->page( $t->doc( '<main><a href="https://example.test/contact/"></a></main>' ), get_permalink( $id ) );
		},
		'expect' => array( 'count' => 1, 'route' => 'content', 'confidence' => 'high', 'where_contains' => 'the content of "About"' ),
	),
	// Rule 2, ACF link field with an empty label.
	'fires_acf_link_field' => array(
		'setup'  => function ( $t ) use ( $about, $theme ) {
			$theme( $t, array( 'page.php' => '<a href="<?php echo esc_url( $cta["url"] ); ?>"><?php echo esc_html( $cta["title"] ); ?></a>' ) );
			$id = $about( $t, '', array( 'cta' => array( 'url' => 'https://calendly.com/omens/intro', 'title' => '' ) ) );
			$t->page( $t->doc( '<main><a class="button" href="https://calendly.com/omens/intro"></a></main>' ), get_permalink( $id ) );
		},
		'expect' => array( 'count' => 1, 'route' => 'content', 'where_contains' => 'ACF field `cta.url`' ),
	),
	// Rule 4: in both editor content and the theme -> content, low confidence.
	'fires_ambiguous_is_low_confidence' => array(
		'setup'  => function ( $t ) use ( $about, $theme ) {
			$theme( $t, array( 'footer.php' => '<a href="/newsletter/"></a>' ) );
			$id = $about( $t, '<p><a href="/newsletter/"></a></p>' );
			$t->page( $t->doc( '<main><a href="/newsletter/"></a></main>' ), get_permalink( $id ) );
		},
		'expect' => array( 'count' => 1, 'route' => 'content', 'confidence' => 'low' ),
	),
	// Rule 4: no usable key (a button with nothing to search for) -> low confidence, code.
	'fires_button_without_key' => array(
		'setup'  => function ( $t ) use ( $theme ) { define( 'OMENS_STARTER_VERSION', '1.12.0' ); $theme( $t, array( 'header.php' => '<button class="menu-toggle"><span></span></button>' ) ); $t->page( $t->doc( '<header><button class="menu-toggle"><span></span></button></header><main>x</main>' ) ); },
		'expect' => array( 'count' => 1, 'route' => 'code', 'confidence' => 'low', 'criterion' => 'WCAG 2.2 4.1.2' ),
	),
	'clean' => array(
		'setup'  => function ( $t ) { $t->page( $t->doc( '<main><a href="/a/">Read more about us</a><a href="/b/" aria-label="Instagram"><svg></svg></a><a href="/c/"><img src="/i.png" alt="Our office"></a><button aria-labelledby="lbl"><span id="lbl">Open menu</span></button><a href="/d/"><svg><title>LinkedIn</title></svg></a><a href="/e/" hidden></a><a name="anchor"></a><input type="submit" value="Send"></main>' ) ); },
		'expect' => array( 'count' => 0 ),
	),
);
