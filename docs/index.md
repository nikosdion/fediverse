# Fediverse Tools for Joomla

**Joomla! extensions to integrate Mastodon with your site**

## What's this all about?

This is a package of Joomla!™ extensions which allow you to integrate Mastodon in meaningful ways with your Joomla! site.

It's a lot like the other microblogging integration extensions, with a big difference: there is no privacy-invading JavaScript, no need for cookie banners. 

## Crash course in the Fediverse lingo

Mastodon is a microblogging platform, essentially a social network much like the infamous site with the bird logo. Unlike that site, Mastodon is neither closed source, nor centralised. It is Open Source, anyone can run a copy of it (called an “instance”), and instances can talk to each other — meaning that you can connect to your friends even if they have an account on a different server.

Each instance can have its own rules such as requiring someone to be a practitioner of a specific profession, or use a specific language for their posts. Some instances have a specific theme, e.g. art, open source software, etc. It's like the BBS of the 90s, only that instead of being isolated communities they can talk to each other and interconnect their members.

This ‘talking to each other’ part about Mastodon instances? It's called _federation_.

Third party software other than Mastodon can also federate with Mastodon instances and each other. That's why the term Mastodon is only used for the software, not the network of federated servers. The global sum total of federated servers is called the _Fediverse_, a portmanteau of “federation” and “universe”.

The microblogging posts users make on a Mastodon instance are called _toots_.

A toot contains plain text, up to 500 characters of it, and optionally media (images and videos). The entire post, or just some of its media files, can have a _content warning_ to indicate that they contain sensitive, triggering, inappropriate, NSFW (not suitable for word), or otherwise upsetting material — this is a lot like a “viewer discretion recommended” kind of warning. It is good practice that content warnings are taken into consideration and the content behind them be hidden by default, until the user chooses to display it.

## Extensions included

* [**Fediverse - Mastodon Feed** (Module)](mod_fediversefeed.md). Displays the public Mastodon feed of a user. Supports media (images and videos), as well as content warnings on the entire toot or just each media item.
* [**Content - Embed Toot (Plugin)**](plg_content_fediverse.md). A plugin to embed toots in articles, Custom HTML modules, and third party extensions which support Joomla's standard content events.

## Supported features

The following features are currently supported when displaying toots:

* **User information: avatar, username, display name** is shown for every toot (embed) or once on the module header (toots stream). This is linked back to the user's profile page on their Mastodon instance.
* **Toot language and RTL languages**. The content of each toot is marked with the language communicated by the server. If the language is using an RTL (Right-to-Left) script —as is the case for arabic or hebrew, for example— the content will be correctly displayed right-to-left.
* **Custom Emoji (in usernames and toots)**. Custom emoji images are loaded from the Mastodon server directly.
* **Content warnings on the entire toot**. The toot is rendered as an HTML DETAILS element with the Content Warning being the only visible content until the user clicks on it.
* **Sensitive media**. Sensitive media appear blurred until the user clicks on them.
* **Media previews**. Supported media files (see below) are shown with each toot using a tiled layout with the first media item preview appearing larger than the other ones. The (downscaled) preview image is displayed for each media file in the preview to save bandwidth and increase the page rendering performance. Clicking on it will load the full image.
* **Images**. Images are fully supported; Mastodon only uses web-safe image formats. 
* **Image descriptions**. Image descriptions are rendered as ALT tags for screen readers and also as a FIGCAPTION for sighted users (the latter only after clicking on the image).
* **Videos (both `video` and `gifv` types)** using the browser's native video player. The media preview shows the static frame (“poster frame”) sent by the Mastodon server. The video player only loads the video file when playing the video, to save bandwidth and make the page loading faster. 
* **Polls**. Poll results and the poll closing date and time appear in toots. You can not vote on the polls through the toot stream and embedded toots; you have to visit the toot on the Mastodon instance to do that.
* **Information about the toot visibility** (only in embeds). An icon with the visibility, public or unlisted, is displayed and presented as human-readable text with a `title` attribute and a visibly hidden (but screen reader announced) text. 
* **Information about the application used to post the toot** (only in embeds). The name of the application used to post the toot will be displayed and, if a link is provided, linked to as well.
* **Reply, reblog, and favourite counts** (only in embeds).
* **Dark Mode**. If your browser is set to use dark mode the software will use alternate rules to render the toots in a bright text on dark background color theme.

Features not supported (yet):

* Static custom emoji. Animated emoji are always loaded as moving images, regardless of the user's preferences on reduced motion. I understand this is an accessibility concern, and I'm trying to find a way to render the static custom emoji image provided by the server when the user prefers reduced motion.
* High contrast mode. Some users with certain types of visual impairment need a high contrast mode which is absolutely _not_ the same as Dark Mode (nor should the two ever be conflated). This is an accessibility issue I am aware of. I have to figure out how to set up high contrast on my own environment in a way that doesn't clash with my cognitive disability (ADHD) so I can work on it.
* Audio embeds. Audio embeds appear as being of an unknown type. An audio player will be used in a future version.

Features which will NOT be supported:

* Displaying private or direct message toots. For obvious reasons accessing these toots requires authenticating with the Mastodon instance using OAuth2. While it's possible to do that with a two-step process (with the user copying a token manually) I am not convinced there is a compelling, real-world use case where a (public) site would need to display this kind of privileged, unlisted information — or what the privacy implications would be for all parties involved.
* Directly launching a reply, reblog, or favourite from an embedded toot. Obviously, your site is NOT a Mastodon instance.
* Language written vertically. I speak none of them, and I am not sure CSS Flexbox can correctly support them. As far as I can tell, only Mongolian is always written top-to-bottom, and it's possible but optional for Chinese and Japanese to be written like that as well (though virtually all content in the latter languages uses left-to-right content flow). Just to be on the safe side, I'm using CSS classes referencing block, inline, start, and end instead of top, bottom, left, and right.