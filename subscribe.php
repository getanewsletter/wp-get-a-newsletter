<?php
$pageUR1  = preg_replace("/\/(.+)/", "", $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"]);
$curdomain  = str_replace("www.", "", $pageUR1);


if(strpos($_SERVER['HTTP_REFERER'], $curdomain)) {
    error_reporting (E_ALL ^ E_NOTICE);
    $post = (!empty($_POST)) ? true : false;
    $errors = false;
    if($post) {

        $response = array(
            'status' => 200,
            'message' => null
        );

        $news_fname = null;
        $news_lname = null;
        $form_link = null;

        $http = new WP_Http;

        if(!empty($_POST['id_first_name'])) {
            $news_fname = stripslashes($_POST['id_first_name']);
        }
        if(!empty($_POST['id_last_name'])) {
            $news_lname = stripslashes($_POST['id_last_name']);
        }

        $body = array(
            'email' => $_POST['id_email'],
            'first_name' => $news_fname,
            'last_name' => $news_lname,
            'gan_repeat_email' => '',
        );

        if (!empty($_POST['attributes'])) {
            foreach ($_POST['attributes'] as $name => $value) {
                $body[$name] = $value;
            }
        }
        if(!empty($_POST['form_link'])) {
            $form_link = str_replace('http:', 'https:', $_POST['form_link']);
        } else {
            $response['message'] = __('Subscription form missing mandatory options, please contact administrator.', 'getanewsletter');
            $errors = true;
        }

        if(!$errors) {
            $request = (object) $http->request($form_link, array(
                'headers' => array(
                    'Accept' => 'application/json'
                ),
                'method' => 'POST',
                'body' => $body
            ));

            if (
                $request instanceof WP_Error &&
                property_exists($request, 'errors') &&
                $request->errors &&
                $request->errors['http_request_failed']
            ) {
                $form_link = str_replace('https:', 'http:', $_POST['form_link']);
                $request = (object) $http->request($form_link, array(
                    'headers' => array(
                        'Accept' => 'application/json'
                    ),
                    'method' => 'POST',
                    'body' => $body
                ));
            }

            $response['status'] = $request->response['code'];

            if($response['status'] == 201) {
                $response['status'] = 201;
                $response['message'] = get_option('newsletter_msg_success');
            } else {
                $response['status'] = 400;
                $response['message'] = __('An unknown error has occurred. Please try again later.', 'getanewsletter');
                // error_log('GetANewsletter API Error - Status: ' . $response['status']);
                // error_log('GetANewsletter API Response: ' . print_r($request, true));
            }
        }

        header('Content-Type: application/json');
        echo json_encode($response, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        wp_die();
    }
}
?>
