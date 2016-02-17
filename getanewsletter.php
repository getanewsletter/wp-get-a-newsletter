<?php
/*
Plugin Name: Get a Newsletter
Plugin URI: http://www.getanewsletter.com/
Description: Plugin to add subscription form to the site using widgets.
Version: 1.9.3
Author: Get a Newsletter
Author URI: http://www.getanewsletter.com/
License: GPLv2 or later
Text Domain: getanewsletter
*/

require_once("GAPI.class.php");

/* ADMIN PANEL */

function newsletter_menu() {
  add_options_page('Get a Newsletter', 'Get a Newsletter', 'administrator', 'newsletter', 'newsletter_options');
}

function newsletter_options() {
    $news_pass = get_option('newsletter_pass');
    $ok = false;
    if($news_pass) {
        $conn = new GAPI('', $news_pass);
        $ok = $conn->check_login();
    }
?>
	<div class="wrap">

	<form method="post" action="options.php">

		<h2>Get a Newsletter Options</h2>

		<h3>Account Information</h3>
		<p>Enter your <a href="http://www.getanewsletter.com" target=_blank>Get a Newsletter</a> API Token here. Don't have an account? Register one for free at the <a href="http://www.getanewsletter.com" target=_blank>website</a>.</p>

		<?php wp_nonce_field('update-options'); ?>
		<table class="form-table">
			<tr valign="top">
				<th scope="row">API Token</th>
				<td><input type="password" name="newsletter_pass" value="<?php echo get_option('newsletter_pass'); ?>" /></td>
			</tr>
            <tr>
                <th scope="row">Login status:</th>
                <td><?php echo $ok == true ? 'Success' : '<span style="color: red;">Failed, please check your API Token</span>';?></td>
            </tr>
		</table>

		<h3>Messages</h3>
		<p>Here you can enter friendly messages that will be displayed on user-end when they interact with the form.</p>
		<table class="form-table">
			<tr valign="top">
				<th scope="row">Successfull submission:</th>
				<td><input type="text" class="regular-text" name="newsletter_msg_success" value="<?php echo get_option('newsletter_msg_success', 'Thank you for subscribing to our newsletters.'); ?>" /></td>
			</tr>
			<tr valign="top">
				<th scope="row">Message - 505:</th>
				<td>
					<input type="text" class="regular-text" name="newsletter_msg_505" value="<?php echo get_option('newsletter_msg_505', 'Invalid e-mail'); ?>" />
					<br/> <span class="small">Invalid email address</span>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">Message - 512:</th>
				<td>
					<input type="text" class="regular-text" name="newsletter_msg_512" value="<?php echo get_option('newsletter_msg_512', 'Subscription already exists'); ?>" />
					<br/> <span class="small">Subscription already exists</span>
				</td>
			</tr>
		</table>

		<input type="hidden" name="action" value="update" />
		<input type="hidden" name="page_options" value="newsletter_user,newsletter_pass,newsletter_apikey,newsletter_msg_success,newsletter_msg_confirm,newsletter_msg_505,newsletter_msg_512" />
		<p class="submit">
			<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
		</p>
	</form>
<?php
}

add_action('admin_menu', 'newsletter_menu');


/* WIDGET */

class GetaNewsletter extends WP_Widget {
	/** constructor */
	function __construct() {
		parent::__construct(false, $name = 'Get a Newsletter');
	}

	/** @see WP_Widget::widget */
	function widget($args, $instance) {
		$apikey = get_option('newsletter_apikey');

		extract( $args );
		$title = apply_filters('widget_title', empty($instance['title']) ? "" : $instance['title']);
		$newskey = esc_attr(empty($instance['newskey']) ? "" : $instance['newskey']);
		$fname = esc_attr(empty($instance['fname']) ? "" : $instance['fname']);
		$fnametxt = esc_attr(empty($instance['fnametxt']) ? "" : $instance['fnametxt']);
		$lname = esc_attr(empty($instance['lname']) ? "" : $instance['lname']);
		$lnametxt = esc_attr(empty($instance['lnametxt']) ? "" : $instance['lnametxt']);
		$submittext = esc_attr(empty($instance['submittext']) ? "" : $instance['submittext']);
		?>
		<?php echo $before_widget; ?>
		  <?php if ( $title )
				echo $before_title . $title . $after_title; ?>

				<form method="post" class="newsletter-signup" action="javascript:alert('success!');" enctype="multipart/form-data">
					<input type="hidden" name="action" value="getanewsletter_subscribe" />
					<?php if($fname): ?>
					<p>
						<label for="id_first_name"><?php if($fnametxt != ''): _e($fnametxt); else: _e('First name'); endif; ?></label><br />
						<input id="id_first_name" type="text" class="text" name="id_first_name" />
					</p>
					<?php endif; ?>
					<?php if($lname): ?>
					<p>
						<label for="id_last_name"><?php if($lnametxt != ''): _e($lnametxt); else: _e('Last name'); endif; ?></label><br />
						<input id="id_last_name" type="text" class="text" name="id_last_name" />
					</p>
					<?php endif; ?>
					<p>
						<label for="id_email"><?php _e('E-mail'); ?></label><br />
						<input id="id_email" type="text" class="text" name="id_email" />
					</p>
					<p>
						<input type="hidden" name="newsletter" value="<?php echo $newskey; ?>" id="id_newsletter" />
						<input type="submit" value="<?php if($submittext != ''): _e($submittext); else: _e('Subscribe'); endif; ?>" />

						<img src="<?php echo WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),'',plugin_basename(__FILE__)); ?>loading.gif" alt="loading" class="news-loading" />
					</p>
				</form>
				<div class="news-note"></div>

		<?php echo $after_widget; ?>
		<?php
	}

