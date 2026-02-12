<?php
/*
Plugin Name: Get a Newsletter
Plugin URI: https://www.getanewsletter.com/
Description: Turn visitors into subscribers. Eliminate manual entry of subscribers with signup forms that sync directly with your Get a Newsletter account.
Version: 4.1.0
Requires at least: 5.2.0
Requires PHP: 7.2
Author: getanewsletter
Author URI: https://www.getanewsletter.com/
License: GPLv2 or later
Text Domain: getanewsletter
Domain Path: /languages/
*/

define( 'GAN_VERSION', '4.1.0' );

require_once("GAPI.class.php");

class GetANewsletterException extends \RuntimeException { }

add_action('admin_init', function() {
    session_start();
    register_setting('newsletter', 'newsletter_user');
    register_setting('newsletter', 'newsletter_pass');
    register_setting('newsletter', 'newsletter_apikey');
    register_setting('newsletter', 'newsletter_msg_success');
    register_setting('newsletter', 'newsletter_msg_confirm');
    register_setting('newsletter', 'gan_enable_popup_forms');
    session_write_close();
});
/* ADMIN PANEL */

add_action('admin_enqueue_scripts', 'gan_enqueue_admin_assets');
function gan_enqueue_admin_assets() {
    if ( ! isset( $_GET['page'] ) || ! $_GET['page'] === 'newsletter_subscription_forms' ) {
        return;
    }

    $plugin_dir = plugin_dir_url( __FILE__ );
    wp_enqueue_style( 'gan-admin-styles', $plugin_dir . 'assets/admin/css/styles.css' );
    wp_enqueue_script( 'gan-admin-scripts', $plugin_dir . 'assets/admin/js/scripts.js', array('jquery', 'wp-i18n'), null, true );

    wp_set_script_translations( 'gan-admin-scripts', 'getanewsletter', plugin_dir_path( __FILE__ ) . 'languages' );
}

function newsletter_menu() {
    add_menu_page('Get a Newsletter', 'Get a Newsletter', 'administrator', 'newsletter', 'newsletter_options');
    add_submenu_page('newsletter', __( 'Forms', 'getanewsletter' ), __( 'Forms', 'getanewsletter' ), 'administrator', 'newsletter_subscription_forms', 'newsletter_subscription_forms');
    remove_submenu_page('newsletter', 'newsletter');
    add_submenu_page('newsletter', __( 'Settings', 'getanewsletter' ), __( 'Settings', 'getanewsletter' ), 'administrator', 'newsletter', 'newsletter_options');
    add_submenu_page( 'newsletter', __( 'Guide', 'getanewsletter' ), __( 'Guide', 'getanewsletter' ), 'administrator', 'gan-guide', 'render_gan_guide_page' );
    add_submenu_page( 'newsletter', __( 'Support', 'getanewsletter' ), __( 'Support', 'getanewsletter' ), 'administrator', 'gan-support', 'render_gan_support_page' );
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
    set_transient('newsletter_flash_message', [
        'msg' => $msg,
        'type' => $type
    ], 30);
}

function get_newsletter_flash_message() {
    $message = get_transient('newsletter_flash_message');
    if ($message) {
        delete_transient('newsletter_flash_message');
    }
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
                    set_newsletter_flash_message(__( 'The subscription form has been deleted', 'getanewsletter' ), 'notice-success');
                    wp_redirect('?page=newsletter_subscription_forms');
                    exit;
                } else {
                    throw new GetANewsletterException("Invalid form id");
                }
            } catch (GetANewsletterException $e) {
                set_newsletter_flash_message($e->getMessage(), 'notice-error');
                wp_redirect('?page=newsletter_subscription_forms');
                exit;
            }
        case 'create':
            if (!empty($_POST)) {
                try {
                    $result = create_subscription_form($news_pass, $_POST);
                    if (empty($result)) {
                        set_newsletter_flash_message(__( 'The subscription form has been created', 'getanewsletter' ), 'notice-success');
                        wp_redirect('?page=newsletter_subscription_forms');
                        exit;
                    } else {
                        $errors_string = stringify_api_errors( $result );
                        set_newsletter_flash_message(__( 'Please correct the errors below: ', 'getanewsletter' ) . $errors_string, 'notice-error');
                        set_session_data('newsletter_form_data', $_POST);
                        set_session_data('newsletter_form_errors', $result);
                        wp_redirect('?page=newsletter_subscription_forms&action=create');
                        exit;
                    }
                } catch (\GetANewsletterException $e) {
                    set_newsletter_flash_message($e->getMessage(), 'notice-error');
                }
            }
            $attributes = get_subscription_attributes($news_pass);
            $lists = get_subscription_lists($news_pass);
            $senders = get_senders($news_pass);
            display_subscription_form(array(
                'attributes' => $attributes, 
                'lists' => $lists, 
                'currentFormData' => get_session_data('newsletter_form_data', []), 
                'form_id' => null, 
                'senders' => $senders
            ));
            break;
        case 'edit':
            $form_id = $_GET['form_id'] ?? '';
            if (!empty($_POST)) {
                try {
                    $result = update_subscription_form($news_pass, $_POST, $form_id);
                    if (empty($result)) {
                        set_newsletter_flash_message(__( 'The subscription form has been updated', 'getanewsletter' ), 'notice-success');
                        wp_redirect('?page=newsletter_subscription_forms');
                        exit;
                    } else {
                        $errors_string = stringify_api_errors( $result );
                        set_newsletter_flash_message(__( 'Please correct the errors below: ', 'getanewsletter' ) . $errors_string, 'notice-error');
                        set_session_data('newsletter_form_data', $_POST);
                        set_session_data('newsletter_form_errors', $result);
                        wp_redirect('?page=newsletter_subscription_forms&action=edit&form_id=' . $form_id);
                        exit;
                    }
                } catch (\GetANewsletterException $e) {
                    set_newsletter_flash_message($e->getMessage(), 'notice-error');
                }
            }
            try {
                $attributes = get_subscription_attributes($news_pass);
                $lists = get_subscription_lists($news_pass);
                $senders = get_senders($news_pass);
                $currentFormData = get_session_data('newsletter_form_data', transform_form_data(get_subscription_form($news_pass, $form_id)));
                display_subscription_form(array(
                    'attributes' => $attributes, 
                    'lists' => $lists, 
                    'currentFormData' => $currentFormData, 
                    'form_id' => $form_id, 
                    'senders' => $senders
                ));
            } catch (\GetANewsletterException $e) {
                set_newsletter_flash_message($e->getMessage(), 'notice-error');
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
        'first_name' => (int)$form_data['first_name'],
        'list' => $form_data['lists'],
        'last_name' => (int)$form_data['last_name'],
        'name' => $form_data['name'],
        'confirmation_email_subject' => $form_data['verify_mail_subject'],
        'confirmation_email_message' => $form_data['verify_mail_text'],
        'next_url' => $form_data['next_url'],
        'button_text' => $form_data['button_text'],
        'sender_id' => $form_data['sender_id'],
    ];
}

function create_subscription_form($news_pass, $postdata) {
    $conn = new GAPI('', $news_pass);
    if (!$conn->check_login()) {
        throw new \GetANewsletterException('Unable to connect to the Get A Newsletter API.');
    }

    if ( ! check_admin_referer( 'newsletter-create-form' ) ) {
        wp_die( 'Nonce verification failed.' );
    }

    $data = [
        'attributes' => $postdata['attributes'] ?? [],
        'first_name' => isset($postdata['first_name']),
        'lists' => [ $postdata['list'] ?? '' ],
        'last_name' => isset($postdata['last_name']),
        'name' => $postdata['name'] ?? '',
        'verify_mail_subject' => $postdata['confirmation_email_subject'] ?? '',
        'verify_mail_text' => $postdata['confirmation_email_message'] ?? '',
        'next_url' => $postdata['next_url'] ?? '',
        'button_text' => $postdata['button_text'] ?? '',
        'sender_id' => $postdata['sender_id']
    ];

    $result = $conn->subscription_form_create($data);

    if ($result) {
        return [];
    }

    if ($conn->errorCode == 400) {
        return $conn->body;
    } else {
        throw new GetANewsletterException( __( 'Unknown error', 'getanewsletter' ) );
    }
}

