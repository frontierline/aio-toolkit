<?php
/**
 * Appends AI-crawler allow rules to robots.txt.
 *
 * @package FrontierLine\AIO
 */

namespace FrontierLine\AIO;

defined( 'ABSPATH' ) || exit;

/**
 * Filters the virtual robots.txt output.
 */
class Robots_Handler {

	/**
	 * Curated list of major AI crawler user agents (2026).
	 *
	 * @var string[]
	 */
	const AI_CRAWLERS = array(
		'GPTBot',
		'ChatGPT-User',
		'OAI-SearchBot',
		'ClaudeBot',
		'Claude-Web',
		'Claude-User',
		'anthropic-ai',
		'PerplexityBot',
		'Perplexity-User',
		'Google-Extended',
		'Applebot-Extended',
		'CCBot',
		'cohere-ai',
		'Bytespider',
		'YouBot',
		'Amazonbot',
		'DuckAssistBot',
		'Diffbot',
		'Meta-ExternalAgent',
		'FacebookBot',
		'MistralAI-User',
		'Timpibot',
	);

	/**
	 * Register hooks.
	 */
	public function hooks() {
		/*
		 * Priority 100000: some SEO plugins rebuild the whole robots.txt at a very
		 * late priority (Yoast SEO uses 99999). Appending after them keeps both
		 * their output and ours intact.
		 */
		add_filter( 'robots_txt', array( $this, 'filter_robots_txt' ), 100000, 2 );
	}

	/**
	 * Append allow rules for AI crawlers.
	 *
	 * @param string $output The robots.txt content.
	 * @param bool   $public Whether the site is public.
	 * @return string
	 */
	public function filter_robots_txt( $output, $public ) {
		if ( '1' !== (string) Plugin::option( 'robots_enabled', '1' ) ) {
			return $output;
		}

		// Respect Settings > Reading "discourage search engines".
		if ( ! $public ) {
			return $output;
		}

		/**
		 * Filter the AI crawler list before it is written.
		 *
		 * @param string[] $crawlers User agent list.
		 */
		$crawlers = apply_filters( 'flaio_ai_crawlers', self::AI_CRAWLERS );

		$block   = "\n# AI crawlers allowed by AIO Toolkit\n";
		foreach ( $crawlers as $agent ) {
			$agent = trim( (string) $agent );
			if ( '' === $agent ) {
				continue;
			}
			$block .= 'User-agent: ' . $agent . "\n";
			$block .= "Allow: /\n\n";
		}

		return $output . $block;
	}
}
