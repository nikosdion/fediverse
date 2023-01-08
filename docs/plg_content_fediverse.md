# Content - Embed Toot

The plugin allows you to embed toots in your articles, Custom HTML modules, and every Joomla first or third party extension which supports Joomla's standard content events.

## Getting started

Go to your site's backend, System, Manage, Plugins.

Publish the “Content - Embed Toot” plugin.

## Embed a toot in your content

Just type the following in your content:

```html
{toot https://www.example.com/web/@user/109313843174797180}
```

where `https://www.example.com/web/@user/109313843174797180` is the URL to a Mastodon status post.

If the toot is in reply to another toot, the parent toot (the one being replied to) is displayed above the toot you referenced with the URL. If you do not want that to happen please append ` noreply` after the toot's URL, e.g.
```html
{toot https://www.example.com/web/@user/109313843174797180 noreply}
```

The `{toot}` plugin code works in any core Joomla and third party extension content area, as long as it goes through Joomla's content preparation. At a minimum, it works in Joomla articles, modules of the “Custom” type (as long as you set Options, Prepare Content to Yes),.

## Plugin options

**Cache Toot**. The plugin needs to load the toot information from the Mastodon instance to display it. Reloading it from the Mastodon server on every page load makes your site slower, puts unnecessary strain on the Mastodon instance, and could even get you kicked from the Mastodon instance. Enabling this option will cache the toot information for a period of time defined in the **Cache Time (seconds)**. This will lighten the load on your server and the Mastodon instance at the expense of not always showing the most recent information about replies, boosts, and the times the toot has been favourited. Please note that this option is _independent_ of the Joomla cache settings in Global Configuration. Joomla's cache settings control how Joomla caches the generated HTML of the entire page. Our setting only controls how we cache the toot information which is then used to render it by the plugin as HTML.

**Cache Time (seconds)**. How long to cache the toot information. We recommend setting this relatively high (e.g. 3600 to 86400 seconds, one hour to one day) for most use cases. If you embed popular toots and have a relaistic need to display up-to-date information about replies, boosts, and the times the toot has been favourited set this option to something between 120 and 600 seconds, two minutes to five minutes.

**Request Timeout (seconds)**. Whenever the plugin needs to fetch the information for a toot it makes an HTTP(S) request to the Mastodon server. If the server is unreachable, under maintenance, etc the request can take up to 30 seconds to time out, making your visitors think that your site has hung. This option limits the time Joomla will wait for the Mastodon server to return the information about the toot. If the toot fails to fetch within that time, Joomla will give up and report the toot as unavailable and link to it instead.

**Custom TLS Certificate Path**. If you are using this module to embed toots from a test, local, or internal server which is using a self-signed TLS certificate for HTTPS enter the full path of the TLS certificate file (in PEM format) to allow Joomla to load the toot. The file can contain more than one certificates, in PEM format. This is NOT necessary for live Mastodon servers. This is only meant to be used for local tests and special use cases (like intranet, company-wide installations of Mastodon behind a firewall). If you do not understand what this is for, you don't need it.

## Good to know

The URL to a Mastodon status post can be plain text or a link (`<a>` tag). Don't worry, the content plugin will figure it out.

The Mastodon status post must be marked Public or Limited. It cannot be Private or a Direct Message; the latter will display an error that the toot cannot be loaded.

The user account of the Mastodon user you are embedding a toot from must be public. If it's a private account all of their toots are marked as Private and cannot be loaded by the content plugin.

Content warnings for the entire toot or just for its media attachments are fully supported and properly used when embedding a toot, much like the [Mastodon Feed module](mod_fediversefeed.md).

If you want to change the styling of the toot you can do a CSS override for the `media/plg_content_fediverse/css/embedded.css` file. Copy the file to `templates/YOURTEMPLATE/css/plg_content_fediverse/embedded.css` where YOURTEMPLATE is the folder name of the template you are using on your site. Modify that new file; it will be loaded _instead of_ the original file we supply with the plugin. For your convenience, we have included the SCSS source file. You can compile the SCSS file to CSS using [a Sass compiler](https://sass-lang.com/documentation/syntax).