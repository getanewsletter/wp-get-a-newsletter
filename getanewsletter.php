<?php
/*
Plugin Name: Get a Newsletter
Plugin URI: http://www.getanewsletter.com/
Description: Plugin to add subscription form to the site using widgets.
Version: 2.0.6
Author: getanewsletter
Author URI: http://www.getanewsletter.com/
License: GPLv2 or later
Text Domain: getanewsletter
Domain Path: /languages/
*/

require_once("GAPI.class.php");

class GetANewsletterException extends \RuntimeException { }

add_action('admin_init', function() {
    session_start();
    register_setting('newsletter', 'newsletter_user');
    register_setting('newsletter', 'newsletter_pass');
    register_setting('newsletter', 'newsletter_apikey');
    register_setting('newsletter', 'newsletter_msg_success');
    register_setting('newsletter', 'newsletter_msg_confirm');
    register_setting('newsletter', 'newsletter_msg_505');
    register_setting('newsletter', 'newsletter_msg_512');
});
/* ADMIN PANEL */

function newsletter_menu() {
  add_menu_page('Get a Newsletter', 'Get a Newsletter', 'administrator', 'newsletter', 'newsletter_options');
  add_submenu_page('newsletter', 'Subscription forms', 'Subscription forms', 'administrator', 'newsletter_subscription_forms', 'newsletter_subscription_forms');
  remove_submenu_page('newsletter', 'newsletter');
  add_submenu_page('newsletter', 'Settings', 'Settings', 'administrator', 'newsletter', 'newsletter_options');
}

function set_session_data($key, $data) {
    $_SESSION[$key] = $data;
}

function get_session_data($key, $default = null) {
    if (isset($_SESSION[$key])) {
        $data = $_SESSION[$key];
        unset($_SESSION[$key]);
    } else {
        $data = $default;
    }

    return $data;
}

function set_newsletter_flash_message($msg, $type) {
    set_session_data('newsletter_message', [
        'msg' => $msg,
        'type' => $type
    ]);
}


function get_newsletter_flash_message() {
    $message = get_session_data('newsletter_message');
    return $message;
}

function newsletter_subscription_forms() {
    $news_pass = get_option('newsletter_pass');
    if (!$news_pass) {
        display_api_key_form();
        return;
    }
    $action = $_GET['action'] ?? 'list';
    $connectionSucceeded = null;
    switch ($action) {
        case 'delete':
            try {
                if (isset($_GET['form_id'])) {
                    delete_subscription_form($_GET['form_id'], $news_pass);
                    set_newsletter_flash_message('Form has been deleted', 'updated');
                    wp_redirect('?page=newsletter_subscription_forms');
                    exit;
                } else {
                    throw new GetANewsletterException("Invalid form id");
                }
            } catch (GetANewsletterException $e) {
                set_newsletter_flash_message($e->getMessage(), 'error');
                wp_redirect('?page=newsletter_subscription_forms');
                exit;
            }
        case 'create':
            if (!empty($_POST)) {
                try {
                    $result = create_subscription_form($news_pass, $_POST);
                    if (empty($result)) {
                        wp_redirect('?page=newsletter_subscription_forms');
                        exit;
                    } else {
                        set_newsletter_flash_message('Please correct errors', 'error');
                        set_session_data('newsletter_form_data', $_POST);
                        set_session_data('newsletter_form_errors', $result);
                        wp_redirect('?page=newsletter_subscription_forms&action=create');
                        exit;
                    }
                } catch (\GetANewsletterException $e) {
                    set_newsletter_flash_message($e->getMessage(), 'error');
                }
            }
            $attributes = get_subscription_attributes($news_pass);
            $lists = get_subscription_lists($news_pass);
            display_subscription_form($attributes, $lists, []);
            break;
        case 'edit':
            $form_id = $_GET['form_id'] ?? '';
            if (!empty($_POST)) {
                try {
                    $result = update_subscription_form($news_pass, $_POST, $form_id);
                    if (empty($result)) {
                        wp_redirect('?page=newsletter_subscription_forms');
                        exit;
                    } else {
                        set_newsletter_flash_message('Please correct errors', 'error');
                        set_session_data('newsletter_form_data', $_POST);
                        set_session_data('newsletter_form_errors', $result);
                        wp_redirect('?page=newsletter_subscription_forms&action=edit&form_id=' . $form_id);
                        exit;
                    }
                } catch (\GetANewsletterException $e) {
                    set_newsletter_flash_message($e->getMessage(), 'error');
                }
            }
            try {
                $attributes = get_subscription_attributes($news_pass);
                $lists = get_subscription_lists($news_pass);
                $currentFormData = get_session_data('newsletter_form_data', transform_form_data(get_subscription_form($news_pass, $form_id)));
                display_subscription_form($attributes, $lists, $currentFormData, $form_id);
            } catch (\GetANewsletterException $e) {
                set_newsletter_flash_message($e->getMessage(), 'error');
                wp_redirect('?page=newsletter_subscription_forms');
            }
            break;
        case 'list':
        default:
            try {
                $forms = get_subscription_forms_list($news_pass);
                $connectionSucceeded = true;
            } catch (GetANewsletterException $e) {
                $forms = [];
                $connectionSucceeded = false;
            }
            display_subscription_forms_list($connectionSucceeded, $forms);
            break;
    }
}

