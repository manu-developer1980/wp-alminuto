<?php

get_header();

?>
<div class="am-layout">
	<section>
		<?php $settings = function_exists( 'alminuto_theme_get_settings' ) ? alminuto_theme_get_settings() : [ 'home_left_posts' => 20, 'home_right_posts' => 20 ]; ?>
		<?php
		$front_id = (int) get_option( 'page_on_front' );
		if ( $front_id ) {
			$front_post = get_post( $front_id );
			if ( $front_post && $front_post->post_status === 'publish' ) {
				$content = apply_filters( 'the_content', $front_post->post_content );
				if ( trim( wp_strip_all_tags( $content ) ) !== '' ) {
					?>
					<article class="am-card" style="margin-bottom:14px;">
						<div class="am-card-body am-content">
							<?php echo wp_kses_post( $content ); ?>
						</div>
					</article>
					<?php
				}
			}
		}
		?>
		<?php
		?>

		<?php
		$left_query = new WP_Query(
			[
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'posts_per_page'      => max( 1, min( 50, (int) ( $settings['home_left_posts'] ?? 20 ) ) ),
				'tag'                 => 'izquierda',
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			]
		);
		$right_query = new WP_Query(
			[
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'posts_per_page'      => max( 1, min( 50, (int) ( $settings['home_right_posts'] ?? 20 ) ) ),
				'tag'                 => 'derecha',
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			]
		);

		$shown_ids = [];
		foreach ( array_merge( $left_query->posts, $right_query->posts ) as $p ) {
			$shown_ids[] = (int) $p->ID;
		}
		$shown_ids = array_values( array_unique( $shown_ids ) );
		?>

		<?php get_template_part( 'template-parts/common/top-banner' ); ?>

		<?php if ( $left_query->have_posts() || $right_query->have_posts() ) : ?>
			<div class="am-home-columns" id="contenedor">
				<div class="am-home-col am-home-col--left">
					<?php if ( $left_query->have_posts() ) : ?>
						<?php $i = 0; ?>
						<?php while ( $left_query->have_posts() ) : ?>
							<?php $left_query->the_post(); ?>
							<?php
							$img_id = get_post_thumbnail_id();
							$size   = 'Columna Izquierda';
							$img    = $img_id ? wp_get_attachment_image( $img_id, $size ) : '';
							if ( ! $img && $img_id ) {
								$img = wp_get_attachment_image( $img_id, 'medium_large' );
							}
							?>
							<article class="am-home-post <?php echo $i === 0 ? 'am-home-post--featured' : ''; ?>">
								<a class="am-home-post-thumb" href="<?php the_permalink(); ?>">
									<?php echo wp_kses_post( $img ); ?>
								</a>
								<div class="am-home-post-body">
									<?php echo alminuto_theme_post_meta_html(); ?>
									<h2 class="am-home-post-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
									<p class="am-home-post-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 18 ) ); ?></p>
									<a class="am-btn" href="<?php the_permalink(); ?>">Leer Más</a>
								</div>
							</article>
							<?php $i++; ?>
						<?php endwhile; ?>
					<?php endif; ?>
				</div>

				<div class="am-home-col am-home-col--right">
					<?php if ( $right_query->have_posts() ) : ?>
						<?php while ( $right_query->have_posts() ) : ?>
							<?php $right_query->the_post(); ?>
							<?php
							$img_id = get_post_thumbnail_id();
							$size   = 'Columna Derecha';
							$img    = $img_id ? wp_get_attachment_image( $img_id, $size ) : '';
							if ( ! $img && $img_id ) {
								$img = wp_get_attachment_image( $img_id, 'thumbnail' );
							}
							?>
							<article class="am-home-post am-home-post--compact">
								<a class="am-home-post-thumb" href="<?php the_permalink(); ?>">
									<?php echo wp_kses_post( $img ); ?>
								</a>
								<div class="am-home-post-body">
									<?php echo alminuto_theme_post_meta_html(); ?>
									<h2 class="am-home-post-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
									<p class="am-home-post-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 14 ) ); ?></p>
									<a class="am-btn" href="<?php the_permalink(); ?>">Leer Más</a>
								</div>
							</article>
						<?php endwhile; ?>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>

		<?php wp_reset_postdata(); ?>
	</section>

	<aside>
		<?php get_template_part( 'template-parts/common/right-column' ); ?>
	</aside>
</div>

<?php

get_footer();
