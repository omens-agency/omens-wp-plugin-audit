<?php
/**
 * seo.staging-links: a production site still links to, or loads files from, a
 * local or staging host. A database push from staging or a local copy carries
 * the old host into page content, page-builder data, theme options, widgets,
 * menus and form settings; a theme file can carry it too. Visitors then land on
 * a staging site, images break when staging is wiped, and form confirmations
 * redirect to the wrong place.
 *
 * Production only: on staging and local copies these hosts are expected.
 * One finding per place to fix (content, forms, theme code), listing the hosts
 * and the locations, so a site with forty stale links files three tasks at most.
 *
 * @package OmensAudit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Omens_Audit_Check_Staging_Links extends Omens_Audit_Check_Base {

	const ROW_LIMIT  = 3000;
	const FILE_LIMIT = 6000;
	const FILE_BYTES = 600000;
	const LOCATIONS  = 12;

	public function id() {
		return 'seo.staging-links';
	}

	public function family() {
		return 'seo';
	}

	public function title() {
		return 'Links to local or staging hosts on production';
	}

	public function severity() {
		return 'serious';
	}

	public function applies( Omens_Audit_Context $ctx ) {
		return 'production' === $ctx->effective_environment ? true : 'only runs on production (this is ' . $ctx->effective_environment . ' by host name)';
	}

	/**
	 * The host pattern. Group 1 is the host. Matches `//host`, JSON-escaped
	 * `\/\/host` and URL-encoded `%2F%2Fhost`, so page-builder data and
	 * WPBakery's `url:https%3A%2F%2F...` attributes are both seen.
	 *
	 * @return string
	 */
	public static function pattern() {
		/**
		 * Host suffixes that mean local or staging.
		 *
		 * @param string[] $suffixes Regex fragments, without the leading dot.
		 */
		$suffixes = (array) apply_filters(
			'omens_audit/staging_link_suffixes',
			array( 'local', 'test', 'localhost', 'mystagingwebsite\.com', 'omens\.site', 'wpengine\.com', 'wpenginepowered\.com', 'flywheelsites\.com', 'kinsta\.cloud', 'pantheonsite\.io', 'myftpupload\.com', 'wpcomstaging\.com', 'instawp\.xyz', 'instawp\.co', 'ngrok\.io', 'ngrok-free\.app' )
		);
		return '~(?:https?:|https?%3A)?(?:\\\\?/|%2F){2}((?:localhost|127\.0\.0\.1|0\.0\.0\.0)(?![.a-z0-9-])|[a-z0-9][a-z0-9.-]*\.(?:' . implode( '|', $suffixes ) . ')(?![a-z0-9-]))(?::\d+)?~i';
	}

	/**
	 * Hosts that match the pattern but are not ours: WP Engine's plugin and theme
	 * update mirrors, WP Engine's own site, and WooCommerce's pattern source.
	 *
	 * @param string $host Lowercase host.
	 * @return bool
	 */
	public static function ignored_host( $host ) {
		$patterns = (array) apply_filters(
			'omens_audit/staging_link_ignore_hosts',
			array(
				'/^(plugin-updates|theme-updates|wpe-plugin-updates|wpe-downloads|downloads|www|my|api)\.wpengine\.com$/',
				'/^wooblockpatterns\.wpcomstaging\.com$/',
			)
		);
		foreach ( $patterns as $p ) {
			if ( preg_match( $p, $host ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Options that hold a staging host for a reason other than a stale link:
	 * migration and search-replace history, update mirrors, licence clone
	 * records, caches and logs.
	 *
	 * @param string $name Option name.
	 * @return bool
	 */
	public static function ignored_option( $name ) {
		if ( in_array( $name, array( 'cron', 'rewrite_rules', 'recently_activated', 'active_plugins', 'siteurl', 'home', 'fs_accounts', 'wpseo-gsc', 'ptk_patterns', 'bsr_data', 'rank_math_backups' ), true ) ) {
			return true;
		}
		$re = (string) apply_filters( 'omens_audit/staging_link_ignore_options', '/^(_transient|_site_transient|jetpack|jpsq|wpcom|omens_audit|wpmdb|wpe_|wpesu-|gv-cache|gadwp_cache|elementor_log|action_scheduler|updraft|ai1wm|duplicator|wpvivid)|_log$|cache|backup|migrat/i' );
		return (bool) preg_match( $re, $name );
	}

	/**
	 * Post meta that records a source or a derived cache, not a link a visitor sees.
	 *
	 * @param string $key Meta key.
	 * @return bool
	 */
	public static function ignored_meta( $key ) {
		return in_array( $key, array( '_edit_lock', '_edit_last', '_wp_old_slug', '_elementor_css', '_elementor_element_cache', '_wp_attachment_backup_sizes', '_source_url', '_wp_attached_file', '_wp_attachment_metadata' ), true ) || 0 === strpos( $key, '_oembed' );
	}

	/**
	 * Post types whose content is visitor-submitted or a revision, never ours.
	 *
	 * @return string[]
	 */
	public static function ignored_post_types() {
		return (array) apply_filters( 'omens_audit/staging_link_ignore_post_types', array( 'revision', 'customize_changeset', 'oembed_cache', 'flamingo_inbound', 'flamingo_contact', 'feedback', 'user_request', 'nf_sub', 'shop_order', 'shop_order_refund', 'shop_order_placehold', 'scheduled-action' ) );
	}

	public function run( Omens_Audit_Context $ctx ) {
		global $wpdb;
		$own  = array_filter(
			array_unique(
				array(
					strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ),
					strtolower( (string) wp_parse_url( (string) get_option( 'siteurl' ), PHP_URL_HOST ) ),
					strtolower( (string) $ctx->host ),
				)
			)
		);
		$re   = self::pattern();
		$hits = array(
			'content' => array(),
			'forms'   => array(),
			'theme'   => array(),
		);

		$scan = function ( $group, $where, $label, $text, $post_id = 0, $code = false ) use ( &$hits, $re, $own ) {
			if ( ! is_string( $text ) || '' === $text || ! preg_match_all( $re, $text, $m ) ) {
				return;
			}
			foreach ( $m[1] as $host ) {
				$host = strtolower( $host );
				if ( in_array( $host, $own, true ) || self::ignored_host( $host ) ) {
					continue;
				}
				// A loopback address in theme code is almost always a guarded local-development branch.
				if ( $code && preg_match( '/^(localhost|127\.0\.0\.1|0\.0\.0\.0)$/', $host ) ) {
					continue;
				}
				if ( ! isset( $hits[ $group ][ $where ] ) ) {
					$hits[ $group ][ $where ] = array(
						'label'   => $label,
						'post_id' => $post_id,
						'hosts'   => array(),
					);
				}
				$hits[ $group ][ $where ]['hosts'][ $host ] = ( $hits[ $group ][ $where ]['hosts'][ $host ] ?? 0 ) + 1;
			}
		};

		$like = function ( $col ) use ( $wpdb ) {
			$parts = array();
			foreach ( array( 'localhost', '127.0.0.1', '0.0.0.0', '.local', '.test', 'mystagingwebsite', 'omens.site', 'wpengine', 'flywheelsites', 'kinsta.cloud', 'pantheonsite', 'myftpupload', 'wpcomstaging', 'instawp', 'ngrok' ) as $l ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $col is one of five fixed column names in this file.
				$parts[] = $wpdb->prepare( "{$col} LIKE %s", '%' . $wpdb->esc_like( $l ) . '%' );
			}
			return '(' . implode( ' OR ', $parts ) . ')';
		};

		$skip_types = self::ignored_post_types();
		$live       = array( 'publish', 'private', 'future' );
		$form_types = array( 'wpcf7_contact_form' );

		// Posts: page content, builder shortcodes, reusable layouts, menu items.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- table names from $wpdb, every LIKE built with prepare(), LIMIT a class constant.
		$posts = (array) $wpdb->get_results( "SELECT ID, post_type, post_status, post_title, post_content, post_excerpt FROM {$wpdb->posts} WHERE post_status IN ('publish','private','future') AND " . $like( 'post_content' ) . ' LIMIT ' . self::ROW_LIMIT );
		foreach ( $posts as $p ) {
			if ( in_array( $p->post_type, $skip_types, true ) || ! in_array( $p->post_status, $live, true ) ) {
				continue;
			}
			$group = in_array( $p->post_type, $form_types, true ) ? 'forms' : 'content';
			$scan( $group, 'post:' . $p->ID, sprintf( '%s "%s" (ID %d)', $p->post_type, omens_audit_trim( $p->post_title, 50 ), $p->ID ), $p->post_content . ' ' . $p->post_excerpt, (int) $p->ID );
		}

		// Post meta: Elementor and other builder data, ACF fields, menu item URLs.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- table names from $wpdb, every LIKE built with prepare(), LIMIT a class constant.
		$meta = (array) $wpdb->get_results( "SELECT m.post_id, m.meta_key, m.meta_value, p.post_type, p.post_status, p.post_title FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE p.post_status IN ('publish','private','future','inherit') AND " . $like( 'm.meta_value' ) . ' LIMIT ' . self::ROW_LIMIT );
		foreach ( $meta as $r ) {
			if ( in_array( $r->post_type, $skip_types, true ) || ! in_array( $r->post_status, array_merge( $live, array( 'inherit' ) ), true ) || self::ignored_meta( $r->meta_key ) ) {
				continue;
			}
			$group = in_array( $r->post_type, $form_types, true ) ? 'forms' : 'content';
			$scan( $group, 'post:' . $r->post_id, sprintf( '%s "%s" (ID %d)', $r->post_type, omens_audit_trim( $r->post_title, 50 ), $r->post_id ), $r->meta_value, (int) $r->post_id );
		}

		// Options: theme options, widgets, the customizer.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- table names from $wpdb, every LIKE built with prepare(), LIMIT a class constant.
		$opts = (array) $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name NOT LIKE '\\_transient%' AND option_name NOT LIKE '\\_site\\_transient%' AND " . $like( 'option_value' ) . ' LIMIT ' . self::ROW_LIMIT );
		foreach ( $opts as $o ) {
			if ( self::ignored_option( $o->option_name ) ) {
				continue;
			}
			$scan( 'content', 'option:' . $o->option_name, 'the ' . $o->option_name . ' option' . ( 0 === strpos( $o->option_name, 'widget_' ) ? ' (a widget)' : ( 0 === strpos( $o->option_name, 'theme_mods_' ) ? ' (the customizer)' : '' ) ), $o->option_value );
		}

		// Term meta: category and taxonomy settings some themes store links in.
		if ( ! empty( $wpdb->termmeta ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- table names from $wpdb, every LIKE built with prepare(), LIMIT a class constant.
			foreach ( (array) $wpdb->get_results( "SELECT term_id, meta_key, meta_value FROM {$wpdb->termmeta} WHERE " . $like( 'meta_value' ) . ' LIMIT 1000' ) as $r ) {
				$scan( 'content', 'term:' . $r->term_id, 'term ' . $r->term_id . ' (' . $r->meta_key . ')', $r->meta_value );
			}
		}

		// Gravity Forms: form HTML, confirmations (redirects) and notification emails.
		$gf = $wpdb->prefix . 'gf_form_meta';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $gf ) ) === $gf ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- table names from $wpdb, every LIKE built with prepare(), LIMIT a class constant.
			$rows = (array) $wpdb->get_results( "SELECT form_id, display_meta, confirmations, notifications FROM {$gf} WHERE " . $like( 'display_meta' ) . ' OR ' . $like( 'confirmations' ) . ' OR ' . $like( 'notifications' ) );
			foreach ( $rows as $r ) {
				$scan( 'forms', 'gf:' . $r->form_id, 'Gravity Forms form ' . $r->form_id, $r->display_meta . ' ' . $r->confirmations . ' ' . $r->notifications );
				// A redirect confirmation to a staging host sends every visitor who submits the form there.
				if ( isset( $hits['forms'][ 'gf:' . $r->form_id ] ) ) {
					foreach ( (array) json_decode( (string) $r->confirmations, true ) as $conf ) {
						if ( is_array( $conf ) && 'redirect' === ( $conf['type'] ?? '' ) && preg_match( $re, (string) ( $conf['url'] ?? '' ), $cm ) && ! in_array( strtolower( $cm[1] ), $own, true ) ) {
							$hits['forms'][ 'gf:' . $r->form_id ]['redirect'] = true;
						}
					}
				}
			}
		}

		// Theme files, parent and child: a hardcoded host in a template or stylesheet.
		$theme_root = rtrim( dirname( get_stylesheet_directory() ), '/' ) . '/';
		$seen       = 0;
		foreach ( array_unique( array( get_stylesheet_directory(), get_template_directory() ) ) as $dir ) {
			if ( ! is_dir( $dir ) ) {
				continue;
			}
			try {
				$it = new RecursiveIteratorIterator(
					new RecursiveCallbackFilterIterator(
						new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
						function ( $f ) {
							$name = $f->getFilename();
							return ! ( $f->isDir() && ( '.' === $name[0] || in_array( $name, array( 'node_modules', 'vendor', 'bower_components' ), true ) ) );
						}
					)
				);
				foreach ( $it as $f ) {
					if ( ++$seen > self::FILE_LIMIT ) {
						break 2;
					}
					if ( $f->isLink() || ! $f->isFile() || $f->getSize() > self::FILE_BYTES || ! preg_match( '/\.(php|js|css|html?|twig|json)$/i', $f->getFilename() ) || preg_match( '/\.min\.(js|css)$/i', $f->getFilename() ) ) {
						continue;
					}
					$rel = str_replace( $theme_root, '', $f->getPathname() );
					$scan( 'theme', 'file:' . $rel, $rel, (string) file_get_contents( $f->getPathname() ), 0, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local theme file, read-only.
				}
			} catch ( \Throwable $e ) {
				continue;
			}
		}

		return $this->findings_from( $hits, $ctx, $own );
	}

	/**
	 * Turn the grouped hits into at most three findings.
	 *
	 * @param array               $hits Group => where => { label, post_id, hosts }.
	 * @param Omens_Audit_Context $ctx  Run context.
	 * @param string[]            $own  This site's own hosts.
	 * @return array[]
	 */
	private function findings_from( array $hits, Omens_Audit_Context $ctx, array $own ) {
		$live     = $own ? (string) reset( $own ) : $ctx->host;
		$findings = array();
		$what     = array(
			'content' => array( 'page content, page-builder data, theme options or widgets', 'content', 'serious' ),
			'forms'   => array( 'form settings (confirmations, form HTML or notification emails)', 'content', 'moderate' ),
			'theme'   => array( 'theme files', 'code', 'serious' ),
		);
		foreach ( $hits as $group => $locations ) {
			if ( ! $locations ) {
				continue;
			}
			$hosts = array();
			$total = 0;
			foreach ( $locations as $loc ) {
				foreach ( $loc['hosts'] as $h => $n ) {
					$hosts[ $h ] = ( $hosts[ $h ] ?? 0 ) + $n;
					$total      += $n;
				}
			}
			arsort( $hosts );
			$host_list = array_keys( $hosts );
			$severity  = $what[ $group ][2];
			$redirects = array_keys(
				array_filter(
					$locations,
					function ( $l ) {
						return ! empty( $l['redirect'] );
					}
				)
			);
			if ( $redirects ) {
				$severity = 'serious';
			}
			$labels = array();
			$url    = '';
			foreach ( array_slice( $locations, 0, self::LOCATIONS, true ) as $loc ) {
				$labels[] = $loc['label'] . ( empty( $loc['redirect'] ) ? '' : ' (its confirmation redirects to staging)' ) . ': ' . implode( ', ', array_keys( $loc['hosts'] ) );
				// Link the first location a visitor can open; header and footer layouts are not pages.
				if ( ! $url && $loc['post_id'] && 'publish' === get_post_status( $loc['post_id'] ) && ( ! function_exists( 'is_post_type_viewable' ) || is_post_type_viewable( (string) get_post_type( $loc['post_id'] ) ) ) && in_array( get_post_type( $loc['post_id'] ), array_merge( array( 'page', 'post' ), (array) apply_filters( 'omens_audit/staging_link_page_types', array() ) ), true ) ) {
					$url = (string) get_permalink( $loc['post_id'] );
				}
			}
			$more = count( $locations ) > self::LOCATIONS ? sprintf( ' and %d more', count( $locations ) - self::LOCATIONS ) : '';

			$places = omens_audit_trim(
				implode(
					'; ',
					array_slice(
						array_map(
							function ( $l ) {
								return $l['label'];
							},
							$locations
						),
						0,
						8
					)
				) . $more,
				400
			);
			if ( 'theme' === $group ) {
				$steps = array(
					sprintf( 'In %s, replace %s with home_url() or a relative path.', $places, implode( ', ', $host_list ) ),
					'Make the change in the theme repo or child theme, not on the server, so the next deploy does not bring it back.',
				);
			} else {
				$cmds = array();
				foreach ( $host_list as $h ) {
					$cmds[] = sprintf( "wp search-replace '%s' '%s' --all-tables-with-prefix --skip-columns=guid --dry-run --report-changed-only", $h, $live );
				}
				$steps = array(
					'Found in: ' . $places . '.',
					sprintf( 'Run %s, check the table list (leave migration and licence history alone), then run it again without --dry-run.', implode( ' then ', $cmds ) ),
					'forms' === $group ? 'Purge the edge cache, then submit each form once to confirm.' : 'Purge the edge cache (and Elementor\'s CSS and element cache if the site uses Elementor), then load those pages to confirm.',
				);
				if ( 'forms' === $group && $redirects ) {
					$ids = array_map(
						function ( $k ) {
							return substr( $k, 3 );
						},
						$redirects
					);
					array_unshift( $steps, sprintf( 'Fix first: Gravity Forms form%s %s redirect%s visitors to staging after they submit.', 1 === count( $ids ) ? '' : 's', 1 === count( $ids ) ? $ids[0] : implode( ', ', array_slice( $ids, 0, -1 ) ) . ' and ' . end( $ids ), 1 === count( $ids ) ? 's' : '' ) );
				}
			}

			$findings[] = $this->finding(
				array(
					'subject'  => $group,
					'severity' => $severity,
					'title'    => sprintf( '%d link%s to %s in %s', $total, 1 === $total ? '' : 's', omens_audit_trim( implode( ', ', $host_list ), 90 ), $what[ $group ][0] ),
					'summary'  => sprintf( 'Production still points at %s in %d place%s.', implode( ', ', $host_list ), count( $locations ), 1 === count( $locations ) ? '' : 's' ),
					'detail'   => omens_audit_trim( implode( ' | ', $labels ) . $more, 1200 ),
					'url'      => $url,
					'fix'      => array(
						'route'      => $what[ $group ][1],
						'where'      => omens_audit_trim( implode( '; ', array_slice( $labels, 0, 6 ) ) . $more, 400 ),
						'steps'      => $steps,
						'confidence' => 'high',
					),
					'evidence' => array_merge(
						array(
							'hosts: ' . implode(
								', ',
								array_map(
									function ( $h, $n ) {
										return $h . ' x' . $n; },
									array_keys( $hosts ),
									$hosts
								)
							),
						),
						array_slice( $labels, 0, self::LOCATIONS )
					),
				)
			);
		}
		return $findings;
	}
}
