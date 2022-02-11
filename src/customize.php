<?php
/**
 * Custom Taxonomy
 *
 * @package Wpinc Taxo
 * @author Takuto Yanagida
 * @version 2022-02-11
 */

namespace wpinc\taxo;

/**
 * Sets taxonomies to be specific to the post type.
 *
 * @param string|string[] $taxonomy_s A taxonomy slug or array of taxonomy slugs.
 * @param string          $post_type  Post type.
 */
function set_taxonomy_post_type_specific( $taxonomy_s, string $post_type ): void {
	$txs = is_array( $taxonomy_s ) ? $taxonomy_s : array( $taxonomy_s );
	add_action(
		'pre_get_posts',
		function ( $query ) use ( $txs, $post_type ) {
			if ( is_admin() || ! $query->is_main_query() ) {
				return;
			}
			foreach ( $txs as $tx ) {
				if ( $query->is_tax( $tx ) ) {
					$query->set( 'post_type', $post_type );
					break;
				}
			}
		},
		9
	);
}

/**
 * Sets a default term to a taxonomy.
 *
 * @param string               $taxonomy          A taxonomy slug.
 * @param string               $default_term_slug Default term slug.
 * @param string|string[]|null $post_type_s       (Optional) A post type or array of post types.
 */
function set_taxonomy_default_term( string $taxonomy, string $default_term_slug, $post_type_s = null ): void {
	if ( $post_type_s ) {
		$pts = is_array( $post_type_s ) ? $post_type_s : array( $post_type_s );
		foreach ( $pts as $post_type ) {
			add_action(
				"save_post_$post_type",
				function ( int $post_id ) use ( $taxonomy, $default_term_slug ) {
					_cb_save_post__set_taxonomy_default_term( $post_id, $taxonomy, $default_term_slug );
				},
				10,
			);
		}
	} else {
		add_action(
			'save_post',
			function ( int $post_id ) use ( $taxonomy, $default_term_slug ) {
				_cb_save_post__set_taxonomy_default_term( $post_id, $taxonomy, $default_term_slug );
			},
			10,
		);
	}
}

/**
 * Callback function for 'save_post' action.
 *
 * @access private
 *
 * @param int    $post_id           Post ID.
 * @param string $taxonomy          Taxonomy slug.
 * @param string $default_term_slug Default term slug.
 */
function _cb_save_post__set_taxonomy_default_term( int $post_id, string $taxonomy, string $default_term_slug ): void {
	$ts = wp_get_object_terms( $post_id, $taxonomy );
	if ( ! is_wp_error( $ts ) && empty( $ts ) ) {
		wp_set_object_terms( $post_id, $default_term_slug, $taxonomy );
	}
}

/**
 * Sets taxonomies to be exclusive.
 *
 * @param string|string[] $taxonomy_s A taxonomy slug or array of taxonomy slugs.
 */
function set_taxonomy_exclusive( $taxonomy_s ): void {
	$txs = is_array( $taxonomy_s ) ? $taxonomy_s : array( $taxonomy_s );
	add_action(
		'admin_print_footer_scripts',
		function () use ( $txs ) {
			_cb_admin_print_footer_scripts__set_taxonomy_exclusive( $txs );
		}
	);
	add_action(
		'set_object_terms',
		function ( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) use ( $txs ) {
			_cb_set_object_terms__set_taxonomy_exclusive( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids, $txs );
		},
		10,
		6
	);
}

/**
 * Callback function for 'admin_print_footer_scripts' action.
 *
 * @param array $txs Taxonomies.
 */
