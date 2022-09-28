//this is the script that interfaces the browser to the backend server
const cors_proxy = 'cors_proxy.php';
function get_torrent(link){
	htm = render_top_menu(active_type);
    document.body.innerHTML = htm+`<h2>Downloading torrent</h2>`;
	fetch(`?add_torrent=${encodeURIComponent(link)}`)
		.then(response => {
			if (response) return response.json()
			throw new Error('Network response was not ok.')
		})
		.then(data => {
			document.title = AppName+": "+data.name;
			document.body.innerHTML += data.body;
			if(!data.done){
				render_update = setTimeout(render, 10000)
			}
		});
}

function render_active_list(){
	var torrents = "";
	htm = render_top_menu(active_type);
    document.body.innerHTML = htm+`<h2>Active Torrents</h2>
    <div></div>`;
	fetch(`?list_torrents`)
		.then(response => {
			if (response) return response.text()
			throw new Error('Network response was not ok.')
		})
		.then(data => {
			document.body.innerHTML += data;
		});

}
