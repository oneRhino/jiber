<?php

namespace App\Console\Commands\RedmineToClubhouse;

use App\RedmineClubhouseChange;
use Illuminate\Http\Request;
use App\Http\Controllers\ClubhouseController;

trait Clubhouse {
	private $clubhouse;

	private function clubhouse() {
		$this->clubhouse = new ClubhouseController;
	}

	private function createStory(array $clubhouse_story) {
		$story = $this->clubhouse->createStory($clubhouse_story);

		if (empty($story['id'])) {
			return false;
		}

		return $story;
	}

	// Update a Clubhouse story.
	private function updateClubhouseStory($RedmineTicket, $RedmineJournalEntry, array $change) {
		// Check if Clubhouse Story has been created
		$story = $RedmineTicket->getRelatedClubhouseStory();

		if (empty($story->story_id)) {
			// TODO: should we create it?
			return null;
		}

		$clubhouse_story_id = $story->story_id;

		// Send update to Clubhouse Story.
		if (!$this->debug) {
			$this->writeLog("-- Sending to Clubhouse: ".print_r($change, true));

			// Start Clubhouse controller
			$this->clubhouse();

			// Send update to Clubhouse
			$clubhouseUpdate = $this->clubhouse->updateStory($clubhouse_story_id, $change);

			$this->writeLog('-- Clubhouse answer: ' . print_r($clubhouseUpdate, true));

			// Save Clubhouse Change
			$this->saveClubhouseChange($RedmineJournalEntry->getID(), $clubhouseUpdate['id']);

			$this->writeLog('-- Change sent to Clubhouse: ' . $RedmineJournalEntry->getID());
		} else {
			$this->writeLog('-- Change NOT sent to Clubhouse due to Debug Mode');
		}
	}

	// Send comment to a Clubhouse story.
	private function sendClubhouseStoryComment($RedmineTicket, $RedmineJournalEntry) {
		$ClubhouseStory = $RedmineTicket->getRelatedClubhouseStory();

		// If this entry has already been sent to clubhouse, then we'll update its comment
		if ($redmine_clubhouse_change) {
			$this->updateClubhouseStoryComment($RedmineJournalEntry, $ClubhouseStory);
		} else {
			$this->createClubhouseStoryComment($RedmineJournalEntry, $ClubhouseStory);
		}
	}

	private function saveClubhouseChange($redmine_change_id, $clubhouse_change_id) {
		$change = new RedmineClubhouseChange;
		$change->redmine_change_id   = $redmine_change_id;
		$change->clubhouse_change_id = $clubhouse_change_id;
		$change->save();
	}

	private function createClubhouseStoryComment($RedmineJournalEntry, $ClubhouseStory) {
		$this->writeLog('-- Comment will be sent on Clubhouse: ' . $redmine_clubhouse_change->redmine_change_id);

		if (!$this->debug) {
			// Send comment to Clubhouse Story.
			$clubhouse_comment = $this->clubhouse->createComment($ClubhouseStory->story_id, $RedmineJournalEntry->getNotes());

			// Check if comment was successfully sent
			if (!array_key_exists('id', $clubhouse_comment)) {
				$this->writeLog("-- Story {$ClubhouseStory->story_id} was not found on Clubhouse, comment not sent: " . $redmine_clubhouse_change->redmine_change_id);
				throw new Exception("-- Story {$ClubhouseStory->story_id} was not found on Clubhouse, comment not sent: " . $redmine_clubhouse_change->redmine_change_id);
			}

			$this->saveClubhouseChange($RedmineJournalEntry->getID(), $clubhouse_comment['id']);

			$this->writeLog('-- Comment sent to Clubhouse: ' . $journal_entry['id']);
		} else {
			$this->writeLog('-- Comment NOT sent to Clubhouse due to Debug Mode');
			return;
		}
	}

	private function updateClubhouseStoryComment($RedmineJournalEntry, $ClubhouseStory) {
		$this->writeLog('-- Comment will be updated on Clubhouse: ' . $RedmineJournalEntry->getID());

		$redmine_clubhouse_change = $RedmineJournalEntry->getSentToClubhouse();
		$change_id                = $redmine_clubhouse_change->clubhouse_comment_id;

		if (!$this->debug) {
			// Send comment to Clubhouse Story.
			$clubhouse_comment = $this->clubhouse->updateComment($ClubhouseStory->story_id, $change_id, $RedmineJournalEntry->getNotes());

			$this->writeLog('-- Comment update sent to Clubhouse: ' . $redmine_clubhouse_change->redmine_change_id);
		} else {
			$this->writeLog('-- Comment update NOT sent to Clubhouse due to Debug Mode');
		}
	}
}
