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

# Rolling To-Do

* [X] Convert to using the outbox table
* [X] Send the first batch of notifications immediately, without going through the scheduled task
* [X] Implement Unfollow (Mastodon sends Undo for a Follow, or Accept Follow)
* [ ] Send an Update when the account details change
* [ ] Backend Fediblock management
* [ ] Backend block management
* [ ] Frontend per-user profile page
* [ ] Frontend per-user block management
