<?php get_header(); ?>

		<div id="container" class="onecolumn">
			<div id="content">

<?php the_post(); ?>

				<div id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
					<h1 class="entry-title"><?php printf(__('Registration Accepted for %s', 'nsevent'), esc_html($event->name)); ?></h1>
					<div class="entry-content"></div><!-- .entry-content -->

					<div id="nsevent-accepted-paypal" class="nsevent-registration <?php echo $early_bird_class; if ($vip) echo ' vip'; ?>">
						<p><?php _e('Your registration has been recorded.', 'nsevent'); ?></p>

						<form action="<?php echo (!$options['paypal_sandbox']) ? 'https://www.paypal.com/cgi-bin/webscr' : 'https://www.sandbox.paypal.com/cgi-bin/webscr' ?>" method="post">
							<input type="image" id="paypal-button" src="https://www.paypal.com/en_US/i/btn/x-click-but6.gif" name="submit" alt="<?php _e('Make payments with PayPal - it\'s fast, free and secure!', 'nsevent'); ?>" />

							<?php NSEvent_FormInput::hidden('cmd',         array('value' => '_cart')); echo "\n"; ?>
							<?php NSEvent_FormInput::hidden('upload',      array('value' => 1)); echo "\n"; ?>
							<?php NSEvent_FormInput::hidden('no_shipping', array('value' => 1)); echo "\n"; ?>
							<?php NSEvent_FormInput::hidden('business',    array('value' => $options['paypal_business'])); echo "\n"; ?>
							<?php // TODO: notify url for IPN ?>

							<?php NSEvent_FormInput::hidden('custom', array('value' => $dancer->id)); echo "\n"; ?>
							<?php NSEvent_FormInput::hidden('first_name'); echo "\n"; ?>
							<?php NSEvent_FormInput::hidden('last_name');  echo "\n"; ?>

							<?php NSEvent_FormInput::hidden('item_name_1', array('value' => __('Processing Fee', 'nsevent'))); echo "\n"; ?>
							<?php NSEvent_FormInput::hidden('amount_1',    array('value' => 2)); echo "\n"; ?>

<?php $i = 2; ?>
<?php foreach (NSEvent::$validated_items as $item): ?>
							<?php NSEvent_FormInput::hidden('item_name_'.$i, array('value' => $item->name)); echo "\n"; ?>
							<?php NSEvent_FormInput::hidden('amount_'.$i,    array('value' => $dancer->registrations($item->id)->price)); echo "\n"; ?>
<?php 	if (isset($_POST['item_meta'][$item->id])): ?>
							<?php NSEvent_FormInput::hidden('on0_'.$i,      array('value' => $item->meta_label())); echo "\n"; ?>
							<?php NSEvent_FormInput::hidden('os0_'.$i,      array('value' => ucfirst($_POST['item_meta'][$item->id]))); echo "\n"; ?>
<?php 	endif; ?>
<?php $i++; ?>
<?php endforeach; ?>
						</form>
					</div>
				</div><!-- #post-<?php the_ID(); ?> -->

			</div><!-- #content -->
		</div><!-- #container -->

<?php get_footer(); ?>
