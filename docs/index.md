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