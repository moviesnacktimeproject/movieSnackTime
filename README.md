# movieSnackTime
movieSnackTime is a website that allows you to view and browse the content of the Popcorn Time API.

It can be used without a server backend(just a static index.html file) to browse the content using webtorrent, or with a server backend that uses rtorrent and ffmpeg to stream content. 

movieSnackTime allows you to easily stream content without installing any software on your device.

## Screenshot
![moviesnacktime_screenshot](moviesnacktime_screenshot.png)

## Try the static version of movieSnackTime
https://moviesnacktimeproject.github.io/movieSnackTime/static/

## Installing movieSnackTime on your server

```bash
echo install dependencies
sudo apt-get -y install apache2 php libapache2-mod-php git rtorrent tmux ffmpeg

echo download project to public web folder
sudo chmod 777 /var/www/html
cd /var/www/html
mkdir /var/www/html/downloads/

git clone https://github.com/moviesnacktimeproject/movieSnackTime
cd movieSnackTime

echo make the converted video folder avalible on the web
mkdir /tmp/videos
ln -s /tmp/videos videos

echo setup rtorrent config
cp -a .rtorrent.rc ~/.rtorrent.rc

echo run rtorrent in tmux 
tmux new-session rtorrent
```
