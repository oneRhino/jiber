<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RedmineJournalEntry extends Model
{
	private $details;
	private $created_on;
	private $notes;
	private $id;
	private $user;

	public function __construct($entry) {
		$this->details    = $entry['details'];
		$this->created_on = $entry['created_on'];
		$this->notes      = $entry['notes'] ?? '';
		$this->id         = $entry['id'];
		$this->user       = $entry['user'];
	}

	public function getID() {
		return $this->id;
	}

	public function getCreatedOn(): \DateTime {
		$created_on = new \DateTime($this->created_on);
		$created_on->setTimezone(new \DateTimezone('UTC'));
		return $created_on;
	}

	public function getNotes() {
		return $this->notes;
	}

	public function getDetails() {
		return $this->details;
	}

	public function isRecent($minutes=10) {
		$last_x_min = new \DateTime("-{$minutes} min");
		$created_on = $this->getCreatedOn();

		return $created_on > $last_x_min;
	}

	public function isSentToClubhouse(): bool {
		$change = $this->getSentToClubhouse();

		return $change ? true : false;
	}

	public function getSentToClubhouse() {
		$change = RedmineClubhouseChange::where('redmine_change_id', $this->id)->first();

		return $change;
	}
}