function transform_form_data($form_data) {
    return [
        'attributes' => $form_data['attributes'],
        'sender_email' => $form_data['email'],
        'first_name' => (int)$form_data['first_name'],
        'list' => $form_data['lists'],
        'last_name' => (int)$form_data['last_name'],
        'name' => $form_data['name'],
        'sender_name' => $form_data['sender'],
        'confirmation_email_subject' => $form_data['verify_mail_subject'],
        'confirmation_email_message' => $form_data['verify_mail_text'],
        'next_url' => $form_data['next_url'],
        'button_text' => $form_data['button_text'],
        'send_advanced_settings' => 1
    ];
}

function create_subscription_form($news_pass, $postdata) {
    $conn = new GAPI('', $news_pass);
    if (!$conn->check_login()) {
        throw new \GetANewsletterException('Cannot connect to Get A Newsletter API');
    }

    wp_verify_nonce('newsletter-create-form');

    $data = [
        'attributes' => $postdata['attributes'] ?? [],
        'email' => $postdata['sender_email'] ?? '',
        'first_name' => isset($postdata['first_name']),
        'lists' => [ $postdata['list'] ?? '' ],
        'last_name' => isset($postdata['last_name']),
        'name' => $postdata['name'] ?? '',
        'sender' => $postdata['sender_name'] ?? '',
        'verify_mail_subject' => $postdata['confirmation_email_subject'] ?? '',
        'verify_mail_text' => $postdata['confirmation_email_message'] ?? '',
        'next_url' => $postdata['next_url'] ?? '',
        'button_text' => $postdata['button_text'] ?? '',
    ];

    $result = $conn->subscription_form_create($data);
    if ($result) {
        return [];
    }

    if ($conn->errorCode == 400) {
        return $conn->body;
    } else {
        throw new GetANewsletterException('Unknown error');
    }
}

function update_subscription_form($news_pass, $postdata, $form_id) {
    $conn = new GAPI('', $news_pass);
    if (!$conn->check_login()) {
        throw new \GetANewsletterException('Cannot connect to Get A Newsletter API');
    }

    wp_verify_nonce('newsletter-create-form');

    $data = [
        'attributes' => $postdata['attributes'] ?? [],
        'email' => $postdata['sender_email'] ?? '',
        'first_name' => isset($postdata['first_name']),
        'lists' => [ $postdata['list'] ?? '' ],
        'last_name' => isset($postdata['last_name']),
        'name' => $postdata['name'] ?? '',
        'sender' => $postdata['sender_name'] ?? '',
        'verify_mail_subject' => $postdata['confirmation_email_subject'] ?? '',
        'verify_mail_text' => $postdata['confirmation_email_message'] ?? '',
        'next_url' => $postdata['next_url'] ?? '',
        'button_text' => $postdata['button_text'] ?? '',
    ];

    $result = $conn->subscription_form_update($data, $form_id);
    if ($result) {
        return [];
    }

    if ($conn->errorCode == 400) {
        return $conn->body;
    } else {
        throw new GetANewsletterException('Unknown error');
    }
}

