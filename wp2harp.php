<?php
/*
 * Plugin Name: WordPress to Harp
 * Plugin URI: http://wellingguzman.com/wp2harp
 * Description: WP 2 Harp
 * Version: 0.1
 * Author: Welling Guzman
 * Author URI: http://wellingguzman.com
 * License: MEH
*/

if (!defined('WP2HARP_PLUGIN_SLUG')) {
  define('WP2HARP_PLUGIN_SLUG', basename(dirname(__FILE__)));
}

if (!defined('WP2HARP_PLUGIN_PATH')) {
  define('WP2HARP_PLUGIN_PATH', plugin_dir_path(__FILE__));
}

add_action( 'admin_menu', 'wp2harp_plugin_menu' );

function wp2harp_plugin_menu() {
  add_options_page( 'My Plugin Options', 'WP 2 Harp', 'manage_options', 'wp2harp', 'wp2harp_plugin_options' );
}

function wp2harp_plugin_options() {
  if ( !current_user_can( 'manage_options' ) ) {
    wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
  }
  $result = wp2harp_get_posts();
  wp2harp_create_zip($result);
}

function wp2harp_create_zip($result) {
  $data = $result['data'];
  $paths= $result['path'];
  $exportDir = WP2HARP_PLUGIN_PATH . '/temp/' . uniqid(rand(), true).time().'.zip';
  //$result = mkdir($exportDir, 0755, true);

  $zip = new ZipArchive();
  
  if ($zip->open($exportDir, ZipArchive::CREATE)!==TRUE) {
    exit("cannot create <$filename>\n");
  }
  $dataCreated = array();
  foreach($paths as $path) {
    $pathParts = explode("/", $path['path']);
    preg_match_all("/^wp-content/uploads/[0-9]/[0-9]/(.*)$/", $path['content'], $out);
    
    $_data_content = html_entity_decode(json_encode($data[$pathParts[0]][$pathParts[1]], JSON_PRETTY_PRINT));
    $zip->addFromString($path['path'].".md", $path['content']);
    if (!in_array(dirname($path['path']), $dataCreated)) {
      $zip->addFromString(dirname($path['path'])."/_data.json", $_data_content);
    }
  }
  
  $zip->close();
}


function wp2harp_get_posts() {
  global $post, $more;
  
  $more = 1; 
  $paths = array();
  $data = array();
  $args = array(
    'post_type'=>'any',
    'posts_per_page' => -1
  );
  $posts = new WP_Query($args);
  
  if ($posts->have_posts()):
    while($posts->have_posts()): $posts->the_post();
      $post_id = get_the_ID();
      $post_type = get_post_type($post_id);
      $post_status = get_post_status($post_id);
      $post_slug = $post->post_name;
      $post_content = '';
      $post_content = apply_filters('shortcode_unautop', get_the_content());
      if (!$post_slug) $post_slug = sanitize_title($post->post_title);
      if (!$post_slug) $post_slug = $post_id.$post_type.$post_status;
      $post_tags = get_the_tags();
      
      $item = array("title"=>get_the_title(), "date"=>$post->post_date, "published"=>true, "tags"=>array());
      if ($post_tags) {
        $tags = array();
        foreach($post_tags as $tag) {
          $tags[] = $tag->slug;
        }
        $item["tags"]=$tags;
      }
      $data[$post_type][$post_status][$post_slug] = $item;
      $path = array();
      $path[] = $post_type;
      $path[] = $post_status;
      $path[] = $post_slug;
      $paths[$post_id] = array('path' => implode("/",  $path), 'content' => $post_content );
      
    endwhile;
  
  wp_reset_postdata();
  endif;
  
  return array('data'=>$data, 'path'=>$paths);
}

function wp2harp_set_data_as_path($data, $parent = null, $level = 1){
  static $result = array();

  if (is_array($data) && count($data) > 0) {
    foreach ($data as $key => $value) {
      if ($level < 4) {
        wp2harp_set_data_as_path($value, $parent . '/' . $key, $level+1);
      } else {
        $path = ltrim($parent, '/');
        if (!in_array($path,$result))
          $result[] = $path; 
      }
    }
  } else {
    $path = ltrim($parent, '/');
    if (!in_array($path,$result))
      $result[] = $path; 
  }
  
  
  return $result;
}
