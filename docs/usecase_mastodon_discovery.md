# Make your Mastodon presence easier to discover

Mastodon does not have a centralised server model like traditional social media such as Twitter, Facebook, LinkedIn etc. It's possible to create an account on one instance and move to a different instance. While it's possible to have your old account forward to the new account this may be confusing if you have printed your Mastodon handle on a stack of business cards. It's also quite arbitrary, making it hard for people to remember how to follow you without having to look you up.

What would be much easier is following someone based on their username on their site. For example, if Alice's site is `example.com` and their username is `alice` it would make more sense if you could just put `alice@example.com` in your Mastodon instance and _magically_ follow the correct Alice.

Well, with Fediverse Tools for Joomla, you can!

This use case requires Search Engine Friendly URLs to be enabled on your site. You can find the instructions to enable that in [the Requirements section of the WebFinger plugin's documentation page](plg_system_webfinger.md#requirements).

Once you've confirmed Search Engine Friendly URLs are set up, here's what you need to do next: 

1. Enable the System - WebFinger plugin and set List Users in WebFinger to Consent-based.
1. Enable the WebFinger - Link to Mastodon plugin.
1. Go to your site's administrator, User Menu (top right), Edit account.
1. Click on the WebFinger tab.
1. Set the following:
    * Display in WebFinger: Yes
    * Search by Email: Yes (if you want to be found by email)
    * Show Full Name: Yes
    * Mastodon Handle: enter your Matodon Handle. Mine is `nikosdion@fosstodon.org`, for example.
1. Save & Close.

Now someone can look you up on Mastodon with your username and your site's domain name.

## Under the hood

Let's say Alice has taken the steps above to make their site, `example.com`, make their Mastodon handle available when someone looks them up by their username (`alice`) on their site.

Someone looks them up on their own Mastodon instance with Alice's username and site domain name, i.e. `alice@example.com`.

The user's Mastodon instance contacts `example.com` at the WebFinger URL (`/.well-known/webfinger`) asking the site if it knows a user account that goes by the handle `alice@example.com`.

The System - WebFinger plugin on Alice's site handles the request and says ‘Yep, I know that user account. They gave me these _aliases_.’ One of these aliases is Alice's real Mastodon handle, let's say `foobar@example.net`.

The Mastodon server then repeats this process with the real Mastodon instance, `example.net`. Provided that the Mastodon server there knows that user it will reply with the information (ActivityPub inbox and outbox, among other things) the user's Mastodon instance needs to display Alice's profile page and the user can now follow Alice. All without knowing _in advance_ that her real Mastodon handle is `foobar@example.net`.

Neat, huh?

## Ideas

The whole point of this mode of operation is making it easier for people to connect to one another, beyond what they can do on your site.

If you have a site where different authors publish articles, or is its own mini social network, you can use the two plugins described above to allow these authors / users to make their Mastodon presence easy to find.

Same goes if you have a forum, e.g. one powered by Kunena. If I like what the user `alice` writes and want to follow them Mastodon I can just ask my Mastodon instance for `alice@example.com`, where `alice` is the username on the forum site `example.com`.