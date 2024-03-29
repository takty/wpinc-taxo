<?php
/**
 * Utilities
 *
 * @package Wpinc Taxo
 * @author Takuto Yanagida
 * @version 2024-03-14
 */

declare(strict_types=1);

namespace wpinc\taxo;

require_once __DIR__ . '/customize.php';

/**
 * Creates a taxonomy object.
 *
 * @psalm-suppress ArgumentTypeCoercion
 *
 * @param array<string, mixed> $args      Arguments.
 * @param string               $post_type Post type.
 * @param string               $suffix    Suffix of taxonomy slug and rewrite slug.
 * @param string|null          $slug      (Optional) Prefix of rewrite slug. Default is $post_type.
 */
function register_post_type_specific_taxonomy( array $args, string $post_type, string $suffix = 'category', ?string $slug = null ): void {
	$slug  = $slug ?? $post_type;
	$args += array(
		'show_admin_column' => true,
		'show_in_rest'      => true,
		'rewrite'           => array(),
	);

	$args['rewrite'] += array(
		'with_front' => false,
		'slug'       => "$slug/$suffix",
	);
	/** @psalm-suppress InvalidArgument */  // phpcs:ignore
	register_taxonomy( "{$post_type}_$suffix", $post_type, $args );  // @phpstan-ignore-line
	set_taxonomy_post_type_specific( array( "{$post_type}_$suffix" ), $post_type );
}


// -----------------------------------------------------------------------------


/**
 * Retrieves root term of term hierarchy.
 *
 * @param \WP_Term $term    Term.
 * @param int      $count   (Optional) Size of retrieved array. Default 1.
 * @param int      $root_id (Optional) Term ID regarded as the root. Default 0.
 * @return \WP_Term[]|null Array of terms. The first element is the root.
 */
function get_term_root( \WP_Term $term, int $count = 1, int $root_id = 0 ): ?array {
	$as = get_ancestors( $term->term_id, $term->taxonomy );

	$end = count( $as );
	foreach ( $as as $idx => $a ) {
		if ( $root_id === $a ) {
			$end = $idx;
			break;
		}
	}
	$as     = array_reverse( array_slice( $as, 0, $end ) );  // The first is root.
	$as_sub = array_slice( $as, 0, $count );

	$ts = array();
	foreach ( $as_sub as $a ) {
		$t = get_term( $a, $term->taxonomy );
		if ( $t instanceof \WP_Term ) {
			$ts[] = $t;
		} else {
			return null;
		}
	}
	return $ts;
}