function _cb_admin_print_footer_scripts__set_taxonomy_exclusive( array $txs ): void {
	?>
	<script type="text/javascript">
	jQuery(function ($) {
		const txs = ['<?php echo implode( "','", $txs );  // phpcs:ignore ?>'];

		// For edit screen
		for (const tx of txs) {
			$(`#taxonomy-${tx} input[type=checkbox]`).each(function () {$(this).attr('type', 'radio');});
		}

		// For quick edit
		for (const tx of txs) {
			const cl = $(`.${tx}-checklist input[type=checkbox]`);
			cl.each(function () { $(this).prop('type', 'radio'); });
		}
		$('#the-list').on('click', 'button.editinline', function () {
			const post_id = inlineEditPost.getId(this);
			const rowData = $('#inline_'+ post_id);
			$('.post_category', rowData).each(function () {
				const tx = $(this).attr('id').replace('_' + post_id, '');
				if (txs.includes(tx)) {
					let term_ids = $(this).text();
					term_ids = term_ids.trim() !== '' ? term_ids.trim() : '0';
					let term_id = term_ids.split(',');
					term_id = term_id ? term_id[0] : '0';
					if (term_id === '0') {
						$(`.${tx}-checklist li input:radio`).prop('checked', false);
					} else {
						$(`li#${tx}-${term_id}`).find('input:radio').first().prop('checked', true);
					}
				}
			});
		});

		// For bulk edit
		$('#doaction, #doaction2').on('click', function(e) {
			const n = $(this).attr('id').substr(2);
			if ('edit' === $(`select[name="${n}"]`).val()) {
				e.preventDefault();
				$('.cat-checklist').each(function () {
					if ($(this).find('input[type="radio"]').length) {
						$(this).find('input[type="radio"]').prop('checked', false);
						$(this).prev('input').remove();
					}
				});
			}
		});
	});
	</script>
	<?php
}

/**
 * Callback function for 'set_object_terms' action.
 *
 * @param int    $object_id  Object ID.
 * @param array  $terms      An array of object term IDs or slugs.
 * @param array  $tt_ids     An array of term taxonomy IDs.
 * @param string $taxonomy   Taxonomy slug.
 * @param bool   $append     Whether to append new terms to the old terms.
 * @param array  $old_tt_ids Old array of term taxonomy IDs.
 * @param array  $txs        Taxonomies.
 */
function _cb_set_object_terms__set_taxonomy_exclusive( int $object_id, array $terms, array $tt_ids, string $taxonomy, bool $append, array $old_tt_ids, array $txs ): void {
	if ( in_array( $taxonomy, $txs, true ) ) {
		$old_tt_ids = array_map( 'intval', $old_tt_ids );
		$tt_ids     = array_map( 'intval', $tt_ids );

		$ai = array_values( array_intersect( $old_tt_ids, $tt_ids ) );
		if ( count( $ai ) === count( $tt_ids ) ) {
			return;  // No change happens.
		}
		$ad = array_values( array_diff( $tt_ids, $ai ) );
		if ( 1 < count( $ad ) ) {
			$ad = _sort_term_taxonomy_ids( $ad, $taxonomy );
			array_shift( $ad );
			$ai = array_merge( $ai, $ad );
		}
		if ( ! empty( $ai ) ) {
			wp_remove_object_terms( $object_id, array_values( $ai ), $taxonomy );
		}
	}
}

/**
 * Sorts term taxonomy ids when ordered term is activated.
 *
 * @param int[]  $tt_ids   Array of term_taxonomy_ids.
 * @param string $taxonomy Taxonomy slug.
 * @return array Sorted term_taxonomy_ids.
 */
function _sort_term_taxonomy_ids( array $tt_ids, string $taxonomy ): array {
	if ( function_exists( '\wpinc\taxo\ordered_term\sort_terms' ) ) {
		$ts = array_map(
			function ( $tt_id ) {
				return get_term_by( 'term_taxonomy_id', $tt_id );
			},
			$tt_ids
		);
		$ts = \wpinc\taxo\ordered_term\sort_terms( $ts, $taxonomy );

		$tt_ids = array_column( $ts, 'term_taxonomy_id' );
	}
	return $tt_ids;
}
