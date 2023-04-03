# WebFinger - Link to Mastodon

This plugin can be used together with the “System - WebFinger” plugin to let users on your site link to their Mastodon profile using WebFinger.

## Requirements

You must have the “System - WebFinger” plugin enabled and its “List Users in WebFinger” option must be set to anything other than None.

## Getting started

Go to your site's backend, System, Manage, Plugins.

Publish the “System - WebFinger” plugin.

Publish the “WebFinger - Link to Mastodon” plugin.

## Plugin options

The plugin has no options.

## Using the plugin

Edit your user profile — or another user's profile.

Go to the WebFinger tab and scroll all the way down.

There is a “Mastodon Handle” field. Enter your Mastodon handle without the at-sign in front of it, e.g. `foo@masto.test`

Click on Save & Close.

### What does this do

Let's say your site is `https://www.example.com`, your Joomla username is `alice`, your Mastodon handle is `foo@masto.test`, and you've configured the plugin as described above.

A Mastodon —or other Fediverse client— user can now search for `alice@example.com`. They will automatically discover your `foo@masto.test` Mastodon account and be able to subscribe to it.

This fulfils three purposes:

* It makes it **easier** for people to discover your Mastodon profile knowing only your site's domain and your usual username.
* If your Mastodon handle changes because your Mastodon instance goes off-line you can still maintain **portability** of your profile by editing your user profile. Note that yes, Mastodon itself does offer portability (by having the old server point to the new handle) but it _only_ works if the old server instance remains online. Using this plugin this won't happen to you. This means you can print your forwarding handle (`alice@example.com` in our example) on marketing material, QR codes, and whatnot without worrying about the instance you're on going off-line.
* It provides a modicum of **authenticity (verification)** by positively linking a user account demonstrably under your control with your Mastodon instance. You should also use your Mastodon instance's verification with a backlink on a publicly available page to provide a strong, two-way verification of your identity i.e. your site proves it's you on Mastodon, and your Mastodon identity proves your site is under the control of this Mastodon handle's owner.

It's a lot to wrap your head around. Just the fact that it's easier for people to find you, and you can print this handle on marketing material should be reason enough for you to use this plugin.

### When you should NOT use this plugin

If you are using the ActivityPub component to provide content to the Fediverse you **MUST NOT** use this plugin.

Think of it like this. This plugin provides a “forwarding address” for you on the Fediverse. If this was a meatspace mail service you wouldn't want to have your packages redirected to a different address if you still live in this house, right?