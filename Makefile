all:
	html

html:
	jb build

clean:
	jb clean

auto:
	fswatch -0 -e _build -e .git . | while read -d "" e; do jb build .; sleep 1; done