function update_subscription_form($news_pass, $postdata, $form_id) {
    $conn = new GAPI('', $news_pass);
    if (!$conn->check_login()) {
        throw new \GetANewsletterException( __( 'Unable to connect to the Get A Newsletter API.', 'getanewsletter' ) );
    }

    if ( ! check_admin_referer( 'newsletter-create-form' ) ) {
        wp_die( 'Nonce verification failed.' );
    }

    $data = [
        'attributes' => $postdata['attributes'] ?? [],
        'first_name' => isset($postdata['first_name']),
        'lists' => [ $postdata['list'] ?? '' ],
        'last_name' => isset($postdata['last_name']),
        'name' => $postdata['name'] ?? '',
        'verify_mail_subject' => $postdata['confirmation_email_subject'] ?? '',
        'verify_mail_text' => $postdata['confirmation_email_message'] ?? '',
        'next_url' => $postdata['next_url'] ?? '',
        'button_text' => $postdata['button_text'] ?? '',
        'sender_id' => $postdata['sender_id']
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
    ?><div class="<?php echo $message['type'] ?> notice is-dismissible"><p><?php echo $message['msg'] ?></p></div><?php
}

function display_subscription_forms_list($connectionSucceeded, $forms) {
    ?>
    <div class="wrap">
        <?php settings_errors('gan'); ?>
        <h1 class="wp-heading-inline"><?php esc_html_e( 'Forms', 'getanewsletter' ); ?></h1>
        <?php if ( $connectionSucceeded ): ?>
            <a href="?page=newsletter_subscription_forms&action=create" class="page-title-action">
                <?php esc_html_e( 'Add new form', 'getanewsletter' ); ?>
            </a>
        <?php endif; ?>
        <?php
        if (!$connectionSucceeded) {
            ?>
            <h2 style="color: red"><?php esc_html_e( 'Unable to connect to the Get a Newsletter API. Please verify your API key', 'getanewsletter' ); ?></h2>
            <?php
        } elseif ($message = get_newsletter_flash_message()) {
            display_newsletter_flash_message($message);
        }
        ?>
        <table style="margin-top: 15px;" class="wp-list-table widefat fixed striped">
            <thead>
            <tr>
                <th class="manage-column"><?php esc_html_e( 'Name', 'getanewsletter' ); ?></th>
                <th class="manage-column"><?php esc_html_e( 'List', 'getanewsletter' ); ?></th>
                <th class="manage-column"><?php esc_html_e( 'Shortcode', 'getanewsletter' ); ?></th>
                <th class="manage-column"><?php esc_html_e( 'Actions', 'getanewsletter' ); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php
            foreach ($forms as $form) {
                ?>
                <tr>
                    <td><?php echo $form['name'] ?></td>
                    <td><?php echo $form['lists_names'] ?></td>
                    <td><code class="gan-shortcode-container">[gan-form id="<?php echo $form['key'] ?>"]</code></td>
                    <td><a href="?page=newsletter_subscription_forms&action=edit&form_id=<?php echo $form['key'] ?>" class="page-title-action"><?php _e( 'Edit', 'getanewsletter' ); ?></a><a href="?page=newsletter_subscription_forms&action=delete&form_id=<?php echo $form['key'] ?>&noheader=true" class="page-title-action"><?php _e( 'Delete', 'getanewsletter' ); ?></a></td>
                </tr>
                <?php
            }
            ?>
            </tbody>
        </table>
    </div>
    <?php
}

function display_subscription_form($params) {
    $default_params = array(
        'form_id' => null
    );

    $params = array_merge($default_params, $params);
    extract($params);

    $currentErrors = get_session_data('newsletter_form_errors', []);
    ?>
    <style>
        th { padding-bottom: 5px !important; padding-top: 5px !important }
        td { padding-bottom: 5px !important; padding-top: 5px !important }
    </style>
    <div class="wrap gan-settings-page">
        <form method="post" action="<?php echo $form_id ? '?page=newsletter_subscription_forms&action=edit&form_id=' . $form_id . '&noheader=true' : '?page=newsletter_subscription_forms&action=create&noheader=true' ?>">
            <h1><?php esc_html_e( 'Add new form', 'getanewsletter' ); ?></h1>
            <?php
            if ($message = get_newsletter_flash_message()) {
                display_newsletter_flash_message($message);
            }
            ?>

            <?php wp_nonce_field('newsletter-create-form'); ?>

            <div class="postbox" id="gan-settings-form-name">
                <div class="postbox-header"><h2 class="hndle"><?php esc_html_e( '1. Name your form', 'getanewsletter' ); ?></h2></div>
                <div class="inside">
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><?php esc_html_e( 'Form name', 'getanewsletter' ); ?></th>
                            <td><input type="text" name="name" value="<?php echo $currentFormData['name'] ?? '' ?>" /></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <div class="postbox" id="gan-settings-contact-fields">
                <div class="postbox-header"><h2 class="hndle"><?php esc_html_e( '2. Contact fields', 'getanewsletter' ); ?></h2></div>
                <div class="inside">
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><?php esc_html_e( 'Email', 'getanewsletter' ); ?></th>
                            <td><input type="checkbox" name="email" value="1" checked="checked" disabled="disabled" /></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php esc_html_e( 'First name', 'getanewsletter' ); ?></th>
                            <td><input type="checkbox" name="first_name" value="1" <?php echo isset($currentFormData['first_name']) && $currentFormData['first_name'] ? 'checked="checked"' : '' ?> /></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php esc_html_e( 'Last name', 'getanewsletter' ); ?></th>
                            <td><input type="checkbox" name="last_name" value="1" <?php echo isset($currentFormData['last_name']) && $currentFormData['last_name'] ? 'checked="checked"' : '' ?> /></td>
                        </tr>
                    </table>
                </div>
            </div>

            <?php
            if (isset($attributes) && is_array($attributes) && !empty($attributes)) {
                ?>
                <div class="postbox" id="gan-settings-attributes">
                    <div class="postbox-header"><h2 class="hndle"><?php _e( '3. Attribute fields', 'getanewsletter' ); ?></h2></div>
                    <div class="inside">
                        <table class="form-table">
                            <?php
                            foreach ($attributes as $attribute) {
                                ?>
                                <tr valign="top">
                                    <th scope="row"><?php echo $attribute['name'] ?></th>
                                    <td><input type="checkbox" name="attributes[]" value="<?php echo $attribute['code'] ?>"
                                            <?php echo in_array($attribute['code'], ($currentFormData['attributes'] ?? [])) ? 'checked="checked"' : '' ?>/>
                                    </td>
                                </tr>
                                <?php
                            }
                            ?>
                        </table>
                    </div>
                </div>
                <?php
            }
            ?>

            <div class="postbox" id="gan-settings-sender">
                <?php if (isset($attributes) && is_array($attributes) && !empty($attributes)): ?>
                    <div class="postbox-header"><h2 class="hndle"><?php esc_html_e( '4. List and Sender', 'getanewsletter' ); ?></h2></div>
                <?php else: ?>
                    <div class="postbox-header"><h2 class="hndle"><?php esc_html_e( '3. List and Sender', 'getanewsletter' ); ?></h2></div>
                <?php endif; ?>
                <div class="inside">
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><?php esc_html_e( 'Choose list', 'getanewsletter' ); ?></th>
                            <td>
                                <select name="list">
                                    <option disabled <?php echo isset( $currentFormData['list'] ) ? '' : 'selected="selected"' ?> value=""> -- <?php esc_html_e( 'Choose a list', 'getanewsletter' ); ?> --</option>
                                    <?php
                                    foreach ($lists as $list) {
                                        ?>
                                        <?php $is_selected_list = isset( $currentFormData['list'] ) && $currentFormData['list'][0] == $list['hash'] ?>
                                        <option value="<?php echo $list['hash'] ?>" <?php echo $is_selected_list ? 'selected="selected"' : '' ?>><?php echo $list['name'] ?></option>
                                        <?php
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        
                        <tr valign="top">
                            <th scope="row"><?php esc_html_e( 'Choose sender', 'getanewsletter' ); ?></th>
                            <td>
                                <select name="sender_id">
                                    <option disabled <?php echo isset( $currentFormData['sender_id'] ) ? '' : 'selected="selected"' ?> value=""> -- <?php esc_html_e( 'Choose a sender', 'getanewsletter' ); ?> --</option>
                                    <?php
                                    foreach ($senders as $sender) {
                                        ?>
                                        <?php $is_selected_sender = isset( $currentFormData['sender_id'] ) && $currentFormData['sender_id'] == $sender['id'] ?>
                                        <?php $is_confirmed_sender = ( isset( $sender['status'] ) && $sender['status'] !== 1 ) ?>
                                        <option value="<?php echo $sender['id'] ?>" <?php echo $is_selected_sender ? 'selected="selected"' : '' ?> <?php echo $is_confirmed_sender ? '' : 'disabled' ?>><?php echo $sender['email'] ?></option>
                                        <?php
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <a href="#" class="gan-advanced-settings-btn"><?php esc_html_e( 'Show advanced settings', 'getanewsletter' ); ?></a>
                </div>
            </div>

            <div class="postbox" id="gan-settings-confirmation" style="display: none">
                <?php if (isset($attributes) && is_array($attributes) && !empty($attributes)): ?>
                    <div class="postbox-header"><h2 class="hndle"><?php esc_html_e( '5. Confirmation email', 'getanewsletter' ); ?></h2></div>
                <?php else: ?>
                    <div class="postbox-header"><h2 class="hndle"><?php esc_html_e( '4. Confirmation email', 'getanewsletter' ); ?></h2></div>
                <?php endif; ?>
                <div class="inside">
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><?php esc_html_e( 'Subject', 'getanewsletter' ); ?></th>
                            <td><input type="text" name="confirmation_email_subject" value="<?php echo $currentFormData['confirmation_email_subject'] ?? 'Welcome as a subscriber to ##list_name##' ?>" style="width: 600px" /></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php _e( 'Message', 'getanewsletter' ); ?></th>
                            <td>
                                <textarea type="text" name="confirmation_email_message" style="width: 600px; height: 250px;"><?php echo $currentFormData['confirmation_email_message'] ?? esc_html__("Hello!\n\nYou have been added as a subscriber to ##list_name##. Before you can receive our newsletter, please confirm your subscription by clicking the following link:\n\n##confirmation_link##\n\nBest regards,\n##sendername##\n\nPs. If you don't want our newsletter in the future, you can easily unsubscribe with the link provided in every newsletter.", 'getanewsletter')?></textarea>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="postbox" id="gan-settings-form-settings" style="display: none;">
                <?php if (isset($attributes) && is_array($attributes) && !empty($attributes)): ?>
                    <div class="postbox-header"><h2 class="hndle"><?php esc_html_e( '6. Form settings', 'getanewsletter' ); ?></h2></div>
                <?php else: ?>
                    <div class="postbox-header"><h2 class="hndle"><?php esc_html_e( '5. Form settings', 'getanewsletter' ); ?></h2></div>
                <?php endif; ?>
                <div class="inside">
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><?php esc_html_e( 'Next URL', 'getanewsletter' ); ?></th>
                            <td><input type="text" name="next_url" value="<?php echo $currentFormData['next_url'] ?? '' ?>" /></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php esc_html_e( 'Button Text', 'getanewsletter' ); ?></th>
                            <td><input type="text" name="button_text" value="<?php echo $currentFormData['button_text'] ?? 'Subscribe' ?>" /></td>
                        </tr>
                        <?php
                        if (isset($currentErrors['button_text'])) {
                            ?><tr><td></td><td><?php echo display_newsletter_form_errors($currentErrors['button_text']) ?></td></tr><?php
                        }
                        ?>
                    </table>
                </div>
            </div>

            <p class="submit">
                <input type="submit" class="button-primary" value="<?php esc_attr_e( 'Save and return', 'getanewsletter' ); ?>" />
                <a class="button button-cancel" href="?page=newsletter_subscription_forms"><?php esc_html_e( 'Cancel', 'getanewsletter' ); ?></a>
            </p>

        </form>
    </div>
    <?php
}

function render_gan_guide_page() {
    ?>
        <div class="wrap">
            <div class="gan-guide-page">
                <h1 class="gan-guide-title"><?php esc_html_e( 'Getting started guide', 'getanewsletter' ); ?></h1>
                <p style="font-size: 18px;"><?php esc_html_e( 'An overview of the Get a Newsletter form plugin and the features available to you.', 'getanewsletter' ); ?></p>
                
                <hr style="margin: 40px 0;">
    
                <h2><?php esc_html_e( 'Create and manage forms', 'getanewsletter' ); ?></h2>
                <p><?php esc_html_e( "There's two types of forms available to you ‒ Embedded forms and popup forms.", 'getanewsletter' ); ?></p>
    
                <div class="guide-columns">
                    <div class="guide-column">
                    <div style="font-size: 28px; margin-bottom: 20px;">📋</div>
                    <h3 class="guide-column__title"><?php esc_html_e( "Embedded forms", 'getanewsletter' ) ?></h3>
                        <p><?php esc_html_e( 'Manage the forms from your Get a Newsletter account directly from your WordPress site. You can view, edit, and add new forms, and any changes made here will automatically update in both locations.', 'getanewsletter' ); ?></p>
                        <div class="guide-column__link-container">
                            <a href="/wp-admin/admin.php?page=newsletter_subscription_forms" class="guide-column__link"><?php esc_html_e( "Manage embedded forms", 'getanewsletter' ); ?></a>
                            <a target="_blank" href="<?php esc_html_e( "https://support.getanewsletter.com/en/articles/3678267-create-a-subscription-form", 'getanewsletter' ); ?>" class="guide-column__link"><?php esc_html_e( "Learn more in our Help center ↗", 'getanewsletter' ); ?></a>
                        </div>
                    </div>
                    <div class="guide-column">
                        <div style="font-size: 28px; margin-bottom: 20px;">🔔</div>
                        <h3 class="guide-column__title"><?php esc_html_e( "Popup forms", 'getanewsletter' ) ?></h3>
                        <p><?php esc_html_e( 'Popup forms can only be managed from your Get a Newsletter account. However, you can enable them directly from the Settings page on your WordPress site.', 'getanewsletter' ); ?></p>
                        <div class="guide-column__link-container">
                            <a href="/wp-admin/admin.php?page=newsletter" class="guide-column__link"><?php esc_html_e( "Enable popup forms", 'getanewsletter' ); ?></a>
                            <a target="_blank" href="<?php esc_html_e( "https://support.getanewsletter.com/en/articles/5335643-create-a-popup-form", 'getanewsletter' ); ?>" class="guide-column__link"><?php esc_html_e( "Learn more in our Help center ↗", 'getanewsletter' ); ?></a>
                        </div>
                    </div>
                </div>
    
                <hr style="margin: 40px 0;">
    
                <h2><?php esc_html_e( 'Displaying forms on your site', 'getanewsletter' ); ?></h2>
                <p><?php esc_html_e( "There's a few different ways to display forms on your site.", 'getanewsletter' ); ?></p>
    
                <div class="display-guides">
                    <div class="display-guide">
                        <img src="<?php echo plugin_dir_url(__FILE__) . 'assets/admin/img/posts-pages.png'; ?>" class="display-guide__image">
                        <div class="display-guide__content">
                            <h3 class="display-guide__title"><?php esc_html_e( 'Posts and pages', 'getanewsletter' ); ?></h3>
                            <p class="display-guide__description"><?php esc_html_e( "Easily add forms to your posts and pages by inserting a new \"Get a Newsletter\"-block. Choose from your existing forms to display, and customize each one to match your site's style. You can include as many forms as you like, giving you complete flexibility.", 'getanewsletter' ); ?></p>
                            <div class="display-guide__link-container">
                                <a target="_blank" href="<?php esc_html_e( "https://support.getanewsletter.com/en/articles/10660480-add-a-subscription-form-in-wordpress#h_e4f60c8206", 'getanewsletter' ); ?>" class="guide-column__link"><?php esc_html_e( "Learn more in our Help center ↗", 'getanewsletter' ); ?></a>
                            </div>
                        </div>
                    </div>
                    <div class="display-guide">
                        <img src="<?php echo plugin_dir_url(__FILE__) . 'assets/admin/img/widgets.png'; ?>" class="display-guide__image">
                        <div class="display-guide__content">
                            <h3 class="display-guide__title"><?php esc_html_e( 'Sidebars and footers', 'getanewsletter' ); ?></h3>
                            <p class="display-guide__description"><?php esc_html_e( "You can also add forms to your website using widgets. Simply navigate to Appearance > Widgets to place forms in any widget area, such as sidebars or the footer.", 'getanewsletter' ); ?></p>
                            <div class="display-guide__link-container">
                                <a target="_blank" href="<?php esc_html_e( "https://support.getanewsletter.com/en/articles/10660480-add-a-subscription-form-in-wordpress#h_e4f60c8206", 'getanewsletter' ); ?>" class="guide-column__link"><?php esc_html_e( "Learn more in our Help center ↗", 'getanewsletter' ); ?></a>
                            </div>
                        </div>
                    </div>
                    <div class="display-guide">
                        <img src="<?php echo plugin_dir_url(__FILE__) . 'assets/admin/img/popups.png'; ?>" class="display-guide__image">
                        <div class="display-guide__content">
                            <h3 class="display-guide__title"><?php esc_html_e( 'Popup', 'getanewsletter' ); ?></h3>
                            <p class="display-guide__description"><?php esc_html_e( "In your Get a Newsletter account, you can easily choose which pages show your popup forms and decide when they appear. Plus, you can turn the popup feature on or off from the plugin settings page.", 'getanewsletter' ); ?></p>
                            <div class="display-guide__link-container">
                                <a target="_blank" href="<?php esc_html_e( "https://support.getanewsletter.com/en/articles/5335643-create-a-popup-form", 'getanewsletter' ); ?>" class="guide-column__link"><?php esc_html_e( "Learn more in our Help center ↗", 'getanewsletter' ); ?></a>
                            </div>
                        </div>
                    </div>
                </div>
    
                <hr style="margin: 40px 0;">
    
                <div class="guide-get-started">
                    <h2><?php esc_html_e( 'Ready to get started?', 'getanewsletter' ); ?></h2>
                    <p style="font-size: 18px;"><?php esc_html_e( 'Continue to forms to view your forms or create a new one', 'getanewsletter' ); ?></p>
                    <a style="font-size: 15px;" class="button button-primary" href="/wp-admin/admin.php?page=newsletter_subscription_forms"><?php esc_html_e( 'Continue to forms', 'getanewsletter' ); ?></a>
                </div>
            </div>
        </div>
    <?php
}

function render_gan_support_page() {
    $php_version = phpversion();
    $wordpress_version = get_bloginfo( 'version' );
    $site_url = home_url();
    $plugin_version = GAN_VERSION;
    $api_token = get_option( 'newsletter_pass' );
    $api_token_is_set = false;
    $api_token_is_valid = false;

    if ( isset( $api_token ) && is_string( $api_token ) && strlen( $api_token ) > 0 ) {
        $api_token_is_set = true;
        $conn = new GAPI( '', $api_token );
        $ok = $conn->check_login();
        $api_token_is_valid = $ok;
    }

    $user_hash_is_set = ( strlen( get_option( 'gan_user_hash', '' ) ) > 0 );
    $popups_enabled = get_option( 'gan_enable_popup_forms', false );

    ?>
        <div class="wrap gan-support-page">
            <h1><?php esc_html_e( 'Support', 'getanewsletter' ); ?></h1>

            <div class="postbox">
                <div class="postbox-header">
                    <h2 class="hndle"><?php esc_html_e( 'Need help?', 'getanewsletter' ); ?></h2>
                </div>

                <div class="inside">
                    <p>
                        <?php 
                        echo sprintf(
                            esc_html__(
                                'If you are experiencing issues with the plugin you can reach out to our support team. In order to help you as fast as possible please copy the details below and include it in your message. Email us at %s',
                                'getanewsletter'
                            ),
                            '<a href="mailto:support@getanewsletter.com">support@getanewsletter.com</a>'
                        );
                        ?>
                    </p>
                    <div class="gan-support-info">
                        <h3 class="gan-support-info-title"><?php _e( 'Debug information', 'getanewsletter' ); ?></h3>
                        <div class="gan-support-info-wrapper">
                            <button class="gan-support-info-copy"><?php _e( 'Copy text to clipboard', 'getanewsletter' ); ?></button>
                            <pre class="gan-support-info-content">
                                PHP Version: <?php echo esc_html( $php_version ); ?>
    
                                WordPress Version: <?php echo esc_html( $wordpress_version ); ?>
    
                                Site URL: <?php echo esc_html( $site_url ); ?>
    
                                Get a Newsletter Plugin Version: <?php echo esc_html( $plugin_version ); ?>
    
                                API key provided: <?php echo $api_token_is_set ? 'Yes' : 'No' ?>
    
                                API key is valid: <?php echo $api_token_is_valid ? 'Yes' : 'No' ?>
    
                                User hash is set: <?php echo $user_hash_is_set ? 'Yes' : 'No' ?>
    
                                Popups enabled: <?php echo $popups_enabled ? 'Yes' : 'No' ?>
                            </pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php
}

function delete_subscription_form($formId, $news_pass) {
    $conn = new GAPI('', $news_pass);
    if (!$conn->check_login()) {
        throw new \GetANewsletterException(__( 'Cannot connect to Get A Newsletter API', 'getanewsletter' ) );
    }

    $result = $conn->subscription_form_delete($formId);
    if (!$result) {
        throw new GetANewsletterException( __( 'Cannot delete a form', 'getanewsletter' ) );
    }
}

function get_subscription_attributes($news_pass) {
    $conn = new GAPI('', $news_pass);
    if (!$conn->check_login()) {
        throw new \GetANewsletterException( __( 'Cannot connect to Get A Newsletter API', 'getanewsletter' ) );
    }

    $conn->attribute_listing();
    return $conn->body['results'];
}

function get_subscription_lists($news_pass) {
    $conn = new GAPI('', $news_pass);
    if (!$conn->check_login()) {
        throw new \GetANewsletterException( __( 'Cannot connect to Get A Newsletter API', 'getanewsletter' ) );
    }

    $conn->subscription_lists_list();
    return $conn->body['results'];
}

function get_senders($news_pass) {
    $conn = new GAPI('', $news_pass);
    if (!$conn->check_login()) {
        throw new \GetANewsletterException( __( 'Cannot connect to Get A Newsletter API', 'getanewsletter' ) );
    }

    $conn->get_senders();
    return $conn->body['results'];
}

function stringify_api_errors($errors) {
    $errors_string = '';

    foreach ($errors as $key => $messages) {
        $errors_string .= '<br>' . $key . ': ' . implode(', ', $messages);
    }

    return $errors_string;
}

function get_subscription_form($news_pass, $form_id) {
    $conn = new GAPI('', $news_pass);
    if (!$conn->check_login()) {
        throw new \GetANewsletterException( __( 'Cannot connect to Get A Newsletter API', 'getanewsletter' ) );
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
        throw new \GetANewsletterException( __( 'Cannot connect to Get A Newsletter API', 'getanewsletter' ) );
    }
}

function display_api_key_form() {
    ?>
    <div class="wrap">
        <div class="gan-onboarding-container">
            <div class="gan-onboarding-content">
                <h2><?php esc_html_e( 'Connect your account', 'getanewsletter' ); ?></h2>
                <p><?php esc_html_e( "To get access to the plugin, you must first connect to your Get a Newsletter-account", 'getanewsletter' ); ?></p>

                <div class="gan-onboarding-step">
                    <div class="gan-onboarding-step-counter">1</div>
                    <div class="gan-onboarding-step-content">
                        <h3><?php esc_html_e( 'Log in or sign up', 'getanewsletter' ); ?></h3>
                        <p>
                            <?php 
                                echo sprintf(
                                __('Log in to <a href="%1$s">app.getanewsletter.com</a>. Don\'t have an account yet, <a href="%2$s">sign up</a> instead.', 'getanewsletter'),
                                esc_url('https://app.getanewsletter.com/'),
                                        esc_url('https://app.getanewsletter.com/signup')
                                ); 
                            ?>
                        </p>
                    </div>
                </div>

                <div class="gan-onboarding-step">
                    <div class="gan-onboarding-step-counter">2</div>
                    <div class="gan-onboarding-step-content">
                        <h3><?php _e( 'Create an API key', 'getanewsletter' ); ?></h3>
                        <p>
                            <?php 
                                echo sprintf(
                                    esc_html__(
                                        'Once logged in, go to %s and create a new API key.',
                                        'getanewsletter'
                                    ),
                                    '<a href="https://app.getanewsletter.com/account/api">'. esc_html__('My Account -> API', 'getanewsletter') .'</a>'
                                );
                            ?>
                        </p>

                    </div>
                </div>

                <div class="gan-onboarding-step">
                    <div class="gan-onboarding-step-counter">3</div>
                    <div class="gan-onboarding-step-content">
                        <h3><?php esc_html_e( 'Add API key to authenticate', 'getanewsletter' ); ?></h3>
                        <p><?php esc_html_e( 'Copy and paste the generated API key below to authenticate.', 'getanewsletter' ); ?></p>
                    </div>
                </div>

                <div class="gan-onboarding-form-container">
                    <form action="#" class="gan-onboarding-form">
                        <label for="token"><?php esc_html_e( 'Your API key', 'getanewsletter' ); ?></label>
                        <input type="password" name="token" id="token" required> 
                        <input type="submit" class="button button-primary" id="gan-submit-token-btn" value="<?php esc_html_e( 'Continue', 'getanewsletter' ); ?>">
                    </form>
                </div>

                <div class="gan-result-message"></div>
            </div>
            <div class="gan-onboarding-image">
                <img src="<?php echo plugin_dir_url(__FILE__) . 'assets/admin/img/onboarding-promo.png'; ?>" alt="Onboarding image">
            </div>
        </div>
    </div>
    <?php
}

function newsletter_options() {
    $news_pass = get_option('newsletter_pass');
    $ok = false;
    $is_api_token_correct = false;

    if ( $news_pass ) {
        $conn = new GAPI( '', $news_pass );
        $ok = $conn->check_login();
    } else {
        display_api_key_form();
        return;
    }

    if ( $ok ) {
        $is_api_token_correct = true;
    }
?>
    <div class="wrap gan-settings-page">
        <h1><?php esc_html_e( 'Settings', 'getanewsletter' ); ?></h1>

        <!-- API Key Section -->
        <form method="post" action="options.php?option_page=newsletter" class="gan-section-form">
            <?php wp_nonce_field('newsletter-options'); ?>
            
            <div class="postbox" id="gan-account-information">
                <div class="postbox-header">
                    <h2 class="hndle"><?php esc_html_e( 'API key', 'getanewsletter' ); ?></h2>
                </div>

                <div class="inside">
                    <?php if ( $is_api_token_correct ): ?>
                        <!-- STATE 3: Key working successfully -->
                        <p>
                            <?php 
                                echo sprintf(
                                    esc_html__(
                                        'This is the API key for connecting your Get a Newsletter account to this WordPress site. To update the API key, log in to your account and go to %s to generate a new one.',
                                        'getanewsletter'
                                    ),
                                    '<a href="https://app.getanewsletter.com/account/api" target="_blank">My Account → API</a>'
                                );
                            ?>
                        </p>

                        <div>
                            <label class="gan-label-block" for="newsletter_pass"><?php _e( 'API key:', 'getanewsletter' ); ?></label>
                            <input type="password" name="newsletter_pass" id="newsletter_pass" value="<?php echo esc_attr(get_option('newsletter_pass')); ?>" />

                            <div class="gan-result-message">
                                <div class="gan-success-message">
                                    <div class="gan-checkmark-container">
                                        <svg xmlns="http://www.w3.org/2000/svg"  viewBox="0 0 50 50" width="50px" height="50px"><path d="M 41.9375 8.625 C 41.273438 8.648438 40.664063 9 40.3125 9.5625 L 21.5 38.34375 L 9.3125 27.8125 C 8.789063 27.269531 8.003906 27.066406 7.28125 27.292969 C 6.5625 27.515625 6.027344 28.125 5.902344 28.867188 C 5.777344 29.613281 6.078125 30.363281 6.6875 30.8125 L 20.625 42.875 C 21.0625 43.246094 21.640625 43.410156 22.207031 43.328125 C 22.777344 43.242188 23.28125 42.917969 23.59375 42.4375 L 43.6875 11.75 C 44.117188 11.121094 44.152344 10.308594 43.78125 9.644531 C 43.410156 8.984375 42.695313 8.589844 41.9375 8.625 Z"/></svg>
                                    </div>
                                    <span><?php esc_html_e( 'Your API key is active and working', 'getanewsletter' ); ?></span>
                                </div>
                            </div>
                        </div>

                    <?php else: ?>
                        <!-- STATE 2: Key exists but failing -->
                        <div class="notice notice-warning inline gan-connection-issue">
                            <p>
                                <strong>⚠️ <?php _e('Connection issue detected', 'getanewsletter'); ?></strong><br><br>
                                <?php 
                                $token_suffix = substr(get_option('newsletter_pass'), -4);
                                echo sprintf(
                                    __('Your API key (ending in %s) failed to connect. This could be temporary or the key might be invalid.', 'getanewsletter'),
                                    '<code>•••' . esc_html($token_suffix) . '</code>'
                                );
                                ?>
                            </p>
                        </div>

                        <div class="gan-error-steps">
                            <h4><?php _e('Step 1: Test your current key', 'getanewsletter'); ?></h4>
                            <div class="gan-step-actions">
                                <button type="button" id="gan-test-connection" class="button">
                                    <span class="gan-test-text"><?php _e('Test Connection', 'getanewsletter'); ?></span>
                                    <span class="gan-test-spinner" style="display:none;">
                                        <span class="spinner" style="visibility:visible;float:none;margin:0 5px 0 0;"></span>
                                        <?php _e('Testing...', 'getanewsletter'); ?>
                                    </span>
                                </button>
                            </div>
                            <div id="gan-test-result" style="margin-top:15px;"></div>

                            <hr class="gan-divider">

                            <h4><?php _e('Step 2: If testing fails, replace with a new key', 'getanewsletter'); ?></h4>
                            <ol class="gan-instructions">
                                <li><?php _e('Log in to Get a Newsletter', 'getanewsletter'); ?></li>
                                <li>
                                    <?php 
                                    echo sprintf(
                                        __('Go to %s', 'getanewsletter'),
                                        '<a href="https://app.getanewsletter.com/account/api" target="_blank">' . __('My Account → API', 'getanewsletter') . '</a>'
                                    );
                                    ?>
                                </li>
                                <li><?php _e('Create a new key and paste it below', 'getanewsletter'); ?></li>
                            </ol>

                            <div>
                                <label class="gan-label-block" for="newsletter_pass"><?php _e( 'New API key:', 'getanewsletter' ); ?></label>
                                <input type="password" name="newsletter_pass" id="newsletter_pass" value="" placeholder="<?php esc_attr_e('Paste your new API key here', 'getanewsletter'); ?>" />
                            </div>
                        </div>
                    <?php endif; ?>

                    <input type="hidden" name="action" value="update" />
                    <input type="hidden" name="page_options" value="newsletter_pass" />
                    <p class="submit">
                        <input type="submit" class="button-primary" value="<?php echo esc_attr($is_api_token_correct ? __( 'Save API Key', 'getanewsletter') : __( 'Save New Key', 'getanewsletter')); ?>" />
                    </p>
                </div>
            </div>
            
            <div class="postbox" id="gan-popup-forms">
                <div class="postbox-header">
                    <h2 class="hndle"><?php _e( 'Popup forms', 'getanewsletter' ); ?></h2>
                </div>

                <div class="inside">
                    <p><?php esc_html_e( "When creating popup forms with our tool, we ask you to paste a universal code snippet into the ⁠<head> section of your website. With this plugin, you can easily enable your popup forms below.", 'getanewsletter' ); ?></p>
                    <label for="gan_enable_popup_forms">
                        <input id="gan_enable_popup_forms" type="checkbox" name="gan_enable_popup_forms" <?php echo get_option( 'gan_enable_popup_forms', false ) ? 'checked' : '' ?> />
                        <strong><?php esc_html_e( 'Enable popup forms', 'getanewsletter' ); ?></strong>
                    </label>

                    <input type="hidden" name="action" value="update" />
                    <input type="hidden" name="page_options" value="gan_enable_popup_forms" />
                    <p class="submit">
                        <input type="submit" class="button-primary" value="<?php _e( 'Save Changes', 'getanewsletter') ?>" />
                    </p>
                </div>
            </div>
            
            <div class="postbox" id="gan-submission-feedback">
                <div class="postbox-header">
                    <h2 class="hndle"><?php esc_html_e( 'Submission feedback', 'getanewsletter' ); ?></h2>
                </div>

                <div class="inside">
                    <p><?php esc_html_e( 'You can customize the messages shown to users when they interact with the form.', 'getanewsletter' ); ?></p>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><?php esc_html_e( 'Successful submission:', 'getanewsletter' ); ?></th>
                            <td>
                                <input type="text" class="regular-text" name="newsletter_msg_success" value="<?php echo get_option('newsletter_msg_success', 'Thank you for subscribing to our newsletters.'); ?>" /> <br>
                                <span class="gan-input-description"><?php _e( 'Displayed when a user successfully enters their details.', 'getanewsletter' ); ?></span>
                            </td>
                        </tr>
                    </table>

                    <input type="hidden" name="action" value="update" />
                    <input type="hidden" name="page_options" value="newsletter_msg_success" />
                    <p class="submit">
                        <input type="submit" class="button-primary" value="<?php _e( 'Save Changes', 'getanewsletter') ?>" />
                    </p>
                </div>
            </div>
        </form>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#gan-test-connection').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $resultDiv = $('#gan-test-result');
            
            // Show loading state
            $button.prop('disabled', true);
            $button.find('.gan-test-text').hide();
            $button.find('.gan-test-spinner').show();
            $resultDiv.empty();
            
            // Make AJAX request
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'gan_test_connection',
                    nonce: '<?php echo wp_create_nonce('gan-test-connection'); ?>'
                },
                success: function(response) {
                    // Reset button state
                    $button.prop('disabled', false);
                    $button.find('.gan-test-text').show();
                    $button.find('.gan-test-spinner').hide();
                    
                    if (response.success) {
                        // Show success message
                        $resultDiv.html(
                            '<div class="notice notice-success inline">' +
                            '<p><strong>✓ ' + response.data.message + '</strong></p>' +
                            '</div>'
                        );
                        
                        // Reload page after 2 seconds to show updated status
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        // Show error message
                        $resultDiv.html(
                            '<div class="notice notice-error inline">' +
                            '<p><strong>✗ ' + response.data.message + '</strong></p>' +
                            '</div>'
                        );
                    }
                },
                error: function() {
                    // Reset button state
                    $button.prop('disabled', false);
                    $button.find('.gan-test-text').show();
                    $button.find('.gan-test-spinner').hide();
                    
                    // Show error message
                    $resultDiv.html(
                        '<div class="notice notice-error inline">' +
                        '<p><strong><?php _e('An error occurred. Please try again.', 'getanewsletter'); ?></strong></p>' +
                        '</div>'
                    );
                }
            });
        });
    });
    </script>
