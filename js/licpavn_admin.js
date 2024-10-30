(function($) {
	var ajaxUrl = licpavn_data.ajaxUrl;
	var compression = 0;
	var count = 0;
	var max = 0;
	var repeat = true;
	var repeat_count = 0;

	$(document).ready(function() {
		$('#licpavn_process').click(function() {
			$('#licpavn_process').attr('disabled', true);
			$('.media_crusher .spinner').show();
			$('.process_bar').show();
			$('#progressBar').val(0);
			$('.progress-value').html('Processing 0%');
			compression = 0;
			count = 0;
			max = 0;
			$('#loaded_n_total').html('');
			// get_file('W2-WDHUygE42', 12);
			get_next(count);
			// get_next(7);
		});
	});

	function get_next(current){
		var images = licpavn_data.crusher;
		var i = current;
		max = images.length;
		repeat = true;
		repeat_count = 0;
		
		if (i < max){
		// if (i < 1) {
			var img_id = images[i]['id'];
			var url = images[i]['url'];
			var file_name = images[i]['filename'];

			process_media(img_id, url, file_name, i);
		}

		if (i == max) {
			$('#licpavn_process').removeAttr('disabled');
			$('.media_crusher .spinner').hide();
			$('.progress-value').html('Completed');
			return;
		}
	}

	function get_file(media, $image_id) {
		$hash = media.hash;
		// MediaCrush.get($hash, function(media) {
			var files = media.files;
			if (files){
				var image_ext_d, image_ext;
				for (var j = 0; j < files.length; j++) {
					if (j === 0){
						image_ext_d = files[j].file.substr(files[j].file.length - 3);
					}
					if (image_ext_d == 'png'){
						image_ext = image_ext_d;
					} else if (j === 1){
						image_ext = files[j].file.substr(files[j].file.length - 3);
					}
				}

				$.ajax({
					type: "post",
					url: ajaxUrl,
					data: {
						action: "licpavn_get_media",
						image_id: $image_id,
						image_ext: image_ext,
						image_ext_d: image_ext_d,
						image_hash: media.hash,
					},
					success: function(response) {
						// console.log(response);
						obj = '';
						obj = JSON.parse(response);
						if (obj.status == 'SUCCESS'){
							count++;
							progress_update(obj.file_name, obj.compression, 'Success', '', count);

							MediaCrush.get($hash, function(media) {
								media.delete();
							});
						} else if (obj.status == 'FAILURE'){
							count++;
							progress_update(obj.file_name, 0, 'Failed', obj.desc, count);

							MediaCrush.get($hash, function(media) {
								media.delete();
							});
						}
					}
				});
			} else{

			}

		// });
	}

	function process_media(img_id, url, file_name, desc, count){
		MediaCrush.upload(url, function(media) {
			if (typeof media.hash !== 'undefined'){
				get_media(media, img_id, count, file_name);
			} else {
				count++;
				progress_update(file_name, 0, 'Failed', desc, count);
			}
		});
	}

	function get_media(media, img_id, count, file_name){
		media.update(function(stat){
			// console.log(stat);
			window.setTimeout(function(){
				MediaCrush.get(media.hash, function(medias) {
					if (! medias.blob_type && repeat_count < 5){
						get_media(medias, img_id, count, file_name);
						repeat_count++;
					} else if(medias.blob_type) {
						get_file(medias, img_id);
					} else{
						count++;
						progress_update(file_name, 0, 'Failed', 'Unable to process the request.', count);
					}
				});
			}, 1000);
			// if (stat.status == 'pending'){
			// 	console.log('if');
			// 	console.log(stat);
			// 	// media.wait(function() {
			// 		media.update(function(stats){
			// 			console.log(stats);
			// 			if (stats.status == 'pending'){
			// 			// if (stats.blob_type != '') {
			// 			// if (typeof stats.blob_type === 'undefined'){
			// 				get_file(media, img_id);
			// 			// } else if (repeat) {
			// 			// 	get_media(media, img_id);
			// 			// 	repeat = false;
			// 			}
			// 		});
			// 	// });
			// } else if (stat.status == 200) {
			// 	console.log('else');
			// 	get_file(media, img_id);
			// }
		});
	}

	function progress_update(file_name, compress, status, desc, count){
		compression = compression + compress;
		// console.log('Count:'+count+' Max:'+max);
		if (desc != ''){
			status = status+' <br />Reason: '+desc;
		}
		var html = 'File name: '+file_name+'<br /> Compression: '+compress+'%<br /> Status: '+status+'<br /><br />';
		$('#loaded_n_total').append(html);
		var progress = ((count/max)*100);
		$('#progressBar').val(progress.toFixed(0));
		$('.progress-value').html('Processing '+progress.toFixed(0) + '%');
		get_next(count);
	}
})(jQuery);