function display_newsletter_flash_message($message) {
    ?><div class="<?= $message['type'] ?> notice is-dismissable"><?= $message['msg'] ?></div><?php
}

function display_newsletter_form_errors(array $errors) {
    return '<span class="error notice notice-error">' . implode(', ', $errors) . '</span>';
}

function display_subscription_forms_list($connectionSucceeded, $forms) {
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Your subscription forms</h1>
        <a href="?page=newsletter_subscription_forms&action=create" class="page-title-action">Add New</a>
        <?php
        if (!$connectionSucceeded) {
            ?>
            <h2 style="color: red">Cannot connect to Get A Newsletter API. Please verify your API Token</h2>
            <?
        } elseif ($message = get_newsletter_flash_message()) {
            display_newsletter_flash_message($message);
        }
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
            <tr>
                <th class="manage-column">Name</th>
                <th class="manage-column">Lists</th>
                <th class="manage-column">Shortcode</th>
                <th class="manage-column">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php
            foreach ($forms as $form) {
                ?>
                <tr>
                    <td><?= $form['name'] ?></td>
                    <td><?= $form['lists_names'] ?></td>
                    <td><code>[gan-form id=<?= $form['key'] ?>]</code></td>
                    <td><a href="?page=newsletter_subscription_forms&action=edit&form_id=<?= $form['key'] ?>" class="page-title-action">Edit</a><a href="?page=newsletter_subscription_forms&action=delete&form_id=<?= $form['key'] ?>&noheader=true" class="page-title-action">Delete</a></td>
                </tr>
                <?php
            }
            ?>
            </tbody>
        </table>
    </div>
    <?php
}

