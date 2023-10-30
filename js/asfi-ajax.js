document.addEventListener('DOMContentLoaded', function() {
	var form = document.getElementById('asfi-form');
	var resultContainer = document.getElementById('asfi-result');
	
	form.addEventListener('submit', function(e) {
		e.preventDefault();
		var postType = document.getElementById('asfi_post_type').value;
		var nonce = document.getElementById('_wpnonce').value;
		
		var data = new FormData();
		data.append('action', 'asfi_process_batch');
		data.append('nonce', nonce);
		data.append('post_type', postType);
		
		fetch(asfi_ajax_object.ajax_url, {
			method: 'POST',
			credentials: 'same-origin',
			body: data
		})
		.then(response => response.json())
		.then(response => {
			if (response.success) {
				resultContainer.innerHTML = '<div class="notice notice-success is-dismissible"><p>' + response.data.updated_count + ' posts processed.</p></div>';
			} else {
				resultContainer.innerHTML = '<div class="notice notice-error is-dismissible"><p>Error occurred. Please try again.</p></div>';
			}
		})
		.catch((error) => {
			console.error('Error:', error);
			resultContainer.innerHTML = '<div class="notice notice-error is-dismissible"><p>An error occurred. Please try again.</p></div>';
		});
	});
});
