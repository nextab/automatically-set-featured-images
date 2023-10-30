<?php
/*
Plugin Name: Automatically Set Featured Images
Description: Automatically sets featured images for posts or custom post types (that lack one) by selecting the first image inside the post / cpt and assigning it as its new featured image.
Version: 1.0
Author: nexTab - Oliver Gehrmann
Text Domain: automatically-set-featured-images
Domain Path: /languages
*/

// Add action hooks and filters
add_action('admin_menu', 'asfi_plugin_setup_menu');
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'asfi_add_settings_link');
add_action('admin_enqueue_scripts', 'asfi_enqueue_scripts');
add_action('wp_ajax_asfi_process_batch', 'asfi_process_batch');
add_action('plugins_loaded', 'asfi_load_plugin_textdomain');

// Enqueue scripts and localize AJAX parameters
function asfi_enqueue_scripts($hook) {
	if ($hook !== 'tools_page_automatically-set-featured-images') {
		return;
	}
	wp_enqueue_script('asfi-ajax-script', plugin_dir_url(__FILE__) . 'js/asfi-ajax.js', array(), null, true);
	wp_localize_script('asfi-ajax-script', 'asfi_ajax_object', array(
		'ajax_url' => admin_url('admin-ajax.php'),
		'nonce'    => wp_create_nonce('asfi_ajax_nonce'),
	));
}


// Function for adding settings link to the plugin overview page
function asfi_add_settings_link($links) {
	$settings_link = '<a href="tools.php?page=automatically-set-featured-images">' . __('Settings', 'automatically-set-featured-images') . '</a>';
	array_push($links, $settings_link);
	return $links;
}

// Function to load plugin textdomain for internationalization
function asfi_load_plugin_textdomain() {
	load_plugin_textdomain('automatically-set-featured-images', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}

// Function for adding the plugin menu under Tools
function asfi_plugin_setup_menu() {
	add_management_page(
		__('Automatically Set Featured Images', 'automatically-set-featured-images'),
		__('Automatically Set Featured Images', 'automatically-set-featured-images'),
		'manage_options',
		'automatically-set-featured-images',
		'asfi_admin_page'
	);
}

// Function for rendering the admin page
function asfi_admin_page() {
	// Get the currently selected formats from options or use defaults
	$selected_formats = get_option('asfi_selected_formats', ['jpg', 'jpeg', 'webp']); // Default formats
?>
	<div class="wrap">
		<h1><?php _e('Automatically Set Featured Images for All Posts', 'automatically-set-featured-images'); ?></h1>
		<form id="asfi-form" method="post" action="">
			<input type="hidden" name="action" value="asfi_process_batch">
			<?php wp_nonce_field('asfi_process_batch'); ?>
			<label for="asfi_post_type"><?php _e('Post Type:', 'automatically-set-featured-images'); ?></label>
			<select name="post_type" id="asfi_post_type">
				<option value="post"><?php _e('Posts', 'automatically-set-featured-images'); ?></option>
				<option value="page"><?php _e('Pages', 'automatically-set-featured-images'); ?></option>
				<!-- Add other post types as needed -->
			</select>
			<p><?php _e('Select image formats to be set as featured images:', 'automatically-set-featured-images'); ?></p>
			<div>
				<label>
					<input type="checkbox" name="formats[]" value="jpg" <?php checked(in_array('jpg', $selected_formats)); ?> /> .jpg
				</label>
				<label>
					<input type="checkbox" name="formats[]" value="jpeg" <?php checked(in_array('jpeg', $selected_formats)); ?> /> .jpeg
				</label>
				<label>
					<input type="checkbox" name="formats[]" value="webp" <?php checked(in_array('webp', $selected_formats)); ?> /> .webp
				</label>
				<label>
					<input type="checkbox" name="formats[]" value="png" <?php checked(in_array('png', $selected_formats)); ?> /> .png
				</label>
				<label>
					<input type="checkbox" name="formats[]" value="avif" <?php checked(in_array('avif', $selected_formats)); ?> /> .avif
				</label>
			</div>
			<br><br>
			<input type="submit" id="asfi-set-featured-img" class="button button-primary" value="<?php _e('Set Featured Images', 'automatically-set-featured-images'); ?>">
		</form>
		<div id="asfi-result"></div>
		<div style="margin-top:20px;">
			<a href="https://paypal.me/nextab/5EUR" class="button button-secondary"><?php _e('Donate', 'automatically-set-featured-images'); ?></a>
			<p><?php _e('This plugin was created with the help of ChatGPT. Help me pay for my subscription. ;-)', 'automatically-set-featured-images'); ?></p>
		</div>
	</div>
<?php
}

// AJAX handler function for processing the batches
function asfi_process_batch() {
	// Verify nonce for security
	check_ajax_referer('asfi_process_batch', 'nonce');

	// Save the selected formats in the options table
	if (isset($_POST['formats'])) {
		update_option('asfi_selected_formats', $_POST['formats']);
	}
	$selected_formats = get_option('asfi_selected_formats', ['jpg', 'jpeg', 'webp']);

	$post_type = isset($_POST['post_type']) ? $_POST['post_type'] : 'post';
	$batch_size = 30; // Fixed batch size

	$args = array(
		'post_type'      => $post_type,
		'posts_per_page' => $batch_size,
		'post_status'    => 'publish',
		'meta_query'     => array(
			array(
				'key'     => '_thumbnail_id',
				'compare' => 'NOT EXISTS'
			),
		),
	);

	$all_posts = get_posts($args);
	$base_url = wp_upload_dir()['baseurl']; // Get the base upload directory URL
	$updated_count = 0;

	foreach ($all_posts as $single_post) {
		preg_match_all('/<img .*?src=["\'](.*?)["\']/', $single_post->post_content, $matches);
		if (!empty($matches) && !empty($matches[1])) {
			foreach ($matches[1] as $image_url) {
				// Remove any query strings from the URL
				$clean_image_url = strtok($image_url, '?');
				$clean_image_url = preg_replace('/-scaled(\.[^.]+)$/', '$1', $clean_image_url); // Remove -scaled if exists
				$file_format = strtolower(pathinfo($clean_image_url, PATHINFO_EXTENSION));
				if (in_array($file_format, $selected_formats) && strpos($image_url, $base_url) !== false) {
					// Check for both the original URL and the scaled URL
					$attachment_id = attachment_url_to_postid($clean_image_url);
					if (!$attachment_id) {
						// Check for a scaled version of the image
						$scaled_image_url = preg_replace('/(\.[^.]+)$/', '-scaled$1', $clean_image_url);
						$attachment_id = attachment_url_to_postid($scaled_image_url);
					}
					if ($attachment_id) {
						if (set_post_thumbnail($single_post->ID, $attachment_id)) {
							$updated_count++;
							break; // Break the loop to avoid setting the last image found.
						} else {
							error_log("Failed to set featured image for post ID: {$single_post->ID}");
						}
					} else {
						error_log("Attachment ID not found for image: {$clean_image_url}");
					}
					break; // Stop looking for images if one has already been processed
				}
			}
		} else {
			error_log("No images found in the content for post ID: {$single_post->ID}");
		}
	}
		
	error_log("Number of posts updated with featured image: {$updated_count}");
	wp_send_json_success(array('updated_count' => $updated_count));
}
