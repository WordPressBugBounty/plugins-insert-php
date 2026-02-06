<?php
/**
 * Server-side rendering for the Woody Snippets block.
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @var string               $content    Block default content.
 * @var WP_Block             $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get the snippet ID from attributes
$snippet_id = isset( $attributes['id'] ) ? $attributes['id'] : null;

if ( ! is_numeric( $snippet_id ) ) {
	return '';
}

// Get snippets data
$snippets_data = WINP_Gutenberg_Snippet::get_prepared_snippets_data();
$current_snippet = isset( $snippets_data[ $snippet_id ] ) ? $snippets_data[ $snippet_id ] : null;

if ( empty( $current_snippet ) ) {
	return '';
}

$snippet_attrs = $current_snippet['tags'];
$type = $current_snippet['type'];

// Build shortcode attributes
$shortcode_attributes = apply_filters( 'wbcr/inp/gutenberg/shortcode_attributes', ' id="' . $snippet_id . '" ', $snippet_id );

$attr_values = isset( $attributes['attrValues'] ) ? $attributes['attrValues'] : null;
if ( ! empty( $attr_values ) ) {
	if ( empty( $snippet_attrs ) ) {
		return '';
	}

	if ( count( $snippet_attrs ) !== count( $attr_values ) ) {
		return '';
	}

	foreach ( $attr_values as $key => $value ) {
		$snippet_attr = $snippet_attrs[ $key ];
		$value = esc_attr( $value );

		if ( empty( $value ) ) {
			continue;
		}
		$shortcode_attributes .= " {$snippet_attr}=\"{$value}\"";
	}

	$shortcode_attributes = trim( $shortcode_attributes );
}

// Build shortcode name
$shortcode_name = apply_filters(
	'wbcr/inp/gutenberg/shortcode_name',
	sprintf( 'wbcr%s_snippet', ( $type === WINP_SNIPPET_TYPE_UNIVERSAL ? '' : '_' . $type ) ),
	$snippet_id
);

// Build the shortcode
$shortcode = "[{$shortcode_name} {$shortcode_attributes}]";

if ( ! empty( $content ) ) {
	$shortcode .= "{$content}[/{$shortcode_name}]";
}

// Render the shortcode
echo do_shortcode( $shortcode );
