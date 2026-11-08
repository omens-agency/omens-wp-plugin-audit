<?php
/**
 * security.php-in-uploads: PHP files under wp-content/uploads, minus an allowlist.
 *
 * Never deletes anything. One finding per file so each fingerprints on its
 * own path and disappears when the file does; capped so a compromised site
 * with hundreds of droppers produces a readable run.
 *
 * Three tiers since 1.0.3, learned from the first fleet run (36 criticals on
 * 10 sites, of which 28 were files plugins put there themselves, 6 were
 * zero-byte remnants of a cleaned infection, and 2 were a live backdoor that
 * read exactly like the guard files around it):
 *
 *   - allowlisted: guard files (index.php that only exits, comments or sends
 *     a header), and plugin files recognised by path plus a content signature;
 *   - inert: a file with no PHP in it at all (zero bytes, or text without an
 *     open tag), moderate, still to be deleted;
 *   - suspected web shell: a file whose code reads request input, decodes or
 *     evaluates strings, or runs commands, critical with the incident steps
 *     first;
 *   - anything else with PHP in it: critical, as before.
 *
 * @package OmensAudit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Omens_Audit_Check_Php_In_Uploads extends Omens_Audit_Check_Base {

	const CAP = 50;

	/** Bytes of a file read for the shell and signature checks. */
	const READ = 65536;

	public function id() {
		return 'security.php-in-uploads';
	}

	public function family() {
		return 'security';
	}

	public function title() {
		return 'PHP files in the uploads folder';
	}

	public function severity() {
		return 'critical';
	}

	public function applies( Omens_Audit_Context $ctx ) {
		$dir = wp_upload_dir();
		if ( ! empty( $dir['error'] ) || empty( $dir['basedir'] ) || ! is_dir( $dir['basedir'] ) ) {
			return 'uploads folder not found';
		}
		return true;
	}

	/**
	 * Allowlist: known-good files under uploads.
	 *
	 * An entry is a relative path or fnmatch() pattern, or an array with
	 * `pattern` plus `contains` (a string the file must hold within its first
	 * 64 KB) and/or `starts` (what the file must begin with). The content
	 * conditions are what stop a dropper named like a plugin file from riding
	 * the allowlist. Guard-only index.php files need no entry: see guard().
	 *
	 * @return array<int, string|array{pattern:string, contains?:string, starts?:string}>
	 */
	public static function allowlist() {
		/**
		 * PHP files under uploads that are expected. Relative to the uploads
		 * folder, fnmatch() patterns allowed, optionally with a content
		 * signature. Seeded from the pilots (M2) and the first fleet run (1.0.3).
		 *
		 * @param array $allow Entries.
		 */
		return (array) apply_filters(
			'omens_audit/uploads_allow',
			array(
				'index.php',
				'*/index.php',
				// WP All Export / Import keep a custom-functions file here, 0 bytes until used.
				'wpallexport/functions.php',
				'wpallimport/functions.php',
				'wp-migrate-db/*',
				// Redux Framework writes its Customizer control classes to uploads/redux/ at runtime (ten-line class stubs).
				array( 'pattern' => 'redux/*.php', 'contains' => 'Redux_Customizer_Control' ),
				// Ajax Load More keeps the site's own repeater templates in uploads (by design, authored in wp-admin).
				'alm_templates/*.php',
				// Calendarize It! ships its contextual help as a mini plugin under uploads.
				'calendarize-it/calendarize-it-help/*',
				// Ultimate Addons for Elementor icon-font maps.
				array( 'pattern' => 'smile_fonts/*/charmap.php', 'contains' => '$icons' ),
				// Mailchimp for WordPress log, guarded by an exit on line one.
				array( 'pattern' => 'mailchimp-for-wp/debug-log.php', 'starts' => '<?php exit;' ),
			)
		);
	}

	public function run( Omens_Audit_Context $ctx ) {
		$base     = untrailingslashit( wp_upload_dir()['basedir'] );
		$allow    = self::allowlist();
		$findings = array();
		$hits     = 0;
		$scanned  = 0;

		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS ),
			RecursiveIteratorIterator::LEAVES_ONLY,
			RecursiveIteratorIterator::CATCH_GET_CHILD
		);
		foreach ( $it as $file ) {
			++$scanned;
			$path = $file->getPathname();
			if ( ! preg_match( '/\.(php\d?|phtml|phar)$/i', $path ) ) {
				continue;
			}
			$rel  = ltrim( substr( $path, strlen( $base ) ), '/' );
			$size = (int) $file->getSize();
			$body = $this->read( $path );
			if ( $this->allowed( $rel, $body, $size, $allow ) ) {
				continue;
			}
			++$hits;
			if ( $hits > self::CAP ) {
				continue;
			}
			$tier     = $this->tier( $body, $size );
			$when     = gmdate( 'c', (int) $file->getMTime() );
			$evidence = array( 'size ' . $size . ' bytes, modified ' . $when );
			if ( 'inert' === $tier ) {
				$findings[] = $this->finding(
					array(
						'subject'  => $rel,
						'severity' => 'moderate',
						'title'    => 'Inert PHP file in uploads: ' . $rel,
						'summary'  => sprintf( '%s is a %d-byte .php file under wp-content/uploads with no PHP code in it (%s). It cannot run, but a randomly named empty .php file is usually what a malware cleaner left behind, and it should go.', $rel, $size, 0 === $size ? 'zero bytes' : 'no opening tag' ),
						'detail'   => $this->head( $body ),
						'fix'      => array(
							'route'      => 'human',
							'where'      => 'wp-content/uploads/' . $rel,
							'steps'      => array(
								'Delete the file over SSH or SFTP; nothing references an empty file.',
								'If several turn up with random eight-letter names and the same date, the site was cleaned once before: ask the host for a fresh malware scan.',
							),
							'confidence' => 'high',
						),
						'evidence' => $evidence,
					)
				);
				continue;
			}
			if ( 'shell' === $tier ) {
				$findings[] = $this->finding(
					array(
						'subject'  => $rel,
						'title'    => 'Suspected web shell in uploads: ' . $rel,
						'summary'  => sprintf( '%s is a %d-byte PHP file under wp-content/uploads whose code reads request input, decodes or evaluates strings, or runs commands (%s). Treat the site as compromised until it is reviewed.', $rel, $size, implode( ', ', $this->shell_markers( $body ) ) ),
						'detail'   => $this->head( $body ),
						'fix'      => array(
							'route'      => 'human',
							'where'      => 'wp-content/uploads/' . $rel,
							'steps'      => array(
								'Incident response first: copy the file somewhere outside the web root for the record, delete it from uploads, and check whether the host executes PHP from uploads (a 403 on its URL means it could not be reached from outside).',
								'Then look for what put it there: core and plugin checksums, mu-plugins and the admin list against the baseline, users and cron events created around the file\'s date, other PHP under uploads.',
								'Rotate administrator passwords and ask the host for a malware scan. Record the file\'s hash and date in the site\'s folder.',
							),
							'confidence' => 'high',
						),
						'evidence' => $evidence,
					)
				);
				continue;
			}
			$findings[] = $this->finding(
				array(
					'subject'  => $rel,
					'title'    => 'PHP file in uploads: ' . $rel,
					'summary'  => sprintf( '%s is a %d-byte PHP file inside wp-content/uploads, where no PHP belongs.', $rel, $size ),
					'detail'   => $this->head( $body ),
					'fix'      => array(
						'route'      => 'human',
						'where'      => 'wp-content/uploads/' . $rel,
						'steps'      => array(
							'Treat as a possible web shell until proven otherwise: open the file read-only and check what it does.',
							'If it is malware, follow the malware response: isolate, remove, rotate credentials, check the admin list and mu-plugins against the baseline.',
							'If it is a plugin\'s own file, add it to the omens_audit/uploads_allow filter with the plugin named and a content signature, so the next run stays quiet.',
						),
						'confidence' => 'high',
					),
					'evidence' => $evidence,
				)
			);
		}
		if ( $hits > self::CAP ) {
			$findings[] = $this->finding(
				array(
					'subject'  => 'overflow',
					'title'    => sprintf( '%d PHP files in uploads; only the first %d are listed', $hits, self::CAP ),
					'summary'  => 'More PHP files than the run lists. Treat the site as compromised until a full listing is reviewed.',
					'fix'      => array(
						'route'      => 'human',
						'where'      => 'wp-content/uploads',
						'steps'      => array( "Run: find wp-content/uploads -type f \\( -name '*.php*' -o -name '*.phtml' -o -name '*.phar' \\) over SSH and review every hit." ),
						'confidence' => 'high',
					),
					'evidence' => array( $scanned . ' files scanned' ),
				)
			);
		}
		return $findings;
	}

	/**
	 * Whether a file is allowlisted: a guard-only index.php, or an allowlist
	 * entry whose pattern matches and whose content signature (if any) holds.
	 *
	 * @param string $rel   Path relative to uploads.
	 * @param string $body  First 64 KB of the file.
	 * @param int    $size  File size.
	 * @param array  $allow Entries.
	 * @return bool
	 */
	private function allowed( $rel, $body, $size, array $allow ) {
		if ( 'index.php' === basename( $rel ) ) {
			return $size <= 1024 && $this->guard( $body );
		}
		foreach ( $allow as $entry ) {
			$pattern  = is_array( $entry ) ? (string) ( $entry['pattern'] ?? '' ) : (string) $entry;
			$contains = is_array( $entry ) ? (string) ( $entry['contains'] ?? '' ) : '';
			$starts   = is_array( $entry ) ? (string) ( $entry['starts'] ?? '' ) : '';
			if ( '' === $pattern || 'index.php' === $pattern || '*/index.php' === $pattern ) {
				continue; // index.php is judged by guard(), never by a blanket pass.
			}
			if ( ! fnmatch( $pattern, $rel, FNM_PATHNAME ) && ! fnmatch( $pattern, $rel ) ) {
				continue;
			}
			if ( '' !== $contains && false === strpos( $body, $contains ) ) {
				continue;
			}
			if ( '' !== $starts && 0 !== strpos( ltrim( $body, "\xEF\xBB\xBF \t\r\n" ), $starts ) ) {
				continue;
			}
			if ( $this->shell_markers( $body ) ) {
				continue; // A plugin path with shell code in it is not the plugin's file.
			}
			return true;
		}
		return false;
	}

	/**
	 * Whether a body is a guard: nothing but an open tag, comments, exit/die,
	 * header() calls and plain text (a .php file with no open tag at all prints
	 * as text and runs nothing). Seen on the fleet: "Silence is golden" in
	 * three spellings, LearnDash's one-line comment with no tag, WPForms's two
	 * 404 header() calls.
	 *
	 * @param string $body File body.
	 * @return bool
	 */
	private function guard( $body ) {
		$body = ltrim( $body, "\xEF\xBB\xBF" );
		if ( '' === trim( $body ) ) {
			return true;
		}
		if ( $this->shell_markers( $body ) ) {
			return false;
		}
		if ( false === strpos( $body, '<?' ) ) {
			return true; // No PHP open tag: served as text.
		}
		$code = preg_replace( '#/\*.*?\*/#s', ' ', $body );
		$code = preg_replace( '#(^|[\r\n])\s*(//|\#)[^\r\n]*#', ' ', $code );
		$code = preg_replace( '/\s+/', ' ', trim( (string) $code ) );
		// An open tag, optional header() calls, optional exit/die, an optional close tag, nothing else.
		return (bool) preg_match( '#^<\?(php)?(\s*header\s*\([^;()]*\)\s*;)*(\s*(exit|die)(\s*\(\s*\))?\s*;?)?(\s*\?>)?\s*$#i', $code );
	}

	/**
	 * inert (no PHP in it), shell (request input, decoding, evaluation or
	 * command execution), or php.
	 *
	 * @param string $body File body.
	 * @param int    $size File size.
	 * @return string
	 */
	private function tier( $body, $size ) {
		if ( 0 === $size || false === strpos( $body, '<?' ) ) {
			return 'inert';
		}
		return $this->shell_markers( $body ) ? 'shell' : 'php';
	}

	/**
	 * The web-shell markers present in a body, by name.
	 *
	 * @param string $body File body.
	 * @return string[]
	 */
	private function shell_markers( $body ) {
		// Detection only: these patterns are searched for in the file, never executed here.
		$markers = array(
			'$_COOKIE'        => '/\$_COOKIE\b/',
			'$_POST'          => '/\$_POST\b/',
			'$_GET'           => '/\$_GET\b/',
			'$_REQUEST'       => '/\$_REQUEST\b/',
			'$_FILES'         => '/\$_FILES\b/',
			'eval()'          => '/\beval\s*\(/i',
			'assert()'        => '/\bassert\s*\(/i',
			'create_function' => '/\bcreate_function\s*\(/i',
			'base64_decode'   => '/\bbase64_decode\b/i',
			'gzinflate'       => '/\b(gzinflate|gzuncompress|str_rot13)\s*\(/i',
			'exec'            => '/\b(system|exec|shell_exec|passthru|popen|proc_open)\s*\(/i',
			'variable call'   => '/\$[a-z_][a-z0-9_]*\s*\(\s*\$[a-z_][a-z0-9_]*\s*\[/i',
			'preg_replace /e' => '/preg_replace\s*\([^,]*\/[a-z]*e[a-z]*[\'"]/i',
		);
		$found = array();
		foreach ( $markers as $name => $re ) {
			if ( preg_match( $re, $body ) ) {
				$found[] = $name;
			}
		}
		/**
		 * Names of the shell markers found in a file under uploads.
		 *
		 * @param string[] $found Markers.
		 * @param string   $body  File body (first 64 KB).
		 */
		return (array) apply_filters( 'omens_audit/uploads_shell_markers', $found, $body );
	}

	/**
	 * The first 64 KB of a file.
	 *
	 * @param string $path File.
	 * @return string
	 */
	private function read( $path ) {
		$fh = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $fh ) {
			return '';
		}
		$body = (string) fread( $fh, self::READ ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return $body;
	}

	/**
	 * The first 300 bytes of a body, printable, for the finding detail.
	 *
	 * @param string $body File body.
	 * @return string
	 */
	private function head( $body ) {
		return preg_replace( '/[^\P{C}\n\t]/u', '?', substr( $body, 0, 300 ) );
	}
}
