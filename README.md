# Astra Customizer Reset #
**Contributors:** [brainstormforce](https://profiles.wordpress.org/brainstormforce)  
**Tags:** astra theme, customizer reset, reset astra customizer, reset astra theme  
**Tested up to:** 7.1  
**Stable tag:** 1.1.0  
**Requires at least:** 5.3  
**Requires PHP:** 5.6  
**License:** GPLv2 or later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html  

This plugin helps to reset customizer settings for the Astra theme, with a preview before you publish.

## Description ##

Astra Customizer Reset plugin resets all Astra customizer settings to their defaults. The plugin will serve to <a href="https://wordpress.org/themes/astra/" target="_blank"> Astra </a>WordPress theme only.

It adds a "Reset All" button in the customizer. It opens a menu where you can reset in the preview first and publish when you are happy, or reset and publish right away. It also links to the Import / Export Customizer Settings plugin, so you can export your settings before you reset.

### When it can be used? ###

This is helpful when you experiment with various customizer options to explore the power of the theme and then want to start over. OR wish to set a new design line for the website.

The reset button will take you back at the initial state with the theme.

This will affect only customizer settings like layouts, typography, colors, buttons, etc.

## Installation ##

1. Install the Astra Customizer Reset plugin either via the WordPress plugin directory, or by uploading the files to your server at wp-content/plugins.
2. After activating, you can reset the WordPress customizer options from customizer interface.

## Frequently Asked Questions ##

### Where to find a customizer reset button? ###

Once you install and activate this plugin, go to Appearance > Customize. The Reset All button will appear at the top of the customizer, next to Publish. Click it to choose how to reset.

### Can I reverse customizer reset action? ###

Use "Reset in Preview" if you are not sure. It shows the default settings in the preview only. Close or reload the customizer without clicking Publish and your settings stay as they were.

Once a reset is published, or if you use "Reset and Publish Now", it can not be reverted. You will have to set all the choices again by hand. To be safe, export your settings first with the Import / Export Customizer Settings plugin. The Reset All menu links to it ("Export Settings First", or "Back Up Settings First" when the plugin is not active yet).

### What is the difference between "Reset in Preview" and "Reset and Publish Now"? ###

"Reset in Preview" resets the settings you can change in the customizer, so you can check the result before you publish. It saves the default values of those settings, so they will not follow changes to the defaults in later Astra versions.

"Reset and Publish Now" removes all saved Astra settings at once, including settings that are not shown in the customizer. This gives you the same clean start as a new site. On sites that were set up with an older version of Astra, some default values can be different from "Reset in Preview".

## Screenshots ##

1. The Reset All button next to Publish in the Astra customizer
2. The Reset All menu: Reset in Preview, Reset and Publish Now, and the backup link

## Changelog ##

### 1.1.0 ###
- New: Reset All now opens a menu with two options: "Reset in Preview" and "Reset and Publish Now".
- New: Reset in Preview shows the default settings first. Your live site does not change until you click Publish.
- New: The Reset All menu links to export a backup first with the Import / Export Customizer Settings plugin.
- Improvement: The Reset All menu works with the keyboard and screen readers.

### 1.0.7 ###
- Fix: Reset All button layout broken in WordPress 7.0+ Customizer.
- Fix: RTL layout support for the Reset All button.
- Fix: Site logo (custom_logo) not being reset when using the customizer reset button.

### 1.0.6 ###
- Improvement: Compatibility with Astra 4.0.0.

### 1.0.5 ###
- Improvement: Compatibility for Astra Global Palette and Typography Preset options.

### 1.0.4 ###
- Fix: White label settings does not get applied for the theme name.

### 1.0.3 ###
- Improvement: Updated strings in the warning box.

### 1.0.2 ###
- Improvement: Hardened the security of plugin
- Improvement: Compatibility with latest WordPress PHP_CodeSniffer rules

### 1.0.1 ###
* Fix: The stored version number of the theme is not resetted now. This will still be retained after reset.

### 1.0.0 ###
* Initial release.
