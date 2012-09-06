<?php
/*
Plugin Name: PPP File Linker
Plugin URI: http://main.peterwongpp.com/post/2881
Description: A plugin for managing files for easier referencing.
Version: 1.0
Author: PeterWong
Author URI: http://peterwongpp.com
*/

define('PPPFL_DB_VERSION', '1.0');

define('PPPFL_SELECT_NO_HIDE', 0);
define('PPPFL_SELECT_HIDE_DIR', 1);
define('PPPFL_SELECT_HIDE_FILE', 2);
define('PPPFL_SELECT_DISABLE_DIR', 3);
define('PPPFL_SELECT_DISABLE_FILE', 4);

define('PPPFL_FILE_PATH', WP_PLUGIN_DIR . '/ppp-file-linker/uploaded-files');

function pppfl_nonce_url($get, $action) {
	$url = admin_url("options-general.php?page=pppfl" . $get);
	return wp_nonce_url($url, $action);
}

require_once("classes.php");
require_once("api.php");

if(is_admin()) {
	add_action('admin_menu', 'pppfl_admin_menu');
}

function pppfl_admin_menu() {
	wp_enqueue_script("pppfl_jquery_tab_ui", "/wp-content/plugins/ppp-file-linker/js/jquery-ui.min.js", array("jquery"));
	wp_enqueue_style("pppfl_jquery_ui_style", "/wp-content/plugins/ppp-file-linker/css/jquery-ui.css");
	
	wp_enqueue_style("pppfl_default_style", "/wp-content/plugins/ppp-file-linker/style.css");
	
	wp_enqueue_script("pppfl_head", "/wp-content/plugins/ppp-file-linker/js/head.js", array("jquery"));
	
	add_options_page(__('PPP File Linker', 'pppfl'), __('PPP File Linker', 'pppfl'), 'administrator', 'pppfl', 'pppfl_decision_panel');
}

function pppfl_decision_panel() {
	$Message = "";
	
	if(isset($_GET['action'])) {
		check_admin_referer('pppfl_decision_panel');
		
		switch($_GET['action']) {
			case 'add_dir': 
				pppfl_db_add_dir($_POST['pppfl_create_dir_pid'], $_POST['pppfl_create_dir_virname']);
				$Message = __('The new directory has been created!', 'pppfl');
				break;
			case 'del_dir':
				pppfl_db_del_dir($_POST['pppfl_delete_dir_id']);
				$Message = __('The specified directories have been deleted!', 'pppfl');
				break;
			case 'rename_dir':
				pppfl_db_rename_dir($_POST['pppfl_rename_dir_id'], $_POST['pppfl_rename_dir_virname']);
				$Message = __("The specified directory has been renamed!", "pppfl");
				break;
			case 'upload_file':
				$ErrorCode = pppfl_db_upload_file($_POST['pppfl_upload_file_did'], $_POST['pppfl_upload_file_virname'], $_FILES['pppfl_upload_file_realname']);
				if($ErrorCode == 0) {
					$Message = __("The file has been uploaded successfully!", "pppfl");
				} else if($ErrorCode == 1) {
					$Message = __("The file cannot be uploaded!", "pppfl");
				} else if($ErrorCode == 2) {
					$Message = __("Upload failed! The vitual file name existed!", "pppfl");
				}
				break;
			case 'delete_file':
				$ErrorCode = pppfl_db_delete_file($_POST['pppfl_delete_file_id']);
				if($ErrorCode == 0) {
					$Message = __("The file has been deleted successfully!", "pppfl");
				} else if($ErrorCode == 1) {
					$Message = __("The file cannot be deleted!", "pppfl");
				} else if($ErrorCode == 2) {
					$Message = __("The file selected is not valid!", "pppfl");
				}
				break;
			case 'rename_file':
				$ErrorCode = pppfl_db_rename_file($_POST['pppfl_rename_file_id'], $_POST['pppfl_rename_file_virname']);
				if($ErrorCode == 0) {
					$Message = __("The virtual file has been renamed successfully!", "pppfl");
				} else if($ErrorCode == 1) {
					$Message = __("Rename failed! The specified virtual file name existed!", "pppfl");
				} else if($ErrorCode == 2) {
					$Message = __("The file selected is not valid!", "pppfl");
				}
				break;
			case 'install':
				pppfl_install_db();
				break;
			case 'uninstall':
				pppfl_uninstall_db();
				break;
			default:
				$Message = __("The action you just performed may not correct!", "pppfl");
				break;
		}
	}
	
	pppfl_show_options_page($Message);
}

