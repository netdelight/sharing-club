<?php
if ( ! defined( 'ABSPATH' ) || !is_user_logged_in() ) exit; // Exit if accessed directly

if(!class_exists('WP_List_Table')){
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Lending_Table extends WP_List_Table {
    function __construct(){
        global $status, $page;
                
        //Set parent defaults
        parent::__construct( array(
            'singular'  => 'lending',     //singular name of the listed records
            'plural'    => 'lendings',    //plural name of the listed records
            'ajax'      => false        //does this table support ajax?
        ) );
        
    }

    function column_cb($item){
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            /*$1%s*/ $this->_args['singular'],  //Let's simply repurpose the table's singular label ("movie")
            /*$2%s*/ $item->ID                //The value of the checkbox should be the record's id
        );
    }
    function column_default($item, $column_name){
        switch($column_name){
            case 'availability':
                return __($item->$column_name, 'sharing-club');
            default:
                //return print_r($item,true); //Show the whole array for troubleshooting purposes
                return $item->$column_name;
        }
    }
    
    function get_columns(){
        $columns = array(
            'cb'        => '<input type="checkbox" />', //Render a checkbox instead of text
            'name'     => __('Object', 'sharing-club'),
            'user_nicename' => __('User', 'sharing-club'),
            'fr_date_start' => __('Lending date', 'sharing-club'),
            'fr_date_end' => __('Return date', 'sharing-club'),
            'availability' => __('Availability', 'sharing-club'),
            'note' => __('Admin notes', 'sharing-club'),
        );
        return $columns;
    }
    function get_sortable_columns() {
        $sortable_columns = array(
            'name'     => array('name',false),
            'user_nicename'     => array('user_nicename',false),
            'fr_date_start'     => array('fr_date_start',false),
            'fr_date_end'     => array('fr_date_end',false),
            'availability'     => array('availability',false),
            'note' => array('note',false),
        );
        return $sortable_columns;
    }
    function column_name($item){
        
        //Build row actions
        $actions = array(
            'edit'      => sprintf('<a href="?page=%s&action=%s&ID=%d&post_type=shared_item">%s</a>','display_lending_form','edit',$item->ID, __('Edit', 'sharing-club')),
            'delete'    => sprintf('<a href="?page=%s&action=%s&lending[0]=%d&post_type=shared_item">%s</a>','display_lending_table','delete',$item->ID, __('Delete', 'sharing-club')),
        );
        
        //Return the title contents
        return sprintf('%1$s <span style="color:silver">(id:%2$s)</span>%3$s',
            /*$1%s*/ $item->name,
            /*$2%s*/ $item->ID,
            /*$3%s*/ $this->row_actions($actions)
        );
    }
    
    function get_bulk_actions() {
        $actions = array(
            'delete' => __('Delete', 'sharing-club'),
            'return'    => __('Mark as returned', 'sharing-club'),
        );
        return $actions;
    }

    function process_bulk_action() {
        global $wpdb;
        $table_name = sanitize_key($wpdb->comments); // do not forget about tables prefix
        // sanitize request arg
        $ids = isset($_REQUEST[$this->_args['singular']]) ? preg_replace('([^0-9,])', '', $_REQUEST[$this->_args['singular']]) : array();
        if (!is_array($ids)) {
            $ids = explode(',', $ids);
            foreach($ids as $n=>$v)$ids[$n] = intval($v);
        }
        $ids = implode(',', $ids);
        if ('delete' === $this->current_action()) {
            if (!empty($ids)) {
                $wpdb->query("DELETE FROM $table_name WHERE comment_ID IN($ids)");
            }
        }else if ('return' === $this->current_action()) {
            if (!empty($ids)) {
                $wpdb->query("UPDATE $table_name SET `comment_date_gmt` = '".date('Y-m-d H:i:s')."' WHERE comment_ID IN($ids)");
            }
        }
        
        
    }

    
    function prepare_items() {
        global $wpdb; //This is used only if making any database queries

        /**
         * First, lets decide how many records per page to show
         */
        $per_page = 20;
        
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array($columns, $hidden, $sortable);
        $this->process_bulk_action();
        
        $orderby = (!empty($_REQUEST['orderby'])) ? sanitize_key($_REQUEST['orderby']) : 'comment_date_gmt'; //If no sort, default to title
        $order = (!empty($_REQUEST['order'])) ? sanitize_key($_REQUEST['order']) : 'desc'; //If no order, default to asc
        
        $query = "SELECT comment_ID as ID, user_nicename, post_title AS name, comment_agent AS note, DATE_FORMAT(comment_date, '%d/%m/%Y') AS fr_date_start, DATE_FORMAT(comment_date_gmt, '%d/%m/%Y') AS fr_date_end,
        CASE WHEN comment_date = '0000-00-00' THEN 'requested'
        WHEN comment_date_gmt > CURRENT_TIMESTAMP OR comment_date_gmt = '0000-00-00' THEN 'na'
        ELSE 'available' END availability
        FROM ".$wpdb->comments." AS lending
        LEFT JOIN ".$wpdb->posts." AS objects ON (objects.ID = comment_post_ID)
        LEFT JOIN ".$wpdb->users." AS users ON (users.ID = user_id) 
        WHERE post_type = 'shared_item'
        ORDER BY FIELD(availability, 'requested', 'na', 'available'), $orderby $order";
        $data = $wpdb->get_results($query);

        $current_page = $this->get_pagenum();
        $total_items = count($data);
        
        
        $data = array_slice($data,(($current_page-1)*$per_page),$per_page);

        $this->items = $data;
        
        $this->set_pagination_args( array(
            'total_items' => $total_items,                  //WE have to calculate the total number of items
            'per_page'    => $per_page,                     //WE have to determine how many items to show on a page
            'total_pages' => ceil($total_items/$per_page)   //WE have to calculate the total number of pages
        ) );
    }


}