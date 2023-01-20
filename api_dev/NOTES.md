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
* [X] Send an Update when the account details change
  * By editing the Actor
  * By updating the user profile
* [X] Frontend per-user profile page
* [ ] Profile fields
* [ ] Profile header image
* [ ] Backend Fediblock management
* [ ] Backend block management
* [ ] Frontend block management (per-user)
  * [ ] Block users by giving their handle (`foo@example.com`), sends a Block activity **IF** already followed
  * [ ] Removing a block sends Undo Block activity
* [ ] Document use cases
  * **Consume-only**. Disable all plugins except for the content plugin.
  * **Alias to Mastodon**. Only enable WebFinger, set up the alias to the Mastodon profile.
  * **Single-user blog**. Enable all plugins. Set up an actor for the current Super User, update its info in the user profile.
  * **Site-wide updates**. Enable all plugins. Set up an aggregator actor.

# Future goals

* [ ] Backend Activities management
  * [ ] Delete: sends a Delete to federated servers
* [ ] Frontend Activities management
  * [ ] Delete: sends a Delete to federated servers
* Convert inline images to attachments
* Convert embeds / links for popular services to attachments and make it extensible
  * YouTube as an example implementation
* Allow the scheduled task to run on a loop waiting for new notifications to send
  * Get a batch of notifications and process them
  * Wait for 1 to 5 seconds (configurable)
  * Rinse and repeat until we hit 60 - wait_time seconds of runtime
  * The user must schedule this task to run once a minute
* [ ] Keep count of reblogs (receiving an Announce activity)