function display_subscription_form($attributes, $lists, $currentFormData, $form_id = null) {
    $currentErrors = get_session_data('newsletter_form_errors', []);
    ?>
    <style>
        <?php
        if (!isset($currentFormData['send_advanced_settings']) || $currentFormData['send_advanced_settings'] == 0) {
            ?>
            .advanced-settings { display: none; }
            <?php
        }
        ?>
        th { padding-bottom: 5px !important; padding-top: 5px !important }
        td { padding-bottom: 5px !important; padding-top: 5px !important }
    </style>
    <script type="text/javascript">
        jQuery(function($) {
            $('.btn-show-advanced').click(function() {
                $('.advanced-settings').show();
                $('input[name="send_advanced_settings"]').val(1);
                $(this).hide();
            });
        });
    </script>
    <div class="wrap">
        <form method="post" action="<?= $form_id ? '?page=newsletter_subscription_forms&action=edit&form_id=' . $form_id . '&noheader=true' : '?page=newsletter_subscription_forms&action=create&noheader=true' ?>">
            <h1>Get a Newsletter - new form</h1>
            <?php
            if ($message = get_newsletter_flash_message()) {
                display_newsletter_flash_message($message);
            }
            ?>

            <?php wp_nonce_field('newsletter-create-form'); ?>
            <h2>Name your form</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Form name</th>
                    <td><input type="text" name="name" value="<?= $currentFormData['name'] ?? '' ?>" /></td>
                </tr>
                <?php
                if (isset($currentErrors['name'])) {
                    ?><tr><td></td><td><?=  display_newsletter_form_errors($currentErrors['name']) ?></td></tr><?php
                }
                ?>
            </table>

            <h2>Contact fields</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Email</th>
                    <td><input type="checkbox" name="email" value="1" checked="checked" disabled="disabled" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">First name</th>
                    <td><input type="checkbox" name="first_name" value="1" <?= isset($currentFormData['first_name']) && $currentFormData['first_name'] ? 'checked="checked"' : '' ?> /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Last name</th>
                    <td><input type="checkbox" name="last_name" value="1" <?= isset($currentFormData['last_name']) && $currentFormData['last_name'] ? 'checked="checked"' : '' ?> /></td>
                </tr>
            </table>

            <?php
            if (isset($attributes) && is_array($attributes) && !empty($attributes)) {
                ?>
                <h2>Attributes fields</h2>
                <table class="form-table">
                    <?php
                    foreach ($attributes as $attribute) {
                        ?>
                        <tr valign="top">
                            <th scope="row"><?= $attribute['name'] ?></th>
                            <td><input type="checkbox" name="attributes[]" value="<?= $attribute['code'] ?>"
                                       <?= in_array($attribute['code'], ($currentFormData['attributes'] ?? [])) ? 'checked="checked"' : '' ?>/>
                            </td>
                        </tr>
                        <?php
                    }
                    ?>
                </table>
                <?php
            }
            ?>

            <h2>List and Sender</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Choose list</th>
                    <td>
                        <select name="list">
                            <?php
                            foreach ($lists as $list) {
                                ?>
                                <option value="<?= $list['hash'] ?>" <?= $list['hash'] = ($currentFormData['list'] ?? '') ? 'selected="selected"' : '' ?>><?= $list['name'] ?></option>
                                <?php
                            }
                            ?>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Sender name</th>
                    <td><input type="text" name="sender_name" value="<?= $currentFormData['sender_name'] ?? '' ?>" /></td>
                </tr>
                <?php
                if (isset($currentErrors['sender'])) {
                    ?><tr><td></td><td><?=  display_newsletter_form_errors($currentErrors['sender']) ?></td></tr><?php
                }
                ?>
                <tr valign="top">
                    <th scope="row">Sender email</th>
                    <td><input type="text" name="sender_email"  value="<?= $currentFormData['sender_email'] ?? '' ?>" /></td>
                </tr>
                <?php
                if (isset($currentErrors['email'])) {
                    ?><tr><td></td><td><?=  display_newsletter_form_errors($currentErrors['email']) ?></td></tr><?php
                }
                ?>
            </table>

            <a class="btn-show-advanced page-title-action" <?= isset($currentFormData['send_advanced_settings']) && $currentFormData['send_advanced_settings'] == 1 ? 'style="display: none;"' : '' ?>>Show Advanced Settings</a>
            <div class="advanced-settings">
                <input type="hidden" name="send_advanced_settings" value="<?= $currentFormData['send_advanced_settings'] ?? '0' ?>" />
                <h2>Confirmation email</h2>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Subject</th>
                        <td><input type="text" name="confirmation_email_subject" value="<?= $currentFormData['confirmation_email_subject'] ?? 'Welcome as a subscriber to ##list_name##' ?>" style="width: 600px" /></td>
                    </tr>
                    <?php
                    if (isset($currentErrors['verify_mail_subject'])) {
                        ?><tr><td></td><td><?=  display_newsletter_form_errors($currentErrors['verify_mail_subject']) ?></td></tr><?php
                    }
                    ?>
                    <tr valign="top">
                        <th scope="row">Message</th>
                        <td>
                            <textarea type="text" name="confirmation_email_message" style="width: 600px; height: 250px;">
<?= $currentFormData['confirmation_email_message'] ??
    'Hello!

You have been added as a subscriber to ##list_name##. Before you can receive our newsletter, please confirm your subscription by clicking the following link:

##confirmation_link##

Best regards
##sendername##

Ps. If you don\'t want our newsletter in the future, you can easily unsubscribe with the link provided in every newsletter.' ?>
                            </textarea>
                        </td>
                    </tr>
                    <?php
                    if (isset($currentErrors['verify_mail_text'])) {
                        ?><tr><td></td><td><?=  display_newsletter_form_errors($currentErrors['verify_mail_text']) ?></td></tr><?php
                    }
                    ?>
                </table>

                <h2>Form Settings</h2>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Next URL</th>
                        <td><input type="text" name="next_url" value="<?= $currentFormData['next_url'] ?? '' ?>" /></td>
                    </tr>
                    <?php
                    if (isset($currentErrors['next_url'])) {
                        ?><tr><td></td><td><?=  display_newsletter_form_errors($currentErrors['next_url']) ?></td></tr><?php
                    }
                    ?>
                    <tr valign="top">
                        <th scope="row">Button Text</th>
                        <td><input type="text" name="button_text" value="<?= $currentFormData['button_text'] ?? 'Subscribe' ?>" /></td>
                    </tr>
                    <?php
                    if (isset($currentErrors['button_text'])) {
                        ?><tr><td></td><td><?=  display_newsletter_form_errors($currentErrors['button_text']) ?></td></tr><?php
                    }
                    ?>
                </table>
            </div>

            <p class="submit">
                <input type="submit" class="button-primary" value="Save and return" />
                <a class="button button-cancel" href="?page=newsletter_subscription_forms">Cancel</a>
            </p>

        </form>
    </div>
    <?php
}

