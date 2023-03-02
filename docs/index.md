# Fediverse Tools for Joomla

**Joomla! extensions to integrate Mastodon with your site**

## What's this all about?

This is a package of Joomla!™ extensions which allow you to integrate Mastodon and ActivityPub in meaningful ways with your Joomla! site.

There are three levels of integration which can be used concurrently or independently of each other:

* **Consume**. You can display Mastodon [posts (toots)](plg_content_fediverse.md) and [activity streams](mod_fediversefeed.md) on your site.
* **Announce**. You can announce your Mastodon presence on your own site so that users who have your username and domain name can follow you on Mastodon, without having to explicitly tell them your Mastodon handle in advance.
* **Publish**. You can publish your own site's content into the Fediverse. Any ActivityPub user (Mastodon, PeerTube, Pixelfed, Tumblr, …) can “follow” an account on your site and see your site's posts in their feed.

Unlike other microblogging / social media integration extensions there is no privacy-invading JavaScript, no need for cookie banners, no centralised control of the published content. You are in full control of your content.

## Getting started

Getting started with software tends to be hard. Most of the time, developers will tell you how every bit and piece works instead of telling _how to get stuff done_. That's why I decided to start this documentation from the opposite direction: give you step-by-step guides on how to do something practical, along with some pointers to let you dive deeper _if you want to_.

**Basic usage: consuming Mastodon posts (toots)**

* [Embed Mastodon toots in your site's content](uscase_embed_toots.md).
* [Display a Mastodon stream on your site](usecase_stream.md).

**Intermediate usage: announcing your Mastodon presence to the world**

* [Make your Mastodon presence easier to discover](usecase_mastodon_discovery.md).

**Advanced usage: publishing your content to Mastodon and the Fediverse**

* [Make your single-author blog available on the Fediverse](usecase_single_author_blog.md).
* [Make your multi-author news section available on the Fediverse](usecase_multiauthor_blog.md).

**ActivityPub? Fediverse? Are you talking in tongues, mate?!**

In case something sounds unfamiliar and unintuitive, please take the [crash course in the Fediverse lingo](lingo.md) a.k.a. the common terminology for all things Fediverse.

## Documentation by extension, a.k.a. Reference Manual

### Displaying toots

Fediverse Tools allows you to display individual toots or toot streams from any Mastodon user.

_Tip_: See [which features are supported](features_displaying.md)

* [**Fediverse - Mastodon Feed** (Module)](mod_fediversefeed.md). Displays the public Mastodon feed of a user. Supports media (images and videos), as well as content warnings on the entire toot or just each media item.
* [**Content - Embed Toot** (Plugin)](plg_content_fediverse.md). A plugin to embed toots in articles, Custom HTML modules, and third party extensions which support Joomla's standard content events.

### WebFinger

WebFinger is an Internet protocol ([RFC 7033](https://www.rfc-editor.org/rfc/rfc7033)) which allows remote servers to query information about users on your site. You can use it to either publish information about your Mastodon presence (so you can be followed as `user@example.com` where example.com is _your Joomla site's domain name_, regardless of what your Mastodon handle is), or to let other ActivityPub / Mastodon users “follow” your site's content when used together with the ActivityPub component described in the next section.

* [**System - WebFinger** (Plugin)](plg_system_webfinger.md). Implements the WebFinger protocol (RFC 7033) in Joomla.
* [**WebFinger - Link to Mastodon** (Plugin)](plg_webfinger_mastodon.md). Add a link to your Mastodon identity in the WebFinger profile.

### Federating your content

Using the component and plugins in this section you can allow ActivityPub / Mastodon users to “follow” your site's content — either the content published by a specific user, or aggregated content across categories and users which you choose.

* [**ActivityPub** (Component)](com_activitypub.md). The component which allows you to define ActivityPub Actors (users) and which handles the ActivityPub API — this enables federation of your site's content with third party ActivityPub servers such as Mastodon instances. Requires all other plugins in this section, as well as “System - WebFinger” to be published with their Access set to Public for content federation to work. Moreover, you will need a scheduled task of the “ActivityPub - Notify” type running every minute through Joomla's Scheduled Tasks.
* [**WebFinger - ActivityPub** (Plugin)](plg_webfinger_activitypub.md). Adds ActivityPub information in the WebFinger protocol (RFC 7033) responses. This lets other ActivityPub users (such as Mastodon users) to “follow” your content. Requires the System - WebFinger plugin to be enabled.
* [**Content - ActivityPub integration for Joomla articles** (Plugin)](plg_content_contentactivitypub.md). Provides the link between Joomla's Articles (`com_content`) and the ActivityPub component. This is what allows your _articles_ to be federated with other ActivityPub instances.
* [**Task - ActivityPub** (Plugin)](plg_task_activitypub.md). Provides the “ActivityPub - Notify” task type. You need a task of that type running every minute; it ‘pushes’ the content you publish into other federated ActivityPub (e.g. Mastodon) instances.
* [**Web Services - ActivityPub** (Plugin)](plg_webservices_activitypub.md). Enables the ActivityPub component's Joomla API part. Having this plugin published is **mandatory** for the ActivityPub component to work.

## Further information

Please take a look at the [accessibility statement](accessibility.md) to understand what Fediverse Tools for Joomla does to make sure that the content it produces is accessible to users with disabilities.
