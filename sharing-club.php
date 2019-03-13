<?php
/*
Plugin Name: Sharing Club
Plugin URI: https://wordpress.org/plugins/sharing-club/
Description: Share books, dvd, tools, toys or any object with your community. Your users can easily lend, borrow and rate items and you know who borrowed what.
Author: Manu Z.
Version: 1.3
Author URI: http://netdelight.be/
Text Domain: sharing-club
Domain Path: /languages/
*/
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/* dummy text for translation */
__('na', 'sharing-club');
__('available', 'sharing-club');
__('requested', 'sharing-club');

load_plugin_textdomain(
    'sharing-club',
    false,
    dirname( plugin_basename( __FILE__ ) ) . '/languages'
);

function scwp_validate_lending($item){
    $messages = array();
    foreach($item as $n=>$v){
        if($n=='comment_karma' && !preg_match("/^[0-5]?$/", $v) )$messages[] = __('Please enter a rating between 0 and 5 (or leave empty).', 'sharing-club');
        if($n=='comment_content' && !is_string($v))$messages[] = __('Please enter a valid comment.', 'sharing-club');
        if(($n=='comment_date'||$n=='comment_date_gmt') && $v!='00-00-0000'){
            list($dd, $mm, $yyyy) = explode('-', $v);
            if ( !@checkdate($mm,$dd,$yyyy) ) {
                    $messages[] = __('Please enter a valid date.', 'sharing-club');
            }
        }
    }
    
    if (empty($messages)) return true;
    return implode('<br />', $messages);
}

function scwp_generate_select($selector_id, $table, $selected = 0, $label = NULL, $where = NULL) {
    global $wpdb;
    if($label==NULL)$label = $select_id;
    $query = "SELECT ID, $label AS label FROM ".$wpdb->$table;
    if($where)$query .= ' WHERE '.$where;
    $data = $wpdb->get_results($query);
    echo '<select name="'. $selector_id .'">';
    foreach ($data as $r) {
        echo '<option value="', $r->ID, '"', $selected == $r->ID ? ' selected="selected"' : '', '>', $r->label, '</option>';
    }
    echo '</select>';
}

function scwp_plugin_activate() {
    if ( current_user_can( 'manage_options' ) ) {
        add_option('sharing-club_version', '1.2.2');
    }
}
register_activation_hook( __FILE__, 'scwp_plugin_activate' );

