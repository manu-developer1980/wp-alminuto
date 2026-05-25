<?php

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
						<?php echo alminuto_theme_post_meta_html(); ?>
					</div>

					<?php if ( has_post_thumbnail() ) : ?>
						<div class="am-post-thumb" style="aspect-ratio:auto;">
							<?php the_post_thumbnail( 'large' ); ?>
						</div>
					<?php endif; ?>

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

