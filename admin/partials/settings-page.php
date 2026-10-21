<?php
/**
 * Settings page markup.
 *
 * @package FrontierLine\AIO
 *
 * @var array  $tabs   Tab key => label.
 * @var string $active Active tab key.
 * @var string $page   Settings page slug.
 */

namespace FrontierLine\AIO;

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap flaio-wrap">
	<h1><?php esc_html_e( 'AIO Toolkit', 'frontierline-aio-toolkit' ); ?></h1>
	<p class="flaio-sub"><?php esc_html_e( 'LLM & AI search optimization for your site.', 'frontierline-aio-toolkit' ); ?></p>

	<h2 class="nav-tab-wrapper">
		<?php foreach ( $tabs as $flaio_tab_key => $flaio_tab_label ) : ?>
			<a
				href="<?php echo esc_url( admin_url( 'options-general.php?page=' . $page . '&tab=' . $flaio_tab_key ) ); ?>"
				class="nav-tab <?php echo ( $active === $flaio_tab_key ) ? 'nav-tab-active' : ''; ?>"
			><?php echo esc_html( $flaio_tab_label ); ?></a>
		<?php endforeach; ?>
	</h2>

	<form action="options.php" method="post">
		<?php
		settings_fields( 'flaio_group' );
		printf( '<input type="hidden" name="flaio_options[_tab]" value="%s" />', esc_attr( $active ) );
		do_settings_sections( $page . '_' . $active );
		submit_button();
		?>
	</form>

	<?php if ( 'llms' === $active ) : ?>
		<p class="flaio-hint">
			<?php
			printf(
				/* translators: 1: llms.txt URL, 2: llms-full.txt URL */
				esc_html__( 'Feeds: %1$s and %2$s', 'frontierline-aio-toolkit' ),
				'<a href="' . esc_url( home_url( '/llms.txt' ) ) . '" target="_blank" rel="noopener">/llms.txt</a>',
				'<a href="' . esc_url( home_url( '/llms-full.txt' ) ) . '" target="_blank" rel="noopener">/llms-full.txt</a>'
			);
			?>
		</p>
	<?php endif; ?>
</div>