function pppfl_db_add_dir($pid, $virname) {
	global $wpdb;
	
	$table_vdir = $wpdb->prefix . "pppfl_vdir";
	$virname = $wpdb->escape($virname);
	
	$sql = "INSERT INTO $table_vdir (pid, virname) VALUES ($pid, '$virname');";
	
	$wpdb->query($sql);
}

function pppfl_db_del_dir($dir_id) {
	global $wpdb;
	
	$table_vdir = $wpdb->prefix . "pppfl_vdir";
	$table_vfile = $wpdb->prefix . "pppfl_vfile";
	
	$sql1 = "";
	$sql2 = "";
	
	if($dir_id == 1) {
		$sql1 = "DELETE FROM $table_vdir WHERE id<>1;";
		$sql2 = "DELETE FROM $table_vfile;";
		$defaultDir = $wpdb->escape(__("Base Directory", "pppfl"));
		$sql3 = "UPDATE $table_vdir SET virname='$defaultDir' WHERE id=1;";
		$wpdb->query($sql3);
	} else {
		$ppTree = pppfl_get_tree_dirfiles();
		
		$dir_ids = pppfl_db_del_dir_get_dir_ids($ppTree, $dir_id);
		
		if($dir_ids != "") {
			$dir_ids = explode(", ", $dir_ids);
			$wh1 = "";
			$wh2 = "";
			foreach($dir_ids as $did) {
				if($wh1 == "") {
					$wh1 = "id=" . $did;
					$wh2 = "did=" . $did;
				} else {
					$wh1 .= " OR id=" . $did;
					$wh2 .= " OR did=" . $did;
				}
			}
			
			if($wh1 != "") {
				$sql1 = "DELETE FROM $table_vdir WHERE $wh1;";
				$sql2 = "DELETE FROM $table_vfile WHERE $wh2;";
			}
		}
	}
	
	if($sql1 != "") {
		$wpdb->query($sql1);
		$wpdb->query($sql2);
	}
}
function pppfl_db_del_dir_get_dir_ids($dir, $find_id, $matched = false) {
	$r = "";
	
	if($matched == true) {
		$r = $dir->id;
	} else if($dir->id == $find_id) {
		$r = $dir->id;
		$matched = true;
	}
	
	foreach($dir->child_dirs as $cdir) {
		$tmp_r = pppfl_db_del_dir_get_dir_ids($cdir, $find_id, $matched);
		
		if($r == "") {
			$r = $tmp_r;
		} else {
			if($tmp_r != "") {
				$r = $r . ", " . $tmp_r;
			}
		}
	}
	
	return $r;
}

function pppfl_db_rename_dir($dir_id, $new_name) {
	global $wpdb;
	
	$table_vdir = $wpdb->prefix . "pppfl_vdir";
	$new_name = $wpdb->escape($new_name);
	
	$sql = "UPDATE $table_vdir SET virname='$new_name' WHERE id=$dir_id;";
	
	$wpdb->query($sql);
}

function pppfl_db_upload_file($did, $virname, $file) {
	global $wpdb;
	
	$table_vfile = $wpdb->prefix . "pppfl_vfile";
	$virname = $wpdb->escape($virname);
	$realname = $wpdb->escape($file['name']);
	
	if(pppfl_file_virname_exist($virname)) {
		return 2;
	}
	
	$targetpath = PPPFL_FILE_PATH . "/" . $realname;
	
	if(file_exists($targetpath)) {
		$ext_dot = strrpos($realname, ".");
		$file_name = substr($realname, 0, $ext_dot);
		$file_ext = substr($realname, $ext_dot+1);
		do {
			$rand = mt_rand();
			$realname = $wpdb->escape($file_name . "_" . $rand . "." . $file_ext);
			$targetpath = PPPFL_FILE_PATH . "/" . $realname;
		} while(file_exists($targetpath));
	}
	
	$realname = $wpdb->escape($realname);
	
	if(move_uploaded_file($file['tmp_name'], $targetpath)) {
		$sql = "INSERT INTO $table_vfile (did, virname, realname) VALUES ($did, '$virname', '$realname');";
		$wpdb->query($sql);
		
		return 0;
	} else {
		return 1;
	}
}

