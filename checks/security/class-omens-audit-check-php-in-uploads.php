<?php
/**
 * security.php-in-uploads: PHP files under wp-content/uploads, minus an allowlist.
 *
 * Never deletes anything. One finding per file so each fingerprints on its
 * own path and disappears when the file does; capped so a compromised site
 * with hundreds of droppers produces a readable run.
 *
 * @package OmensAudit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Omens_Audit_Check_Php_In_Uploads extends Omens_Audit_Check_Base {

	const CAP = 50;

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
	 * Allowlist: relative paths (or fnmatch patterns) that are known good.
	 *
	 * @return string[]
	 */
	public static function allowlist() {
		/**
		 * PHP files under uploads that are expected. Relative to the uploads
		 * folder, fnmatch() patterns allowed. Seeded in M2 from the pilots.
		 *
		 * @param string[] $allow Patterns.
		 */
		return (array) apply_filters(
			'omens_audit/uploads_allow',
			array(
				'index.php',
				'*/index.php',
				// Plugins that keep PHP in their own uploads subfolder, seen on the pilots (M2 seeding).
				'wpallexport/functions.php', // WP All Export's custom-functions file, 0 bytes until used.
				'wpallimport/functions.php', // WP All Import's equivalent.
				'wp-migrate-db/*',
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
			$rel = ltrim( substr( $path, strlen( $base ) ), '/' );
			if ( $this->allowed( $rel, $path, $allow ) ) {
				continue;
			}
			++$hits;
			if ( $hits > self::CAP ) {
				continue;
			}
			$findings[] = $this->finding(
				array(
					'subject'  => $rel,
					'title'    => 'PHP file in uploads: ' . $rel,
					'summary'  => sprintf( '%s is a %d-byte PHP file inside wp-content/uploads, where no PHP belongs.', $rel, (int) $file->getSize() ),
					'detail'   => $this->head( $path ),
					'fix'      => array(
						'route'      => 'human',
						'where'      => 'wp-content/uploads/' . $rel,
						'steps'      => array(
							'Treat as a possible web shell until proven otherwise: open the file read-only and check what it does.',
							'If it is malware, follow the malware response: isolate, remove, rotate credentials, check the admin list and mu-plugins against the baseline.',
							'If it is a plugin\'s own file, add it to the omens_audit/uploads_allow filter with the plugin named, so the next run stays quiet.',
						),
						'confidence' => 'high',
					),
					'evidence' => array( 'size ' . (int) $file->getSize() . ' bytes, modified ' . gmdate( 'c', (int) $file->getMTime() ) ),
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
	 * Whether a file is allowlisted: an index.php that is empty or "Silence is
	 * golden", or a path matching a filter pattern.
	 *
	 * @param string   $rel   Path relative to uploads.
	 * @param string   $path  Absolute path.
	 * @param string[] $allow Patterns.
	 * @return bool
	 */
	private function allowed( $rel, $path, array $allow ) {
		if ( 'index.php' === basename( $rel ) ) {
			$size = (int) filesize( $path );
			if ( 0 === $size ) {
				return true;
			}
			if ( $size < 200 ) {
				$body = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file, not a URL.
				if ( preg_match( '#^\s*<\?php\s*(//|/\*|\#)?\s*silence is golden\.?\s*(\*/)?\s*(\?>)?\s*$#i', $body ) ) {
					return true;
				}
			}
			return false;
		}
		foreach ( $allow as $pattern ) {
			if ( 'index.php' === $pattern || '*/index.php' === $pattern ) {
				continue; // Handled above with a content check, not a blanket pass.
			}
			if ( fnmatch( $pattern, $rel, FNM_PATHNAME ) || fnmatch( $pattern, $rel ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The first 300 bytes of a file, for the finding detail.
	 *
	 * @param string $path File.
	 * @return string
	 */
	private function head( $path ) {
		$fh = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $fh ) {
			return '';
		}
		$head = (string) fread( $fh, 300 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return preg_replace( '/[^\P{C}\n\t]/u', '?', $head );
	}
}
