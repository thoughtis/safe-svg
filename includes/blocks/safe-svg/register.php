<?php
/**
 * SafeSvg Block setup
 *
 * @package SafeSvg\Blocks\SafeSvgBlock
 */

namespace SafeSvg\Blocks\SafeSvgBlock;

use WP_HTML_Tag_Processor;

/**
 * Register the block
 */
function register() {
	$n = function( $function ) {
		return __NAMESPACE__ . "\\$function";
	};
	// Register the block.
	\register_block_type_from_metadata(
		SAFE_SVG_PLUGIN_DIR . '/includes/blocks/safe-svg',
		[
			'render_callback' => $n( 'render_block_callback' ),
		]
	);
}

/**
 * Render callback method for the block.
 *
 * @param array $attributes The blocks attributes
 *
 * @return string|\WP_Post[] The rendered block markup.
 */
function render_block_callback( $attributes ) {
	// If image is not an SVG return empty string.
	if ( 'image/svg+xml' !== get_post_mime_type( $attributes['imageID'] ) ) {
		return '';
	}

	// If we couldn't get the contents of the file, empty string again.
	if ( ! $contents = file_get_contents( get_attached_file( $attributes['imageID'] ) ) ) { // phpcs:ignore
		return '';
	}

	$contents = new WP_HTML_Tag_Processor( $contents );
		
	if ( $contents->next_tag( 'svg' ) ) {
		$contents->set_attribute( 'width', isset( $attributes['dimensionWidth'] ) ? esc_attr( $attributes['dimensionWidth'] . 'px' ) : 'auto' );
		$contents->set_attribute( 'height', isset( $attributes['dimensionHeight'] ) ? esc_attr( $attributes['dimensionHeight'] . 'px' ) : 'auto' );
		$contents->get_updated_html();
	}

	/**
	 * The wrapper class name.
	 *
	 * Allows a user to adjust the inline svg wrapper class name.
	 *
	 * @param string The class name.
	 *
	 * @since 2.1.0
	 */
	$class_name = apply_filters( 'safe_svg_inline_class', 'safe-svg-inline' );

	if ( isset( $attributes['className'] ) ) {
		$class_name = $class_name . ' ' . $attributes['className'];
	}
	
	$inside_style = array();

	if ( isset( $attributes['style']['spacing']['padding'] ) ) {
		$inside_style = array_merge(
			$inside_style,
			add_css_property_prefix( $attributes['style']['spacing']['padding'], 'padding' )
		);
	}
	
	if ( isset( $attributes['style']['spacing']['margin'] ) ) {
		$inside_style = array_merge (
			$inside_style,
			add_css_property_prefix( $attributes['style']['spacing']['margin'], 'margin' )
		);
	}

	$inside_style = array_map(
		fn( $value ) => convert_to_css_variable( $value ),
		$inside_style
	);

	$inside_style = array_merge(
		$inside_style,
		array(
			'background-color' => ( isset( $attributes['backgroundColor'] ) ? esc_attr( 'var(--wp--preset--color--' . $attributes['backgroundColor'] . ')' ) : '' ) ?: ( isset( $attributes['style']['color']['background'] ) ? esc_attr( $attributes['style']['color']['background'] ) : '' ),
			'color'            => ( isset( $attributes['textColor'] ) ? esc_attr( 'var(--wp--preset--color--' . $attributes['textColor'] . ')' ) : '' ) ?: ( isset( $attributes['style']['color']['text'] ) ? esc_attr( $attributes['style']['color']['text'] ) : '' ),
		)
	);

	/**
	 * The inside style.
	 * 
	 * Allows a user to adjust the inline svg inside style attribute.
	 * 
	 * @param string The style attribute.
	 * 
	 * @since 2.5.6
	 */	
	$inside_style = apply_filters( 'safe_svg_inside_inline_style', render_inline_css( $inside_style ) );

	/**
	 * The wrapper markup.
	 *
	 * Allows a user to adjust the inline svg wrapper markup.
	 *
	 * @param string                The current wrapper markup.
	 * @param string $contents      The SVG contents.
	 * @param string $class_name    The wrapper class name.
	 * @param int    $attachment_id The ID of the attachment.
	 *
	 * @since 2.1.0
	 */
	return apply_filters(
		'safe_svg_inline_markup',
		sprintf(
			'<div class="wp-block-safe-svg-svg-icon safe-svg-cover%s">
				<div class="safe-svg-inside%s"%s>%s</div>
			</div>',
			isset( $attributes['align'] ) ? ' align' . $attributes['align'] : '',
			empty( $class_name ) ? '' : ' ' . esc_attr( $class_name ),
			empty( $inside_style ) ? '' : ' style="' . esc_attr( $inside_style ) . '"',
			$contents
		),
		$contents,
		$class_name,
		$attributes['imageID']
	);
}

/**
 * Convert to CSS variable.
 * 
 * Converts a given value to a CSS variable if it starts with 'var:'.
 *
 * @param string $value The value to be converted.
 * @return string The converted value or the original value if it doesn't start with 'var:'.
 */
function convert_to_css_variable( $value ) {
	if ( strpos( $value, 'var:' ) === 0 ) {
		$parts = explode( '|', $value );
		if ( count( $parts ) === 3 ) {
			return 'var(--wp--preset--' . $parts[1] . '--' . $parts[2] . ')';
		}
	}
	return $value;
}

/**
 * Add CSS property prefix.
 * 
 * Adds a prefix to an array of CSS properties.
 *
 * @param array $properties The properties to be prefixed.
 * @param string $prefix The prefix to prepend to properties.
 * @return array The converted properties.
 */
function add_css_property_prefix( array $properties, string $prefix ): array {
	if ( empty( $properties ) ) {
		return array();
	}
	
	return array_combine(
		array_map( 
			fn( $property ) => "$prefix-$property", 
			array_keys( $properties )
		),
		$properties
	);
}

/**
 * Render inside styles.
 * 
 * Collects 
 *
 * @param array $styles An associative array of CSS properties and their values.
 * @return string A string containing inline CSS styles.
 */
function render_inline_css( array $styles ): string {
	$style_strings = array_map(
		__NAMESPACE__ . "\\render_css_property_string",
		array_keys( $styles ),
		array_values( $styles )
	);

	$validated_style_strings = array_values(
		array_filter(
			$style_strings,
			function( $value ) {
				return is_string( $value ) && '' !== $value;
			}
		)
	);

	if ( '' === $validated_style_strings ) {
		return '';
	}

	return implode( ' ', $validated_style_strings );
}

/**
 * Render CSS property string.
 * 
 * Converts a property name and value pair into a string to use as CSS.
 *
 * @param string $property Style property.
 * @param string $value Style value.
 * @return string Property and value CSS string.
 */
function render_css_property_string( string $property, string $value ): string {
	if ( empty( $value ) ) {
		return '';
	}

	return sprintf( '%s: %s;', $property, $value );
}
