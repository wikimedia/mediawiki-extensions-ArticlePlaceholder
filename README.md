- - -
Introduction to ArticlePlaceholder
====================

The ArticlePlaceholder is a MediaWiki extension. Read the detailed documentation at https://www.mediawiki.org/wiki/Extension:ArticlePlaceholder

ArticlePlaceholder enables placeholder pages on Wikibase Client installations using data of the repository.

The file includes/Template/Wikidata-P18.xml shouldn't be used in production but is for testing purposes.

## Installation

### Dependencies
ArticlePlaceholder requires
* MediaWiki
* Wikibase Client, connected to a Wikibase Repo
* Scribunto for the display of the content
* Cite for the display of the references on a placeholder page

### Configuration
```
wfLoadExtension( 'ArticlePlaceholder' );
```

needs to be set in the LocalSettings.php of your MediaWiki Installation.

There are multiple settings for the ArticlePlaceholder, which can be configured, a list can be found on [the MediaWiki page of the extension](https://www.mediawiki.org/wiki/Extension:ArticlePlaceholder#Configuration).

### Set-up
In order to be able to actually use the functionalities of the special page and the Lua module, it is necessary to import (via Special:Import or the importDump.php maintenance script) the Template and Module AboutTopic.
These are located in the extension folder in includes/Template.

Optionally to enable the ordering of properties on the ArticlePlaceholder pages, your wiki needs a page with a list of sorted properties.
This page needs to be called MediaWiki:Wikibase-SortedProperties.