function delete_subscription_form($formId, $news_pass) {
    $conn = new GAPI('', $news_pass);
    if (!$conn->check_login()) {
        throw new \GetANewsletterException('Cannot connect to Get A Newsletter API');
    }

    $result = $conn->subscription_form_delete($formId);
    if (!$result) {
        throw new GetANewsletterException('Cannot delete a form');
    }
}

function get_subscription_attributes($news_pass) {
    $conn = new GAPI('', $news_pass);
    if (!$conn->check_login()) {
        throw new \GetANewsletterException('Cannot connect to Get A Newsletter API');
    }

    $conn->attribute_listing();
    return $conn->body['results'];
}

function get_subscription_lists($news_pass) {
    $conn = new GAPI('', $news_pass);
    if (!$conn->check_login()) {
        throw new \GetANewsletterException('Cannot connect to Get A Newsletter API');
    }

    $conn->subscription_lists_list();
    return $conn->body['results'];
}

function get_subscription_form($news_pass, $form_id) {
    $conn = new GAPI('', $news_pass);
    if (!$conn->check_login()) {
        throw new \GetANewsletterException('Cannot connect to Get A Newsletter API');
    }

    $conn->subscription_form_get($form_id);
    return $conn->body;
}

function get_subscription_forms_list($news_pass): array {
    $conn = new GAPI('', $news_pass);
    if ($conn->check_login()) {
        $conn->subscription_form_list();
        $forms = $conn->body['results'];
        return $forms;
    } else {
        throw new \GetANewsletterException('Cannot connect to Get A Newsletter API');
    }
}

