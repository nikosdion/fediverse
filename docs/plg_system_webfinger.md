# System - WebFinger

This plugin implements the WebFinger protocol (RFC 7033) for Joomla! sites.

It can be used on its own, or in conjunction with the ActivityPub component.

## Requirements

For the plugin to work you _must_ have enabled SEF URLs with rewrite in your Global Configuration.

Go to your site's administrator, System, Setup, Global Configuration and check the following in the System tab, under the SEO heading:

* Search Engine Friendly URLs must be set to Yes.
* Use URL Rewriting must be set to Yes.
* Add Suffix to URL should be Yes, but it's not necessary.
* Unicode Aliases should be set to Yes if your site or your username uses characters other than a-z, A-Z (without accents or diacritics), and 0-9. For example the username δοκιμή and the domain name παράδειγμα.gr require Unicode Aliases to be set to Yes.

If you are using an Apache or LiteSpeed web server you need to copy the `htaccess.txt` file shipped with Joomla to `.htaccess` in your site's root. Alternatively, you can use Admin Tools Professional's .htaccess Maker feature.

If you are using an IIS (Microsoft Internet Information Services) web server you need to copy the `web.config.txt` file shipped with Joomla to `web.config` in your site's root. Alternatively, you can use Admin Tools Professional's Web.Config Maker feature.

If you are using an NginX web server you need to [follow Joomla's advice](https://docs.joomla.org/Nginx) on setting up URL redirections. Alternatively, you can create the necessary code using Admin Tools Professional's NginX Conf Maker feature.

## Getting started

Go to your site's backend, System, Manage, Plugins.

Publish the “System - WebFinger” plugin.

## Plugin options

**List Users in WebFinger**. Controls which of the site's users are going to be made available through WebFinder. The options are:
    * All. All users will be listed.
    * Consent-based. Only the users who have consented in their user options will be listed, unless they are in the Forced Forbidden Groups (in which case they are never listed), or in the Forced Allowed Groups (in which case they are always listed regardless of their consent)/
    * None. No users will be listed unless explicitly defined in the ActivityPub component.

**Forced Allowed Groups**. Users who belong in at least one of these groups will be listed in WebFinger regardless of their consent. 

**Forced Forbidden Groups**. Users who belong in at least one of these groups will NOT be listed in WebFinger, regardless of their consent.

If a user is in both the Forced Allowed Groups and the Forced Forbidden Groups they will not be listed (Forbidden trumps Allowed, as per Joomla's standard convention for permissions).