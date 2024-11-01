var vinehost = "https://vine.eu"

window.addEventListener('message', function (e) {
	if (e.data.message && e.data.message == 'ma_wordpress_token') {
		var data = {
			action: 'vine_ma_save_option',
			apikey: e.data.token
		};

		jQuery.post(ajaxurl, data, function (response) {
			if (authWindow != null)
				authWindow.close();
			location.reload();
		});
	}
});

var authWindow;

function openMaLoginWindow() {
	authWindow = window.open(vinehost + '/ma/oauth?appName=WordPress', 'mywindow', 'toolbar=no, menubar=no, width=600, height=500');
}

function logoutFromMA() {
	var data = {
		action: 'vine_ma_logout'
	};

	jQuery.post(ajaxurl, data, function (response) {
		location.reload();
	});
}