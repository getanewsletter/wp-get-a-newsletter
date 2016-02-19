<?php
/*
Plugin Name: Get a Newsletter
Plugin URI: http://www.getanewsletter.com/
Description: Plugin to add subscription form to the site using widgets.
Version: 1.9.4
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


function newsletter_plugin_check_version($plugin_data) {
    $version = get_option('newsletter_plugin_version');

    update_option('newsletter_plugin_version', $plugin_data['Version']);

    if(empty($version)) {
        newsletter_plugin_upgrade_prior_v19();
    }
}


function newsletter_stdout($args) {
    file_put_contents('php://stdout', print_r($args, true));
}


function newsletter_plugin_upgrade_prior_v19() {
    $settings = array(
        'password' => get_option('newsletter_pass'),
        'username' => get_option('newsletter_user'),
        'apikey' => get_option('newsletter_apikey'),
        'blogname' => get_option('blogname'),
    );

    $settings['verify_mail_text'] = "Hello!"
        . "\n\nYou have been added as a subscriber to ##list_name##.\n"
        . "Before you can receive our newsletter, please confirm your \n"
        . "subscription by clicking the following link:"
        . "\n\n"
        . "##confirmation_link##"
        . "\n\n"
        . "Best regards"
        . "\n"
        . "##sendername##\n\n"
        . "Ps. If you don't want our newsletter in the future, you can \n"
        . "easily unsubscribe with the link provided in every newsletter.";
    $settings['verify_mail_subject'] = "Welcome as a subscriber to ##list_name##";

    update_option('newsletter_default_verify_mail_subject', $settings['verify_mail_subject']);
    update_option('newsletter_default_verify_mail_text', $settings['verify_mail_text']);

    // If we have a password, we can start with checking if it works with v3 API
    if(!empty($settings['password'])) {
        $api = new GAPI('', $settings['password']);
        if($api->check_login()) {
            newsletter_upgrade_create_subscription_form($settings, $api);
            return;
        }
    }

    // Try authenticate user
    if(!empty($settings['password']) && !empty($settings['username'])) {
        newsletter_migrate_from_old_tool($settings);
        return;
    }
}


function newsletter_migrate_from_old_tool($settings) {
    $api = new GAPI('', '');
    if($api->login_with_password($settings['username'], $settings['password']) == false) {
        update_option('newsletter_pass', '');
        update_option('newsletter_user', '');
        update_option('newsletter_apikey', '');
        return;
    }

    $token_name = 'wp-'.strtolower(get_option('blogname'));
    $found_token = false;

    // Try to reuse token if possible
    foreach($api->token_get() as $token) {
        if($token->name == $token_name) {
            $found_token = true;
            $api = new GAPI('', $token->key);
            break;
        }
    }

    // Create a token an store it
    if(!$found_token) {
        $api->token_create($token_name);
    }

    // Ensure that we can login
    if($api->check_login() == false) {
        newsletter_notify_activation_trouble(__('Could not login'));
        return;
    }

    // This catches both cases (create, reuse)
    update_option('newsletter_pass', $api->password);
    // clear old unused stuff
    delete_option('newsletter_user');
    delete_option('newsletter_apikey');
    newsletter_upgrade_create_subscription_form($settings, $api);

}


/**
 * Placeholder function
 */
function newsletter_notify_activation_trouble($message) {}


function newsletter_upgrade_create_subscription_form($settings, $api) {
    $widgets = get_option('widget_getanewsletter');

    foreach($widgets as $index => $widget) {
        if(!empty($widget['newskey']) && $api->newsletter_get($widget['newskey'])) {
            $list = $api->result;

            $form = array(
                'name' => 'wp-' . strtolower($settings['blogname']) . '-' . strtolower($widget['title']),
                'lists' => [$list->hash],
                'sender' => $list->sender,
                'email' => $list->email,
                'attributes' => [],
                'verify_mail_text' => $settings['verify_mail_text'],
                'verify_mail_subject' => $settings['verify_mail_subject']
            );

            if(!empty($widget['fname'])) {
                $form['first_name'] = true;
            }
            if(!empty($widget['lname'])) {
                $form['last_name'] = true;
            }

            $api->subscription_form_create($form);

            newsletter_stdout($api->body);

            if($api->result) {
                unset($widgets[$index]['newskey']);
                $widgets[$index]['key'] = $api->body->key;
                $widgets[$index]['alias'] = $api->body->name;
                $widgets[$index]['form_link'] = $api->body->form_link;
            }
        }
    }
    newsletter_stdout($widgets);
    update_option('widget_getanewsletter', $widgets);
}


/* WIDGET */

