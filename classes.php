<?php

function pppfl_get_tree_dirfiles() {
	global $wpdb;
	
	$table_vdir = $wpdb->prefix . "pppfl_vdir";
	$table_vfile = $wpdb->prefix . "pppfl_vfile";
	//pppfl_db_version = 1.0
	//vdir: {id, pid, virname}
	//vfile: {id, did, virname, realname}
	
	$tmp_arr = array();
	
	$sql = "SELECT * FROM $table_vfile ORDER By did, id ASC";
	$files = $wpdb->get_results($sql, ARRAY_A);
	$file_arr = array();
	if($files) {
		foreach($files as $file) {
			if(!is_array($file_arr[$file['did']])) {
				$file_arr[$file['did']] = array();
			}
			
			$vFile = new pppfl_vFile();
			
			$vFile->id = $file['id'];
			$vFile->virname = $file['virname'];
			$vFile->realname = $file['realname'];
			
			$file_arr[$file['did']][] = $vFile;
		}
	}
	
	$sql = "SELECT * FROM $table_vdir ORDER BY id ASC";
	$srs = $wpdb->get_results($sql, ARRAY_A);
	foreach($srs as $sr) {
		if(!isset($tmp_arr[$sr['id']])) {
			$vdir = new pppfl_vDir();
			
			$vdir->id = $sr['id'];
			$vdir->virname = $sr['virname'];
			$vdir->child_dirs = array();
			if(is_array($file_arr[$sr['id']])) {
				$vdir->child_files = $file_arr[$sr['id']];
			} else {
				$vdir->child_files = array();
			}
			
			$tmp_arr[$sr['id']] = $vdir;
		} else {
			$vdir = $tmp_arr[$sr['id']];
			
			$vdir->virname = $sr['virname'];
		}
		
		if(!isset($tmp_arr[$sr['pid']])) {
			$vdir = new pppfl_vDir();
			
			$vdir->id = $sr['pid'];
			$vdir->virname = '';
			$vdir->child_dirs = array($tmp_arr[$sr['id']]);
			if(is_array($file_arr[$sr['pid']])) {
				$vdir->child_files = $file_arr[$sr['pid']];
			} else {
				$vdir->child_files = array();
			}
			
			$tmp_arr[$sr['pid']] = $vdir;
		} else {
			$vdir = $tmp_arr[$sr['pid']];
			
			if($sr['id'] != 1) { //1 is the base directory and so is not child of any directory.
				$vdir->child_dirs[] = $tmp_arr[$sr['id']];
			}
		}
	}
	
	return $tmp_arr[1];
}

class pppfl_vDir {
	public $id;
	public $virname;
	public $child_dirs = array();
	public $child_files = array();
}

class pppfl_vFile {
	public $id;
	public $virname;
	public $realname;
}

?>