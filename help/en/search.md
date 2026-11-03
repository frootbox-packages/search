---
title: Search on the website
about: [block:frootbox/search/Search]
order: 72
---
Visitors search your website with the block **Site search**. It finds the
published pages, news articles, events and the contents of other modules
in the language of the visitor, best hits first. Drafts never show up.

## Setting up the search

Create a page, e.g. "Search", and place the block **Site search** on it. The
head of the theme then shows a link to it on every page. You do not need
to put the page into a navigation.

## What is found

- Titles, descriptions and the text of all blocks of a page.
- Title, teaser and text of articles and events, for events also the venue.
- Every word must occur; parts of words count, so "roast" finds "roastery".

Not found are locked pages and pages you leave out under **Search on
the website** in the settings of the page, with their subpages if you
choose so, and pages in a language that only show the content of
another language.

## After changes

The search follows every change you publish right away. If a developer
changed the database directly, `bin/frootbox search:rebuild --website`
builds the index anew.
