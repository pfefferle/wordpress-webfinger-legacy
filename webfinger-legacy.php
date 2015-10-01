<?php
/**
 * Plugin Name: WebFinger Legacy
 * Plugin URI: https://github.com/pfefferle/wordpress-webfinger-legacy
 * Description: WebFinger Legacy plugin for WordPress
 * Version: 1.0.0
 * Author: pfefferle
 * Author URI: http://notizblog.org/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// initialize plugin
add_action( 'init', array( 'WebfingerLegacyPlugin', 'init' ) );

/**
 * webfinger-legacy
 *
 * @author Matthias Pfefferle
 */
class WebfingerLegacyPlugin {

	/**
	 * Initialize the plugin, registering WordPress hooks.
	 */
	public static function init() {
		add_action( 'query_vars', array( 'WebfingerLegacyPlugin', 'query_vars' ) );
		add_filter( 'host_meta', array( 'WebfingerLegacyPlugin', 'host_meta_discovery' ) );

		// XRD output
		add_action( 'webfinger_render', array( 'WebfingerLegacyPlugin', 'render_xrd' ), 5 );
	}

	/**
	 * add query vars
	 *
	 * @param array $vars
	 *
	 * @return array
	 */
	public static function query_vars( $vars ) {
		$vars[] = 'format';
		$vars[] = 'resource';
		$vars[] = 'rel';

		return $vars;
	}

	/**
	 * render the XRD representation of the WordPress resource.
	 *
	 * @param array $webfinger the WordPress data-array
	 */
	public static function render_xrd( $webfinger ) {
		global $wp;

		// interpret accept header
		if ( $pos = stripos( $_SERVER['HTTP_ACCEPT'], ';' ) ) {
			$accept_header = substr( $_SERVER['HTTP_ACCEPT'], 0, $pos );
		} else {
			$accept_header = $_SERVER['HTTP_ACCEPT'];
		}

		// accept header as an array
		$accept = explode( ',', trim( $accept_header ) );

		$format = null;
		if ( array_key_exists( 'format', $wp->query_vars ) ) {
			$format = $wp->query_vars['format'];
		}

		if ( ! in_array( 'application/xrd+xml', $accept ) &&
				! in_array( 'application/xml+xrd', $accept ) &&
				'xrd' != $format ) {
			return $webfinger;
		}

		header( 'Content-Type: application/xrd+xml; charset=' . get_bloginfo( 'charset' ), true );

		echo '<?xml version="1.0" encoding="' . get_bloginfo( 'charset' ) . '"?>' . PHP_EOL;
		echo '<XRD xmlns="http://docs.oasis-open.org/ns/xri/xrd-1.0"';
			// add xml-only namespaces
			do_action( 'webfinger_ns' );
		echo '>' . PHP_EOL;

		echo self::jrd_to_xrd( $webfinger );
		// add xml-only content
		do_action( 'webfinger_xrd' );

		echo PHP_EOL . '</XRD>';

		exit;
	}

	/**
	 * add the host meta information
	 */
	public static function host_meta_discovery($array) {
		$array['links'][] = array( 'rel' => 'lrdd', 'template' => site_url( '/?well-known=webfinger&resource={uri}&format=xrd' ), 'type' => 'application/xrd+xml' );

		return $array;
	}

	/**
	 * recursive helper to generade the xrd-xml from the jrd array
	 *
	 * @param string $host_meta
	 *
	 * @return string
	 */
	public static function jrd_to_xrd( $webfinger ) {
		$xrd = null;

		foreach ( $webfinger as $type => $content ) {
			// print subject
			if ( 'subject' == $type ) {
				$xrd .= '<Subject>' . $content . '</Subject>';
				continue;
			}

			// print aliases
			if ( 'aliases' == $type ) {
				foreach ( $content as $uri ) {
					$xrd .= '<Alias>' . htmlentities( $uri ) . '</Alias>';
				}
				continue;
			}

			// print properties
			if ( 'properties' == $type ) {
				foreach ( $content as $type => $uri ) {
					$xrd .= '<Property type="' . htmlentities( $type ) . '">' . htmlentities( $uri ) . '</Property>';
				}
				continue;
			}

			// print titles
			if ( 'titles' == $type ) {
				foreach ( $content as $key => $value ) {
					if ( 'default' == $key ) {
						$xrd .= '<Title>' . htmlentities( $value ) . '</Title>';
					} else {
						$xrd .= '<Title xml:lang="' . htmlentities( $key ) . '">' . htmlentities( $value ) . '</Title>';
					}
				}
				continue;
			}

			// print links
			if ( 'links' == $type ) {
				foreach ( $content as $links ) {
					$temp = array();
					$cascaded = false;
					$xrd .= '<Link ';

					foreach ( $links as $key => $value ) {
						if ( is_array( $value ) ) {
							$temp[ $key ] = $value;
							$cascaded = true;
						} else {
							$xrd .= htmlentities( $key ) . '="' . htmlentities( $value ) . '" ';
						}
					}
					if ( $cascaded ) {
						$xrd .= '>';
						$xrd .= WebfingerPlugin::jrd_to_xrd( $temp );
						$xrd .= '</Link>';
					} else {
						$xrd .= ' />';
					}
				}
				continue;
			}
		}

		return $xrd;
	}
}