function pppfl_db_delete_file($id) {
	global $wpdb;
	
	if($id == -1) {
		return 2;
	}
	
	$table_vfile = $wpdb->prefix . "pppfl_vfile";
	
	$sql = "SELECT realname FROM $table_vfile WHERE id=$id;";
	$realname = $wpdb->get_var($sql);
	
	$file_path = PPPFL_FILE_PATH . "/" . $realname;
	
	if(!file_exists($file_path)) {
		$sql = "DELETE FROM $table_vfile WHERE id=$id;";
		$wpdb->query($sql);
		
		return 0;
	} else {
		if(unlink($file_path)) {
			$sql = "DELETE FROM $table_vfile WHERE id=$id;";
			$wpdb->query($sql);
			
			return 0;
		} else {
			return 1;
		}
	}
}

function pppfl_db_rename_file($id, $virname) {
	global $wpdb;
	
	if($id == -1) {
		return 2;
	}
	
	$table_vfile = $wpdb->prefix . "pppfl_vfile";
	$virname = $wpdb->escape($virname);
	
	if(pppfl_file_virname_exist($virname)) {
		return 1;
	}
	
	$sql = "UPDATE $table_vfile SET virname='$virname' WHERE id=$id;";
	$wpdb->query($sql);
	
	return 0;
}

function pppfl_file_virname_exist($virname) {
	global $wpdb;
	
	$table_vfile = $wpdb->prefix . "pppfl_vfile";
	$virname = $wpdb->escape($virname);
	
	$sql = "SELECT count(*) FROM $table_vfile WHERE virname='$virname' GROUP BY virname;";
	if($wpdb->get_var($sql) == 0) {
		return false;
	}
	
	return true;
}

function pppfl_show_options_page($Message) {
	?>
	<div class="wrap">
		<div class="pppfl_header">
			<h2><?php _e("PPP File Linker", "pppfl"); ?></h2>
			<?php pppfl_show_db_message(); ?>
		</div>
		
		<?php pppfl_show_general_message($Message); ?>
		
		<div class="pppfl_body">
			<?php
				if(get_option("pppfl_db_state") == "installed") {
					pppfl_show_contents();
				}
			?>
			<br style="clear:both;" />
		</div>
		
		<div class="pppfl_footer">
			<?php _e("Developed by PeterWong (http://peterwongpp.com)", "pppfl"); ?>
		</div>
		
	</div>
	<?php
}

function pppfl_show_db_message() {
	if(get_option('pppfl_db_state') == 'installed') {
		_e('If you would like to delete all files that uploaded through this plugin, please click: ', 'pppfl');
		
		$link = pppfl_nonce_url("&action=uninstall", "pppfl_decision_panel");
		echo "<a href='$link' onclick='return confirm(\"" . __("Are you sure to delete all files?") . "\")'>" . __('Remove Completely', 'pppfl') . "</a>";
		
		_e('(Note that this CANNOT BE RESTORED!)', 'pppfl');
	} else {
		_e('Please install the needed database tables before using the plugin: ', 'pppfl');
		
		$link = pppfl_nonce_url("&action=install", "pppfl_decision_panel");
		echo "<a href='$link'>" . __('Install needed tables', 'pppfl') . "</a>";
	}
	
	echo "<br />";
	printf(__('The installed database tables will be named as: %s and %s', 'pppfl'), $wpdb->prefix.'pppfl_vdir', $wpdb->prefix.'pppfl_vfile');
}

function pppfl_show_general_message($Message) {
	if($Message != "") {
		?>
		<div class="pppfl_message">
			<?php echo $Message; ?>
		</div>
		<?php
	}
}

