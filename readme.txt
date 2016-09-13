# TheGameDataBase Importer #
Contributors:  MarcDK  
Tags: tgdb, shortscore, API, TheGameDB  
Requires at least: 3.0  
Tested up to: 4.1  
Stable tag: 1.0
License: GPL2  
License URI: http://www.gnu.org/licenses/gpl-2.0.html  

Imports games from TheGameDatabase API as "game" post types.  

## Description ##

Uses TheGameDB API to generate custom post type 'game' and save them to Wordpress with taxonomies and custom fields.
Implements a wp cron to pull for updates.

## Features ##


## Changelog ##

### 0.9 ###

* commented code

### 0.8 ###

* wp cron for api updates
* publisher and peveloper as taxonomy.
* much better id handling.
* better looking import menu.
* smarter logging.

### 0.7 ###

Custom field "score" initalised with 0.
Refactoring.

### 0.6 ###

error logging to custom log
display custom log on import
update method for new content for the same game

### 0.5 ###

* overview to custom field.
* better error handling.

### 0.4 ###

* Extended image support.
* Refactoring.

### 0.3 ###

* basic image import
* Add ESBN and Youtube

### 0.2 ###

* adds platforms to posts as terms.
* adds genres to posts as terms.
* imports class of game-api.
* added function to check if title exists and then adding platform.
* better validation.


### 0.1 ###

* Imports some games as game post types with title, body, publisher, developer as drafts
* Imports platform taxonomy terms with a check if they are already present.
* Check if tgdb id is already present.
