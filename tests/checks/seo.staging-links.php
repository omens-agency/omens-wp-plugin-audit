<?php
// phpcs:ignoreFile
// Shapes taken from the November 10, 2026 fleet sweep (WPBakery url: attributes, Elementor
// JSON, ThemeREX layouts, theme options, Gravity Forms confirmations, a hardcoded theme file).
$prod = function ( $t ) {
	$t->state()['home'] = 'https://example.com';
	$t->state()['env']  = 'production';
	$t->state()['options']['siteurl'] = 'https://example.com';
	$t->state()['theme']['dir'] = $t->tmp . '/themes/example';
	$t->file( 'themes/example/style.css', '/* Theme Name: Example */' );
};
$posts = function ( $t, array $rows ) {
	foreach ( $rows as $r ) {
		$t->state()['db_rows']['wp_posts'][] = array_merge( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Home', 'post_excerpt' => '' ), $r );
		omens_test_add_post( array_merge( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Home' ), $r ) );
	}
};
return array(
	'fires' => array(
		'setup'  => function ( $t ) use ( $prod, $posts ) {
			$prod( $t );
			$posts( $t, array(
				array( 'ID' => 2, 'post_content' => '[tm_button button="url:https%3A%2F%2Fexample.mystagingwebsite.com%2Fcontact%2F|title:Contact"]' ),
				array( 'ID' => 18, 'post_type' => 'cpt_layouts', 'post_title' => 'Header Default', 'post_content' => '<img src="//example.mystagingwebsite.com/wp-content/uploads/2023/10/logo.png">' ),
			) );
			$t->state()['db_rows']['wp_postmeta'][] = array( 'post_id' => 44, 'meta_key' => '_elementor_data', 'meta_value' => '[{"settings":{"image":{"url":"https:\/\/examplestg.omens.site\/wp-content\/uploads\/hero.jpg"}}}]', 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'About' );
			$t->state()['db_rows']['wp_options'][] = array( 'option_name' => 'minti_data', 'option_value' => 'a:1:{s:9:"copyright";s:70:"<a href="https://example.mystagingwebsite.com/terms-and-conditions/">T</a>";}' );
		},
		'expect' => function ( $findings, $run, $t ) {
			$t->assert( 1 === count( $findings ), 'expected one finding, got ' . count( $findings ) );
			$f = $findings[0];
			$t->assert( 'content' === $f['subject'] && 'serious' === $f['severity'] && 'content' === $f['fix']['route'], 'content/serious/content expected' );
			$t->assert( false !== strpos( $f['title'], '4 links' ), 'title counts 4 links: ' . $f['title'] );
			$t->assert( false !== strpos( $f['title'], 'example.mystagingwebsite.com' ) && false !== strpos( $f['title'], 'examplestg.omens.site' ), 'both hosts named: ' . $f['title'] );
			$t->assert( false !== strpos( $f['fix']['where'], 'minti_data' ) && false !== strpos( $f['fix']['where'], 'cpt_layouts' ), 'locations named: ' . $f['fix']['where'] );
			$t->assert( false !== strpos( implode( ' ', $f['fix']['steps'] ), "wp search-replace 'example.mystagingwebsite.com' 'example.com'" ), 'search-replace command to the live host' );
			$t->assert( 0 === strpos( $f['fix']['steps'][0], 'Found in: ' ), 'first step names the places' );
			$t->assert( false === strpos( implode( ' ', $f['fix']['steps'] ), 'slider editor' ), 'no slider step without a slider' );
		},
	),
	'fires_forms_confirmation_redirect' => array(
		'setup'  => function ( $t ) use ( $prod ) {
			$prod( $t );
			$t->state()['db_tables'][] = 'wp_gf_form_meta';
			$t->state()['db_rows']['wp_gf_form_meta'][] = array( 'form_id' => 4, 'display_meta' => '{}', 'confirmations' => '{"a":{"type":"redirect","url":"https:\/\/example.mystagingwebsite.com\/thanks\/"}}', 'notifications' => '{}' );
		},
		// The confirmation redirects to staging, so it ranks with page content.
		'expect' => array( 'count' => 1, 'subject' => 'forms', 'severity' => 'serious', 'route' => 'content', 'where_contains' => 'form 4 (its confirmation redirects to staging)' ),
	),
	'fires_forms_notification_only' => array(
		'setup'  => function ( $t ) use ( $prod ) {
			$prod( $t );
			$t->state()['db_tables'][] = 'wp_gf_form_meta';
			$t->state()['db_rows']['wp_gf_form_meta'][] = array( 'form_id' => 1, 'display_meta' => '{}', 'confirmations' => '{"a":{"type":"message","message":"Thanks"}}', 'notifications' => '{"n":{"message":"<a href=\\"https:\\/\\/example.mystagingwebsite.com\\/\\">site<\\/a>"}}' );
		},
		'expect' => array( 'count' => 1, 'subject' => 'forms', 'severity' => 'moderate' ),
	),
	'fires_theme_file' => array(
		'setup'  => function ( $t ) use ( $prod ) {
			$prod( $t );
			$t->file( 'themes/example/sections/logo-grid.php', '<img class="logo" src="https://exampledev.wpengine.com/wp-content/uploads/2022/10/intel.png">' );
		},
		// Example has no starter core, so it is a purchased build and the engine sends code fixes to a person.
		'expect' => function ( $findings, $run, $t ) {
			$t->assert( 1 === count( $findings ), 'one theme finding' );
			$f = $findings[0];
			$t->assert( 'theme' === $f['subject'] && 'serious' === $f['severity'] && 'human' === $f['fix']['route'], 'theme/serious/human' );
			$t->assert( 0 === strpos( $f['fix']['steps'][0], 'In example/sections/logo-grid.php, replace exampledev.wpengine.com' ), 'plain path in the step: ' . $f['fix']['steps'][0] );
		},
	),
	'fires_local_host_in_post' => array(
		'setup'  => function ( $t ) use ( $prod, $posts ) {
			$prod( $t );
			$posts( $t, array( array( 'ID' => 8522, 'post_type' => 'post', 'post_title' => 'News', 'post_content' => '<a href="http://www.example.local/kirsten">www.example.com</a> and <a href="http://localhost:8895/x">x</a>' ) ) );
		},
		'expect' => array( 'count' => 1, 'subject' => 'content', 'title_contains' => '2 links' ),
	),
	// November 10 slider sweep: Slider Revolution slides in their own table, the slider placed on the home page by
	// its Elementor widget (estebaninteriors, hotelsanbuena), plus a LayerSlider slider on no page and one in its trash (miresball).
	'fires_slider_placed_and_lists_unused' => array(
		'setup'  => function ( $t ) use ( $prod, $posts ) {
			$prod( $t );
			$t->state()['db_tables'] = array_merge( $t->state()['db_tables'], array( 'wp_revslider_sliders', 'wp_revslider_slides', 'wp_revslider_slides_bkp', 'wp_revslider_css', 'wp_layerslider' ) );
			$t->state()['db_rows']['wp_revslider_sliders'][] = array( 'id' => 1, 'title' => 'Main', 'alias' => 'main', 'params' => '{"layout":"fullwidth"}' );
			$t->state()['db_rows']['wp_revslider_sliders'][] = array( 'id' => 2, 'title' => 'Demo', 'alias' => 'identity', 'params' => '{"bg":"https:\\/\\/exampledemo.wpengine.com\\/d.jpg"}' );
			$t->state()['db_rows']['wp_revslider_slides'][]  = array( 'id' => 7, 'slider_id' => 1, 'params' => '{"bg":{"image":"https:\\/\\/example.mystagingwebsite.com\\/wp-content\\/uploads\\/2024\\/02\\/hotel.jpg"}}', 'layers' => '{}' );
			$t->state()['db_rows']['wp_revslider_slides_bkp'][] = array( 'id' => 7, 'slider_id' => 1, 'params' => 'https://old.mystagingwebsite.com/x.jpg', 'layers' => '' );
			$t->state()['db_rows']['wp_revslider_css'][] = array( 'id' => 1, 'handle' => '.x', 'params' => 'url(https://example.mystagingwebsite.com/a.png)' );
			$t->state()['db_rows']['wp_layerslider'][] = array( 'id' => 1, 'name' => 'Full width demo slider', 'slug' => '', 'data' => '{"layers":[{"image":"https:\\/\\/exampledemo.wpengine.com\\/a.jpg"}]}', 'flag_hidden' => 0, 'flag_deleted' => 1 );
			$t->state()['db_rows']['wp_layerslider'][] = array( 'id' => 3, 'name' => 'Identity', 'slug' => 'identity', 'data' => '{"layers":[{"image":"https:\\/\\/exampledemo.wpengine.com\\/b.jpg"}]}', 'flag_hidden' => 0, 'flag_deleted' => 0 );
			// Adminimize keeps a copy of the admin toolbar, which lists every slider as data-alias="...". Not a placement.
			$t->state()['db_rows']['wp_options'][] = array( 'option_name' => 'mw_adminimize', 'option_value' => '{"home":{"title":"<span class=\\"rs-label\\" data-alias=\\"identity\\">Identity<\\/span>"}}' );
			$t->state()['db_rows']['wp_postmeta'][] = array( 'post_id' => 2, 'meta_key' => '_elementor_data', 'meta_value' => '[{"widgetType":"slider_revolution","settings":{"shortcode":"[rev_slider alias=\\"main\\" slidertitle=\\"Main\\"][\\/rev_slider]"}}]', 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Home' );
			$posts( $t, array( array( 'ID' => 2, 'post_content' => '<p>Welcome</p>' ) ) );
		},
		'expect' => function ( $findings, $run, $t ) {
			$t->assert( 2 === count( $findings ), 'one content finding and one unused-slider note, got ' . count( $findings ) . ': ' . json_encode( array_column( $findings, 'title' ) ) );
			$by = array_column( $findings, null, 'subject' );
			$c  = $by['content'] ?? null;
			$t->assert( $c && 'serious' === $c['severity'], 'content finding at serious' );
			$t->assert( false !== strpos( $c['fix']['where'], 'Slider Revolution slider "Main" (on page "Home" (ID 2))' ), 'slider named with its page: ' . $c['fix']['where'] );
			$t->assert( false === strpos( $c['title'], 'old.mystagingwebsite.com' ), 'backup table not read' );
			$t->assert( false !== strpos( implode( ' ', $c['fix']['steps'] ), 'save it once so its own cache rebuilds' ), 'slider re-save step' );
			$t->assert( 1 === substr_count( $c['title'], 'link' ) && false !== strpos( $c['title'], '1 link to example.mystagingwebsite.com' ), 'one link counted, the css table skipped: ' . $c['title'] );
			$u = $by['sliders-unused'] ?? null;
			$t->assert( $u && 'info' === $u['severity'], 'unused sliders at info' );
			$t->assert( false !== strpos( $u['title'], '3 sliders on no page' ), 'both LayerSlider sliders and the toolbar-only revslider listed: ' . $u['title'] );
			$t->assert( false !== strpos( $u['fix']['where'], 'Slider Revolution slider "Demo"' ), 'a data-alias in an admin cache is not a placement: ' . $u['fix']['where'] );
			$t->assert( false !== strpos( $u['fix']['where'], 'Full width demo slider" (in the trash)' ) && false !== strpos( $u['fix']['where'], 'LayerSlider slider "Identity"' ), 'names and the trash note: ' . $u['fix']['where'] );
		},
	),
	'clean_slider_on_own_host' => array(
		'setup'  => function ( $t ) use ( $prod ) {
			$prod( $t );
			$t->state()['db_tables'][] = 'wp_revslider_slides';
			$t->state()['db_rows']['wp_revslider_slides'][] = array( 'id' => 1, 'slider_id' => 1, 'params' => 'https://example.com/wp-content/uploads/a.jpg https://www.wpengine.com/', 'layers' => '' );
		},
		'expect' => array( 'count' => 0 ),
	),
	'clean' => array(
		'setup'  => function ( $t ) use ( $prod, $posts ) {
			$prod( $t );
			$posts( $t, array(
				array( 'ID' => 3, 'post_content' => '<a href="https://example.com/contact/">Contact</a> <a href="https://www.localcitation.online/">x</a> email me at a@example.local.' ),
				array( 'ID' => 748, 'post_type' => 'flamingo_inbound', 'post_content' => 'spam https://example.mystagingwebsite.com/' ),
				array( 'ID' => 9, 'post_status' => 'draft', 'post_content' => 'https://example.mystagingwebsite.com/' ),
			) );
			$t->state()['db_rows']['wp_postmeta'][] = array( 'post_id' => 818, 'meta_key' => '_source_url', 'meta_value' => 'https://examplestg.omens.site/wp-content/uploads/icon.png', 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_title' => 'Icon' );
			foreach ( array(
				'wpmdb_state_1'                    => 'replace_old%5B%5D=https%3A%2F%2Fexample.mystagingwebsite.com',
				'fs_accounts'                      => '<a href="https://example.mystagingwebsite.com">clone</a>',
				'wpe_site_transient_update_plugins' => 's:63:"https://plugin-updates.wpengine.com/accessibe/accessibe.2.7.zip";',
				'ptk_patterns'                     => 'https://wooblockpatterns.wpcomstaging.com/2024/07/10/intro/',
				'bsr_data'                         => 's:29:"https://example.wpengine.com";',
				'gv-cache-f1'                      => 'https://example.mystagingwebsite.com',
				'widget_text'                      => '<a href="https://www.wpengine.com/">Hosted by WP Engine</a>',
			) as $k => $v ) {
				$t->state()['db_rows']['wp_options'][] = array( 'option_name' => $k, 'option_value' => $v );
			}
			$t->file( 'themes/example/functions.php', "<?php if ( is_local() ) { \$portal = 'http://127.0.0.1:8095'; }" );
			$t->file( 'themes/example/.nova/Publishing/STG.json', '{"remoteURL":"https:\/\/examplestg.wpengine.com"}' );
			$t->file( 'themes/example/node_modules/x/index.js', 'fetch("http://example.local/")' );
		},
		'expect' => array( 'count' => 0 ),
	),
	'clean_own_host_and_skipped_on_staging' => array(
		'setup'  => function ( $t ) use ( $posts ) {
			$t->state()['home'] = 'https://example.mystagingwebsite.com';
			$t->state()['env']  = 'staging';
			$posts( $t, array( array( 'ID' => 2, 'post_content' => 'https://example.mystagingwebsite.com/ https://other.mystagingwebsite.com/' ) ) );
		},
		'expect' => function ( $findings, $run, $t ) {
			$t->assert( 1 === count( $findings ), 'one skip note expected' );
			foreach ( $findings as $f ) { $t->assert( 'not-applicable' === $f['subject'] && 'info' === $f['severity'], 'only the not-on-production note: ' . $f['title'] ); }
		},
	),
);