function pppfl_show_contents() {
	?>
	<div class="pppfl_left">
		<h3><?php _e("Diretories and Files"); ?></h3>
		<?php
			$ppTree = pppfl_get_tree_dirfiles();
			
			echo "<ul class='pppfl_dna'>";
			pppfl_gen_vir_list($ppTree);
			echo "</ul>";
		?>
	</div>
	<div class="pppfl_right">
		<?php
			pppfl_widget_dirs();
			pppfl_widget_files();
		?>
	</div>
	<?php
}

function pppfl_widget_dirs() {
	?>
	<div class="pppfl_widget">
		<h3><?php _e("Directory"); ?></h3>
		<div id="pppfl_dir_tabs">
			<ul>
				<li><a href="#pppfl_dir_tab_1"><?php _e("Create", "pppfl"); ?></a></li>
				<li><a href="#pppfl_dir_tab_2"><?php _e("Delete", "pppfl"); ?></a></li>
				<li><a href="#pppfl_dir_tab_3"><?php _e("Rename", "pppfl"); ?></a></li>
			</ul>
			<div id="pppfl_dir_tab_1">
				<h4><?php _e("Create New Directory", "pppfl"); ?></h4>
				<form method="post" action="<?php echo pppfl_nonce_url("&action=add_dir", "pppfl_decision_panel"); ?>">
					<p>
						<label for="pppfl_create_dir_pid"><?php _e("Parent Category", "pppfl"); ?></label>
						<?php
							$ppTree = pppfl_get_tree_dirfiles();
							
							echo "<select name='pppfl_create_dir_pid' style='width:100%;'>";
							pppfl_gen_vir_select($ppTree, PPPFL_SELECT_HIDE_FILE);
							echo "</select>";
						?>
					</p>
					<p>
						<label for="pppfl_create_dir_virname"><?php _e("Directory Name: "); ?></label><input type="text" name="pppfl_create_dir_virname" value="" />
					</p>
					<p>
						<input type="submit" value="<?php _e("Submit"); ?>" />
					</p>
				</form>
			</div>
			<div id="pppfl_dir_tab_2">
				<h4><?php _e("Delete Directories", "pppfl"); ?></h4>
				<form method="post" action="<?php echo pppfl_nonce_url("&action=del_dir", "pppfl_decision_panel"); ?>" onsubmit="return confirm('<?php _e("Are you sure to delete the directory with all sub-directories and files inside?"); ?>');">
					<p>
						<label for="pppfl_delete_dir_id"><?php _e("Deleta Category and its contents", "pppfl"); ?></label>
						<?php
							$ppTree = pppfl_get_tree_dirfiles();
							
							echo "<select name='pppfl_delete_dir_id' style='width:100%;'>";
							pppfl_gen_vir_select($ppTree, PPPFL_SELECT_HIDE_FILE);
							echo "</select>";
						?>
					</p>
					<p>
						<input type="submit" value="<?php _e("Submit"); ?>" />
					</p>
				</form>
			</div>
			<div id="pppfl_dir_tab_3">
				<h4><?php _e("Rename Directory", "pppfl"); ?></h4>
				<form method="post" action="<?php echo pppfl_nonce_url("&action=rename_dir", "pppfl_decision_panel"); ?>">
					<p>
						<label for="pppfl_rename_dir_id"><?php _e("Rename Category", "pppfl"); ?></label>
						<?php
							$ppTree = pppfl_get_tree_dirfiles();
							
							echo "<select name='pppfl_rename_dir_id' style='width:100%;'>";
							pppfl_gen_vir_select($ppTree, PPPFL_SELECT_HIDE_FILE);
							echo "</select>";
						?>
					</p>
					<p>
						<label for="pppfl_rename_dir_virname"><?php _e("New Directory Name: "); ?></label><input type="text" name="pppfl_rename_dir_virname" value="" />
					</p>
					<p>
						<input type="submit" value="<?php _e("Submit"); ?>" />
					</p>
				</form>
			</div>
		</div>
	</div>
	<?php
}

