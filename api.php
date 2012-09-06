<?php

//input:
//	$virname: the virtual name of the file
//output:
//	the url of the file will be returned
function pppfl_getFileByVirtualName($virname) {
	global $wpdb;
	
	$table_vfile = $wpdb->prefix . "pppfl_vfile";
	$virname = $wpdb->escape($virname);
	
	return get_bloginfo('url') . "/wp-content/plugins/ppp-file-linker/uploaded-files/" . $wpdb->get_var("SELECT realname FROM $table_vfile WHERE virname='$virname';");
}

//input:
//	$virname: the id of the file
//output:
//	the url of the file will be returned
function pppfl_getFileById($file_id) {
	global $wpdb;
	
	$table_vfile = $wpdb->prefix . "pppfl_vfile";
	$file_id = $file_id + 0;
	
	return get_bloginfo('url') . "/wp-content/plugins/ppp-file-linker/uploaded-files/" . $wpdb->get_var("SELECT realname FROM $table_vfile WHERE id=$file_id");
}

?>