<?php
}

add_action('admin_menu', 'newsletter_menu');

add_action('admin_notices', 'gan_show_auth_failure_notice');
function gan_show_auth_failure_notice() {
    // Don't show on the settings page itself (user already sees the detailed error there)
    $screen = get_current_screen();
    if ($screen && $screen->id === 'toplevel_page_newsletter') {
        return;
    }
    
    $api_token = get_option('newsletter_pass');
    if (!$api_token) {
        return;
    }
    
    if (get_transient('gan_auth_failed_' . md5($api_token))) {
        // Show last 4 characters of token for identification
        $token_suffix = substr($api_token, -4);
        $masked_token = '•••' . $token_suffix;
        
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <strong><?php _e('Get a Newsletter: Connection needs your attention', 'getanewsletter'); ?></strong><br>
                <?php 
                echo sprintf(
                    __('Your API key (ending in %s) has stopped working.', 'getanewsletter'),
                    '<code>' . esc_html($masked_token) . '</code>'
                );
                ?>
                <br>
                <a href="<?php echo admin_url('admin.php?page=newsletter'); ?>" class="button button-primary" style="margin-top: 8px;">
                    <?php _e('Fix Connection', 'getanewsletter'); ?>
                </a>
            </p>
        </div>
        <?php
    }
}


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

