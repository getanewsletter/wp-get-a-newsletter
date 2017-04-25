<?php
/*
Plugin Name: Get a Newsletter
Plugin URI: http://www.getanewsletter.com/
Description: Plugin to add subscription form to the site using widgets.
Version: 2.1.0
Author: getanewsletter
Author URI: http://www.getanewsletter.com/
License: GPLv2 or later
Text Domain: getanewsletter
Domain Path: /languages/
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
        <hr>
        <h3>Messages</h3>
        <p>Add messages for successful and failed submissions. This will be displayed to the user when interacting with the form.</p>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">Successful submission:</th>
                <td><input type="text" class="regular-text" name="newsletter_msg_success" value="<?php echo get_option('newsletter_msg_success', 'Thanks for your subscription.'); ?>" /></td>
            </tr>
            <tr valign="top">
                <th scope="row">Error:</th>
                <td>
                    <input type="text" class="regular-text" name="error_msg" value="<?php echo get_option('error_msg', 'An error occured.'); ?>" />
                </td>
            </tr>
        </table>
        <hr>
        <h3>Design</h3>
        <p>Submit button positioning</p>
        <div style="padding-bottom:9px;">
            <h4 style="display:inline;">Left</h4>
            <input type="radio" class="regular-text" name="align" value="left" <?php checked('left',get_option('align')); ?> />
        </div>
        <div style="padding-bottom:9px;">
            <h4 style="display:inline;">Right</h4>
            <input type="radio" class="regular-text" name="align" value="right" <?php checked('right',get_option('align')); ?> />
        </div>
        <div style="padding-bottom:9px;">
            <h4 style="display:inline;">Center</h4>
            <input type="radio" class="regular-text" name="align" value="center" <?php checked('center',get_option('align')); ?> />
        </div>
        <p>Submit button size</p>
        <div style="padding-bottom:9px;">
            <h4 style="display:inline;">Full width</h4>
            <input type="checkbox" class="regular-text" name="fullwidth" value="100%" <?php checked('100%',get_option('fullwidth')); ?> />
        </div>
        <hr>

        <input type="hidden" name="action" value="update" />
        <input type="hidden" name="page_options" value="newsletter_user,newsletter_pass,newsletter_apikey,newsletter_msg_success,newsletter_msg_confirm,error_msg,align,fullwidth" />
        <p class="submit">
            <input type="submit" class="button-primary" value="<?php _e('Save Changes', 'getanewsletter') ?>" />
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
        newsletter_notify_activation_trouble(__('Could not login', 'getanewsletter'));
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
                'lists' => array($list->hash),
                'sender' => $list->sender,
                'email' => $list->email,
                'attributes' => array(),
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

            if($api->result) {
                unset($widgets[$index]['newskey']);
                $widgets[$index]['key'] = $api->body->key;
                $widgets[$index]['alias'] = $api->body->name;
                $widgets[$index]['form_link'] = $api->body->form_link;
            }
        }
    }

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
                echo $before_title . $title . $after_title;

                print ""
                    ."<form method=\"post\" autocomplete=\"off\" class=\"newsletter-signup\" enctype=\"multipart/form-data\">"
                    ."  <input type=\"hidden\" name=\"action\" value=\"getanewsletter_subscribe\" />";

                if($fname) {
                    print ""
                        ."<p>"
                        ."  <label for=\"id_first_name\">" . (!empty($fnametxt) ? $fnametxt : __('First name', 'getanewsletter')) . "</label>"
                        ."  <input id=\"id_first_name\" type=\"text\" class=\"text\" name=\"id_first_name\" />"
                        ."</p>";
                }

                if($lname) {
                    print ""
                        ."<p>"
                        ."  <label for=\"id_last_name\">" . (!empty($lnametxt) ? $lnametxt : __('Last name', 'getanewsletter')) . "</label>"
                        ."  <input id=\"id_last_name\" type=\"text\" class=\"text\" name=\"id_last_name\" />"
                        ."</p>";
                }

                print ""
                    ."  <p>"
                    ."      <label for=\"id_email\">". __('E-mail', 'getanewsletter') . (($fname || $lname) ? "*" : "") ."</label>"
                    ."      <input id=\"id_email\" type=\"email\"  class=\"text\" name=\"id_email\" />"

                    ."  </p>";

                print ""
                    ."  <p>"
                    ."      <span id=\"message_text\"></span>"
                    ."  </p>";

                print ""
                    ."  <p>"
                    ."      <input type=\"hidden\" name=\"form_link\" value=\"{$form_link}\" id=\"id_form_link\" />"
                    ."      <input type=\"hidden\" name=\"key\" value=\"{$key}\" id=\"id_key\" />"
                    ."  </p>";

                if (get_option('fullwidth') == '100%') {

                    print ""
                        ."<div>"
                        ."      <button style=\"width:100%;\" type=\"submit\" id=\"id_submit\" value=\""
                        .       ($submittext != '' ?  __($submittext, 'getanewsletter') : __('Subscribe', 'getanewsletter')) . "\"> <i style=\"margin-right: 7px;\" class=\"fa fa-envelope\" aria-hidden=\"true\"></i>"

                        .       ($submittext != '' ?  __($submittext, 'getanewsletter') : __('Subscribe', 'getanewsletter')) . "</button>"
                        ."</div>";
                } else {
                    print ""
                        ."<div style=\"text-align:" .get_option('align'). "\" >"
                        ."      <button type=\"submit\" id=\"id_submit\" value=\""
                        .       ($submittext != '' ?  __($submittext, 'getanewsletter') : __('Subscribe', 'getanewsletter')) . "\"> <i style=\"margin-right: 7px;\" class=\"fa fa-envelope\" aria-hidden=\"true\"></i>"

                        .       ($submittext != '' ?  __($submittext, 'getanewsletter') : __('Subscribe', 'getanewsletter')) . "</button>"
                        ."</div>";
                }

                print ""
                    ."<div style=\"padding-top:9px;\" id=\"respond_message\">"
                    ."&nbsp"
                    ."</div>";

                print ""
                    ."</form>";

        echo $after_widget;
    }

    /** @see WP_Widget::update */
    function update($new_instance, $old_instance) {
        if(empty($new_instance['key'])) {
            return false;
        }

        $api = new GAPI('', get_option('newsletter_pass'));
        $api->subscription_form_get($new_instance['key']);

        $new_instance['form_link'] = $api->body->form_link;

        // If submittext is empty we take the one from app, otherwise we use local stored.
        if (empty($new_instance['submittext'])) {
            $new_instance['submittext'] = $api->body->button_text;
        }

        return $new_instance;
    }

    /** @see WP_Widget::form */
    function form($instance) {
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
                    ."  <label for=\"{$this->get_field_id('title')}\">" . __('Title', 'getanewsletter') .":</label>"
                    ."  <input class=\"widefat\""
                    ."      id=\"{$this->get_field_id('title')}\""
                    ."      name=\"{$this->get_field_name('title')}\""
                    ."      type=\"text\" value=\"{$title}\" />"
                    ."</p>";

                print ""
                    ."<p>"
                    ."  <label for=\"{$this->get_field_id('key')}\">" . __('Subscription form', 'getanewsletter') . ":</label>";

                    if ($news_con->subscription_form_list()) {
                        print "<select class=\"widefat\" id=\"{$this->get_field_id("key")}\" name=\"{$this->get_field_name("key")}\">";
                        print "<option></option>";
                        foreach($news_con->body->results as $form) {
                            $selected_list = $key == $form->key ? "selected=\"selected\"" : "";
                            print "<option {$selected_list} value=\"{$form->key}\">{$form->name}</option>";
                        }

                        print "</select>";
                    }
                    else {
                        print __("Subscription forms not created yet, create a form <a href=\"https://app.getanewsletter.com/api/forms/\">here</a>", 'getanewsletter');
                    }

                print "</p>";

                print ""
                    ."<p>"
                    ."  <input class=\"checkbox\" id=\"{$this->get_field_id('fname')}\""
                    ."      name=\"{$this->get_field_name('fname')}\""
                    ."      type=\"checkbox\" " . (!empty($fname) ? "checked=\"checked\"" : "") . " />"
                    ."  <label for=\"{$this->get_field_id('fname')}\">" . __('Ask for First Name?<br>Label for First Name', 'getanewsletter'). "</label>"
                    ."  <input size=\"15\""
                    ."      id=\"{$this->get_field_id('fnametxt')}\""
                    ."      name=\"{$this->get_field_name('fnametxt')}\""
                    ."      type=\"text\" value=\"{$fnametxt}\" />"
                    ."</p>";

                print ""
                    ."<p>"
                    ."  <input class=\"checkbox\""
                    ."      id=\"{$this->get_field_id('lname')}\""
                    ."      name=\"{$this->get_field_name('lname')}\""
                    ."      type=\"checkbox\" " . (!empty($lname) ? "checked=\"checked\"" : "") . " />"
                    ."  <label for=\"{$this->get_field_id('lname')}\">" . __('Ask for Last Name?<br>Label for Last Name', 'getanewsletter') . "</label>"
                    ."  <input size=\"15\""
                    ."      id=\"{$this->get_field_id('lnametxt')}\""
                    ."      name=\"{$this->get_field_name('lnametxt')}\""
                    ."      type=\"text\" value=\"{$lnametxt}\" />"
                    ."</p>";

                print ""
                    ."<p>"
                    ."  <label for=\"{$this->get_field_id('submittext')}\">" . __('Submit text', 'getanewsletter') . ":</label>"
                    ."  <input class=\"widefat\""
                    ."      id=\"{$this->get_field_id('submittext')}\""
                    ."      name=\"{$this->get_field_name('submittext')}\""
                    ."      type=\"text\" value=\"{$submittext}\" />"
                    ."</p>";

            } else {
                print '<p>' . __('Wrong Login details. Enter correct details in Get a Newsletter options page.', 'getanewsletter') . '</p>';
            }
        } else {
            print '<p>' . __('Enter required details in Get a Newsletter options page.', 'getanewsletter') . '</p>';
        }
    }
}