function pppfl_widget_files() {
	?>
	<div class="pppfl_widget">
		<h3><?php _e("File"); ?></h3>
		<div id="pppfl_file_tabs">
			<ul>
				<li><a href="#pppfl_file_tab_1"><?php _e("Upload", "pppfl"); ?></a></li>
				<li><a href="#pppfl_file_tab_2"><?php _e("Delete", "pppfl"); ?></a></li>
				<li><a href="#pppfl_file_tab_3"><?php _e("Rename", "pppfl"); ?></a></li>
			</ul>
			<div id="pppfl_file_tab_1">
				<h4><?php _e("Upload New File", "pppfl"); ?></h4>
				<form method="post" action="<?php echo pppfl_nonce_url("&action=upload_file", "pppfl_decision_panel"); ?>" enctype="multipart/form-data">
					<p>
						<label for="pppfl_upload_file_did"><?php _e("Choose Category", "pppfl"); ?></label>
						<?php
							$ppTree = pppfl_get_tree_dirfiles();
							
							echo "<select name='pppfl_upload_file_did' style='width:100%;'>";
							pppfl_gen_vir_select($ppTree, PPPFL_SELECT_HIDE_FILE);
							echo "</select>";
						?>
					</p>
					<p>
						<label for="pppfl_upload_file_realname"><?php _e("Choose File: "); ?></label><input type="file" name="pppfl_upload_file_realname" /><br />
						<label for="pppfl_upload_file_virname"><?php _e("Virtual Name: "); ?></label><input type="text" name="pppfl_upload_file_virname" value="" />
					</p>
					<p>
						<input type="submit" value="<?php _e("Submit"); ?>" />
					</p>
				</form>
			</div>
			<div id="pppfl_file_tab_2">
				<h4><?php _e("Delete File", "pppfl"); ?></h4>
				<form method="post" action="<?php echo pppfl_nonce_url("&action=delete_file", "pppfl_decision_panel"); ?>">
					<p>
						<label for="pppfl_delete_file_id"><?php _e("Choose File", "pppfl"); ?></label>
						<?php
							$ppTree = pppfl_get_tree_dirfiles();
							
							echo "<select name='pppfl_delete_file_id' style='width:100%;'>";
							pppfl_gen_vir_select($ppTree, PPPFL_SELECT_DISABLE_DIR);
							echo "</select>";
						?>
					</p>
					<p>
						<input type="submit" value="<?php _e("Submit"); ?>" />
					</p>
				</form>
			</div>
			<div id="pppfl_file_tab_3">
				<h4><?php _e("Rename File", "pppfl"); ?></h4>
				<form method="post" action="<?php echo pppfl_nonce_url("&action=rename_file", "pppfl_decision_panel"); ?>">
					<p>
						<label for="pppfl_rename_file_id"><?php _e("Rename File", "pppfl"); ?></label>
						<?php
							$ppTree = pppfl_get_tree_dirfiles();
							
							echo "<select name='pppfl_rename_file_id' style='width:100%;'>";
							pppfl_gen_vir_select($ppTree, PPPFL_SELECT_DISABLE_DIR);
							echo "</select>";
						?>
					</p>
					<p>
						<label for="pppfl_rename_file_virname"><?php _e("New Virtual Name: "); ?></label><input type="text" name="pppfl_rename_file_virname" value="" />
					</p>
					<p>
						<input type="submit" value="<?php _e("Submit"); ?>" />
					</p>
				</form>
			</div>
		</div>
	</div>
	<?php
}

function pppfl_gen_vir_list($dir) {
	echo "<li class='pppfl_dir'>";
	echo $dir->virname . "<br />ID: " . $dir->id;
	
	$count_cdir = count($dir->child_dirs);
	$count_cfile = count($dir->child_files);
	
	if($count_cdir > 0 || $count_cfile > 0) {
		echo "<ul>";
	}
	
	foreach($dir->child_dirs as $cdir) {
		pppfl_gen_vir_list($cdir);
	}
	
	foreach($dir->child_files as $cfile) {
		echo "<li class='pppfl_file'>";
		
		echo $cfile->virname . "<br />ID: " . $cfile->id . "; File: " . $cfile->realname;
		
		echo "</li>";
	}
	
	if($count_cdir > 0 || $count_cfile > 0) {
		echo "</ul>";
	}
	
	echo "</li>";
}

