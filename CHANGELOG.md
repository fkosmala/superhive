# Change Log

All notable changes to this publication will be documented in this file.

## 0.6.0 - 2023-03-23
- [Feature] Popular tags
- [Feature] Add tags page to display post with selected tag
- [Feature] Search bar & page
- [Feature] Add Whoops php debugger for dev mode
- [Feature] Add pages to sitemap
- [Update] Improve the about page
- [Fix] Minimal theme Change icon set from IconMonstr to Typicons
- [Fix] Fix data settings Admin save() function
- [Feature] New Admin dashboard
- [Feature] New Admin settings page
- [Feature] Theme selector
- [Fix] Tweak - Remove unused Classes in every PHP files

## 0.5.0 - 2023-01-22
- [Feature] Add New Post button in Admin
- [Feature] Minify HTML Code in production mode
- [Feature] New CommonController
- [Feature] New genPostsFile() function to query the chain and generate the file
- [Feature] Off-chain static pages
- [Fix] Editor button on fullscreen
- [Fix] Only Minify public pages in production mode
- [Fix] Call posts file generation in Admin & Posts controller
- [Fix] Change blockchain queries to official Hive PHP library
- [Fix] Install script & InstallController
- [Fix] Remove dead code to make cleaner code
- [Fix] Cache folder deletion when Dev Mode
- [Update] More recent composer.lock for production
- [Update] Easier and better Install script

## 0.4.2 - 2022-12-07
- [Update] PDS compliance (app to src folder)
- [Update] All controllers are now PSR compliants
- [Update] New Install controller
- [Update] DocBlocks for phpDocumentor
- [Fix] Fix some bugs in admin & install
- [Fix] More secure and easier installation script
- [Feature] Move from php-hive-tools & php-he-tools to hive-php-toolbox

## 0.4.1 - 2022-07-22
- [Fix] Fix Admin Minify bug (the minify system is removed, will be replaced)
- [Fix] Fix PHP-HE-Tools bug for wallet page
- [Fix] Fix Image drop on editor bug
- [Fix] Fix social save button bug

## 0.4.0 - 2022-07-01
- [Feature] Add 15 most used tags list
- [Feature] Wallet Page
- [Update] Change Markdown parser
- [Update] Add displayTypes for posts
- [Update] Remove config.sample.json verification on GIT
- [Update] Reduce API checking time to 2 minutes
- [Fix] Production mode
- [Fix] Comments display for reblogs only display mode
- [Fix] Typo on Classic theme
- [Fix] Fix overwriteconfig file
- [Fix] Fix invalid request from HiveMind node

## 0.3.0 - 2022-04-21
- [Feature] Composer auto install
- [Feature] Dependancies auto install by Composer
- [Feature] Add Apache2 htaccess files for autoconfig
- [Feature] Add showcase links in README
- [Update] Auto rename of config.sample.json
- [Update] Update README

## 0.2.0 - 2022-02-26
- [Fix] Admin : add images to json_metadata
- [Fix] Recover wallet icon on admin newPost & newPage
- [Fix] Remove some whitespaces in admin templates
- [Fix] Default theme: fix lists bugs
- [Feature] Community account
- [Feature] Upvote
- [Theme] change SakuraCSS to PicoCSS for minimal theme
- [Theme] New theme : Celeste
- [Theme] New theme : Classic

## 0.1.1 - 2021-11-12
- [Fix] Sitemap
- [Fix] RSS
- [Update] WCAG & section508 accessibility compliance
- [Update] Add title to Social networks icons

## 0.1.0 - 2021-11-03
This is the first production release of SuperHive stand-alone version.
You can use it on your PHP server (7.4 minimum).