# Outbox

## Reading the Outbox

The outbox represents a stream of activity for a given actor.

Each `activitypub` or `content` plugin listens to the event
`onActivityPubGetActivityListQuery(ActorTable $actor): QueryInterface`
which returns a Joomla query object representing a standardised list of activities' id, context and timestamp. For example
```mysql
SELECT id                    as id,
       created               as timestamp,
       'com_content.article' as context
FROM bot4_content
WHERE state = 1
  AND catid IN (20)
```

The Outbox model then creates a UNION query out of the lot, e.g:

```mysql
SELECT id                    as id,
       created               as timestamp,
       'com_content.article' as context
FROM bot4_content
WHERE state = 1
  AND catid IN (20)
UNION
SELECT e.id                 as id,
       e.created            as timestamp,
       'com_engage.comment' as context
FROM bot4_engage_comments e
         LEFT JOIN
     bot4_assets a ON e.asset_id = a.id
         LEFT JOIN
     bot4_content c ON c.asset_id = a.id
WHERE e.enabled = 1
  AND c.catid IN (20)
```

This UNION query can be queried for the total number of records:

```mysql
SELECT COUNT(*)
FROM
(
    SELECT id                    as id,
           created               as timestamp,
           'com_content.article' as context
    FROM bot4_content
    WHERE state = 1
      AND catid IN (20)
    UNION
    SELECT e.id                 as id,
           e.created            as timestamp,
           'com_engage.comment' as context
    FROM bot4_engage_comments e
             LEFT JOIN
         bot4_assets a ON e.asset_id = a.id
             LEFT JOIN
         bot4_content c ON c.asset_id = a.id
    WHERE e.enabled = 1
      AND c.catid IN (20)
) activities;
```

or you can paginate it very easily using ORDER BY and LIMIT:

```mysql
SELECT id                    as id,
       created               as timestamp,
       'com_content.article' as context
FROM bot4_content
WHERE state = 1
  AND catid IN (20)
UNION
SELECT e.id                 as id,
       e.created            as timestamp,
       'com_engage.comment' as context
FROM bot4_engage_comments e
         LEFT JOIN
     bot4_assets a ON e.asset_id = a.id
         LEFT JOIN
     bot4_content c ON c.asset_id = a.id
WHERE e.enabled = 1
  AND c.catid IN (20)
ORDER BY timestamp DESC
LIMIT 0, 10
```

Each activity will get a unique URL: `v1/activitypub/activity/<USERNAME>/<IDENTIFIER>` where `<IDENTIFIER>` is the concatenation of the context and ID. For example `v1/activitypub/activity/myuser/com_content.article.123`.

After getting the contexts and IDs in the current page, the outbox needs to be able to produce the individual activity objects.

It collects all IDs per context and calls the following `activitypub` or `content` plugin event:
`onActivityPubGetActivity(Actor $actor, string $context, array $ids): \ActivityPhp\Type\Core\Activity[]`
Note that the exact type of the array elements could be any subtype of Activity, typically Create. The array is keyed as `<context>.<id>` e.g. `com_content.123`.

The model takes the activities and reorders them based on the result of the paginated UNION query.

TODO: Post-process the activities

## Posting to the Outbox

Not implemented. Returns appropriate HTTP code.

# Activities

The `v1/activitypub/activity/<USERNAME>/<IDENTIFIER>` endpoint executes the same `onActivityPubGetActivity` event with a single ID in the `$ids` list. It returns the Activity document to the caller, or a 404 if it doesn't exist.

# Inbox

## Reading the inbox

Not implemented. Always returns the appropriate HTTP response.

## Posting to the inbox

To be implemented. Required to provide followers.

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