function display_api_key_form() {
    ?>
    <div class="wrap">
        <form method="post" action="options.php?option_page=newsletter">
            <h2>Get Started</h2>
            <p>Enter your <a href="http://www.getanewsletter.com" target=_blank>Get a Newsletter</a> API Token here. Don't have an account? Register one for free at the <a href="http://www.getanewsletter.com" target=_blank>website</a>.</p>
            <?php wp_nonce_field('newsletter-options'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">API Token</th>
                    <td><input type="password" name="newsletter_pass" value="<?php echo get_option('newsletter_pass'); ?>" /></td>
                </tr>
                <input type="hidden" name="action" value="update" />
                <input type="hidden" name="page_options" value="newsletter_pass" />
            </table>
            <p class="submit">
                <input type="submit" class="button-primary" value="<?php _e('Save Changes', 'getanewsletter') ?>" />
            </p>
        </form>
    </div>
    <?php
}

function newsletter_options() {
    $news_pass = get_option('newsletter_pass');
    $ok = false;
    if($news_pass) {
        $conn = new GAPI('', $news_pass);
        $ok = $conn->check_login();
    } else {
        display_api_key_form();
        return;
    }
?>
    <div class="wrap">

    <form method="post" action="options.php?option_page=newsletter">

        <h2>Get a Newsletter Options</h2>

        <h3>Account Information</h3>
        <p>Enter your <a href="http://www.getanewsletter.com" target=_blank>Get a Newsletter</a> API Token here. Don't have an account? Register one for free at the <a href="http://www.getanewsletter.com" target=_blank>website</a>.</p>

        <?php wp_nonce_field('newsletter-options'); ?>
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

function gan_shortcode( $atts ) {
    $a = shortcode_atts( array(
        'id' => null,
    ), $atts );

    if (null === $a['id']) {
        return '';
    }

    $news_pass = get_option('newsletter_pass');
    $form = get_subscription_form($news_pass, $a['id']);

    $customAttributes = get_subscription_attributes(get_option('newsletter_pass'));
    $content = ""
        ."<form method=\"post\" class=\"newsletter-signup\" action=\"javascript:alert('success!');\" enctype=\"multipart/form-data\">"
        ."  <input type=\"hidden\" name=\"action\" value=\"getanewsletter_subscribe\" />";

    if($form['first_name']) {
        $content .= ""
            ."<p>"
            ."  <label for=\"id_first_name\">" . (!empty($form['first_name_label']) ? $form['first_name_label'] : __('First name', 'getanewsletter')) . "</label><br />"
            ."  <input id=\"id_first_name\" type=\"text\" class=\"text\" name=\"id_first_name\" />"
            ."</p>";
    }

    if($form['last_name']) {
        $content .=  ""
            ."<p>"
            ."  <label for=\"id_last_name\">" . (!empty($form['last_name_label']) ? $form['last_name_label'] : __('Last name', 'getanewsletter')) . "</label><br />"
            ."  <input id=\"id_last_name\" type=\"text\" class=\"text\" name=\"id_last_name\" />"
            ."</p>";
    }

    $content .=  ""
        ."  <p>"
        ."      <label for=\"id_email\">". __('E-mail', 'getanewsletter') ."</label><br />"
        ."      <input id=\"id_email\" type=\"text\" class=\"text\" name=\"id_email\" />"
        ."  </p>";

    foreach ($customAttributes as $attribute) {
        if (!in_array($attribute['code'], $form['attributes'])) {
            continue;
        }
        $content .=  ""
            ."  <p>"
            ."      <label for=\"attr_${attribute['code']}\">". $attribute['name'] ."</label><br />"
            ."      <input id=\"attr_${attribute['code']}\" type=\"text\" class=\"text\" name=\"attributes[{$attribute['code']}]\" />"
            ."  </p>";
    }

    $content .=  ""
        ."  <p>"
        ."      <input type=\"hidden\" name=\"form_link\" value=\"{$form['form_link']}\" id=\"id_form_link\" />"
        ."      <input type=\"hidden\" name=\"key\" value=\"{$form['key']}\" id=\"id_key\" />"
        ."      <input type=\"submit\" value=\"" . ($form['button_text'] != '' ?  __($form['button_text'], 'getanewsletter') : __('Subscribe', 'getanewsletter')) . "\" />"
        ."      <img src=\"" . WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__), '', plugin_basename(__FILE__)) . "loading.gif\""
        ."          alt=\"loading\""
        ."          class=\"news-loading\" />"
        ."  </p>";
    $content .=  ""
        ."</form>"
        ."<div class=\"news-note\"></div>";

    return $content;
}
add_shortcode( 'gan-form', 'gan_shortcode' );

