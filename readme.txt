=== Plugin Name ===
Contributors: PeterWong
Donate link: http://peterwongpp.com
Tags: file, link, links
Requires at least: 2.9.2
Tested up to: 2.9.2
Stable tag: 1.0

This is a plugin for managing files for easier referencing throughout the entire site (especially in the template files)!

== Description ==

Do you have any images like logos that will be used in many themes? Hate to upload them into individual theme's image folders? Hate to hard code the links in template files?

This is your soultion! Through the pluging's setting page in the admin menu, you can upload and manage your files in virtual manner and re-call those files anywhere through the plugin's api provided. All files uploaded will be stored in `/plugins/ppp-file-linker/uploaded-files` directory (ppp-file-linker is the plugin's directory).

When one day you don't want the functionality anymore, simply click `Remove Completely` in the setting page and deactivate! However, remember backup your important files inside `/plugins/ppp-file-linker/uploaded-files`!!! When you clicked `Remove Completely`, all files inside that directory will also be deleted!

== Installation ==

1. Upload folder `ppp-file-link` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to `Setting Page > PPP File Linker` and click `Install needed tables`
4. Upload and manage files in the ways you like most (note. only the virtual name or id of the files will be used later)
5. Place `<?php echo pppfl_getFileByVirtualName('virtual name of your file'); ?>` or `<?php echo pppfl_getFileById(id); ?>` in your templates. Please be noted that, virtual name is case-INsensitive!

== Frequently Asked Questions ==

= Can I upload two files with the same virtual name? =

No, you can't. Because the virtual name must identify a unique file!

= Can I upload the same file twice with different virtual name? =

Yes,  you can. Note that the second file will be renamed (a random number will be append at the end of the file name). 

== Screenshots ==

1. Sceeenshot-1

== Changelog ==

= 1.0 =
* The plugin is published.

== Upgrade Notice ==

= 1.0 =
The plugin is published.