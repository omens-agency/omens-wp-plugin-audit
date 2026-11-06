<?php
/**
 * seo.description-missing: published, indexable entries with no stored meta
 * description. One finding per post type listing the entries, so the
 * fingerprint is stable while the list changes.
 *
 * @package OmensAudit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Omens_Audit_Check_Description_Missing extends Omens_Audit_Check_Base {

	const LIST = 25;

	public function id() {
		return 'seo.description-missing';
	}

	public function family() {
		return 'seo';
	}

	public function title() {
		return 'Entries without a meta description';
	}

	public function severity() {
		return 'moderate';
	}

	public function run( Omens_Audit_Context $ctx ) {
		$seo = omens_audit_seo_plugin();
		if ( '' === $seo['description_key'] ) {
			return array( $this->cannot_run( 'none' === $seo['slug'] ? 'no SEO plugin is active, so there is nowhere a description would be stored' : $seo['name'] . ' keeps descriptions in its own tables; not read in version 1' ) );
		}
		$types         = get_post_types(
			array(
				'public'             => true,
				'publicly_queryable' => true,
			),
			'names'
		);
		$types['page'] = 'page';
		unset( $types['attachment'] );
		/**
		 * Post types to skip for the description check.
		 *
		 * @param string[] $skip Post type names.
		 */
		$skip     = (array) apply_filters( 'omens_audit/description_skip_types', array() );
		$findings = array();
		foreach ( $types as $type ) {
			if ( in_array( $type, $skip, true ) ) {
				continue;
			}
			$ids     = get_posts(
				array(
					'post_type'        => $type,
					'post_status'      => 'publish',
					'posts_per_page'   => 2000, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- IDs only; a bound, not a page.
					'fields'           => 'ids',
					'orderby'          => 'modified',
					'order'            => 'DESC',
					'suppress_filters' => false,
					'no_found_rows'    => true,
				)
			);
			$missing = array();
			foreach ( $ids as $id ) {
				if ( is_callable( $seo['noindex'] ) && call_user_func( $seo['noindex'], $id ) ) {
					continue;
				}
				if ( '' !== trim( (string) get_post_meta( $id, $seo['description_key'], true ) ) ) {
					continue;
				}
				$missing[] = (int) $id;
			}
			if ( ! $missing ) {
				continue;
			}
			$steps = array( sprintf( 'Write a 120 to 160 character description for each entry in %s (%s):', $seo['name'], $type ) );
			foreach ( array_slice( $missing, 0, self::LIST ) as $id ) {
				$steps[] = sprintf( '%s: %s', get_the_title( $id ), omens_audit_edit_link( $id ) );
			}
			if ( count( $missing ) > self::LIST ) {
				$steps[] = sprintf( '… and %d more.', count( $missing ) - self::LIST );
			}
			$findings[] = $this->finding(
				array(
					'subject'  => $type,
					'title'    => sprintf( '%d %s entr%s without a meta description', count( $missing ), $type, 1 === count( $missing ) ? 'y' : 'ies' ), // phpcs:ignore WordPress.PHP.YodaConditions.NotYoda -- it is Yoda; the sniff misreads the ternary.
					'summary'  => sprintf( '%d published, indexable %s entr%s have no stored description in %s; search engines will write their own snippet.', count( $missing ), $type, 1 === count( $missing ) ? 'y' : 'ies', $seo['name'] ), // phpcs:ignore WordPress.PHP.YodaConditions.NotYoda
					'fix'      => array(
						'route'      => 'content',
						'where'      => sprintf( 'the %s description field on each entry', $seo['name'] ),
						'steps'      => $steps,
						'confidence' => 'high',
					),
					'evidence' => array( sprintf( 'meta key %s empty on %d of %d published %s', $seo['description_key'], count( $missing ), count( $ids ), $type ) ),
				)
			);
		}
		return $findings;
	}
}
