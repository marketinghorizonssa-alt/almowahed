<?php
require '/home/u414915683/domains/almowahid.sa/public_html/wp-load.php';
global $wpdb;
$out=['active_plugins'=>get_option('active_plugins',[])];
$out['options']=$wpdb->get_results($wpdb->prepare("SELECT option_name, LEFT(option_value,1200) option_value FROM {$wpdb->options} WHERE option_value LIKE %s OR option_name LIKE '%gtm%' OR option_name LIKE '%tagmanager%'",'%GTM-5ZKR4X9C%'),ARRAY_A);
$out['postmeta']=$wpdb->get_results($wpdb->prepare("SELECT post_id,meta_key,LEFT(meta_value,1200) meta_value FROM {$wpdb->postmeta} WHERE meta_value LIKE %s",'%GTM-5ZKR4X9C%'),ARRAY_A);
$out['posts']=$wpdb->get_results($wpdb->prepare("SELECT ID,post_type,post_title FROM {$wpdb->posts} WHERE post_content LIKE %s",'%GTM-5ZKR4X9C%'),ARRAY_A);
echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
