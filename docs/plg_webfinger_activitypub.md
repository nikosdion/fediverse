# WebFinger - ActivityPub

This plugin is used together with the ActivityPub component. It's responsible for creating and removing Fediverse posts when an article is created, published, unpublished, or deleted.

## Plugin options

**Displayed Content**. Select which part of an article's content will be used as the body text of the fediverse post.

* Intro text. Only the article's intro text (before the Read More separator) will be used.
* Full text. Only the article's full text (after the Read More separator) will be used.
* Both intro and full text. Both the intro and full text (before _and_ after the Read More separator) will be used.
* Meta description. The article's Meta Description will be used instead of its content.

**ActivityPub Object Type**. The ActivityPub specification allows for semantically relevant post types. The semantically correct type for Joomla articles is "Article" _but_ it's not properly supported by all Fediverse clients, most notably Mastodon (only the article title and a link are displayed, but not the article text). 

The other option is "Note" which is normally used for microblogging applications such as what you get with Twitter, Mastodon, etc. While this is not semantically correct for longer form content it is the only type fully supported by some popular Fediverse clients, such as Mastodon.

If you plan on federating your site with Mastodon you should use Note. If you are more interested in federating your site with other kinds of Fediverse clients which are geared towards long-form content (e.g. Write Freely) you should use Article instead — but keep in mind that Mastodon will only display the article title and a link.

**Article URL**. The URL pointing back to your article on your site can be included either as a link at the bottom of the post text, or in the post's URL field. The latter is the “correct” way to do it according to the ActivityPub specification. However, Mastodon displays is as plain text (not a link) after the post's text. This means we can give you the following options:

* None. No link back to the article on your site will be provided in the post.
* URL Field. The link will be included in the URL field — but Mastodon will not display it as a link.
* Link. An HTML anchor (`<a>`) tag with the text Read More pointing to your article's URL on your site will be included at the end of the post's text.
* Both. This works as having both URL Field and Link selected at the same time.

**Include Images As Attachments**. Should the Intro Text Images and Full Text Images (depending on what the Displayed Content option is) be included as attachments to the post? If enabled, these images will appear below the post as standalone images the user can view full size.

Please note that this only works with the images set up in Joomla's Images tab. It does not work with images added to your content inline. Inline images are never included as attachments and most fediverse clients (including Mastodon) do not display them at all.

**Process Queue Immediately**. When you create, edit, publish, or unpublish a content item the plugin enqueues notifications for federated servers (so that your followers can see your activity). When this option is disabled these notifications will only be processed by the scheduled task you've created using the “Task - ActivityPub” plugin. When this option is enabled the processing will start immediately — meaning that edit operations on articles will appear to be slower by up to 10 seconds.