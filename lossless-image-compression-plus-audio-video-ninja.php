<?php
/*
Plugin Name: Lossless Image Compression Plus Audio Video Ninja
Plugin URI: http://customwpninjas.com/
Description: Lossless image compression now offers audio and video compression! Do not sacrifice quality for Google PageSpeed, get both with this plugin developed to fit your website.
Version: 1.0.1
Author: CustomWPNinjas
Author URI: http://customwpninjas.com/
Contributor: Ishan Kukadia
Tested up to: 4.0
Text Domain: acf-ngmdl

License: GPL2
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit; 

// Constants
define('LICPAVN_PLUGIN_URL', plugins_url() . '/lossless-image-compression-plus-audio-video-ninja');
define('LICPAVN_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

function licpavn_register_admin_scripts(){
	wp_register_script('licpavn_media_crusher_api', 'https://mediacru.sh/static/mediacrush.js', array(), '1');
	wp_enqueue_script('licpavn_media_crusher_api');

	wp_register_style( 'licpavn-style', LICPAVN_PLUGIN_URL.'/css/licpavn_style.css' );
	wp_enqueue_style( 'licpavn-style' );
}
add_action( 'admin_enqueue_scripts', 'licpavn_register_admin_scripts' );

function licpavn_columns_head($columns){
	$columns['nmc'] = 'Media Crush';
	return $columns;
}
add_filter('manage_media_columns', 'licpavn_columns_head');

function licpavn_columns_content($column_name, $post_ID) {
    if ($column_name == 'nmc') {
    	$compressed = get_post_meta($post_ID, 'licpavn_compressed', true);
    	
    	$current_file = get_attached_file((int) $post_ID, true);
		$current_path = substr($current_file, 0, (strrpos($current_file, "/")));
		$file_path = str_replace("//", "/", $current_file);

		$file_size = size_format(filesize($file_path), 2);
		$file_size = preg_replace('/\.00 B /', ' B', $file_size);

		if (!empty($compressed)) {
			echo 'Reduced by '.$compressed['compressed'].'%';
			// output the filesize
			echo '<br> Image Size: '. $file_size;
			//
			$original_size = preg_replace('/\.00 B /', ' B', size_format($compressed['original'], 2));
			echo '<br> Original Size: '. $original_size;

			// output a link to re-optimize manually
			echo '<br><a target="_blank" href="'.admin_url("upload.php?page=licpavn-media-crusher&licpavn_id=$post_ID").'">Re-Optimize</a>';			
		} else {
    		echo 'Not processed';
    		// output the filesize
    		echo '<br> Image Size: '. $file_size;
			// output a link to re-optimize manually
			echo '<br><a target="_blank" href="'.admin_url("upload.php?page=licpavn-media-crusher&licpavn_id=$post_ID").'">Optimize</a>';			
    	} 
	}
}
add_filter('manage_media_custom_column', 'licpavn_columns_content', 10, 2);

function licpavn_submenu(){
	add_submenu_page( 'upload.php', 'Compression Ninja', 'Compression Ninja', 'manage_options', 'compression-ninja', 'licpavn_media_crusher') ;
}
add_action( 'admin_menu', 'licpavn_submenu');

function licpavn_media_crusher(){

	$post_in = '';
	if (isset($_GET['licpavn_id'])){
		$post_in = array($_GET['licpavn_id']);
	}

	$unoptimized = array(
		'meta_query' => array(
		   'relation' => 'OR',
		    array(
				'key' => 'licpavn_compressed',
				'value' => '',
				'compare' => 'NOT EXISTS', // works!				
		    ),
		    array(
				'key' => 'licpavn_compressed',
				'value' => '',
				'compare' => '=',
		    )
		)
	);

	$query_images_args = array(
	    'post_type' => 'attachment', 
	    // 'post_mime_type' =>'image', 
	    'post_status' => 'inherit', 
	    'posts_per_page' => -1,
	    'post__in' => $post_in,
	);

	if (empty($post_in)){
		$query_images_args = array_merge($query_images_args, $unoptimized);
	}

	$query = new WP_Query( $query_images_args );
	$count = $query->found_posts;
	$total_images = 0;
	$medias = array();
	$i = 0;
	foreach ( $query->posts as $media) {
		$attachment_id = $media->ID;
		$medias[$i]['id'] = $attachment_id;
	    $medias[$i]['url']= wp_get_attachment_url( $attachment_id );
	    $filename = $media->guid;
	    $medias[$i]['filename'] = basename($filename);
	    $i++;
	    $metas = wp_get_attachment_metadata( $attachment_id, $unfiltered );
	    $total_images += count($metas['sizes']);
	}

	wp_reset_postdata();

	wp_register_script('licpavn_admin_js', LICPAVN_PLUGIN_URL. '/js/licpavn_admin.js', array(), '1');
	$translation_array = array( 'ajaxUrl' => admin_url('admin-ajax.php'), 'crusher' => $medias );
	wp_localize_script( 'licpavn_admin_js', 'licpavn_data', $translation_array );
	wp_enqueue_script('licpavn_admin_js');

	echo '<div class="media_crusher">';
	echo '<h2>Lossless Image Compression Plus Audio Video Ninja</h2>';
	echo '<div class="update-nag">NOTE: You are about to replace the media files. There is no undo. Think about it!.</div>';
	
	echo '<div class="process_go">';
	echo '<h3 style="display: block;">Compress Entire Media Library</h3>';
	if ($count == 0){
		echo '<p>No Media to crush. Either there are no media files in the library or all the media files are already crushed or the selected file doesn\'t exist.</p>';
	} else {
		echo '<p>'.$count.' images in the Media Library have been selected ('.$count.' unoptimized), with '.$total_images.' resizes ('.$total_images.' unoptimized).<br />';
		if (empty($post_in)){
			echo 'Previously optimized images will be skipped by default.';
		}
		echo '</p>';
		$other_attributes = array( 'id' => 'licpavn_process' );
		submit_button( 'Crush!', 'primary', '', true, $other_attributes);
		echo '<span class="spinner"></span>';
		echo '</div>';

		echo '<div class="process_bar"><progress id="progressBar" value="0" max="100"></progress><span class="progress-value">0%</span></div>';
		echo '<div id="loaded_n_total"></div>';
	}
	echo '</div>';
}

function licpavn_get_media(){
	$file_name = $_POST['image_hash'];
	$file_ext = str_replace('.', '', $_POST['image_ext']);
	$file_ext_d = str_replace('.', '', $_POST['image_ext_d']);
	$image_id = $_POST['image_id'];
	$status = 'SUCCESS';
	$desc = '';

	$file_ext_d = str_replace(array('mv'), array('mp4'), $file_ext_d);

	$image = 'https://mediacru.sh/download/'.$file_name.'.'.$file_ext_d;

	if ($content = file_get_contents($image)){
		if (file_put_contents(LICPAVN_PLUGIN_PATH."images/".$file_name.".".$file_ext, $content)){
			/**/
			// Define DB table names
			global $wpdb;
			$table_name = $wpdb->prefix . "posts";
			$postmeta_table_name = $wpdb->prefix . "postmeta";

			// Get old guid and filetype from DB
			$sql = "SELECT guid, post_mime_type FROM $table_name WHERE ID = '" . (int) $image_id . "'";
			list($current_filename, $current_filetype) = $wpdb->get_row($sql, ARRAY_N);

			// Massage a bunch of vars
			$current_guid = $current_filename;
			$current_filename = substr($current_filename, (strrpos($current_filename, "/") + 1));

			$current_file = get_attached_file((int) $image_id, true);
			$current_path = substr($current_file, 0, (strrpos($current_file, "/")));
			$current_file = str_replace("//", "/", $current_file);
			$current_filename = basename($current_file);
			$current_filesize = filesize($current_file);

			$new_filename = $file_name.".".$file_ext;
			$new_filepath = LICPAVN_PLUGIN_PATH."images/".$new_filename;
			$new_filesize = filesize($new_filepath);

			// New method for validating that the uploaded file is allowed, using WP:s internal wp_check_filetype_and_ext() function.
			$filedata = wp_check_filetype_and_ext($new_filepath, $new_filename);
			$new_filetype = $filedata["type"];

			$compression = 0;
			
			if ($filedata["ext"] == "") {
				$status = 'FAILURE';
				$desc = 'File type does not meet security guidelines.';
			} else {
				// save original file permissions
				$original_file_perms = fileperms($current_file) & 0777;

				// Drop-in replace and we don't even care if you uploaded something that is the wrong file-type.
				// That's your own fault, because we warned you!

				$del = licpavn_delete_current_files($current_file, $image_id);
				$desc = $del['desc'];
				if ($del['status'] != 'FAILURE'){

					// Move new file to old location/name
					if (copy($new_filepath, $current_file)){
						unlink($new_filepath);
					} else {
						$status = 'FAILURE';
					}

					// Chmod new file to original file permissions
					chmod($current_file, $original_file_perms);

					// Get image meta
					$meta = get_post_meta( $image_id, 'licpavn_compressed', true );
					if (!empty($meta) && !empty($meta['original'])){
						// Compression percentage
						$compression = array( 'compressed' => number_format((float)((($current_filesize-$new_filesize)*100)/$current_filesize), 2, '.', ''), 'original' => $meta['original']);
					} else {
						// Compression percentage
						$compression = array( 'compressed' => number_format((float)((($current_filesize-$new_filesize)*100)/$current_filesize), 2, '.', ''), 'original' => $current_filesize);
					}

					// Make thumb and/or update metadata
					wp_update_attachment_metadata( (int) $image_id, wp_generate_attachment_metadata( (int) $image_id, $current_file ) );

					update_post_meta( $image_id, 'licpavn_compressed', $compression );

					// Trigger possible updates on CDN and other plugins 
					update_attached_file( (int) $image_id, $current_file);
				} else {
					$return = $del['status'];
				}
			}
			$return = array('status' => $status, 'desc' => $desc, 'compression' => $compression['compressed'], 'file_name' => $current_filename);
			
			echo json_encode($return);
		} else {
			echo json_encode(array('status' => "FAILURE", 'desc' => 'Unable to copy file from source.' ));
		}
	} else {
		echo json_encode(array('status' => "FAILURE", 'desc' => 'Unable to get source file.' ));
	}
	exit();
}
add_action('wp_ajax_licpavn_get_media', 'licpavn_get_media');

