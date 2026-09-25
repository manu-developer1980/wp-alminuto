<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

?>
<div class="am-layout">
	<section>
		<?php get_template_part( 'template-parts/common/top-banner' ); ?>
		<div class="am-card" style="margin-bottom:14px;">
			<div class="am-card-body">
				<h1 class="am-archive-title">
					<?php echo esc_html( 'Búsqueda: ' . get_search_query() ); ?>
				</h1>
			</div>
		</div>

		<div class="am-post-grid">
			<?php if ( have_posts() ) : ?>
				<?php $i = 0; ?>
				<?php while ( have_posts() ) : ?>
					<?php the_post(); ?>
					<article class="am-post">
						<a class="am-post-thumb" href="<?php the_permalink(); ?>" aria-label="<?php the_title_attribute(); ?>">
							<?php if ( has_post_thumbnail() ) : ?>
								<?php
								$thumb_attr = $i === 0
									? [ 'loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async' ]
									: [ 'loading' => 'lazy', 'decoding' => 'async' ];
								the_post_thumbnail( 'content_4_3', $thumb_attr );
								?>
							<?php endif; ?>
						</a>
						<div class="am-post-body">
							<h2 class="am-post-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
							<?php echo alminuto_theme_post_meta_html(); ?>
							<p class="am-post-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></p>
							<a class="am-btn" href="<?php the_permalink(); ?>">Leer más</a>
						</div>
					</article>
					<?php $i++; ?>
				<?php endwhile; ?>
			<?php else : ?>
				<div class="am-card"><div class="am-card-body">No hay resultados.</div></div>
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
