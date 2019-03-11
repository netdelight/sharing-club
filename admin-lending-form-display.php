<?php
if ( ! defined( 'ABSPATH' ) || !is_user_logged_in()) exit; // Exit if accessed directly

global $wpdb;
$table_name = $wpdb->comments; // do not forget about tables prefix

$message = '';
$notice = '';

// this is default $item which will be used for new records
$default = array(
    'comment_ID' => 0,
    'comment_post_ID' => '',
    'user_id' => '',
    'comment_content'=>'',
    'comment_karma'=>'',
    'comment_date' => '00-00-0000',
    'comment_date_gmt' => '00-00-0000',
    'comment_type' => 'lending',
    'comment_agent'=>'',
);

function scwp_sanitize_date($date){
    return preg_replace("([^0-9-])", "", $date);
}
function scwp_prepare_date($date){
    // reformat
    $date = implode('-', array_reverse(explode('-', $date)));
    $date .= ' 00:00:00';
    return $date;
}

// here we are verifying does this request is post back and have correct nonce
if (wp_verify_nonce(@$_REQUEST['nonce'], basename(__FILE__))) {
    // combine our default item with request params
    $item = shortcode_atts($default, $_REQUEST);
    
    // sanitize
    $item['comment_ID']         = intval($item['comment_ID']);
    $item['comment_post_ID']    = intval($item['comment_post_ID']);
    $item['user_id']            = intval($item['user_id']);
    $item['comment_karma']      = sanitize_text_field($item['comment_karma']);
    $item['comment_content']    = sanitize_text_field($item['comment_content']);
    $item['comment_agent']      = sanitize_text_field($item['comment_agent']);
    
    // validate data, and if all ok save item to database
    // if id is zero insert otherwise update
    $item_valid = scwp_validate_lending($item);
    if ($item_valid === true) {
        $item['comment_date']       = scwp_prepare_date(scwp_sanitize_date($item['comment_date']));
        $item['comment_date_gmt']   = scwp_prepare_date(scwp_sanitize_date($item['comment_date_gmt']));
        if ($item['comment_ID'] == 0) {
            $result = $wpdb->insert($table_name, $item);
            $item['comment_ID'] = $wpdb->insert_id;
            if ($result!==false) {
                $message = __('Item was successfully saved', 'sharing-club');
            } else {
                $notice = __('There was an error while saving item', 'sharing-club');
            }
        } else {
            $result = $wpdb->update($table_name, $item, array('comment_ID' => $item['comment_ID']));
            if ($result!==false) {
                $message = __('Item was successfully updated', 'sharing-club');
            } else {
                $notice = __('There was an error while updating item', 'sharing-club');
            }
        }
    } else {
        // if $item_valid not true it contains error message(s)
        $notice = $item_valid;
    }
    // reverse the dates back
    $item['comment_date'] = scwp_sanitize_date($_REQUEST['comment_date']);
    $item['comment_date_gmt'] = scwp_sanitize_date($_REQUEST['comment_date_gmt']);
}
else {
    // if this is not post back we load item to edit or give new one to create
    $item = $default;
    if (isset($_REQUEST['ID'])) {
        // escape the date_format string with %%
        $id = intval($_REQUEST['ID']);
        $item = $wpdb->get_row($wpdb->prepare("SELECT comment_ID, user_id, comment_post_ID, DATE_FORMAT(comment_date, '%%d-%%m-%%Y') AS comment_date, DATE_FORMAT(comment_date_gmt, '%%d-%%m-%%Y') AS comment_date_gmt, comment_karma, comment_content, comment_agent FROM ".$wpdb->comments." WHERE comment_ID = %s", $id), ARRAY_A);
        if (!$item) {
            $item = $default;
            $notice = __('Item not found', 'sharing-club');
        }
    }
}

// here we adding our custom meta box
add_meta_box('form_meta_box',  __('Lending data', 'sharing-club'), 'scwp_meta_box_handler', 'product', 'normal', 'default');

