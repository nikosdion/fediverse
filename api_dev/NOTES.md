# Outbox

## Reading the outbox

JSON view for the `#__activitypub_outbox` table.

## Posting to the outbox

Not implemented. Always returns the appropriate HTTP response. 

Note: Content plugins are supposed to create the `#__activitypub_outbox` entries. 

# Inbox

## Reading the inbox

Not implemented. Always returns the appropriate HTTP response.

Note: Will not be implemented as I don't want to have to implement the entire OAuth2 authentication rigmarole.

## Posting to the inbox

Goes through the `onActivityPubHandleActivity` event handler, see `\Dionysopoulos\Component\ActivityPub\Administrator\Event\HandleActivity`

# Thoughts

Option to include a link to the original content IF Mastodon can't use the URL I am already sending.

I need a table to track Followers `#__activitypub_followers`, including their sharedInbox and the original, cached document.
    id SERIAL // PK to facilitate DB management
    actor_id BIGINT(10) // FK to #__activitypub_actors
    follower_actor VARCHAR(1024) // The actor who requested to follow us, e.g. https://mastodon.example.com/users/remoteuser
    username VARCHAR(256) // So I can search by it and display it: actor's preferredUsername
    domain VARCHAR(256) // So I can search by it and display it: actor's domain name
    follow_id VARCHAR(1024) // The follow activity ID, e.g. https://mastodon.example.com/2ff5bc37-9b1f-4c48-86fb-6f3450e366d6 Will be used to unfollow
    inbox TEXT
    sharedInbox TEXT
    created_on DATETIME
    UNIQUE KEY (actor_id, actor)

Follow activity: POSTed to the inbox:
```http request
POST /api/v1/activitypub/inbox/mylocaluser
Content-Type: application/activity+json

{
  "@context": "https://www.w3.org/ns/activitystreams",
  "id": "https://mastodon.example.com/2ff5bc37-9b1f-4c48-86fb-6f3450e366d6",
  "type": "Follow",
  "actor": "https://mastodon.example.com/users/remoteuser",
  "object": "https://mysite.example.com:443/api/v1/activitypub/actor/mylocaluser"
}
```
We must reply with an Accept or Reject activity POSTed to the actor's inbox, see https://www.w3.org/TR/activitypub/#follow-activity-inbox

Per-account option to disallow Followers. This will make all Follow activity result in a Reject activity.

I need to be able to block users: remove from Followers, Reject their future follows, and block them from POSTing to my inbox.
`#__activitypub_block`
    `id` SERIAL
    `actor_id` BIGINT(20) UNSIGNED NOT NULL FK actors
    `username` VARCHAR(512)
    `domain` VARCHAR(512)
    UNIQUE(actor_id,username,domain)

It should be possible to "fediblock", i.e. block followers and replies from entire domains. Good resource for this is https://joinfediverse.wiki/FediBlock
`#__activitypub_fediblock`

Unfollow sends an Undo activity with the previous Follow activity attached.

## Notify remote servers

Content plugins need to detect when publishing an article would result in a new activity and add to a temp table of outbound activities.

Notifying followers requires a Scheduled Task.

**Step 1 (event origin): Create a list of temporary update information**
It has the following information:
* `id` Serial
* `actor_id` originating actor ID
* `activity` what to send
* `recipient_type` one of `actor` or `followers`
* `actor` Remote actor URL. Use `as:followers` to notify followers

**Step 2 (event origin): Create update actions**

* If the `actor` is NOT `as:followers` create a `#__activitypub_update_queue` record; exit
* If `actor` is `as:followers`
  * Get a list of all followers
  * Group them by domain
  * Foreach domain
    * Foreach follower
        * If there is a sharedInbox
            * Queue `#__activitypub_update_queue` to the sharedInbox URL
            * Exit the foreach (updating a sharedInbox updates all followers on that domain!)
        * Queue `#__activitypub_update_queue` to the remote actor's inbox

The `#__activitypub_queue` records have the following information:
* `id` SERIAL
* `activity` what to send
* `inbox` where to send it
* `retry_count` Starts at 0
* `next_try` DATETIME

**Step 3 (task): Execute update actions**

* Fetch 10 actions, deleting them from the pool
* POST `activity` to the `inbox`
* If we received a 4xx or 5xx status
  * Increase retry_count by one
  * If retry_count > 10: discard
  * next_try += 3^next_try seconds (exponential backoff; will retry for just over 24 hours with a max retry_count of 10)
  * Throw the update activity back into the pool

If there is enough time repeat steps 2 and 3

## Receiving replies

We will get a Create activity POSTed to the actor's inbox.

Allowing replies will require integration with Engage. I have to think about how to best do that.

## Frontend

I need a frontend (site) feed view which will act as the user's homepage

This needs to be linked in their WebFinger and Actor.

# Rolling To-Do

* [X] Convert to using the outbox table
* [ ] Send the first batch of notifications immediately, without going through the scheduled task
* [ ] Backend Fediblock management
* [ ] Backend block management
* [ ] Frontend per-user profile page
* [ ] Frontend per-user block management
