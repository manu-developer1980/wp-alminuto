<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

?>
<div class="am-layout">
	<section>
		<?php get_template_part( 'template-parts/common/top-banner' ); ?>
		<?php if ( have_posts() ) : ?>
			<?php while ( have_posts() ) : ?>
				<?php the_post(); ?>

				<article class="am-card">
					<div class="am-card-body">
						<h1 class="am-single-title"><?php the_title(); ?></h1>
						<p class="am-home-post-single-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 14 ) ); ?></p>
						<?php echo alminuto_theme_post_meta_html(); ?>
					</div>

					<?php
					$post_id = get_the_ID();
					$youtube  = (string) get_post_meta( $post_id, '_video_youtube', true );
					$facebook = (string) get_post_meta( $post_id, '_video_facebook', true );
					$has_video = $youtube !== '' || $facebook !== '';

					$media = alminuto_theme_primary_media_html( $post_id );
					if ( $media ) {
						echo $media;

						if ( ! $has_video && has_post_thumbnail( $post_id ) ) {
							$caption = (string) get_the_post_thumbnail_caption( $post_id );
							if ( $caption !== '' ) {
								echo '<div class="am-card-body am-post-thumb-caption">' . wp_kses_post( $caption ) . '</div>';
							}
						}
					}
					?>

					<div class="am-card-body am-content">
						<?php the_content(); ?>
					</div>

					<?php
					$share = alminuto_theme_share_links( get_permalink(), get_the_title() );
					?>
					<div class="am-share">
						<a class="am-share-btn am-share-btn--facebook" href="<?php echo esc_url( $share['facebook'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="Share on Facebook">
							<svg viewBox="0 0 320 512" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M279.14 288l14.22-92.66h-88.91V117.78c0-25.35 12.42-50.06 52.24-50.06H295V6.26S259.36 0 225.36 0c-73.22 0-121.08 44.38-121.08 124.72v70.62H22.89V288h81.39v224h100.17V288z"/></svg>
						</a>
						<a class="am-share-btn am-share-btn--x" href="<?php echo esc_url( $share['x'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="Share on X">
							<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
						</a>
						<a class="am-share-btn am-share-btn--whatsapp" href="<?php echo esc_url( $share['whatsapp'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="Share on WhatsApp">
							<svg viewBox="0 0 448 512" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M380.9 97.1C339 55.1 283.2 32 223.9 32c-122.4 0-222 99.6-222 222 0 39.1 10.2 77.3 29.6 111L0 480l117.7-30.9c32.4 17.7 68.9 27 106.1 27h.1c122.3 0 224.1-99.6 224.1-222 0-59.3-25.2-115-67.1-157zm-157 341.6c-33.2 0-65.7-8.9-94-25.7l-6.7-4-69.8 18.3L72 359.2l-4.4-7c-18.5-29.4-28.2-63.3-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.6-186.6 184.6zm101.2-138.2c-5.5-2.8-32.8-16.2-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8-3.7 5.6-14.3 18-17.6 21.8-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7.9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.8 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z"/></svg>
						</a>
						<a class="am-share-btn am-share-btn--telegram" href="<?php echo esc_url( $share['telegram'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="Share on Telegram">
							<svg viewBox="0 0 496 512" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M248 8C111 8 0 119 0 256s111 248 248 248 248-111 248-248S385 8 248 8zm121.8 169.9l-40.7 191.8c-3 13.6-11.1 16.9-22.4 10.5l-62-45.7-29.9 28.8c-3.3 3.3-6.1 6.1-12.5 6.1l4.4-63.1 114.9-103.8c5-4.4-1.1-6.9-7.7-2.5l-142 89.4-61.2-19.1c-13.3-4.2-13.6-13.3 2.8-19.7l239.1-92.2c11.1-4 20.8 2.7 17.2 19.3z"/></svg>
						</a>
					</div>
				</article>
			<?php endwhile; ?>
		<?php endif; ?>
	</section>

	<aside>
		<?php get_template_part( 'template-parts/common/right-column' ); ?>
	</aside>
</div>

<?php

get_footer();