?>
<div class="wrap">
    <div class="icon32 icon32-posts-post" id="icon-edit"><br></div>
    <h2><?php _e('Lendings', 'sharing-club')?> <a class="add-new-h2" href="<?php echo get_admin_url(get_current_blog_id(), 'edit.php?post_type=shared_item&page=display_lending_table');?>"><?php _e('Back to list', 'sharing-club')?></a>
    </h2>

    <?php if (!empty($notice)): ?>
    <div id="notice" class="error"><p><?php echo $notice ?></p></div>
    <?php endif;?>
    <?php if (!empty($message)): ?>
    <div id="message" class="updated"><p><?php echo esc_html($message) ?></p></div>
    <?php endif;?>

    <form id="form" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce(basename(__FILE__))?>"/>
        <?php /* NOTICE: here we storing id to determine will be item added or updated */ ?>
        <input type="hidden" name="comment_ID" value="<?php echo esc_attr($item['comment_ID']) ?>"/>

        <div class="metabox-holder" id="post-shared_item">
            <div id="post-body">
                <div id="post-body-content">
                    <?php /* And here we call our custom meta box */ ?>
                    <?php do_meta_boxes('product', 'normal', $item); ?>
                    <input type="submit" value="<?php _e('Save', 'sharing-club')?>" id="submit" class="button-primary" name="submit">
                </div>
            </div>
        </div>
    </form>
</div>

<?php
    
    
                           
    function scwp_meta_box_handler($item) {
    ?>

<table cellspacing="2" cellpadding="5" style="width: 100%;" class="form-table">
    <tbody>
    <tr class="form-field">
        <th valign="top" scope="row">
            <label for="user_ID"><?php _e('User', 'sharing-club')?></label>
        </th>
        <td>
            <?php scwp_generate_select('user_id', 'users', intval($item['user_id']), 'user_nicename'); ?>
        </td>
    </tr>
    <tr class="form-field">
        <th valign="top" scope="row">
            <label for="comment_post_ID"><?php _e('Object', 'sharing-club')?></label>
        </th>
        <td>
            <?php scwp_generate_select('comment_post_ID', 'posts', intval($item['comment_post_ID']), 'post_title', 'post_type = \'shared_item\''); ?>
        </td>
    </tr>
    <tr class="form-field">
        <th valign="top" scope="row">
            <label for="name"><?php _e('Lending date', 'sharing-club')?></label>
        </th>
        <td>
            <input id="comment_date" name="comment_date" type="text" style="width: 95%" value="<?php echo esc_attr($item['comment_date'])?>" size="50" class="datepicker" placeholder="<?php _e('YYYY-MM-DD', 'sharing-club')?>" required />
        </td>
    </tr>
    <tr class="form-field">
        <th valign="top" scope="row">
            <label for="name"><?php _e('Return date', 'sharing-club')?></label>
        </th>
        <td>
            <input id="comment_date_gmt" name="comment_date_gmt" type="text" style="width: 95%" value="<?php echo esc_attr($item['comment_date_gmt'])?>" size="50" class="datepicker" placeholder="<?php _e('YYYY-MM-DD', 'sharing-club')?>" required />
        </td>
    </tr>
    <tr class="form-field">
        <th valign="top" scope="row">
            <label for="comment_content"><?php _e('Reviews', 'sharing-club')?></label>
        </th>
        <td>
            <textarea id="comment_content" name="comment_content" style="width: 95%"><?php echo stripslashes(esc_html($item['comment_content']))?></textarea>
        </td>
    </tr>
    <tr class="form-field">
        <th valign="top" scope="row">
            <label for="comment_agent"><?php _e('Admin notes', 'sharing-club')?></label>
        </th>
        <td>
            <input id="comment_agent" name="comment_agent" type="text" style="width: 95%" value="<?php echo stripslashes(esc_html($item['comment_agent']))?>"/>
        </td>
    </tr>
    <tr class="form-field">
        <th valign="top" scope="row">
            <label for="comment_karma"><?php _e('Rating', 'sharing-club')?></label>
        </th>
        <td>
            <input id="comment_karma" name="comment_karma" type="text" style="width: 10%" value="<?php echo esc_attr($item['comment_karma'])?>" size="50" />
        </td>
    </tr>
        
    </tbody>
</table>
<?php
}