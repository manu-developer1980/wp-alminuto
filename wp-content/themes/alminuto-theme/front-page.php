<?php

get_header();

?>
<div class="am-layout">
	<section>
		<div class="am-post-grid">
			<?php
			$paged = max( 1, (int) get_query_var( 'paged' ) );
			$query = new WP_Query(
				[
					'post_type'           => 'post',
					'post_status'         => 'publish',
					'posts_per_page'      => 12,
					'paged'               => $paged,
					'ignore_sticky_posts' => true,
					'no_found_rows'       => false,
				]
			);
			?>

			<?php if ( $query->have_posts() ) : ?>
				<?php while ( $query->have_posts() ) : ?>
					<?php $query->the_post(); ?>
					<article class="am-post">
						<a class="am-post-thumb" href="<?php the_permalink(); ?>">
							<?php if ( has_post_thumbnail() ) : ?>
								<?php the_post_thumbnail( 'medium_large' ); ?>
							<?php endif; ?>
						</a>
						<div class="am-post-body">
							<div class="am-post-meta"><?php echo esc_html( get_the_date() ); ?></div>
							<h2 class="am-post-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
							<p class="am-post-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></p>
							<a class="am-btn" href="<?php the_permalink(); ?>">Leer más</a>
						</div>
					</article>
				<?php endwhile; ?>
			<?php else : ?>
				<div class="am-card"><div class="am-card-body">No hay noticias.</div></div>
			<?php endif; ?>

			<?php wp_reset_postdata(); ?>
		</div>

		<?php
		$pagination = paginate_links(
			[
				'type'      => 'array',
				'current'   => $paged,
				'total'     => (int) $query->max_num_pages,
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
		<?php if ( is_active_sidebar( 'sidebar-right' ) ) : ?>
			<?php dynamic_sidebar( 'sidebar-right' ); ?>
		<?php endif; ?>
	</aside>
</div>

<?php

get_footer();

