# The ActivityPub component

This component is used together with the “System - WebFinger”, “WebFinger - ActivityPub”, “Content - ActivityPub integration for Joomla articles”, “Task - ActivityPub”, and “Web Services - ActivityPub” plugins to publish your Joomla articles into the Fediverse.

In short, this plugin and its plugins convert your Joomla site into a real Fediverse server, with accounts which can be subscribed to by any ActivityPub client such as Mastodon, PeerTube, Pixelfed, Pleroma, Tumblr, etc.

## Quick start

Step-by-step guides for beginners can be found in the [Getting Started](index.md#Getting_Started) section of the main documentation.

## Options

**Allowed Actors**.

This option tells the component which users will be made available to the Fediverse through the ActivityPub API. Note that ‘actor’ is the ActivityPub terminology for a user which can publish and consume content on the Fediverse.

By default, it's set to “Only Configured Actors”. This setting means that only the Actors you have explicitly configured in the component's Actors page will be available.

Set to “Configured Actors and other users” to allow any user belonging to one of the “Allowed Groups” below to automatically create an Actor —if one does not exist— the first time it's accessed through the ActivityPub API.

**Allowed User Groups**

Visible only when the Allowed Actors option is set to “Configured Actors and other users”.

If a user belongs to one of the following groups their Activities (articles) will be made available through the ActivityPub API. If an Actor for this user does not already exist, a new one will be created the first time their ActivityPub is accessed.

## Actors

This is the main page of the ActivityPub component. It determines which ActivityPub usernames (‘actors‘) are made known to the Fediverse.

The columns displayed are:
 
* **User**. The username of the actor, corresponding to the part of their Fediverse username before the at sign. A grayed out name with an icon showing a user with a cog (screen readers announce it as “Virtual User”) represents an actor which does not correspond to a Joomla user account and typically acts as a content aggregator. A solid name with an icon showing a user (screen readers do not announce it at all) represents an actor which corresponds to a Joomla user account with the same username.
* **Displayed Name**. The full name of the account displayed to Fediverse clients.
* **Type**. The type of the actor, as conveyed to the Fediverse. It is one of Person (actual human), Organisation (legal entity), or Service.
* **ID**. The internal numeric ID of the actor.

### Editing or creating actors

#### The Main Options tab

If the actor you are creating / editing corresponds to a Joomla user account, select the account by clicking the person button (screen readers: announced as Select User) next to the **User** field. 

Note: Selecting a user hides the Displayed name and Username fields. ActivityPub will automatically use the Full Name and Username of the corresponding Joomla user account.

The **Displayed name** field sets the human-readable, full display name of the user account. Choose something descriptive for your actor.

The **Username** field sets the username part of the actor's handle. For example, if you enter `alice` here and your site's domain name is `www.example.com` the Fediverse username will be `alice@example.com`.

The **Actor Type** describes what kind of account this actor is for:
- _Person_. A real human. This is what you want for your _personal_ blog.
- _Organisation_. A legal entity such as a sole proprietorship, company, corporation, educational institution, governmental organisation etc. This is what you want for your _company's_ blog, job listings etc.
- _Service_. A site-specific service account e.g. forum, status updates, the latest posts, etc. This is a much less common type than the other two.

Note: the [ActivityStreams specification](https://www.w3.org/TR/activitystreams-core/#actors) also defines Application and Group. These are not currently implemented. The former represents a software application which seems to be something I had hard time trying to translate to a use case with Joomla. The latter is a collection of actors which I currently have not implemented.

#### The Integration tab

The **Integration** tab tells the component how to present the actor's profile to fediverse clients and what to publish as the actor's status updates.

##### Profile options

The first part of this page is about the user profile presented to other fediverse clients.  If the actor is linked to a Joomla user (the “User” option is not empty) you can override these options in the user's profile settings.

**Summary Text** is the long, human-readable description (“bio”) sent to other fediverse clients.

**Profile Picture Source** tells the component where to get a profile photo for this actor.

* _None_. No profile photo will be used.
* _Gravatar_. Use the [Gravatar](https://en.gravatar.com) service. Only valid when you have selected a real user as it needs an email address. 
* _URL_. Enter an image URL on _any_ site or image hosting service.
* _Image file_. Select an image file using Joomla's Media Manager image picker.

**Profile Picture URL**. Only applies when the Profile Picture Source is set to “URL”. Enter the URL to an image file to use as the actor's profile photo. Ideally a JPG, or PNG file between 512px and 1024px square. 

**Profile Picture**. Only applies when the Profile Picture Source is set to “Image File”. Pick an image file to use as the actor's profile photo. Ideally a JPG, or PNG file between 512px and 1024px square.

##### Article (content) options

The **Articles** option controls whether core Joomla articles will be published as activities (“posts”) by this actor.

The **Categories**, **Languages**, and **Access** options control which articles will be conveyed as posts. Only articles published in one of the selected Categories, in one of the selected Languages, and having the one of the selected Access levels will be published.

Selected Categories do _not_ include subcategories. Each subcategory must be selected explicitly.

Articles published with their language set to “All” (default) will not be filtered by the Languages options; they will always be included. If you do not select any Languages then only articles with their language set to “All” will be published as posts.

Remember that all fediverse posts created by the ActivityPub component are public, i.e. visible by anyone. You should therefore only include public access levels in the Access option. If you select any access levels which are not public on your site, the articles having one of these access levels will still create public fediverse posts which may not be what you want.

## Configuring the posts generation

You can configure which parts of the articles are included in a post by editing the [Content - ActivityPub integration for Joomla articles](plg_webfinger_activitypub.md) plugin.

## How publishing / unpublishing / deletion works

While the ActivityPub specification allows servers to update posts, both content and visibility, this does _not_ work on many popular fediverse clients including Mastodon.

As a result, we chose to instead mark an article's post _deleted_ when the article is unpublished and create a new post, with a new published timestamp, when the post is published again. This works great with Mastodon.

When an article is deleted from your site its corresponding posts will also be marked as deleted.

Please note that due to the way federation works, third party servers may keep a copy of the deleted posts in their cache. This means that unpublishing an article is not guaranteed to make its content vanish from the Fediverse.