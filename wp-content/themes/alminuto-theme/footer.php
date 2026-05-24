<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
	</div>
</main>

<footer class="am-footer">
	<div class="am-container">
		<div class="am-footer-inner">
			<div><strong><?php bloginfo( 'name' ); ?></strong></div>
			<div><?php echo esc_html( gmdate( 'Y' ) ); ?> · <?php bloginfo( 'name' ); ?></div>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>