function licpavn_delete_current_files($current_file, $image_id) {
	// Delete old file

	// Find path of current file
	$current_path = substr($current_file, 0, (strrpos($current_file, "/")));
	
	// Check if old file exists first
	if (file_exists($current_file)) {
		// Now check for correct file permissions for old file
		clearstatcache();
		if (is_writable($current_file)) {
			// Everything OK; delete the file
			unlink($current_file);
		} else {
			// File exists, but has wrong permissions. Let the user know.
			return array('status' => "FAILURE", 'desc' => 'The file '.$current_file.' can not be deleted by the web server, most likely because the permissions on the file are wrong.');
		}
	}
	
	// Delete old resized versions if this was an image
	$suffix = substr($current_file, (strlen($current_file)-4));
	$prefix = substr($current_file, 0, (strlen($current_file)-4));
	// $imgAr = array(".png", ".gif", ".jpg", '.mp4');
	// if (in_array($suffix, $imgAr)) { 
		// It's a png/gif/jpg based on file name
		// Get thumbnail filenames from metadata
		$metadata = wp_get_attachment_metadata($image_id);
		if (is_array($metadata)) { // Added fix for error messages when there is no metadata (but WHY would there not be? I don't knowâ€¦)
			foreach($metadata["sizes"] AS $thissize) {
				// Get all filenames and do an unlink() on each one;
				$thisfile = $thissize["file"];
				// Create array with all old sizes for replacing in posts later
				$oldfilesAr[] = $thisfile;
				// Look for files and delete them
				if (strlen($thisfile)) {
					$thisfile = $current_path . "/" . $thissize["file"];
					if (file_exists($thisfile)) {
						unlink($thisfile);
					}
				}
			}
		}
		// Old (brutal) method, left here for now
		//$mask = $prefix . "-*x*" . $suffix;
		//array_map( "unlink", glob( $mask ) );
	// }
}
?>