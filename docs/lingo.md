# Crash course in the Fediverse lingo

Mastodon is a microblogging platform, essentially a social network much like the infamous site with the bird logo. Unlike that site, Mastodon is neither closed source, nor centralised. It is Open Source, anyone can run a copy of it (called an “instance”), and instances can talk to each other — meaning that you can connect to your friends even if they have an account on a different server.

Each instance can have its own rules such as requiring someone to be a practitioner of a specific profession, or use a specific language for their posts. Some instances have a specific theme, e.g. art, open source software, etc. It's like the BBS of the 90s, only that instead of being isolated communities they can talk to each other and interconnect their members.

This ‘talking to each other’ part about Mastodon instances? It's called _federation_.

Third party software other than Mastodon can also federate with Mastodon instances and each other. That's why the term Mastodon is only used for the software, not the network of federated servers. The global sum total of federated servers is called the _Fediverse_, a portmanteau of “federation” and “universe”.

Federation takes place using [ActivityPub](https://www.w3.org/TR/activitypub/#client-to-server-interactions), a W3C standard which defines how servers talk to each other to exchange posts and other user-generated content.

ActivityPub users are called Actors. An _Actor_ can be a physical user (Alice, Bob, Charlie, …) or a virtual / abstract concept (news, blog, updates, …) acting as an aggregator of content.

ActivityPub Actors' posts are called _Activities_. An Activity is usually some kind of content announcement (an article, a comment, …), some action on the content (published, unpublished, edited, …), or anything else.

Mastodon is an ActivityPub implementation. Not all ActivityPub implementations are Mastodon, through. There are ActivityPub implementations for WordPress and Joomla (this here extension!). There is a YouTube clone called PeerTube based on ActivityPub. There is an Instagram clone called Pixelfed also based on ActivityPub. Tumblr will be adding ActivityPub support soon. All these disparate ActivityPub implementations can talk together using federation. That's the beauty of it. Just like HTML and CSS allowed the proliferation and interconnection of radically different websites, ActivityPub allows the same for social / activity feeds.

Servers find out about each other's users using _WebFinger_, which is an Internet protocol ([RFC 7033](https://www.rfc-editor.org/rfc/rfc7033)) for retrieving information about the users of a site. WebFinger can be used on your Joomla site to advertise which Mastodon account a user of your site prefers to be contacted on, or tell other servers that a user on your site itself can be “followed” to provide posts the other servers can display to their own users.

The microblogging posts users make on a Mastodon instance are called _toots_. All toots are ActivityPub Activities. However, not all ActivityPub Activities are toots — only Activities concerning text and/or multimedia content generated by Mastodon are called tootls.

A toot contains plain text, by default up to 500 characters of it (depends on the Mastodon instance), and optionally media (images and videos). The entire post, or just some of its media files, can have a _content warning_ to indicate that they contain sensitive, triggering, inappropriate, NSFW (not suitable for word), or otherwise upsetting material — this is a lot like a “viewer discretion recommended” kind of warning. It is good practice that content warnings are taken into consideration and the content behind them be hidden by default, until the user chooses to display it.