function scwp_init() {
    //if ( current_user_can( 'manage_options' ) ) {
        $object = array(
            'label' => __('Objects', 'sharing-club'),
            'labels' => array(
              'name' => __('Sharing Club', 'sharing-club'),
              'singular_name' => __('Objects', 'sharing-club'),
              'all_items' => __('All objects', 'sharing-club'),
              'add_new_item' => __('Add object', 'sharing-club'),
              'edit_item' => __('Edit object', 'sharing-club'),
              'new_item' => __('New object', 'sharing-club'),
              'view_item' => __('View object', 'sharing-club'),
              'search_items' => __('Search object', 'sharing-club'),
              'not_found' => __('Object not found', 'sharing-club'),
              'not_found_in_trash'=> __('Trashed object not found', 'sharing-club'),
              // this is the menu label :
              'add_new' => __('Add object', 'sharing-club'),
              ),
            'menu_icon' => 'dashicons-universal-access-alt',
            'public' => true,
            'publicly_queryable' => true,
            'has_archive' => true,
            'rewrite' => array('slug' => 'shared_item'),
            'supports' => array(
                'title',
                'editor',
                'thumbnail',
                'excerpt',
                'custom-fields'
            ),
            // meta capabilities (enable roles customization)
            // see https://wordpress.stackexchange.com/questions/14553/allow-member-to-have-access-to-custom-post-type-only-permission-to-only-edit-th
            'capability_type' => 'shared_item',
            'capabilities' => array(
                'edit_post' => 'edit_shared_item',
                'edit_posts' => 'edit_shared_item',
                'edit_others_posts' => 'edit_other_shared_item',
                'publish_posts' => 'publish_shared_item',
                'read_post' => 'read_shared_item',
                'read_private_posts' => 'read_private_shared_item',
                'delete_post' => 'delete_shared_item',
                'delete_posts' => 'delete_shared_item'
            ),            
        );
    
        function scwp_admin_init(){
            $role = get_role( 'administrator' );
            $role->add_cap( 'publish_shared_item' );
            $role->add_cap( 'edit_shared_item' );
            $role->add_cap( 'edit_others_shared_item' );
            $role->add_cap( 'delete_shared_item' );
            $role->add_cap( 'delete_others_shared_item' );
            $role->add_cap( 'read_private_shared_item' );
        }

        add_action('admin_init', 'scwp_admin_init');

        register_post_type('shared_item', $object);

        register_taxonomy(
            'shared_item_category',
            'shared_item',
            array(
                'label' => __('Categories'),
                'labels' => array(
                    'name' => __('Categories'),
                    'singular_name' => __('Category'),
                    'all_items' => __('All categories'),
                    'edit_item' => __('Edit category'),
                    'view_item' => __('View category'),
                    'update_item' =>  __('Update category'),
                    'add_new_item' =>  __('Add category'),
                    'new_item_name' =>  __('New category'),
                    'search_items' =>  __('Search categories'),
                    'popular_items' =>  __('Popular categories')
                ),
                'hierarchical' => true,
                'show_admin_column' => true,
            )
        );

        register_taxonomy_for_object_type( 'shared_item_category', 'shared_item' );

        //Ensure the $wp_rewrite global is loaded
        global $wp_rewrite;
        //Call flush_rules() as a method of the $wp_rewrite object
        $wp_rewrite->flush_rules( false );
    //}
}
add_action('init', 'scwp_init');

function scwp_display_lending_table(){
    require_once plugin_dir_path( __FILE__ ) . 'admin-lending-table-display.php';
}
function scwp_display_lending_form(){
    if ( current_user_can( 'manage_options' ) ) {
        wp_enqueue_style('jquery-ui-css', plugin_dir_url( __FILE__ ) . 'css/jquery-ui.css');
        wp_enqueue_script( 'scwp-admin', plugin_dir_url( __FILE__ ) . 'js/lending-library-admin.js', array( 'jquery', 'jquery-ui-datepicker' ) );
        require_once plugin_dir_path( __FILE__ ) . 'admin-lending-form-display.php';
    }
}
function scwp_add_menu_item(){
    if ( current_user_can( 'manage_options' ) ) {
        add_submenu_page(
            'edit.php?post_type=shared_item', 
            __('Lendings', 'sharing-club'), 
            __('Lendings', 'sharing-club'), 
            'activate_plugins', 
            'display_lending_table', 
            'scwp_display_lending_table'
        );
        add_submenu_page(
            null, // parent slug
            __('Lending form', 'sharing-club'),     // page title
            __('Lending form', 'sharing-club'),     // menu title
            'activate_plugins',   // capability
            'display_lending_form',     // menu slug
            'scwp_display_lending_form' // callback function
        );
    }
}
add_action('admin_menu', 'scwp_add_menu_item');

// add an author column
function shared_item_columns($columns) {
	$new_columns = array(
		'author' => __('Author'),
        'note' => __('Excerpt'),
	);
    return array_merge($columns, $new_columns);
}
add_filter('manage_shared_item_posts_columns' , 'shared_item_columns');

function display_shared_item_columns( $column, $post_id ) {
    if ($column == 'note'){
        echo get_post_field( 'post_excerpt', $post_id );
    }
}
add_action( 'manage_posts_custom_column' , 'display_shared_item_columns', 10, 2 );

// exclude custom comments from regular comments
add_filter( 'comments_clauses', 'scwp_exclude_comments', 10, 1);
function scwp_exclude_comments( $clauses ) {
    // Hide all those comments which aren't of type system_message
    $clauses['where'] .= ' AND comment_type != "lending"';   
    return $clauses;
}




