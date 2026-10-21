<?php
/**
 * Generates llms.txt and llms-full.txt content.
 *
 * @package FrontierLine\AIO
 */

namespace FrontierLine\AIO;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the LLM-facing plain-text feeds.
 */
class Llms_Generator {

	/**
	 * Transient key for the cached llms-full.txt body.
	 */
	const CACHE_KEY = 'flaio_llms_full';

	/**
	 * Default llms-full.txt size cap, in kilobytes.
	 */
	const DEFAULT_MAX_KB = 500;

	/**
	 * Hard upper bound for the size cap, in kilobytes.
	 */
	const MAX_KB_LIMIT = 10240;

	/**
	 * Register hooks (cache invalidation on content change).
	 */
	public function hooks() {
		add_action( 'save_post', array( $this, 'flush_cache' ) );
		add_action( 'deleted_post', array( $this, 'flush_cache' ) );
		// update_option() delegates to add_option() the first time the option is
		// written, and that path fires add_option_{$option} instead. Both are
		// needed or the very first settings save leaves a stale cache behind.
		add_action( 'update_option_flaio_options', array( $this, 'flush_cache' ) );
		add_action( 'add_option_flaio_options', array( $this, 'flush_cache' ) );
	}

	/**
	 * Invalidate the llms-full.txt cache.
	 */
	public function flush_cache() {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Send plain-text headers.
	 *
	 * @return bool True when the plain-text content type could be sent.
	 */
	private function send_headers() {
		if ( headers_sent() ) {
			return false;
		}
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Robots-Tag: noindex' );
		return true;
	}

	/**
	 * Print a generated feed body.
	 *
	 * The body is plain text: it is served as text/plain with nosniff and all
	 * markup has already been removed by clean(), so escaping it would only
	 * corrupt the feed. If another plugin has already sent headers we cannot
	 * guarantee the content type, so the body is escaped in that case.
	 *
	 * @param string $body Generated feed body.
	 */
	private function output( $body ) {
		if ( $this->send_headers() ) {
			echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text/plain response with nosniff; markup already stripped in clean().
			return;
		}
		echo esc_html( $body );
	}

	/**
	 * Output the llms.txt feed.
	 */
	public function output_llms_txt() {
		$this->output( $this->build_llms_txt() );
	}

	/**
	 * Output the llms-full.txt feed.
	 */
	public function output_llms_full_txt() {
		$this->output( $this->build_llms_full_txt() );
	}

	/**
	 * Build the llms.txt body: overview + company info + key pages.
	 *
	 * @return string
	 */
	public function build_llms_txt() {
		$site_name = Plugin::option( 'site_name', get_bloginfo( 'name' ) );
		$summary   = Plugin::option( 'site_summary', get_bloginfo( 'description' ) );

		$lines   = array();
		$lines[] = '# ' . $this->clean( $site_name );
		$lines[] = '';
		if ( '' !== trim( (string) $summary ) ) {
			$lines[] = '> ' . $this->clean( $summary );
			$lines[] = '';
		}

		$company = Plugin::option( 'company_info', '' );
		if ( '' !== trim( (string) $company ) ) {
			$lines[] = '## Company';
			$lines[] = '';
			$lines[] = $this->clean( $company );
			$lines[] = '';
		}

		$lines[] = '## Key Pages';
		$lines[] = '';

		$pages = $this->get_key_pages();
		if ( empty( $pages ) ) {
			$lines[] = '- [' . $this->clean( $site_name ) . '](' . esc_url_raw( home_url( '/' ) ) . ')';
		} else {
			foreach ( $pages as $page ) {
				if ( $this->is_protected( $page ) ) {
					continue;
				}
				$title   = $this->clean( get_the_title( $page ) );
				$url     = esc_url_raw( get_permalink( $page ) );
				$excerpt = $this->excerpt( $page, 30 );
				$line    = '- [' . $title . '](' . $url . ')';
				if ( '' !== $excerpt ) {
					$line .= ': ' . $excerpt;
				}
				$lines[] = $line;
			}
		}

		// Only advertise llms-full.txt while that endpoint is actually served.
		if ( '1' === (string) Plugin::option( 'llms_full_enabled', '1' ) ) {
			$lines[] = '';
			$lines[] = '## Optional';
			$lines[] = '';
			$lines[] = '- [Full content](' . esc_url_raw( home_url( '/llms-full.txt' ) ) . ')';
		}
		$lines[] = '';

		/**
		 * Filter the final llms.txt body.
		 *
		 * @param string $body Generated text.
		 */
		return apply_filters( 'flaio_llms_txt', implode( "\n", $lines ) );
	}

	/**
	 * Build (and cache) the llms-full.txt body: aggregated post/page content.
	 *
	 * @return string
	 */
	public function build_llms_full_txt() {
		/*
		 * The feed queries deliberately run with suppress_filters => false so
		 * membership plugins can exclude their restricted posts. That makes the
		 * body depend on the current user, so it must never be written to - or
		 * read from - the shared transient while somebody is logged in:
		 * an administrator opening the feed would otherwise cache content that
		 * anonymous crawlers are then served for a day.
		 */
		$use_cache = ! is_user_logged_in();

		if ( $use_cache ) {
			$cached = get_transient( self::CACHE_KEY );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$max_kb = (int) Plugin::option( 'llms_full_max_kb', self::DEFAULT_MAX_KB );
		if ( $max_kb <= 0 ) {
			$max_kb = self::DEFAULT_MAX_KB;
		}
		$max_bytes = min( $max_kb, self::MAX_KB_LIMIT ) * 1024;

		$site_name = Plugin::option( 'site_name', get_bloginfo( 'name' ) );
		$body      = '# ' . $this->clean( $site_name ) . " — Full Content\n\n";

		$post_types = apply_filters( 'flaio_llms_full_post_types', array( 'post', 'page' ) );

		/**
		 * Filter the maximum number of posts scanned for llms-full.txt.
		 *
		 * @param int $max Maximum post count.
		 */
		$max_posts = (int) apply_filters( 'flaio_llms_full_max_posts', 2000 );

		// Fetch IDs only, then hydrate in small batches: keeps peak memory flat
		// on shared hosting even with thousands of posts.
		$ids = get_posts(
			array(
				'post_type'              => $post_types,
				'post_status'            => 'publish',
				'has_password'           => false,
				'posts_per_page'         => $max_posts,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'fields'                 => 'ids',
				// Let membership/access-control plugins filter the query.
				'suppress_filters'       => false,
			)
		);

		// Keep the object cache from filling up with every post body on a
		// large site, without invalidating anything the site already cached.
		$was_suspended = wp_suspend_cache_addition();
		wp_suspend_cache_addition( true );

		$truncated = false;
		foreach ( array_chunk( $ids, 50 ) as $batch ) {
			$posts = get_posts(
				array(
					'post_type'              => $post_types,
					'post_status'            => 'publish',
					'post__in'               => $batch,
					'orderby'                => 'post__in',
					'posts_per_page'         => count( $batch ),
					'no_found_rows'          => true,
					'ignore_sticky_posts'    => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'suppress_filters'       => false,
				)
			);

			foreach ( $posts as $post ) {
				// Belt and braces: never expose password-protected content.
				if ( '' !== (string) $post->post_password ) {
					continue;
				}

				$chunk   = '## ' . $this->clean( get_the_title( $post ) ) . "\n";
				$chunk  .= esc_url_raw( get_permalink( $post ) ) . "\n\n";
				$content = wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) );
				$chunk  .= $this->clean( $content ) . "\n\n---\n\n";

				if ( strlen( $body ) + strlen( $chunk ) > $max_bytes ) {
					$body     .= "\n[Truncated: size limit reached]\n";
					$truncated = true;
					break;
				}
				$body .= $chunk;
			}

			unset( $posts );

			if ( $truncated ) {
				break;
			}
		}

		wp_suspend_cache_addition( $was_suspended );

		/**
		 * Filter the final llms-full.txt body.
		 *
		 * @param string $body Generated text.
		 */
		$body = apply_filters( 'flaio_llms_full_txt', $body );

		if ( $use_cache ) {
			set_transient( self::CACHE_KEY, $body, DAY_IN_SECONDS );
		}
		return $body;
	}

