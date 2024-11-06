<?php

namespace Globalis\WP\Test;

define('REGISTRATION_ACF_KEY_LAST_NAME', 'field_64749cfff238e');
define('REGISTRATION_ACF_KEY_FIRST_NAME', 'field_64749d4bf238f');
define('REGISTRATION_ACF_KEY_EVENT', 'field_64749cde33fd7');
define('REGISTRATION_ACF_KEY_EMAIL', 'field_64749d780cd14');
define('REGISTRATION_ACF_KEY_PHONE', 'field_64749d920cd15');

add_filter('wp_insert_post_data', __NAMESPACE__ . '\\save_auto_title', 99, 2);
add_action('edit_form_after_title', __NAMESPACE__ . '\\display_custom_title_field');

function save_auto_title($data, $postarr)
{
    if (! $data['post_type'] === 'registrations') {
        return $data;
    }
    if ('auto-draft' == $data['post_status']) {
        return $data;
    }

    if (!isset($postarr['acf'][REGISTRATION_ACF_KEY_LAST_NAME]) || !isset($postarr['acf'][REGISTRATION_ACF_KEY_FIRST_NAME])) {
        return $data;
    }

    $data['post_title'] = "#" . $postarr['ID'] .  " (" . $postarr['acf'][REGISTRATION_ACF_KEY_LAST_NAME] . " " . $postarr['acf'][REGISTRATION_ACF_KEY_FIRST_NAME] . ")";

    $data['post_name']  = wp_unique_post_slug(sanitize_title(str_replace('/', '-', $data['post_title'])), $postarr['ID'], $postarr['post_status'], $postarr['post_type'], $postarr['post_parent']);

    send_registration_mail(
        $postarr['acf'][REGISTRATION_ACF_KEY_EVENT], 
        $postarr['acf'][REGISTRATION_ACF_KEY_LAST_NAME], 
        $postarr['acf'][REGISTRATION_ACF_KEY_FIRST_NAME],
        $postarr['acf'][REGISTRATION_ACF_KEY_EMAIL],
        $postarr['acf'][REGISTRATION_ACF_KEY_PHONE]);

    return $data;
}

function display_custom_title_field($post)
{
    if ($post->post_type !== 'registrations' || $post->post_status === 'auto-draft') {
        return;
    }
    ?>
    <h1><?= $post->post_title ?></h1>
    <?php
}

# Envoi du mail
function send_registration_mail($event_id, $lastname, $firstname, $email, $phone = null) 
{
    $event = get_event_name($event_id);
    $message = "Registration to event: $event\nLast name: $lastname\nFirst name: $firstname\nEmail: $email";
    if (!empty($phone)) {
        $message .= "\nPhone: $phone";
    }
    $message = wordwrap($message, 70, "\r\n");
    $attachments = array(get_ticket($event_id));

    wp_mail(
        $email,
        'Registration to ' . $event,
        $message,
        $attachments
    );
}

function get_event_name($id) {
    global $wpdb;
    $sql_query = $wpdb->prepare("SELECT `post_title` FROM %i WHERE `ID` =  %d", $wpdb->posts, $id);
    $result = $wpdb->get_row($sql_query, ARRAY_A);
    return $result['post_title'];
}

function get_ticket($id) {
    global $wpdb;
    $sql_query = $wpdb->prepare("SELECT `meta_value` FROM %i WHERE `meta_key` = 'event_pdf_entrance_ticket' AND `post_id` = %d", $wpdb->postmeta, $id);
    $result = $wpdb->get_row($sql_query, ARRAY_A);
    return wp_get_attachment_url($result['meta_value']);
}