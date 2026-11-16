<?php
/* @var array $args */
$access = !empty($args['access']) ? ( is_array($args['access']) ? $args['access'] : explode(',', $args['access']) ) : array();
?>
<!-- START Online Search -->
<div class="em-search-online em-search-field input">
	<label for="em-search-online-<?php echo absint($args['id']); ?>" class="inline-left em-tooltip" aria-label="<?php echo esc_attr($args['online_tooltip']); ?>">
		<?php echo esc_html($args['online_label']); ?>
	</label>
	<input type="checkbox" name="access" class="em-search-online" id="em-search-online-<?php echo absint($args['id']); ?>" value="online" <?php checked( in_array('online', array_map('trim', $access), true) ); ?>>
</div>
<!-- END Online Search -->