add_action( 'wp_ajax_newsletter_get_form', function() {
    $formId = $_GET['formId'];

    $news_pass = get_option('newsletter_pass');
    $form = get_subscription_form($news_pass, $formId);

    echo json_encode($form);
    wp_die();
});

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
        $lname = esc_attr(empty($instance['lname']) ? "" : $instance['lname']);
        $submittext = esc_attr(empty($instance['submittext']) ? "" : $instance['submittext']);

        $customAttributes = get_subscription_attributes(get_option('newsletter_pass'));

        ?>
        <?php echo $before_widget; ?>
          <?php if ( $title )
                echo $before_title . $title . $after_title;

                print ""
                    ."<form method=\"post\" class=\"newsletter-signup\" action=\"javascript:alert('success!');\" enctype=\"multipart/form-data\">"
                    ."  <input type=\"hidden\" name=\"action\" value=\"getanewsletter_subscribe\" />";

                if($fname) {
                    print ""
                        ."<p>"
                        ."  <label for=\"id_first_name\">" . (!empty($fnametxt) ? $fnametxt : __('First name', 'getanewsletter')) . "</label><br />"
                        ."  <input id=\"id_first_name\" type=\"text\" class=\"text\" name=\"id_first_name\" />"
                        ."</p>";
                }

                if($lname) {
                    print ""
                        ."<p>"
                        ."  <label for=\"id_last_name\">" . (!empty($lnametxt) ? $lnametxt : __('Last name', 'getanewsletter')) . "</label><br />"
                        ."  <input id=\"id_last_name\" type=\"text\" class=\"text\" name=\"id_last_name\" />"
                        ."</p>";
                }

                print ""
                    ."  <p>"
                    ."      <label for=\"id_email\">". __('E-mail', 'getanewsletter') ."</label><br />"
                    ."      <input id=\"id_email\" type=\"text\" class=\"text\" name=\"id_email\" />"
                    ."  </p>";

                foreach ($customAttributes as $attribute) {
                    if (!isset($instance[$attribute['code']]) || !$instance[$attribute['code']]) {
                        continue;
                    }
                    print ""
                        ."  <p>"
                        ."      <label for=\"attr_${attribute['code']}\">". $attribute['name'] ."</label><br />"
                        ."      <input id=\"attr_${attribute['code']}\" type=\"text\" class=\"text\" name=\"attributes[{$attribute['code']}]\" />"
                        ."  </p>";
                }

                print ""
                    ."  <p>"
                    ."      <input type=\"hidden\" name=\"form_link\" value=\"{$form_link}\" id=\"id_form_link\" />"
                    ."      <input type=\"hidden\" name=\"key\" value=\"{$key}\" id=\"id_key\" />"
                    ."      <input type=\"submit\" value=\"" . ($submittext != '' ?  __($submittext, 'getanewsletter') : __('Subscribe', 'getanewsletter')) . "\" />"
                    ."      <img src=\"" . WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__), '', plugin_basename(__FILE__)) . "loading.gif\""
                    ."          alt=\"loading\""
                    ."          class=\"news-loading\" />"
                    ."  </p>";
                print ""
                    ."</form>"
                    ."<div class=\"news-note\"></div>";

        echo $after_widget;
    }

    /** @see WP_Widget::update */
    function update($new_instance, $old_instance) {
        if(empty($new_instance['key'])) {
            return false;
        }

        $api = new GAPI('', get_option('newsletter_pass'));
        $api->subscription_form_get($new_instance['key']);

        $new_instance['form_link'] = $api->body['form_link'];

        // If submittext is empty we take the one from app, otherwise we use local stored.
        if (empty($new_instance['submittext'])) {
            $new_instance['submittext'] = $api->body['button_text'];
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
                $lname = esc_attr(empty($instance['lname']) ? "" : $instance['lname']);
                $submittext = esc_attr(empty($instance['submittext']) ? "" : $instance['submittext']);

                $customAttributes = get_subscription_attributes($news_pass);
                foreach ($customAttributes as $attribute) {
                    ${$attribute['code']} = $instance[$attribute['code']] ?? false;
                }

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
                        print "<select data-widget-id='{$this->number}' class=\"widefat\" id=\"{$this->get_field_id("key")}\" name=\"{$this->get_field_name("key")}\">";
                        print "<option></option>";
                        foreach($news_con->body['results'] as $form) {
                            $selected_list = $key == $form['key'] ? "selected=\"selected\"" : "";
                            print "<option {$selected_list} value=\"{$form['key']}\">{$form['name']}</option>";
                        }

                        print "</select>";
                    }
                    else {
                        print __("Subscription forms not created yet, create a form <a href=\"https://app.getanewsletter.com/api/forms/\">here</a>", 'getanewsletter');
                    }

                print "</p>";

                print '<h3>Attribute fields</h3>';
                print '<span style="text-style: italic">Choose which fields to include for this widget. Current options are copied from original form</span>';

                print ""
                    ."<p>"
                    ."  <input class=\"checkbox\""
                    .""
                    ."      type=\"checkbox\" checked='checked' disabled='disabled' />"
                    ."  <label for=\"{$this->get_field_id('email')}\">" . __('Email <span style="font-style: italic">Required</span>', 'getanewsletter'). "</label>"
                    ."</p>";

                print ""
                    ."<p>"
                    ."  <input data-newsletter-field-name='fname-{$this->number}' class=\"checkbox\" id=\"{$this->get_field_id('fname')}\""
                    ."      name=\"{$this->get_field_name('fname')}\""
                    ."      type=\"checkbox\" " . (!empty($fname) ? "checked=\"checked\"" : "") . " />"
                    ."  <label for=\"{$this->get_field_id('fname')}\">" . __('First Name', 'getanewsletter'). "</label>"
                    ."</p>";

                print ""
                    ."<p>"
                    ."  <input data-newsletter-field-name='lname-{$this->number}' class=\"checkbox\""
                    ."      id=\"{$this->get_field_id('lname')}\""
                    ."      name=\"{$this->get_field_name('lname')}\""
                    ."      type=\"checkbox\" " . (!empty($lname) ? "checked=\"checked\"" : "") . " />"
                    ."  <label for=\"{$this->get_field_id('lname')}\">" . __('Last Name', 'getanewsletter') . "</label>"
                    ."</p>";

                foreach ($customAttributes as $attribute) {
                    print ""
                        ."<p>"
                        ."  <input class=\"checkbox\" data-attribute-name='{$attribute['code']}-{$this->number}' rel='newsletter_attribute' data-attribute-name='{$attribute['code']}'"
                        ."      id=\"{$this->get_field_id($attribute['code'])}\""
                        ."      name=\"{$this->get_field_name($attribute['code'])}\""
                        ."      type=\"checkbox\" " . (!empty(${$attribute['code']}) ? "checked=\"checked\"" : "") . " />"
                        ."  <label for=\"{$this->get_field_id($attribute['code'])}\">" . $attribute['name'] . "</label>"
                        ."</p>";
                }

                print ""
                    ."<p>"
                    ."  <label for=\"{$this->get_field_id('submittext')}\">" . __('Submit text', 'getanewsletter') . ":</label>"
                    ."  <input data-newsletter-field-name='submit-text-{$this->number}' class=\"widefat\""
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

add_action('widgets_init', function() {
    register_widget("GetaNewsletter");
});

add_action('admin_footer', function() {
    print "
                <script type=\"text/javascript\">
                    jQuery(function($) {
                        $('.widget-liquid-right').on('change', 'select[data-widget-id]', function() {
                            var widgetId = $(this).attr('data-widget-id');
                            $.ajax(ajaxurl, {
                                method: 'GET',
                                data: {
                                    action: 'newsletter_get_form',
                                    formId: $(this).val()
                                },
                                success: function(response) {
                                    response = $.parseJSON(response);
                                    if (response.first_name) {
                                        $('input[data-newsletter-field-name=fname-' + widgetId + ']').prop('checked', true);
                                    } else {
                                        $('input[data-newsletter-field-name=fname-' + widgetId + ']').prop('checked', false);
                                    }
                                    
                                    if (response.last_name) {
                                        $('input[data-newsletter-field-name=lname-' + widgetId + ']').prop('checked', true);
                                    } else {
                                        $('input[data-newsletter-field-name=lname-' + widgetId + ']').prop('checked', false);
                                    }
                                    
                                    var i;
                                    var \$attributes = $('input[rel=newsletter_attribute-' + widgetId + ']');
                                    for (i = 0; i < \$attributes.length; i++) {
                                        attr = \$attributes[i];
                                        $(attr).prop('checked', false);
                                    }
                                    
                                    for (i = 0; i < response.attributes.length; i++) {
                                        attr = response.attributes[i];
                                        $('input[data-attribute-name=' + attr + '-' + widgetId + ']').prop('checked', true);
                                    }
                                    
                                    $('input[data-newsletter-field-name=submit-text-' + widgetId + ']').val(response.button_text);
                                }
                            });
                        });
                    });
                </script>";
});

register_activation_hook(__FILE__, array('GetaNewsletter', 'install'));
function getanewsletter_load_plugin_textdomain() {
    load_plugin_textdomain('getanewsletter', FALSE, dirname(plugin_basename(__FILE__)).'/languages/');
}
add_action( 'plugins_loaded', 'getanewsletter_load_plugin_textdomain' );

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
                    'error': function(response) {
                        spinner.hide();
                        resultWrapper.append(
                            resultContainer.removeClass('news-success')
                                .addClass('news-error')
                                .html(response.responseJSON.message));
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
