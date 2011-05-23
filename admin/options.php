
<div class="wrap">
	<div id="icon-options-general" class="icon32"><br></div>
	<h2>NSEvent Options</h2>

	<form method="post" action="options.php">
		<?php settings_fields('nsevent' ); ?>
		<?php $options = get_option('nsevent'); ?>

		<h3><?php _e('Registration', 'nsevent'); ?></h3>

		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php _e('Current Event', 'nsevent'); ?></th>
				<td>
					<select name="nsevent[current_event_id]" class="postform">
<?php foreach ($events as $event): ?>
						<option class="level-0" value="<?php echo (int) $event->get_id(); ?>"<?php if (isset($options['current_event_id']) and $options['current_event_id'] == $event->get_id()) echo ' selected="selected"'; ?>><?php echo esc_attr($event->get_name()); ?></option>
<?php endforeach; ?>
					</select>
					<span class="description"><?php _e('The event currently used by the registration form.', 'nsevent'); ?></span>
				</td>
			</tr>

			<tr valign="top">
				<th scope="row"><?php _e('Registration Testing', 'nsevent'); ?></th>
				<td>
					<fieldset>
						<legend class="screen-reader-text"><span><?php _e('Registration Testing', 'nsevent'); ?></span></legend>
						<label>
							<input name="nsevent[registration_testing]" type="checkbox" value="1"<?php if (isset($options['registration_testing']) and $options['registration_testing']) echo ' checked="checked"'; ?>>
							<?php _e('Only "capable" users will be able to access the registration form.', 'nsevent'); ?>
						</label>
						<br>
					</fieldset>
				</td>
			</tr>

			<tr valign="top">
				<th scope="row"><?php _e('Postmark Within', 'nsevent'); ?></th>
				<td>
					<span>Payments must be postmarked within <input type="text" name="nsevent[postmark_within]" value="<?php echo (int) $options['postmark_within']; ?>" class="regular-text" style="width: 3em"> days.</span>
				</td>
			</tr>
		</table>

		<h3><?php _e('Pay by Mail', 'nsevent'); ?></h3>

		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php _e('Payable To', 'nsevent'); ?></th>
				<td>
					<input type="text" name="nsevent[payable_to]" value="<?php if (isset($options['payable_to'])) echo esc_attr($options['payable_to']); ?>" class="regular-text">
				</td>
			</tr>

			<tr valign="top">
				<th scope="row"><?php _e('Mailing Address', 'nsevent'); ?></th>
				<td>
					<fieldset>
						<p><textarea name="nsevent[mailing_address]" rows="4" cols="50" class="large-text code"><?php if (isset($options['mailing_address'])) { echo esc_html($options['mailing_address']); } ?></textarea></p>
					</fieldset>
				</td>
			</tr>
		</table>

		<h3><?php _e('PayPal', 'nsevent'); ?></h3>

		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php _e('PayPal Business Address', 'nsevent'); ?></th>
				<td>
					<input type="text" name="nsevent[paypal_business]" value="<?php if (isset($options['paypal_business'])) echo esc_attr($options['paypal_business']); ?>" class="regular-text">
					<span class="description"><?php _e('The email address used to receive payments via PayPal. (If this is not set, then the PayPal payment option will not be available.)', 'nsevent'); ?></span>
				</td>
			</tr>

			<tr valign="top">
				<th scope="row"><?php _e('PayPal Fee', 'nsevent'); ?></th>
				<td>
					<input type="text" name="nsevent[paypal_fee]" value="<?php if (isset($options['paypal_fee'])) echo (int) $options['paypal_fee']; ?>" class="regular-text">
					<span class="description"><?php _e('The processing fee, if any, for payments made via PayPal.', 'nsevent'); ?></span>
				</td>
			</tr>
		</table>

		<h3><?php _e('Confirmation Email', 'nsevent'); ?></h3>

		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php _e('Confirmation Email Address', 'nsevent'); ?></th>
				<td>
					<input type="text" name="nsevent[confirmation_email_address]" value="<?php if (isset($options['confirmation_email_address'])) echo esc_attr($options['confirmation_email_address']); ?>" class="regular-text">
					<span class="description"><?php _e('This email address will appear on confirmation emails.', 'nsevent'); ?></span>
				</td>
			</tr>

			<tr valign="top">
				<th scope="row"><?php _e('Confirmation Email Bcc', 'nsevent'); ?></th>
				<td>
					<input type="text" name="nsevent[confirmation_email_bcc]" value="<?php if (isset($options['confirmation_email_bcc'])) echo esc_attr($options['confirmation_email_bcc']); ?>" class="regular-text">
					<span class="description"><?php _e('The email address will receive a copy of confirmation emails.', 'nsevent'); ?></span>
				</td>
			</tr>
		</table>

		<p class="submit"><input type="submit" class="button-primary" value="<?php _e('Save Changes'); ?>"></p>
	</form>
</div>
