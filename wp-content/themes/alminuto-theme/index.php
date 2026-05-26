<?php

get_header();

?>
<div class="am-layout">
	<section>
		<?php get_template_part( 'template-parts/common/top-banner' ); ?>
		<div class="am-post-grid">
			<?php if ( have_posts() ) : ?>
				<?php while ( have_posts() ) : ?>
					<?php the_post(); ?>
					<article class="am-post">
						<a class="am-post-thumb" href="<?php the_permalink(); ?>">
							<?php if ( has_post_thumbnail() ) : ?>
								<?php the_post_thumbnail( 'content_4_3' ); ?>
							<?php endif; ?>
						</a>
						<div class="am-post-body">
							<?php echo alminuto_theme_post_meta_html(); ?>
							<h2 class="am-post-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
							<p class="am-post-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></p>
							<a class="am-btn" href="<?php the_permalink(); ?>">Leer más</a>
						</div>
					</article>
				<?php endwhile; ?>
			<?php else : ?>
				<div class="am-card"><div class="am-card-body">No hay contenido.</div></div>
			<?php endif; ?>
		</div>

		<?php
		$pagination = paginate_links(
			[
				'type'      => 'array',
				'prev_text' => '«',
				'next_text' => '»',
			]
		);
		if ( is_array( $pagination ) ) :
			?>
			<nav class="am-pagination" aria-label="Paginación">
				<?php foreach ( $pagination as $link ) : ?>
					<?php echo wp_kses_post( $link ); ?>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>
	</section>

	<aside>
		<?php get_template_part( 'template-parts/common/right-column' ); ?>
	</aside>
</div>

<?php

get_footer();

