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
							<i aria-hidden="true" class="fab fa-facebook"></i>
						</a>
						<a class="am-share-btn am-share-btn--twitter" href="<?php echo esc_url( $share['twitter'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="Share on Twitter">
							<i aria-hidden="true" class="fab fa-twitter"></i>
						</a>
						<a class="am-share-btn am-share-btn--whatsapp" href="<?php echo esc_url( $share['whatsapp'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="Share on WhatsApp">
							<i aria-hidden="true" class="fab fa-whatsapp"></i>
						</a>
						<a class="am-share-btn am-share-btn--telegram" href="<?php echo esc_url( $share['telegram'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="Share on Telegram">
							<i aria-hidden="true" class="fab fa-telegram"></i>
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

