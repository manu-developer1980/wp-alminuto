<?php

get_header();

?>
<div class="am-layout">
	<section>
		<?php if ( have_posts() ) : ?>
			<?php while ( have_posts() ) : ?>
				<?php the_post(); ?>

				<article class="am-card">
					<div class="am-card-body">
						<h1 class="am-single-title"><?php the_title(); ?></h1>
						<div class="am-single-meta">
							<?php echo esc_html( get_the_date() ); ?>
							<?php $cats = get_the_category(); ?>
							<?php if ( ! empty( $cats ) ) : ?>
								· <a href="<?php echo esc_url( get_category_link( $cats[0]->term_id ) ); ?>"><?php echo esc_html( $cats[0]->name ); ?></a>
							<?php endif; ?>
						</div>
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
						<a href="<?php echo esc_url( $share['facebook'] ); ?>" target="_blank" rel="noopener noreferrer">Facebook</a>
						<a href="<?php echo esc_url( $share['twitter'] ); ?>" target="_blank" rel="noopener noreferrer">X</a>
						<a href="<?php echo esc_url( $share['whatsapp'] ); ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a>
					</div>
				</article>
			<?php endwhile; ?>
		<?php endif; ?>
	</section>

	<aside>
		<?php if ( is_active_sidebar( 'sidebar-right' ) ) : ?>
			<?php dynamic_sidebar( 'sidebar-right' ); ?>
		<?php endif; ?>
	</aside>
</div>

<?php

get_footer();