/* FRONT END */

/* Filter the single_template & archive_template with our custom function*/
function scwp_custom_post_templates($template) {
    global $post;
    $archive_page = 'archive-shared_item.php';
    $single_page = 'single-shared_item.php';
    $exists_in_theme = locate_template(array($archive_page, $single_page), false);
    if ( $exists_in_theme != '' ) {
        $template = $exists_in_theme;
    }else{
        wp_enqueue_style( 'scwp-public', plugin_dir_url( __FILE__ ) . 'css/lending-library-public.css');
        wp_enqueue_script( 'scwp-public', plugin_dir_url( __FILE__ ) . 'js/lending-library-public.js', array( 'jquery', 'jquery-ui-datepicker' ) );
        if ( is_post_type_archive ( 'shared_item' ) ) {
            /* Checks for archive template */
            if(file_exists(plugin_dir_path( __FILE__ ).'/templates/'.$archive_page ))
                $template =  plugin_dir_path( __FILE__ ).'/templates/'.$archive_page;
        }else if ($post->post_type == 'shared_item'){
            /* Checks for single template */
            if(file_exists(plugin_dir_path( __FILE__ ).'/templates/'.$single_page))
                $template = plugin_dir_path( __FILE__ ).'/templates/'.$single_page;
        }
    }

    return $template;
}
add_filter('single_template',   'scwp_custom_post_templates');
add_filter('archive_template',  'scwp_custom_post_templates' ) ;


function scwp_process_data(){
    if(is_user_logged_in()){
        global $post, $wpdb;
        if(isset($_POST['comment_ID']) || isset($_POST['iwantit'])){
            if( $post->post_type == 'shared_item' && is_user_logged_in() ){
                if(isset($_REQUEST['iwantit']) && isset( $_POST['booking_nonce'] ) && wp_verify_nonce( $_POST['booking_nonce'], 'booking' )){
                    // email notification
                    $to = get_option('admin_email');
                    $headers = array('Content-Type: text/html; charset=UTF-8');
                    $current_user = wp_get_current_user();
                    $subject = sprintf(__('%s requested an object', 'sharing-club'), $current_user->user_login);
                    $message = __("There's a new request for lending. You can manage the requests connecting to :", 'sharing-club');
                    $message .= ' <a href="'.get_admin_url().'" target="_blank">'.get_admin_url().'</a>';
                    add_filter( 'wp_mail_content_type', function( $content_type ) {
                        return 'text/html';
                    });
                    wp_mail( $to, $subject,  $message, $headers) ;
                    $wpdb->insert( $wpdb->comments, array('user_id'=>get_current_user_id(), 'comment_post_ID'=>get_the_ID(), 'comment_type'=>'lending'));
                    header('Location: '.$_SERVER['REQUEST_URI']);
                }
                if((isset($_POST['comment_karma']) || isset($_POST['comment_content']) ) && wp_verify_nonce( $_POST['review_nonce'], 'review' )){
                    // sanitized data
                    $comment_ID = intval($_POST['comment_ID']);
                    $vars = array('comment_type'=>'lending');
                    if(isset($_POST['comment_karma']))  $vars['comment_karma']  = preg_replace('([^0-5])', '', $_POST['comment_karma']);
                    if(isset($_POST['comment_content'])) $vars['comment_content'] = sanitize_text_field($_POST['comment_content']);
                    $validation = scwp_validate_lending($vars);
                    if($validation){
                        $wpdb->update( $wpdb->comments, $vars, array('comment_ID'=>$comment_ID));   
                    }else{
                        echo $validation;
                        exit();
                    }
                }
            }
            wp_redirect( $_SERVER['REQUEST_URI'] );
            exit();
        }
    }
}
add_action( 'template_redirect', 'scwp_process_data' );

// ADMIN OPTION PAGE
require_once plugin_dir_path( __FILE__ ) . 'admin-options.php';

