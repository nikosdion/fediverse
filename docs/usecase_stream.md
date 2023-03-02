# Display a Mastodon stream on your site

Quite often you want to display a Mastodon account's stream of toots on your site. For example, a business might want to display their business account's toots on their news page, or a blogger might want to display their toots and reblogs on their site's home page. 

Mastodon, unlike traditional social media providers like Facebook and Twitter, does not have a centralised server. As a result, there is no embed code for a toot stream. This can be solved with Fediverse Tools for Joomla.

To add a toot stream module go to your site's backend and click on Content, Site Modules, New.

Select the **Fediverse - Mastodon Feed** module type.

In the new module enter the Mastodon username, e.g. `nikosdion@fosstodon.org` to display my toots.

Select the position and give your module a title.

Click on Save & Close and you'll see the Mastodon user's toots like so:

[//]: # (TODO: IMAGE)

You can have more than one Mastodon Feed module on your site.

To understand what the options do, please check [the module's documentation](mod_fediversefeed.md).

## Caching

By default, the Mastodon user account information and the feed itself are cached for one hour, regardless of Joomla's caching options. This speeds up the display of your site's pages without overloading the Mastodon server.

You can change the caching configuration in the Advanced tab when editing a module. These options are set _per module_. 

## Presentation

The toots are read as JSON documents. They are formatted locally, on your own server, using the CSS provided with the plugin. There's even an automatic light and dark mode provision.

If you would like to override the way the toots are presented you should do a [standard Joomla media override](https://docs.joomla.org/Understanding_Output_Overrides#Media_Files_Override), i.e. copy the file `media/mod_fediversefeed/css/custom.css` to `templates/YOUR_TEMPLATE/media/css/mod_fediversefeed/custom.css` (or, if your template uses the new template structure available since Joomla 4.1, `media/templates/site/YOUR_TEMPLATE/css/mod_fediversefeed/custom.css`). Make the changes to the latter file.

Please note that we provide the [SCSS / Sass](https://sass-lang.com/) source of the CSS in `media/mod_fediversefeed/css/custom.scss`. We advise you to make changes to a copy of that SCSS file, compile it to CSS, and use that CSS in your template's media override directory.

## Privacy

Showing toot streams, unlike embedding Facebook post and Twitter tweet streams, does NOT use any third party JavaScript and does NOT expose your users' private information to the Mastodon instance. This means that you do not need to hide the embed behind a convoluted layer of JavaScript to obtain the user's consent to display a toot.

Kindly note that when a toot has an image or a video this is loaded directly from the Mastodon server. This only lets the Mastodon server see the IP address of your visitor. In most jurisdictions this is not a problem as an IP address is not personally identifiable information. In some jurisdictions, e.g. Germany, the IP address _might_ be considered personally identifiable information, e.g. if your site's visitor is currently logged into the same Mastodon server as the one you are displaying the toot from. If you are unsure about whether embedding toots may be illegal in your jurisdictions please ask a lawyer and keep in mind that **you** are solely responsible for legal compliance of your use of this software as per articles 15, 16, and 17 of [the software's license](../LICENSE). 
