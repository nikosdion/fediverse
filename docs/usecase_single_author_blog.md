# Make your single-author blog available on the Fediverse

Let's say you have a personal blog site, like the one I have on [my own site](https://www.dionysopoulos.me), where only one person writes blog posts, the blog posts are attributed to this person, and you want to share them on Mastodon and the Fediverse.

The traditional model is to create a Mastodon account on someone else's Mastodon instance. Then, whenever you publish a blog post, you get to make a new toot linking to your post. This has two drawbacks. One, you obviously need an account on someone else's Mastodon server. Two, Mastodon users only see a link to your content (and possibly a preview, if you use a template which correctly outputs microdata), not the content itself.

What if I told you that you can make your own site a federated server discoverable on Mastodon, with your published content automatically appearing as posts? That is to say, someone on Mastodon can ‘follow’ your personal blog by following a Mastodon user like `alice@example.com` where example.com is your site and `alice` is your username.

## Initial setup

### Search Engine Friendly URLs

This use case requires Search Engine Friendly URLs to be enabled on your site. You can find the instructions to enable that in [the Requirements section of the WebFinger plugin's documentation page](plg_system_webfinger.md#requirements).

### Enabling plugins

First, we need to set up a few plugins. These handle the grunt work that happens behind the scenes to make it possible for our Joomla site to appear as a federated ActivityPub server to Mastodon servers.

* Go to your site's backend, System, Manage, Plugins.
* Publish (enable) the following plugins:
  * System - WebFinger
  * WebFinger - ActivityPub
  * Content - ActivityPub integration for Joomla articles
  * Task - ActivityPub
  * Web Services - ActivityPub
* Unpublish (disable) the “WebFinger - Link” to Mastodon plugin.

### Configure plugins

Since this is a single-user site, we are going to have people follow you on Mastodon as `your_username@your_site_domain.tld` e.g. `alice@example.com`.

Edit the **System - WebFinger** plugin and set the following options:
* List Users in WebFinger: Consent-based
* Forced Allowed Groups: _(empty)_
* Forced Forbidden Groups: _(empty)_

As to what gets published, we'll show the intro text (and intro image!) of your blog posts, along with a Read More link which leads to the full text on your site. 

Edit the **Content - ActivityPub integration for Joomla articles** plugin and set the following options:
* Displayed Content: Intro text
* ActivityPub Object Type: Note (using Article is the better option semantically, but it's not well-supported by Mastodon at the time of this writing)
* Article URL: Link
* Include Images As Attachments: Yes
* Process Queue Immediately: Yes

## Set up your user preferences

Go to the backend of your site and click on User Menu, Edit account at the top of the page.

Click on the WebFinger tab and set the following settings:

* Display in WebFinger: Yes
* Search by Email: No
* Show Email: No
* Show Full Name: Yes
* Show Gravatar: Yes
* Custom aliases: _(empty)_
* ActivityPub Discoverable: Yes
* Summary Text: _(empty)_

_Tip_: You can edit your profile's photo at https://gravatar.com using the email address of your Joomla! user account.

## Set up your federation account

We need to tell the ActivityPub component, which is part of the Fediverse Tools for Joomla, which user's articles are going to be made available as Mastodon articles. We do that by creating an ‘Actor’, which is a fancy way of saying ‘user account’ in the ActivityPub lingo.

* Go to your site's backend and click on Components, ActivityPub, Actors. 
* On that page click New on the toolbar.
* Find the User field. Click button the button next to it and select your user.
* Set the Actor Type to Person.
* Click on the calendar icon of the Created Date field and then click on Today.
* Click on the Integration tab and enter the following:
  * Summary text: enter some text which says that this is your blog feed, but replies to this account are not read.
  * Profile Picture Source: Gravatar
  * Articles: Yes
  * Categories: select your blog categories
  * Languages: _(empty)_
  * Access: Public, Guest _(that's the default)_
* Click on Save & Close

There's another thing to do — if you don't the scheduled task we will be setting below will fail if it's triggered from a CLI CRON job:

* Go to your site's backend, System, Maintenance, Clear Cache
* Click on Clear Expired Cache

**IMPORTANT!** You must do that only _after_ having visited the Components, ActivityPub, Actors page at least once.

### Scheduled task

The next thing we need to do is set up an automation to push (federate) the content whenever an article is published. We're going to do that with Scheduled Tasks, one of the least known and most useful features of Joomla.

* Go to your site's backend, System, Manage, Scheduled Tasks and click on New
* Select the “ActivityPub - Notify” task type
* Use the following options:
  * Execution Rule: Interval, Minutes
  * Interval in Minutes: 1
  * Maximum execution time (CLI): 60 _(that's the default)_
  * Maximum execution time (web): 15 _(that's the default)_
* Give it a title, e.g. “Update fediverse followers”
* Click on Save & Close

If this is the first time you are setting up scheduled tasks on your site, also do this:

* Go to your site's backend, System, Manage, Scheduled Tasks and click on Options
* Click on the Lazy Scheduler tab
* Set the following:
  * Lazy Scheduler: Enabled
  * Request Interval (seconds): 30
* Click on Save & Close

_Note:_ Advanced users may instead choose to use Web Cron or a real CLI CRON job to trigger scheduled tasks every minute. If you don't know what that means you can ignore this note.

## Make sure this is plugged in

We need to verify that WebFinger returns the correct information about your user account. For this, you need to go to the [WebFinger lookup client](https://webfinger.net/lookup).

Towards the top right of the page there's a Lookup WebFinger box.

In there you need to enter your actor's username (i.e. your Joomla! username), followed by an at-sign, followed by your domain name (without www in front). For example, if your username is `alice` and your site is `https://www.example.com` you need to enter `alice@example.com`. Then, click on the magnifying glass icon next to the box.

You should get a response like the following within a few seconds:

```json
{
  "subject": "acct:alice@example.com",
  "links": [
    {
      "rel": "author",
      "titles": {
        "und": "Alice A. Appleseed"
      }
    },
    {
      "rel": "http://webfinger.net/rel/avatar",
      "href": "https://www.gravatar.com/avatar/aa1db827772e1d51d453b844394b7617?s=128"
    },
    {
      "rel": "self",
      "type": "application/activity+json",
      "href": "https://www.example.com/api/v1/activitypub/actor/alice"
    }
  ]
}
```

The important bits you should see are:
* `subject` must be the username you gave it (`alice@example.com` in our example).
* There must be an entry with `rel` set to `self` and its `href` set to `https://www.example.com/api/v1/activitypub/actor/alice` where `https://www.example.com` is your site's URL and `alice` is your username. 

Now visit the URL in that last `href` you see, e.g. `https://www.example.com/api/v1/activitypub/actor/alice` in our example. You should get a JSON document like the following:

```json
{
  "type": "Person",
  "streams": [],
  "@context": [
    "https:\/\/www.w3.org\/ns\/activitystreams",
    "https:\/\/w3id.org\/security\/v1",
    {
      "@language": "en-GB"
    }
  ],
  "id": "https:\/\/www.example.com\/api\/v1\/activitypub\/actor\/alice",
  "preferredUsername": "alice",
  "name": "Alice A. Appleseed",
  "inbox": "https:\/\/www.example.com\/api\/v1\/activitypub\/inbox\/alice",
  "outbox": "https:\/\/www.example.com\/api\/v1\/activitypub\/outbox\/alice",
  "endpoints": {
    "sharedInbox": "https:\/\/www.example.com\/api\/v1\/activitypub\/sharedInbox\/alice"
  },
  "publicKey": {
    "id": "https:\/\/www.example.com\/api\/v1\/activitypub\/actor\/alice#main-key",
    "owner": "https:\/\/www.example.com\/api\/v1\/activitypub\/actor\/alice",
    "publicKeyPem": "-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAtKr9\/Padz0TgDM8Sef74\niKNnkRw44J5650GvLgtlVoJhyWvOQ\/4GXa0NCeKtHhMad6pFfdiDlRyVvyCi1Z8G\nd1WqHWVefh5PftMpQCKVbDZe44LlIMIqFqPllDUDH+CEHSNr2Uk\/YWEPTt4BY50+\nJHXJryi97mzSRlxZ6iVp4KJakfAe7BG6581rJSsYuydoCKGfoRvlOiWI2ARvN9Y6\nKfqMB1baWr3EtMdoFDEk8RnNuGbz5B2lXt3irlfD2i\/UgQYOTkI2jDWduRwyhqcn\nIJA9SI2j\/Lb4rK0sMhqJ9cKipouN4+S7cyxMfpEz64IVD8gTN22vTvRCySvl9uB7\nlQIDAQAB\n-----END PUBLIC KEY-----\n"
  },
  "published": "2023-02-20T12:13:14+00:00",
  "url": "https:\/\/www.example.com\/component\/activitypub\/profile\/alice.html?Itemid=101",
  "icon": {
    "type": "Image",
    "mediaType": "image\/jpeg",
    "url": "https:\/\/www.gravatar.com\/avatar\/aa1db827772e1d51d453b844394b7617?s=256"
  }
}
```

If you are receiving the error message `{"errors":[{"title":"Resource not found","code":404}]}` remember to publish the “Web Services - ActivityPub” plugin. 

If you get an error please remember that you must NOT be blocking the Joomla API application (the `/api` folder of your site). This is something that some of you may have done following **erroneous** advice after the security issue which was _fixed_ in Joomla 4.2.8 in mid-February 2023. Most likely you added something to your `.htaccess` or `web.config` file, or changed your NginX configuration. **DO NOT** disable the Joomla API; it will eventually break your site — and we need the Joomla API for ActivityPub to work since, well, ActivityPub is just a JSON API!

## Tell people to follow you

People can now follow you on Mastodon using the username you figured out in the section above. As a reminder, this is your actor's username (i.e. your Joomla! username), followed by an at-sign, followed by your domain name (without www in front). For example, if your username is `alice` and your site is `https://www.example.com` you need to enter `alice@example.com`.

Whenever you publish an article it will appear as a toot (Mastodon post) to your followers' main feeds.

## Caveats

When you first look for the Mastodon user (more accurately called an ‘ActivityPub actor’) made available by your site you will see your profile information but may see no posts. This is normal. Mastodon _does not_ load previous posts when the account you are looking up is hosted on a different server. This is how Mastodon itself works, not something you can do anything about.

Whenever you unpublish an article, the toot announcing it is deleted. When you republish an article, a new toot is created. This is intentional; Mastodon —and ActivityPub in general— does not have the concept of “unpublishing” content; it only knows about deleting it.

When you edit an article the toot will not be updated. Technically speaking, we do send an update notification to Mastodon, but Mastodon ignores it. If you want to edit something which appears in a toot about a post you need to unpublish and republish the article.

The ActivityPub component fully supports the Publish Up and Publish Down features in Joomla. The toot will only be made available to your followers after the article is published on your site which will be a point in time _after_ the Publish Up date and time you have set up. The toot will be deleted when the article is unpublished at some point in time _after_ the Publish Down date and time you have set up. Please keep in mind that publishing and unpublishing articles is Joomla's responsibility, not the ActityPub component's. In other words, someone needs to access a list of articles in the front- or backend of your site for Joomla to handle publishing and unpublishing articles. The act of an article getting published or unpublished triggers the ActivityPub code which creates and deletes toots.

You can set up the ActivityPub component to create toots for articles which are not public. The component will comply with your demand, creating the toots. The links back to the article on your site will of course require a user with the necessary access to log into the site to view the article. This is intentional! A practical use case for that is showing the intro text of articles to everyone but keeping the full text of the article behind a registration or paywall — just like many newspapers and online publications do.