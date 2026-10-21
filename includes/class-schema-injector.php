<?php
/**
 * Injects JSON-LD structured data into wp_head.
 *
 * @package FrontierLine\AIO
 */

namespace FrontierLine\AIO;

defined( 'ABSPATH' ) || exit;

/**
 * Builds and prints context-aware schema.org JSON-LD.
 */
class Schema_Injector {

	/**
	 * Register hooks.
	 */
	public function hooks() {
		add_action( 'wp_head', array( $this, 'inject' ), 20 );
	}

	/**
	 * Print JSON-LD for the current request.
	 */
	public function inject() {
		if ( '1' !== (string) Plugin::option( 'schema_enabled', '1' ) ) {
			return;
		}

		if ( is_singular() ) {
			$post_id = get_queried_object_id();
			// Never describe attachments, or content that is not publicly published.
			// post_password_required() is false once the visitor has entered the
			// password; the raw field keeps protected posts out for everyone.
			if ( is_attachment() || '' !== (string) get_post_field( 'post_password', $post_id ) || 'publish' !== get_post_status( $post_id ) ) {
				return;
			}
		}

		$graph = array();

		if ( is_front_page() ) {
			$graph[] = $this->organization();
			$graph[] = $this->website();
		} elseif ( is_singular( 'page' ) ) {
			$graph[] = $this->web_page();
		} elseif ( is_singular() ) {
			$graph[] = $this->article();
		} elseif ( is_author() ) {
			$graph[] = $this->person( get_queried_object_id() );
		}

		/**
		 * Filter the JSON-LD @graph nodes.
		 *
		 * @param array $graph Schema nodes.
		 */
		$graph = array_filter( apply_filters( 'flaio_schema_graph', $graph ) );
		if ( empty( $graph ) ) {
			return;
		}

		$payload = array(
			'@context' => 'https://schema.org',
			'@graph'   => array_values( $graph ),
		);

		/*
		 * JSON_HEX_TAG / AMP / APOS / QUOT escape <, >, &, ' and " as \uXXXX so no
		 * value in the graph can terminate the script element or inject markup.
		 */
		$json = wp_json_encode(
			$payload,
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
		);
		if ( false === $json ) {
			return;
		}

		echo "\n<script type=\"application/ld+json\">";
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON escaped above with JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT.
		echo "</script>\n";
	}

	/**
	 * Organization node.
	 *
	 * @return array
	 */
	private function organization() {
		$node = array(
			'@type' => 'Organization',
			'name'  => $this->text( Plugin::option( 'org_name', get_bloginfo( 'name' ) ) ),
			'url'   => home_url( '/' ),
		);
		$logo = Plugin::option( 'org_logo', '' );
		if ( '' !== $logo ) {
			$node['logo'] = $this->image_object( $logo );
		}
		return $node;
	}

	/**
	 * WebSite node.
	 *
	 * @return array
	 */
	private function website() {
		return array(
			'@type'           => 'WebSite',
			'name'            => $this->text( get_bloginfo( 'name' ) ),
			'url'             => home_url( '/' ),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => home_url( '/?s={search_term_string}' ),
				),
				'query-input' => 'required name=search_term_string',
			),
		);
	}

	/**
	 * WebPage node for static pages.
	 *
	 * @return array
	 */
	private function web_page() {
		$id = get_queried_object_id();
		return array(
			'@type'       => 'WebPage',
			'name'        => $this->text( get_the_title( $id ) ),
			'url'         => get_permalink( $id ),
			'description' => $this->text( Plugin::instance()->llms->excerpt( $id ) ),
		);
	}

	/**
	 * Article node for singular posts.
	 *
	 * @return array
	 */
	private function article() {
		$id   = get_queried_object_id();
		$node = array(
			'@type'         => 'Article',
			'headline'      => $this->text( get_the_title( $id ) ),
			'url'           => get_permalink( $id ),
			'datePublished' => get_the_date( 'c', $id ),
			'dateModified'  => get_the_modified_date( 'c', $id ),
			'description'   => $this->text( Plugin::instance()->llms->excerpt( $id ) ),
		);

		$author = $this->person( (int) get_post_field( 'post_author', $id ) );
		if ( ! empty( $author ) ) {
			$node['author'] = $author;
		}

		if ( has_post_thumbnail( $id ) ) {
			$src = wp_get_attachment_image_url( get_post_thumbnail_id( $id ), 'full' );
			if ( $src ) {
				$node['image'] = $this->image_object( $src );
			}
		}

		return $node;
	}

	/**
	 * Person node for an author.
	 *
	 * @param int $author_id Author user ID.
	 * @return array
	 */
	private function person( $author_id ) {
		$author_id = (int) $author_id;
		if ( $author_id <= 0 ) {
			return array();
		}

		$name = $this->text( get_the_author_meta( 'display_name', $author_id ) );
		if ( '' === $name ) {
			return array();
		}

		return array(
			'@type' => 'Person',
			'name'  => $name,
			'url'   => get_author_posts_url( $author_id ),
		);
	}

	/**
	 * Normalise a value for use as schema.org plain text.
	 *
	 * Strips markup and decodes the HTML entities WordPress adds through
	 * wptexturize()/convert_chars(), so consumers get "Foo & Bar" rather
	 * than "Foo &#038; Bar".
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private function text( $value ) {
		$value = (string) $value;
		// Decode first, then strip, so entity-encoded markup cannot survive.
		$value = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$value = wp_strip_all_tags( $value );
		$value = preg_replace( '/\s+/u', ' ', $value );
		return trim( (string) $value );
	}

	/**
	 * ImageObject node.
	 *
	 * @param string $url Image URL.
	 * @return array
	 */
	private function image_object( $url ) {
		return array(
			'@type' => 'ImageObject',
			'url'   => $url,
		);
	}
}
