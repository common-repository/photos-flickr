readme:
	pod2html --title="Photos Flickr" \
		--css=http://dannyman.toldme.com/wp-content/themes/dtc3/style.css \
		< photos-flickr.php > readme.html
	rm *.tmp