add_action('widgets_init', create_function('', 'return register_widget("GetaNewsletter");'));

register_activation_hook(__FILE__, array('GetaNewsletter', 'install'));
function getanewsletter_load_plugin_textdomain() {
    load_plugin_textdomain('getanewsletter', FALSE, dirname(plugin_basename(__FILE__)).'/languages/');
}
add_action( 'plugins_loaded', 'getanewsletter_load_plugin_textdomain' );

/* CSS */

add_action('wp_head', 'news_css');

function news_css()
{?>
        <link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">
    <?php
}

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

            jQuery.fn.extend({
               qcss: function(css) {
                  return jQuery(this).queue(function(next) {
                     jQuery(this).css(css);
                     next();
                  });
               }
            });

            function is_email_valid(email) {
                var re = /[a-z0-9!#$%&'*+/=?^_`{|}~-]+(?:\.[a-z0-9!#$%&'*+/=?^_`{|}~-]+)*@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?/;
                return re.test(email);
            }

            jQuery("#id_email").keyup(function () {
                if (is_email_valid(jQuery("#id_email").val())){
                    jQuery("#id_email").css('border-color','green');
                } else {
                    jQuery("#id_email").css('border-color','red');
                }
            });

            jQuery('.newsletter-signup').submit(function() {
                var form = jQuery(this);
                var data = form.serialize();
                var is_form_valid = false;
                var success_message = '<?php echo get_option('newsletter_msg_success', 'Thank you for subscribing to our newsletters.'); ?>';
                var error_message = '<?php echo get_option('error_msg', 'Something wrong on our side. Please contact us directly if error continues.'); ?>';

                if (is_email_valid(jQuery("#id_email").val())) {
                    jQuery.ajax({
                        'type': 'POST',
                        'url': '<?php echo admin_url('admin-ajax.php'); ?>',
                        'data': data,
                        'cache': false,
                        'beforeSend': function(message) {
                            jQuery("#respond_message").html("&nbsp");
                        },
                        'success': function(response) {
                            if (jQuery("#id_first_name").val()) {
                                jQuery("#id_first_name").val("");
                            }
                            if (jQuery("#id_last_name").val()) {
                                jQuery("#id_last_name").val("");
                            }
                            jQuery("#respond_message").html(success_message);
                            jQuery("#id_email").val("");
                            jQuery("#id_email").qcss({ 'border-color': 'unset' });
                        },
                        'error': function(response) {
                            resultWrapper.append(
                                resultContainer.removeClass('news-success')
                                    .addClass('news-error')
                                    .html(error_msg));
                        }
                    });
                } else {
                    jQuery("#id_email").css("border-color", "red");
                }


                return false;
            });
        });
        //]]>
    </script>
    <?php
}
?>
