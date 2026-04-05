## Poesías Antonio Pardal

Create Markdown Book:
```bash
php bin/generate_book.php
```

Create PDF:

```bash
docker run --rm \
  -v "$(pwd):/data" \
  -w /data \
  pandoc/latex \
  output/book/book.md \
  -o output/book/book.pdf \
  --pdf-engine=xelatex \
  -V geometry:margin=2.5cm \
  -V papersize=a5
```
