<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<section class="am-top-banners" id="bannersSuperiores">
	<?php if ( function_exists( 'do_shortcode' ) ) : ?>
		<?php echo do_shortcode( '[banners_alminuto slot="top_left" slider="1" limit="10" autoplay="9500" size="banner_superior"]' ); ?>
	<?php endif; ?>
</section>
