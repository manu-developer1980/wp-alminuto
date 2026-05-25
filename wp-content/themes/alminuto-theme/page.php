<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

?>
<article class="am-page">
	<?php if ( have_posts() ) : ?>
		<?php while ( have_posts() ) : ?>
			<?php the_post(); ?>
			<header class="am-page-header">
				<h1 class="am-page-title"><?php the_title(); ?></h1>
			</header>
			<div class="am-content am-page-content">
				<?php the_content(); ?>
			</div>
		<?php endwhile; ?>
	<?php endif; ?>
</article>

<?php

get_footer();