function gan_shortcode($atts) {
    $news_pass = get_option('newsletter_pass');

    if (!isset($news_pass) || !is_string($news_pass) || strlen($news_pass) === 0) {
        return '';
    }

    $conn = new GAPI('', $news_pass);
    $ok = $conn->check_login();

    if (!$ok) {
        return '';
    }

    $a = shortcode_atts([
        'id' => null,
    ], $atts);

    if (null === $a['id']) {
        return '';
    }

    $form = get_subscription_form($news_pass, $a['id']);
    $customAttributes = get_subscription_attributes($news_pass);
    $unique_id = uniqid() . bin2hex(random_bytes(5));

    ob_start();
    ?>
    <style>
        button[type="submit"].gan-button-container--button {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        button[type="submit"].gan-button-container--button
        .gan-button-container--button-spinner {
            position: absolute;
            animation: spin 1s linear infinite;
            visibility: hidden;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }

        button[type="submit"].gan-button-container--button.loading .gan-button-container--button-text {
            opacity: 0;
        }

        button[type="submit"].gan-button-container--button.loading .gan-button-container--button-spinner {
            visibility: visible;
        }

        button[type="submit"].gan-button-container--button:disabled {
            opacity: 0.7;
            cursor: default;
        }
    </style>

    <div class="gan-newsletter-widget">
        <form method="post" class="newsletter-signup" action="javascript:alert('success!');" enctype="multipart/form-data">
            <input type="hidden" name="action" value="getanewsletter_subscribe" />

            <?php if ($form['first_name']): ?>
                <div>
                    <label for="id_first_name_<?php echo esc_attr( $unique_id ); ?>">
                        <?php echo !empty($form['first_name_label']) ? $form['first_name_label'] : esc_html__('First name', 'getanewsletter'); ?>
                    </label><br />
                    <input id="id_first_name_<?php echo esc_attr( $unique_id ); ?>" type="text" class="text" name="id_first_name" />
                </div>
            <?php endif; ?>

            <?php if ($form['last_name']): ?>
                <div>
                    <label for="id_last_name_<?php echo esc_attr( $unique_id ); ?>">
                        <?php echo !empty($form['last_name_label']) ? $form['last_name_label'] : esc_html__('Last name', 'getanewsletter'); ?>
                    </label><br />
                    <input id="id_last_name_<?php echo esc_attr( $unique_id ); ?>" type="text" class="text" name="id_last_name" />
                </div>
            <?php endif; ?>

            <div>
                <label for="id_email_<?php echo esc_attr( $unique_id ); ?>"><?php echo esc_html__('E-mail', 'getanewsletter'); ?></label><br />
                <input id="id_email_<?php echo esc_attr( $unique_id ); ?>" required type="email" class="text" name="id_email" />
            </div>

            <?php foreach ($customAttributes as $attribute): ?>
                <?php if (in_array($attribute['code'], $form['attributes'])): ?>
                    <div>
                        <label for="attr_<?php echo $attribute['code']; ?>_<?php echo esc_attr( $unique_id ); ?>">
                            <?php echo $attribute['name']; ?>
                        </label><br />
                        <input id="attr_<?php echo $attribute['code']; ?>_<?php echo esc_attr( $unique_id ); ?>" type="text" class="text" name="attributes[<?php echo $attribute['code']; ?>]" />
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>

            <div>
                <input type="hidden" name="form_link" value="<?php echo $form['form_link']; ?>" id="id_form_link" />
                <input type="hidden" name="key" value="<?php echo $form['key']; ?>" id="id_key" />
                <div class="gan-button-container">
                    <button type="submit" class="gan-button-container--button">
                        <span class="gan-button-container--button-text"><?php echo !empty($form['button_text']) ? esc_attr($form['button_text']) : esc_html__('Subscribe', 'getanewsletter'); ?></span>
                        <svg class="gan-button-container--button-spinner" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 256 256"><path d="M140,32V64a12,12,0,0,1-24,0V32a12,12,0,0,1,24,0Zm33.25,62.75a12,12,0,0,0,8.49-3.52L204.37,68.6a12,12,0,0,0-17-17L164.77,74.26a12,12,0,0,0,8.48,20.49ZM224,116H192a12,12,0,0,0,0,24h32a12,12,0,0,0,0-24Zm-42.26,48.77a12,12,0,1,0-17,17l22.63,22.63a12,12,0,0,0,17-17ZM128,180a12,12,0,0,0-12,12v32a12,12,0,0,0,24,0V192A12,12,0,0,0,128,180ZM74.26,164.77,51.63,187.4a12,12,0,0,0,17,17l22.63-22.63a12,12,0,1,0-17-17ZM76,128a12,12,0,0,0-12-12H32a12,12,0,0,0,0,24H64A12,12,0,0,0,76,128ZM68.6,51.63a12,12,0,1,0-17,17L74.26,91.23a12,12,0,0,0,17-17Z"></path></svg>
                    </button>
                </div>
            </div>
        </form>
        <div class="news-note"></div>
    </div>
    <?php

    return ob_get_clean();
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
    function __construct() {
        $widget_ops = array(
            'classname' => 'getanewsletter_widget',
            'description' => __('Easily integrate Get a Newsletter forms into your WordPress site.', 'getanewsletter')
        );
        parent::__construct(false, __('Get a Newsletter', 'getanewsletter'), $widget_ops);
    }

    static function install() {
        newsletter_plugin_check_version(get_plugin_data(__FILE__));
    }

    /** @see WP_Widget::widget */
    function widget($args, $instance) {
        $apikey = get_option('newsletter_apikey');
    
        extract($args);
        $title = apply_filters('widget_title', empty($instance['title']) ? "" : $instance['title']);
        $key = esc_attr(empty($instance['key']) ? "" : $instance['key']);
        $form_link = empty($instance['form_link']) ? "" : $instance['form_link'];
        $fname = esc_attr(empty($instance['fname']) ? "" : $instance['fname']);
        $lname = esc_attr(empty($instance['lname']) ? "" : $instance['lname']);
        $submittext = esc_attr(empty($instance['submittext']) ? "" : $instance['submittext']);
    
        $customAttributes = get_subscription_attributes(get_option('newsletter_pass'));
        $unique_id = uniqid() . bin2hex(random_bytes(5));
    
        ?>
        <?php echo $before_widget; ?>
    
        <?php if ($title): ?>
            <?php echo $before_title . $title . $after_title; ?>
        <?php endif; ?>
    
        <form method="post" class="newsletter-signup" action="javascript:alert('success!');" enctype="multipart/form-data">
            <input type="hidden" name="action" value="getanewsletter_subscribe" />
    
            <?php if ($fname): ?>
                <div>
                    <label for="id_first_name_<?php echo esc_attr( $unique_id ); ?>">
                        <?php echo !empty($fnametxt) ? $fnametxt : esc_html__('First name', 'getanewsletter'); ?>
                    </label><br />
                    <input id="id_first_name_<?php echo esc_attr( $unique_id ); ?>" type="text" class="text" name="id_first_name" />
                </div>
            <?php endif; ?>
    
            <?php if ($lname): ?>
                <div>
                    <label for="id_last_name_<?php echo esc_attr( $unique_id ); ?>">
                        <?php echo !empty($lnametxt) ? $lnametxt : esc_html__('Last name', 'getanewsletter'); ?>
                    </label><br />
                    <input id="id_last_name_<?php echo esc_attr( $unique_id ); ?>" type="text" class="text" name="id_last_name" />
                </div>
            <?php endif; ?>
    
            <div>
                <label for="id_email_<?php echo esc_attr( $unique_id ); ?>">
                    <?php echo esc_html__('E-mail', 'getanewsletter'); ?>
                </label><br />
                <input id="id_email_<?php echo esc_attr( $unique_id ); ?>" required type="email" class="text" name="id_email" />
            </div>
    
            <?php foreach ($customAttributes as $attribute): ?>
                <?php if (!isset($instance[$attribute['code']]) || !$instance[$attribute['code']]): ?>
                    <?php continue; ?>
                <?php endif; ?>
    
                <div>
                    <label for="attr_<?php echo $attribute['code']; ?>_<?php echo esc_attr( $unique_id ); ?>">
                        <?php echo $attribute['name']; ?>
                    </label><br />
                    <input id="attr_<?php echo $attribute['code']; ?>_<?php echo esc_attr( $unique_id ); ?>" type="text" class="text" name="attributes[<?php echo $attribute['code']; ?>]" />
                </div>
            <?php endforeach; ?>
    
            <div>
                <input type="hidden" name="form_link" value="<?php echo $form_link; ?>" id="id_form_link" />
                <input type="hidden" name="key" value="<?php echo $key; ?>" id="id_key" />
                <div class="gan-button-container">
                    <button type="submit" class="gan-button-container--button">
                        <span class="gan-button-container--button-text"><?php echo ($submittext != '' ? esc_html( $submittext ) : esc_html__('Subscribe', 'getanewsletter')); ?></span>
                        <svg class="gan-button-container--button-spinner" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 256 256"><path d="M140,32V64a12,12,0,0,1-24,0V32a12,12,0,0,1,24,0Zm33.25,62.75a12,12,0,0,0,8.49-3.52L204.37,68.6a12,12,0,0,0-17-17L164.77,74.26a12,12,0,0,0,8.48,20.49ZM224,116H192a12,12,0,0,0,0,24h32a12,12,0,0,0,0-24Zm-42.26,48.77a12,12,0,1,0-17,17l22.63,22.63a12,12,0,0,0,17-17ZM128,180a12,12,0,0,0-12,12v32a12,12,0,0,0,24,0V192A12,12,0,0,0,128,180ZM74.26,164.77,51.63,187.4a12,12,0,0,0,17,17l22.63-22.63a12,12,0,1,0-17-17ZM76,128a12,12,0,0,0-12-12H32a12,12,0,0,0,0,24H64A12,12,0,0,0,76,128ZM68.6,51.63a12,12,0,1,0-17,17L74.26,91.23a12,12,0,0,0,17-17Z"></path></svg>
                    </button>
                </div>
            </div>
        </form>
        <div class="news-note"></div>
    
        <?php echo $after_widget; ?>
        <?php
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
        if ($news_pass) {
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
    
                if ($key) {
                    if ($news_con->subscription_form_get($key)) {
                        $form = $news_con->body;
                        $verify_mail_text = $form->verify_mail_text;
                        $verify_mail_subject = $form->verify_mail_subject;
                    }
                } else {
                    $verify_mail_text = get_option('newsletter_default_verify_mail_text');
                    $verify_mail_subject = get_option('newsletter_default_verify_mail_subject');
                }
    
                ?>
    
                <p>
                    <label for="<?php echo $this->get_field_id('title'); ?>">
                        <?php echo esc_html__('Title', 'getanewsletter'); ?>:
                    </label>
                    <input class="widefat" 
                        id="<?php echo $this->get_field_id('title'); ?>" 
                        name="<?php echo $this->get_field_name('title'); ?>" 
                        type="text" value="<?php echo $title; ?>" />
                </p>
    
                <p>
                    <label for="<?php echo $this->get_field_id('key'); ?>">
                        <?php echo esc_html__('Subscription form', 'getanewsletter'); ?>:
                    </label>
                    <?php if ($news_con->subscription_form_list()): ?>
                        <select data-widget-id="<?php echo $this->number; ?>" class="widefat" id="<?php echo $this->get_field_id('key'); ?>" name="<?php echo $this->get_field_name('key'); ?>">
                            <?php if (empty($key)): ?>
                                <option value=''></option>
                            <?php endif; ?>
                            <?php foreach ($news_con->body['results'] as $form): ?>
                                <option value="<?php echo $form['key']; ?>" <?php echo $key == $form['key'] ? "selected=\"selected\"" : ""; ?>>
                                    <?php echo $form['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <?php
                            echo sprintf(
                                /* translators: %s: URL to create a subscription form */
                                __('Subscription forms not created yet, create a form <a href="%s">here</a>', 'getanewsletter'), 
                                esc_url('https://app.getanewsletter.com/api/forms/')
                            ); 
                        ?>
                    <?php endif; ?>
                </p>
    
                <h3><?php esc_html__( 'Attribute fields', 'getanewsletter' ); ?></h3>
                <span style="font-style: italic"><?php esc_html__( 'Choose which fields to include for this widget. Current options are copied from the original form', 'getanewsletter' ) ?></span>
    
                <p>
                    <input class="checkbox" type="checkbox" checked="checked" disabled="disabled" />
                    <label for="<?php echo $this->get_field_id('email'); ?>">
                        <?php echo esc_html__('Email', 'getanewsletter'); ?>
                        <span style="font-style: italic"> <?php esc_html__( 'Required', 'getanewsletter' ) ?> </span>
                    </label>
                </p>
    
                <p>
                    <input data-newsletter-field-name="fname-<?php echo $this->number; ?>" class="checkbox" id="<?php echo $this->get_field_id('fname'); ?>" 
                        name="<?php echo $this->get_field_name('fname'); ?>" type="checkbox" <?php echo !empty($fname) ? "checked=\"checked\"" : ""; ?> />
                    <label for="<?php echo $this->get_field_id('fname'); ?>">
                        <?php echo esc_html__('First name', 'getanewsletter'); ?>
                    </label>
                </p>
    
                <p>
                    <input data-newsletter-field-name="lname-<?php echo $this->number; ?>" class="checkbox" id="<?php echo $this->get_field_id('lname'); ?>" 
                           name="<?php echo $this->get_field_name('lname'); ?>" type="checkbox" <?php echo !empty($lname) ? "checked=\"checked\"" : ""; ?> />
                    <label for="<?php echo $this->get_field_id('lname'); ?>">
                        <?php echo esc_html__('Last name', 'getanewsletter'); ?>
                    </label>
                </p>
    
                <?php foreach ($customAttributes as $attribute): ?>
                    <p>
                        <input class="checkbox" data-attribute-name="<?php echo $attribute['code'] . '-' . $this->number; ?>" rel="newsletter_attribute" 
                               id="<?php echo $this->get_field_id($attribute['code']); ?>" 
                               name="<?php echo $this->get_field_name($attribute['code']); ?>" 
                               type="checkbox" <?php echo !empty(${$attribute['code']}) ? "checked=\"checked\"" : ""; ?> />
                        <label for="<?php echo $this->get_field_id($attribute['code']); ?>">
                            <?php echo $attribute['name']; ?>
                        </label>
                    </p>
                <?php endforeach; ?>
    
                <p>
                    <label for="<?php echo $this->get_field_id('submittext'); ?>">
                        <?php echo esc_html__('Submit text', 'getanewsletter'); ?>:
                    </label>
                    <input data-newsletter-field-name="submit-text-<?php echo $this->number; ?>" class="widefat" id="<?php echo $this->get_field_id('submittext'); ?>" 
                           name="<?php echo $this->get_field_name('submittext'); ?>" type="text" value="<?php echo $submittext; ?>" />
                </p>
    
                <?php
            } else {
                ?>
                <p><?php echo esc_html__('Wrong Login details. Enter correct details in Get a Newsletter options page.', 'getanewsletter'); ?></p>
                <?php
            }
        } else {
            ?>
            <p><?php echo esc_html__('Enter required details in Get a Newsletter options page.', 'getanewsletter'); ?></p>
            <?php
        }
    }    
}

add_action('widgets_init', function() {
    $api_token = get_option( 'newsletter_pass' );

    if ( ! isset( $api_token ) || ! is_string( $api_token ) || strlen( $api_token ) === 0 ) {
        return;
    }

    $conn = new GAPI( '', $api_token );
    $ok = $conn->check_login();

    if ( ! $ok ) {
        return;
    }

    register_widget("GetaNewsletter");
});

add_action('admin_footer', function() {
    print "
                <script type=\"text/javascript\">
                    jQuery(function($) {
                        $('.widget-liquid-right').on('change', 'select[data-widget-id]', function() {
                            var widgetId = $(this).attr('data-widget-id');
                            $(this).find('option[value=\"\"]').remove();
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
            jQuery('.news-note').hide();

            jQuery('.newsletter-signup').submit(function(e) {
                e.preventDefault();
                
                var form = jQuery(this);
                var data = form.serialize();
                var inputs = form.find('input:not([type="hidden"])');
                var submitButton = form.find('.gan-button-container--button');
                var resultContainer = jQuery('<span></span>');
                var resultWrapper = form.siblings('.news-note');

                resultWrapper.css({
                    'border-radius': '8px',
                    'padding': '10px 20px',
                    'font-weight': '500',
                    'line-height': '1.2'
                });

                jQuery.ajax({
                    'type': 'POST',
                    'url': '<?php echo admin_url('admin-ajax.php'); ?>',
                    'data': data,
                    'cache': false,
                    'beforeSend': function(message) {
                        submitButton.addClass('loading');
                        submitButton.attr('disabled', true);
                    },
                    'success': function(response) {
                        submitButton.removeClass('loading');
                        submitButton.attr('disabled', false);

                        if (response.status === 201) {
                            inputs.val('');
                            form.hide();
                            resultWrapper.show();
                            resultWrapper.css({
                                'background': '#daf1e0',
                                'border': '1px solid #37a169',
                                'color': '#194c34'
                            });
                            resultWrapper.empty().append(
                                resultContainer.addClass('news-success')
                                    .removeClass('news-error')
                                    .html(response.message));
                        } else {
                            resultWrapper.css({
                                'margin-top': '10px',
                                'background': '#fbe5e5',
                                'border': '1px solid #da4444',
                                'color': '#8c2828'
                            });
                            resultWrapper.show();
                            resultWrapper.empty().append(
                                resultContainer.removeClass('news-success')
                                    .addClass('news-error')
                                    .html(response.message));
                        }
                    },
                    'error': function(response) {
                        // This will only trigger for actual HTTP errors
                        submitButton.removeClass('loading');
                        submitButton.attr('disabled', false);
                        resultWrapper.css({
                            'background': '#fbe5e5',
                            'border': '1px solid #da4444',
                            'color': '#8c2828'
                        });
                        resultWrapper.show();
                        resultWrapper.empty().append(
                            resultContainer.removeClass('news-success')
                                .addClass('news-error')
                                .html('A network error occurred. Please try again later.'));
                    }
                });
            });
        });
        //]]>
    </script>
    <?php
}


add_action( 'wp_ajax_gan_register_admin_api_key', 'gan_register_admin_api_key' );
function gan_register_admin_api_key() {
    $token = $_POST['token'];
    $conn = new GAPI('', $token);
    $ok = $conn->check_login();
    $hash = isset( $conn->body['hash'] ) ? $conn->body['hash'] : '';

    if ( ! $ok ) {
        $error_message = esc_html__( 'Please, double check if the provided API key is correct', 'getanewsletter' );

        wp_send_json( array(
            'success' => false,
            'message' => $error_message
        ) );

        die();
    }

    update_option( 'newsletter_pass', $token );
    update_option( 'gan_user_hash', $hash );

    wp_send_json( array(
        'success' => true
    ) );
}

function gan_uninstall_action() {
	delete_option( 'newsletter_plugin_version' );
	delete_option( 'newsletter_default_verify_mail_subject' );
	delete_option( 'newsletter_default_verify_mail_text' );
	delete_option( 'newsletter_pass' );
	delete_option( 'newsletter_user' );
	delete_option( 'newsletter_apikey' );
	delete_option( 'widget_getanewsletter' );
	delete_option( 'gan_redirect_after_activation' );
	delete_option( 'gan_user_hash' );
    delete_option( 'newsletter_msg_success' );
    delete_option( 'newsletter_msg_confirm' );
    delete_option( 'gan_enable_popup_forms' );
}
register_uninstall_hook( __FILE__, 'gan_uninstall_action' );

register_activation_hook( __FILE__, function() {
    add_option( 'gan_redirect_after_activation', 'no' );
} );

add_action( 'admin_init', function() {
    if ( get_option( 'gan_redirect_after_activation' ) !== 'yes' && current_user_can( 'manage_options' ) ) {
        update_option( 'gan_redirect_after_activation', 'yes' );
        wp_safe_redirect( admin_url( 'admin.php?page=newsletter_subscription_forms' ) );
        exit;
    }
} );

add_action( 'plugins_loaded', 'gan_check_user_hash', 99 );
function gan_check_user_hash() {
    $newsletter_pass = get_option( 'newsletter_pass' );

    // If the user hasn't provided the API token - do nothing
    if ( ! isset( $newsletter_pass ) || ! is_string( $newsletter_pass ) || strlen( $newsletter_pass ) === 0 ) {
        return;
    }

    $user_hash = get_option( 'gan_user_hash' );

    // If the user hash is already set - do nothing
    if ( isset( $user_hash ) && is_string( $user_hash ) && strlen( $user_hash ) > 0 ) {
        return;
    }

    // If the API key has been provided, but the user hash is empty - we need to set it for the user
    $conn = new GAPI( '', $newsletter_pass );
    $ok = $conn->check_login();

    if ( ! $ok ) {
        return;
    }

    $hash = $conn->body['hash'];

    if ( ! isset( $hash ) || ! is_string( $hash ) || strlen( $hash ) === 0 ) {
        return;
    }

    update_option( 'gan_user_hash', $hash );
}

add_action( 'wp_head', 'gan_inject_popup_script', 99 );
function gan_inject_popup_script() {
    $enable_popup_form = get_option( 'gan_enable_popup_forms', false );

    if ( ! $enable_popup_form ) {
        return;
    }

    $newsletter_pass = get_option( 'newsletter_pass' );

    if ( ! isset( $newsletter_pass ) || ! is_string( $newsletter_pass ) || strlen( $newsletter_pass ) === 0 ) {
        return;
    }

    $user_hash = get_option( 'gan_user_hash' );

    if ( ! isset( $user_hash ) || ! is_string( $user_hash ) || strlen( $user_hash ) === 0 ) {
        return;
    }

    ?>

    <!-- Get a Newsletter popup form -->
    <script>
    !function(e,t,n,a,c,r){function o(){var e={a:arguments,q:[]},t=this.push(e)
    ;return"number"!=typeof t?t:o.bind(e.q)}
    e.GetanewsletterObject=c,o.q=o.q||[],e[c]=e[c]||o.bind(o.q),
    e[c].q=e[c].q||o.q,r=t.createElement(n);var i=t.getElementsByTagName(n)[0]
    ;r.async=1,
    r.src="https://cdn.getanewsletter.com/js-forms-assets/universal.js?v"+~~((new Date).getTime()/1e6),
    i.parentNode.insertBefore(r,i)}(window,document,"script",0,"gan");
    var gan_account=gan("accounts","<?php echo esc_html( $user_hash ); ?>","load");
    </script>
    <!-- End Get a Newsletter popup form -->

    <?php
}

add_action( 'update_option_newsletter_pass', 'update_user_hash_after_token_update', 99, 3 );
function update_user_hash_after_token_update( $old_value, $value, $option ) {
    $token = $value;
    $conn = new GAPI('', $token);
    $ok = $conn->check_login();

    if ( ! $ok ) {
        delete_option( 'gan_user_hash' );
    }

    $hash = isset( $conn->body['hash'] ) ? $conn->body['hash'] : '';
    update_option( 'gan_user_hash', $hash );
}

add_action('update_option_newsletter_pass', 'gan_clear_auth_cache', 10, 3);
function gan_clear_auth_cache($old_value, $new_value, $option) {
    // Clear cache for both old and new tokens
    foreach ([$old_value, $new_value] as $token) {
        if ($token) {
            $hash = md5($token);
            delete_transient('gan_auth_failed_' . $hash);
            delete_transient('gan_auth_email_sent_' . $hash);
        }
    }
}

add_action( 'init', 'gan_register_blocks' );
function gan_register_blocks() {
    wp_register_script(
        'gan-block-js',
        plugins_url( 'blocks/build/index.js', __FILE__ ),
        array('wp-blocks', 'wp-element', 'wp-editor', 'wp-i18n'),
        null
    );

    wp_set_script_translations( 'gan-block-js', 'getanewsletter', plugin_dir_path(__FILE__) . 'languages' );

    wp_localize_script( 'gan-block-js', 'ganAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
    ) );

    wp_register_style(
        'gan-block-css',
        plugins_url( 'blocks/build/style-index.css', __FILE__ ),
        array(),
        null
    );

    register_block_type( 'gan/newsletter-form', array(
        'editor_script' => 'gan-block-js',
        'style' => 'gan-block-css',
        'render_callback' => 'render_gan_block',
        'attributes' => array(
            'formId' => array(
                'type' => 'string',
                'default' => '',
            ),
            'isTitleEnabled' => array(
                'type' => 'boolean',
                'default' => false,
            ),
            'formTitle' => array(
                'type' => 'string',
                'default' => __('Join our newsletter', 'getanewsletter'),
            ),
            'isDescriptionEnabled' => array(
                'type' => 'boolean',
                'default' => false,
            ),
            'formDescription' => array(
                'type' => 'string',
                'default' => __('Enter your details below to sign up for our newsletter', 'getanewsletter'),
            ),
            'appearance' => array(
                'type' => 'string',
                'default' => 'square',
            ),
            'fieldBackground' => array(
                'type' => 'string',
                'default' => '#ffffff',
            ),
            'fieldBorder' => array(
                'type' => 'string',
                'default' => '#000000',
            ),
            'labelColor' => array(
                'type' => 'string',
                'default' => '#000000',
            ),
            'buttonBackground' => array(
                'type' => 'string',
                'default' => '#0280FF',
            ),
            'buttonTextColor' => array(
                'type' => 'string',
                'default' => '#ffffff',
            ),
        ),
    ) );
}

function gan_block_get_subscription_form( $form_id ) {
    $news_pass = get_option('newsletter_pass');
    if ( !isset( $news_pass ) || ! is_string( $news_pass ) || strlen( $news_pass ) === 0) {
        return [
            'success' => false,
            'error' => 'Invalid API token'
        ];
    }

    $conn = new GAPI( '', $news_pass );
    $ok = $conn->check_login();
    if ( ! $ok ) {
        return [
            'success' => false,
            'error' => 'Failed API authentication'
        ];
    }

    if ( ! isset( $form_id ) || ! is_string( $form_id ) || strlen( $form_id ) === 0 ) {
        return [
            'success' => false,
            'error' => 'Invalid form key'
        ];
    }

    $form = get_subscription_form( $news_pass, $form_id );
    $customAttributes = get_subscription_attributes( get_option( 'newsletter_pass' ) );

    return [
        'success' => true,
        'data' => [
            'form' => $form,
            'customAttributes' => $customAttributes,
        ]
    ];
}

function render_gan_block( $attributes ) {
    if ( empty( $attributes['formId'] ) ) {
        $no_form_error_text = __( 'No form selected.', 'getanewsletter' );
        return '<p>' . esc_html( $no_form_error_text ) . '</p>';
    }

    $form_id = esc_attr( $attributes['formId'] );
    $gutenberg_unique_id = $attributes['uniqueId'];

    $response = gan_block_get_subscription_form( $form_id );

    if ( ! isset( $response['success']) || $response['success'] === false || empty( $response['data'] ) ) {
        if ( isset( $response['error'] ) ) {
            return '<p>' . $response['error'] . '</p>';
        } else {
            $render_error_text = __( 'This element cannot be rendered at the moment.', 'getanewsletter' );
            return '<p>' . esc_html( $render_error_text ) . '</p>';
        }
    }

    $form_data = $response['data'];
    $border_radius = $attributes['appearance'] === 'rounded' ? '8px' : '0';

    $classname = 'gan-block-form';

    if ( isset( $gutenberg_unique_id ) && strlen( $gutenberg_unique_id ) > 0 ) {
        $classname .= ' gan-block-form-' . $gutenberg_unique_id;
    }

    $form_html = '<div class="' . esc_attr( $classname ) . '" style="--gan-border-radius: ' . esc_attr($border_radius) . '; --gan-field-background: ' . esc_attr($attributes['fieldBackground']) . '; --gan-field-border: ' . esc_attr($attributes['fieldBorder']) . '; --gan-label-color: ' . esc_attr($attributes['labelColor']) . '; --gan-button-background: ' . esc_attr($attributes['buttonBackground']) . '; --gan-button-text-color: ' . esc_attr($attributes['buttonTextColor']) . ';">';

    if ( $attributes['isTitleEnabled'] ) {
        $form_html .= '<h2 class="gan-block-form--title">' . esc_html( $attributes['formTitle'] ) . '</h2>';
    }

    if ( $attributes['isDescriptionEnabled'] ) {
        $form_html .= '<p class="gan-block-form--description">' . esc_html( $attributes['formDescription'] ) . '</p>';
    }

    $form_html .= '<form method="post" class="newsletter-signup" enctype="multipart/form-data">';
    $form_html .= '<input type="hidden" name="key" value="' . esc_attr( $form_data['form']['key'] ) . '" />';
    $form_html .= '<input type="hidden" name="form_link" value="' . esc_attr( $form_data['form']['form_link'] ) . '" />';
    $form_html .= '<input type="hidden" name="action" value="getanewsletter_subscribe" />';

    if (!empty($form_data['form']['first_name'])) {
        $form_html .= '<div class="gan-block-form--input-field"><label for="id_first_name_' . esc_attr($gutenberg_unique_id) . '">' . esc_html( ( strlen( $form_data['form']['first_name_label'] ) > 0 ? $form_data['form']['first_name_label'] : esc_html__( 'First name', 'getanewsletter' ) ) ) . '</label>';
        $form_html .= '<input id="id_first_name_' . esc_attr($gutenberg_unique_id) . '" type="text" name="id_first_name" /></div>';
    }

    if (!empty($form_data['form']['last_name'])) {
        $form_html .= '<div class="gan-block-form--input-field"><label for="id_last_name_' . esc_attr($gutenberg_unique_id) . '">' . esc_html( ( strlen( $form_data['form']['last_name_label'] ) > 0 ? $form_data['form']['last_name_label'] : esc_html__( 'Last name', 'getanewsletter' ) ) ) . '</label>';
        $form_html .= '<input id="id_last_name_' . esc_attr($gutenberg_unique_id) . '" type="text" name="id_last_name" /></div>';
    }

    $form_html .= '<div class="gan-block-form--input-field"><label for="id_email_' . esc_attr($gutenberg_unique_id) . '">' . esc_html__('Email address', 'getanewsletter') . '</label>';
    $form_html .= '<input id="id_email_' . esc_attr($gutenberg_unique_id) . '" required type="email" name="id_email" /></div>';

    foreach ( $form_data['customAttributes'] as $attribute ) {
        if ( in_array( $attribute['code'], $form_data['form']['attributes'], true ) ) {
            $form_html .= '<div class="gan-block-form--input-field"><label for="attr_' . esc_attr( $attribute['code'] ) . '_' . esc_attr($gutenberg_unique_id) . '">' . esc_html( $attribute['name'] ) . '</label>';
            $form_html .= '<input id="attr_' . esc_attr( $attribute['code'] ) . '_' . esc_attr($gutenberg_unique_id) . '" type="text" name="attributes[' . esc_attr( $attribute['code'] ) . ']" /></div>';
        }
    }

    $form_html .= '<div class="gan-button-container">';
    $form_html .= '<button class="gan-button-container--button" type="submit">';
    $form_html .= '<span class="gan-button-container--button-text">' . esc_html( $form_data['form']['button_text'] ?? esc_html__( 'Subscribe', 'getanewsletter' ) ) . '</span>';
    $form_html .= '<svg class="gan-button-container--button-spinner" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 256 256"><path d="M140,32V64a12,12,0,0,1-24,0V32a12,12,0,0,1,24,0Zm33.25,62.75a12,12,0,0,0,8.49-3.52L204.37,68.6a12,12,0,0,0-17-17L164.77,74.26a12,12,0,0,0,8.48,20.49ZM224,116H192a12,12,0,0,0,0,24h32a12,12,0,0,0,0-24Zm-42.26,48.77a12,12,0,1,0-17,17l22.63,22.63a12,12,0,0,0,17-17ZM128,180a12,12,0,0,0-12,12v32a12,12,0,0,0,24,0V192A12,12,0,0,0,128,180ZM74.26,164.77,51.63,187.4a12,12,0,0,0,17,17l22.63-22.63a12,12,0,1,0-17-17ZM76,128a12,12,0,0,0-12-12H32a12,12,0,0,0,0,24H64A12,12,0,0,0,76,128ZM68.6,51.63a12,12,0,1,0-17,17L74.26,91.23a12,12,0,0,0,17-17Z"></path></svg>';
    $form_html .= '</button>';
    $form_html .= '</div>';
    $form_html .= '</form>';
    $form_html .= '<div class="news-note"></div>';
    $form_html .= '</div>';

    return $form_html;
}

add_action( 'wp_ajax_gan_get_subscription_forms_list', 'gan_ajax_get_subscription_forms_list' );
function gan_ajax_get_subscription_forms_list() {
    if ( ! is_user_logged_in() ) {
        wp_send_json_error(
            'You are not logged in'
        );
    }

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error(
            'You are not allowed to use this AJAX endpoint'
        );
    }

    $news_pass = get_option( 'newsletter_pass' );
    
    try {
        $forms = get_subscription_forms_list( $news_pass );
        wp_send_json_success( $forms );
    } catch( \GetANewsletterException $e ) {
        wp_send_json_error(
            'Invalid API token or failed API authentication'
        );
    }
}

add_action('wp_ajax_gan_get_subscription_form', 'gan_ajax_get_subscription_form');
function gan_ajax_get_subscription_form() {
    if ( ! is_user_logged_in() ) {
        wp_send_json_error(
            'You are not logged in'
        );
    }

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error(
            'You are not allowed to use this AJAX endpoint'
        );
    }

    $form_id = isset( $_POST['form_id'] ) ? $_POST['form_id'] : null;

    if ( null === $form_id ) {
        wp_send_json_error( 'Invalid form ID' );
    }

    $result = gan_block_get_subscription_form( $form_id );

    if ( $result['success'] === false ) {
        wp_send_json_error( $result['error'] );
    }

    wp_send_json_success( array(
        'form' => $result['data']['form'],
        'customAttributes' => $result['data']['customAttributes'],
    ) );
}

add_action('wp_ajax_gan_test_connection', 'gan_ajax_test_connection');
function gan_ajax_test_connection() {
    // Check permissions
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array(
            'message' => __('You do not have permission to perform this action.', 'getanewsletter')
        ) );
    }

    // Check nonce
    check_ajax_referer('gan-test-connection', 'nonce');

    $api_token = get_option('newsletter_pass');
    
    if ( ! $api_token ) {
        wp_send_json_error( array(
            'message' => __('No API token configured.', 'getanewsletter')
        ) );
    }

    // Clear the auth cache to force a fresh test
    // Note: We only clear the auth cache, not the email sent cache,
    // to maintain the 24-hour email rate limit
    $token_hash = md5($api_token);
    delete_transient('gan_auth_failed_' . $token_hash);

    // Test the connection
    $conn = new GAPI('', $api_token);
    $result = $conn->check_login();

    if ( $result ) {
        wp_send_json_success( array(
            'message' => __('It works! Your connection is back.', 'getanewsletter')
        ) );
    } else {
        wp_send_json_error( array(
            'message' => sprintf(
                __('Still not working: %s', 'getanewsletter'),
                $conn->errorMessage
            )
        ) );
    }
}