	/**
	 * Plain-text excerpt for a post, without running the the_content filter.
	 *
	 * get_the_excerpt() falls back to wp_trim_excerpt(), which applies the whole
	 * the_content stack (shortcodes, page builders, oEmbed auto-embeds and the
	 * HTTP requests they make) - inside wp_head and inside the feed loop, with
	 * the global $post pointing at an unrelated post. The manual excerpt is used
	 * when there is one; otherwise the stored content is trimmed directly.
	 *
	 * @param int|\WP_Post $post  Post ID or object.
	 * @param int          $words Maximum number of words.
	 * @return string
	 */
	public function excerpt( $post, $words = 55 ) {
		$post = get_post( $post );
		if ( ! $post instanceof \WP_Post || '' !== (string) $post->post_password ) {
			return '';
		}
		$text = (string) $post->post_excerpt;
		if ( '' === trim( $text ) ) {
			$text = excerpt_remove_blocks( (string) $post->post_content );
			$text = strip_shortcodes( $text );
		}
		return wp_trim_words( $this->clean( $text ), $words, '…' );
	}

	/**
	 * Whether a post is password protected.
	 *
	 * @param int|\WP_Post $post Post ID or object.
	 * @return bool
	 */
	private function is_protected( $post ) {
		$post = get_post( $post );
		return ( $post instanceof \WP_Post ) && '' !== (string) $post->post_password;
	}

	/**
	 * Return key pages: user-selected, else published pages.
	 *
	 * @return array Array of post IDs.
	 */
	private function get_key_pages() {
		return get_posts(
			array(
				'post_type'        => 'page',
				'post_status'      => 'publish',
				'has_password'     => false,
				'posts_per_page'   => 20,
				'orderby'          => 'menu_order title',
				'order'            => 'ASC',
				'fields'           => 'ids',
				// Let membership/access-control plugins filter the query.
				'suppress_filters' => false,
			)
		);
	}

	/**
	 * Normalize a string for plain-text output: strip tags, decode entities,
	 * collapse whitespace.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	private function clean( $text ) {
		$text = (string) $text;
		// Decode first, then strip, so entity-encoded markup cannot survive.
		$text = html_entity_decode( $text, ENT_QUOTES, get_bloginfo( 'charset' ) );
		$text = wp_strip_all_tags( $text );
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
		$text = preg_replace( '/[ \t]+/', ' ', $text );
		$text = preg_replace( '/\n{3,}/', "\n\n", $text );
		return trim( $text );
	}
}