function pppfl_gen_vir_select($dir, $PPPFL_SELECT, $level=0) {
	$the_level = "";
	for($i=0; $i<$level; $i++) $the_level .= '--';
	if($PPPFL_SELECT == PPPFL_SELECT_HIDE_DIR) {
		
	} else if($PPPFL_SELECT === PPPFL_SELECT_DISABLE_DIR) {
		echo "<option value='-1' disabled='disabled'>" . $the_level . $dir->virname . "</option>";
	} else {
		echo "<option value='$dir->id'>" . $the_level . $dir->virname . "</option>";
	}
	
	foreach($dir->child_dirs as $cdir) {
		pppfl_gen_vir_select($cdir, $PPPFL_SELECT, $level+1);
	}
	
	$file_level = $level+1;
	if($PPPFL_SELECT == PPPFL_SELECT_HIDE_FILE) {
		
	} else if($PPPFL_SELECT == PPPFL_SELECT_DISABLE_FILE) {
		foreach($dir->child_files as $cfile) {
			echo "<option value='-1' disabled='disabled'>" . $the_level . $cfile->virname . "</option>";
		}
	} else {
		foreach($dir->child_files as $cfile) {
			echo "<option value='$cfile->id'>" . $the_level . $cfile->virname . "</option>";
		}
	}
}

function pppfl_install_db() {
	if(is_dir(PPPFL_FILE_PATH)) {
		pppfl_uninstall_db_rmdir(PPPFL_FILE_PATH);
	}
	
	if(!mkdir(PPPFL_FILE_PATH, 0777)) {
		_e("The directory for storing uploaded files cannot be created!", "pppfl");
		return false;
	}
	
	global $wpdb;
	$table_vdir = $wpdb->prefix . 'pppfl_vdir';
	$table_vfile = $wpdb->prefix . 'pppfl_vfile';
	
	$defaultDir = $wpdb->escape(__("Base Directory", "pppfl"));
	
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	
	$sql = "CREATE TABLE $table_vdir (
		id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
		pid MEDIUMINT(9) NOT NULL,
		virname VARCHAR(255) NOT NULL,
		UNIQUE KEY id (id)
	);";
	dbDelta($sql);
	$sql = "ALTER TABLE $table_vdir CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;";
	$wpdb->query($sql);
	
	$sql = "CREATE TABLE $table_vfile (
		id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
		did MEDIUMINT(9) NOT NULL,
		virname VARCHAR(255) NOT NULL,
		realname VARCHAR(255) NOT NULL,
		UNIQUE KEY id (id)
	);";
	dbDelta($sql);
	$sql = "ALTER TABLE $table_vfile CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;";
	$wpdb->query($sql);
	
	$sql = "INSERT INTO $table_vdir (pid, virname) VALUES (1, '$defaultDir');";
	$wpdb->query($sql);
	
	update_option('pppfl_db_state', 'installed');
	update_option('pppfl_db_version', PPPFL_DB_VERSION);
}

function pppfl_uninstall_db() {
	global $wpdb;
	$table_vdir = $wpdb->prefix . 'pppfl_vdir';
	$table_vfile = $wpdb->prefix . 'pppfl_vfile';
	
	$sql = "DROP TABLE $table_vdir;";
	$wpdb->query($sql);
	
	$sql = "DROP TABLE $table_vfile;";
	$wpdb->query($sql);
	
	update_option('pppfl_db_state', 'uninstalled');
	update_option('pppfl_db_version', 0);
	
	pppfl_uninstall_db_rmdir(PPPFL_FILE_PATH);
}
function pppfl_uninstall_db_rmdir($dir) {
	$files = glob($dir."/*");
	
	if($files) {
		foreach($files as $file) {
			if(is_dir($file)) { 
				pppfl_uninstall_db_rmdir("$file");
			} else {
				unlink($file);
			}
		}
	}
	
	if(is_dir($dir)) {
		rmdir($dir);
	}
}

?>