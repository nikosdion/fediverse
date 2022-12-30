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

Must have an /activity endpoint to list a single activity, as seen in the Outbox

Option to allow Followers. If this is disabled don't show a Followers collection in WebFinger, Actor, Outbox.

I need a table to track Followers, including their sharedInbox and the original, cached document.

It should be possible to "fediblock", i.e. block followers and replies from entire domains. Good resource for this is https://joinfediverse.wiki/FediBlock

I need to implement the Inbox. Reading will return 405. Writing should be allowed for follow requests — throw 405 if the user has opted out of being followed or following has been disabled.

Content plugins need to detect when publishing an article would result in a new activity and add to a temp table of outbound activities.

Notifying followers (requires a Scheduled Task):
* Step 1, use the temp outbound activity and the followers (and their shared inboxes) to create N number of actionable sends
* Step 2, start going through the actionable sends. Maybe use curl multi mode to send ~10 of them at a time

Allowing replies will require integration with Engage. I have to think about how to best to that. Essentially, I'd need to allow writing non-follow requests to my Inbox and reply with 405 if nothing handles it. 