# frootbox/search

Search for the Frootbox CMS: visitors find published pages, news
articles, events and the contents of other modules on the website, in
their language, best hits first.

- **Site search** (`frootbox/search/Search`) is a block with a search field
  and, after a search, the hits page by page with the words marked. Pages
  with results never go into the page cache. Place it on a page of its
  own; the head of the base theme links to the first page with this block
  (`block_page('frootbox/search/Search')`).

The search uses the index of the core (`search_index`), the same as the
global search of the backend, with the published state of every search
source that describes it (`Frootbox\Core\Search\PublishedSource`). The
index follows every action right away; it fills itself on the first
search and `bin/frootbox search:rebuild --website` builds it anew.

Left out are drafts, locked pages, pages left out of the search on the
website (with their branch if so set) and addresses that only show the
content of another language.

## For extensions

A search source of an extension takes part as soon as it implements
`PublishedSource::published()` and returns a `PublishedEntry` with the
target of each item, e.g. `acme/shop/product:01J…`. The target needs an
address, usually from `$registry->plugin()`.

## Query

`frootbox/search/website.find` with `q`, `language`, `limit` and
`offset`, open to anyone, also over the REST API:
`GET /api/v1/frootbox/search/website.find?q=coffee`.
