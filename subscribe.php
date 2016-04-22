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
        if(!empty($_POST['form_link'])) {
            $form_link = str_replace('http', 'https', $_POST['form_link']);
        } else {
            $response['message'] = __('Subscription form missing mandatory options, please contact administrator.');
            $errors = true;
        }

        if(!$errors) {
            $request = (object) $http->request($form_link, array(
                'headers' => array(
                    'Accept' => 'application/json'
                ),
                'method' => 'POST',
                'body' => array(
                    'email' => $_POST['id_email'],
                    'first_name' => $news_fname,
                    'last_name' => $news_lname
                )
            ));

            $response['status'] = $request->response['code'];

            if($response['status'] == 201) {
                $response['message'] = get_option('newsletter_msg_success');
            } else {
                $response['message'] = __('An error has occured', 'getanewsletter');
            }
        }

        header("HTTP/1.0 " . $response['status']);
        header('Content-Type: application/json');
        echo json_encode($response, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        wp_die();
    }
}
?>
