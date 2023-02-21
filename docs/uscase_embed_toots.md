# Embed Mastodon toots in your site's content

Mastodon is a wonderful social network with a lot of meaningful and interesting conversation. When writing an article or blog post you may want to reference the source toot (Mastodon post) by embedding it in your content. However, Mastodon not having a centralised server it also lacks a way to embed toots in the way you can embed, for example, Twitter tweets or Facebook posts.

You can do that very easily with Fediverse Tools for Joomla.

First, make sure that the plugin “Content - Embed Toot” is enabled.

Then go to the Mastodon profile of the user on the web and click the date on the upper right hand side of the toot you want to embed. This opens a new page, e.g. `https://fosstodon.org/@nikosdion/109516579924736746`.

Go to your Joomla article and type `{toot YOUR_URL}` where YOUR_URL is the URL you copied above. For this example, it's `{toot https://fosstodon.org/@nikosdion/109516579924736746}`.

Save the article and view it in the frontend. It shows up like this:

<img height="312" src="pics/embedded_toot.png" width="657"/>

## Caching and Performance

The toot content is cached —regardless of Joomla's caching settings— to ensure speedy loading of pages with embedded toots. You can fine-tune caching in the “Content - Embed Toot” plugin's options, [per the documentation](plg_content_fediverse.md)

## Presentation

The toots are read as JSON documents. They are formatter locally, on your own server, using the CSS provided with the plugin. There's even an automatic light and dark mode provision.

If you would like to override the way the toots are presented you should do a [standard Joomla media override](https://docs.joomla.org/Understanding_Output_Overrides#Media_Files_Override), i.e. copy the file `media/plg_content_fediverse/css/embedded.css` to `templates/YOUR_TEMPLATE/media/css/plg_content_fediverse/embedded.css` (or, if your template uses the new template structure available since Joomla 4.1, `media/templates/site/YOUR_TEMPLATE/css/plg_content_fediverse/embedded.css`). Make the changes to the latter file.

Please note that we provide the [SCSS / Sass](https://sass-lang.com/) source of the CSS in `media/plg_content_fediverse/css/embedded.scss`. We advise you to make changes to a copy of that SCSS file, compile it to CSS, and use that CSS in your template's media override directory.