	/** @see WP_Widget::update */
	function update($new_instance, $old_instance) {
		return $new_instance;
	}

	/** @see WP_Widget::form */
	function form($instance) {
		$news_pass = get_option('newsletter_pass');

		if($news_pass)
		{

			$news_con = new GAPI('', $news_pass);

			if ($news_con->check_login())
			{
				$title = esc_attr(empty($instance['title']) ? "" : $instance['title']);
				$newskey = esc_attr(empty($instance['newskey']) ? "" : $instance['newskey']);
				$fname = esc_attr(empty($instance['fname']) ? "" : $instance['fname']);
				$fnametxt = esc_attr(empty($instance['fnametxt']) ? "" : $instance['fnametxt']);
				$lname = esc_attr(empty($instance['lname']) ? "" : $instance['lname']);
				$lnametxt = esc_attr(empty($instance['lnametxt']) ? "" : $instance['lnametxt']);
				$submittext = esc_attr(empty($instance['submittext']) ? "" : $instance['submittext']);
				?>
				<p>
					<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
					<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
				</p>
				<p>
					<label for="<?php echo $this->get_field_id('newskey'); ?>"><?php _e('Newsletter:'); ?></label>
					<?php
						if ($news_con->newsletters_show())
						{
							echo '<select class="widefat" id='.$this->get_field_id("newskey").' name="'.$this->get_field_name("newskey").'">';
							foreach($news_con->result as $newsitem)
							{
								$selected_list = "";
								if($newskey == $newsitem["list_id"]) $selected_list = 'selected="selected"';
								echo '<option '.$selected_list.' value="'.$newsitem["list_id"].'">'.$newsitem["newsletter"].'</option>';
							}
							echo '</select>';
						}
						else {
							print $news_con->show_errors();
						}
					?>
				</p>
				<p>
					<input class="checkbox" id="<?php echo $this->get_field_id('fname'); ?>" name="<?php echo $this->get_field_name('fname'); ?>" type="checkbox" <?php if($fname) echo 'checked="checked"'; ?> />
					<label for="<?php echo $this->get_field_id('fname'); ?>"><?php _e('Ask for First Name?<br>Label for First Name:'); ?></label>
					<input size="15" id="<?php echo $this->get_field_id('fnametxt'); ?>" name="<?php echo $this->get_field_name('fnametxt'); ?>" type="text" value="<?php echo $fnametxt; ?>" />
				</p>
				<p>
					<input class="checkbox" id="<?php echo $this->get_field_id('lname'); ?>" name="<?php echo $this->get_field_name('lname'); ?>" type="checkbox" <?php if($lname) echo 'checked="checked"'; ?> />
					<label for="<?php echo $this->get_field_id('lname'); ?>"><?php _e('Ask for Last Name?<br>Label for Last Name:'); ?></label>
					<input size="15" id="<?php echo $this->get_field_id('lnametxt'); ?>" name="<?php echo $this->get_field_name('lnametxt'); ?>" type="text" value="<?php echo $lnametxt; ?>" />
				</p>
				<p>
					<label for="<?php echo $this->get_field_id('submittext'); ?>"><?php _e('Submit button text:'); ?></label>
					<input class="widefat" id="<?php echo $this->get_field_id('submittext'); ?>" name="<?php echo $this->get_field_name('submittext'); ?>" type="text" value="<?php echo $submittext; ?>" />
				</p>
				<?php
			}
			else
			{
				echo '<p>Wrong Login details. Enter correct details in Get a Newsletter options page.</p>';
			}
		}
		else
		{
			echo '<p>Enter required details in Get a Newsletter options page.</p>';
		}
	}
}

add_action('widgets_init', create_function('', 'return register_widget("GetaNewsletter");'));


/* AJAX */

add_action('wp_ajax_getanewsletter_subscribe', 'getanewsletter_subscribe');
add_action('wp_ajax_nopriv_getanewsletter_subscribe', 'getanewsletter_subscribe');

function getanewsletter_subscribe() {
	require_once(plugin_dir_path( __FILE__) . '/subscribe.php');
}

add_action('wp_head', 'news_js_ajax' );

function news_js_ajax()
{
	wp_print_scripts( array( 'jquery' ));
	$pluginurl = WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__));
	?>
	<script type="text/javascript">
		//<![CDATA[
		jQuery(document).ready(function()
		{
			jQuery('.news-loading').hide();

			jQuery('.newsletter-signup').submit(function() {
                var form = jQuery(this);
				var data = form.serialize();
                var resultContainer = jQuery('<span></span>');
                var resultWrapper = jQuery('.news-note');
                var spinner = jQuery('.news-loading');

				jQuery.ajax({
					'type': 'POST',
					'url': '<?php echo admin_url('admin-ajax.php'); ?>',
					'data': data,
					'cache': false,
					'beforeSend': function(message) {
						spinner.show();
					},
					'success': function(response) {
                        spinner.hide();
						resultWrapper.append(
                            resultContainer.addClass('news-success')
                                .removeClass('news-error')
                                .html(response.message));
                        jQuery('.newsletter-signup').hide();
					},
					'error': function() {
						spinner.hide();
                        resultWrapper.append(
                            resultContainer.removeClass('news-success')
                                .addClass('news-error')
                                .html(response.message));
					}
				});

				return false;
			});
		});
		//]]>
	</script>
	<?php
}
?>