class GetaNewsletter extends WP_Widget {
    /** constructor */
    function __construct() {
        parent::__construct(false, $name = 'Get a Newsletter');
    }

    static function install() {
        newsletter_plugin_check_version(get_plugin_data(__FILE__));
    }

    /** @see WP_Widget::widget */
    function widget($args, $instance) {
        $apikey = get_option('newsletter_apikey');

        extract( $args );
        $title = apply_filters('widget_title', empty($instance['title']) ? "" : $instance['title']);
        $key = esc_attr(empty($instance['key']) ? "" : $instance['key']);
        $form_link = empty($instance['form_link']) ? "" : $instance['form_link'];
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
                        <label for="id_first_name"><?php if($fnametxt != ''): _ex($fnametxt); else: _ex('First name', 'first_name'); endif; ?></label><br />
                        <input id="id_first_name" type="text" class="text" name="id_first_name" />
                    </p>
                    <?php endif; ?>
                    <?php if($lname): ?>
                    <p>
                        <label for="id_last_name"><?php if($lnametxt != ''): _ex($lnametxt); else: _ex('Last name', 'last_name'); endif; ?></label><br />
                        <input id="id_last_name" type="text" class="text" name="id_last_name" />
                    </p>
                    <?php endif; ?>
                    <p>
                        <label for="id_email"><?php _ex('E-mail', 'email'); ?></label><br />
                        <input id="id_email" type="text" class="text" name="id_email" />
                    </p>
                    <p>
                        <input type="hidden" name="form_link" value="<?php echo $form_link; ?>" id="id_form_link" />
                        <input type="hidden" name="key" value="<?php echo $key; ?>" id="id_key" />
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
        if(empty($new_instance['form_link']) || $new_instance['form_link'] != $old_instance['form_link']) {
            $api = new GAPI('', get_option('newsletter_pass'));
            $api->subscription_form_get($new_instance['key']);
            $new_instance['form_link'] = $api->body->form_link;
        }

        return $new_instance;
    }

    /** @see WP_Widget::form */
    function form($instance) {
        newsletter_stdout($instance);
        $news_pass = get_option('newsletter_pass');

        if($news_pass) {

            $news_con = new GAPI('', $news_pass);

            if ($news_con->check_login()) {
                $title = esc_attr(empty($instance['title']) ? "" : $instance['title']);
                $key = esc_attr(empty($instance['key']) ? null : $instance['key']);
                $fname = esc_attr(empty($instance['fname']) ? "" : $instance['fname']);
                $fnametxt = esc_attr(empty($instance['fnametxt']) ? "" : $instance['fnametxt']);
                $lname = esc_attr(empty($instance['lname']) ? "" : $instance['lname']);
                $lnametxt = esc_attr(empty($instance['lnametxt']) ? "" : $instance['lnametxt']);
                $submittext = esc_attr(empty($instance['submittext']) ? "" : $instance['submittext']);

                if($key) {
                    if($news_con->subscription_form_get($key)) {
                        $form = $news_con->body;
                        $verify_mail_text = $form->verify_mail_text;
                        $verify_mail_subject = $form->verify_mail_subject;
                    }
                } else {
                    $verify_mail_text = get_option('newsletter_default_verify_mail_text');
                    $verify_mail_subject = get_option('newsletter_default_verify_mail_subject');
                }

                print ""
                    ."<p>"
                    ."  <label for=\"{$this->get_field_id('title')}\">" . __('Title:') ."</label>"
                    ."  <input class=\"widefat\""
                    ."      id=\"{$this->get_field_id('title')}\""
                    ."      name=\"{$this->get_field_name('title')}\""
                    ."      type=\"text\" value=\"{$title}\" />"
                    ."</p>";

                print ""
                    ."<p>"
                    ."  <label for=\"{$this->get_field_id('key')}\">" . __('Subscription form', 'newsletter') . ":</label>";

                    if ($news_con->subscription_form_list()) {
                        print "<select class=\"widefat\" id=\"{$this->get_field_id("key")}\" name=\"{$this->get_field_name("key")}\">";

                        foreach($news_con->body->results as $form) {
                            $selected_list = $key == $form->key ? "selected=\"selected\"" : "";
                            print "<option {$selected_list} value=\"{$form->key}\">{$form->name}</option>";
                        }

                        print "</select>";
                    }
                    else {
                        print __('Subscription forms not created yet, create a form <a href=\"https://app.getanewsletter.com/api/forms/\">here</a>');
                    }
                print ""
                    ."</p>";
                ?>

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

register_activation_hook(__FILE__, array('GetaNewsletter', 'install'));

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
        jQuery(document).ready(function() {
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
