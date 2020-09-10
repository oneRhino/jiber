<?php

namespace App\Console\Commands\RedmineToClubhouse;

use Mail;
use Illuminate\Console\Command;

class CreatedTasks extends Command
{
	use Redmine, Clubhouse, Log;

	/**
	* The name and signature of the console command.
	*
	* @var string
	*/
	protected $signature = 'redmine-to-clubhouse:sync-created-tasks {--limit=} {--debug}';

	/**
	* The console command description.
	*
	* @var string
	*/
	protected $description = 'Syncs created tickets from Redmine to Clubhouse.';

	private $debug = false;

	/**
	* Create a new command instance.
	*
	* @return void
	*/
	public function __construct() {
		parent::__construct();
	}

	/**
	* Execute the console command.
	*
	* @return mixed
	*/
	public function handle() {
		$this->setLogFilename('redmine-clubhouse-create');

		$this->writeLog('***** INIT - Get new tickets from Redmine, and create on Clubhouse *****');

		if ($this->option('debug')) {
			$this->debug = true;
		}

		// Grab tickets recently created on Redmine
		$tickets = $this->getRedmineTickets();

		// Create tickets on Redmine, and get Redmine ID/Clubhouse ID combinations
		$this->createTicketsClubhouse($tickets);

		$this->writeLog('***** END *****');
	}

	/**
	* Get all Redmine tickets, except the ones not on RedmineProjects
	*/
	private function getRedmineTickets() {
		// Redmine controller
		$this->redmine();

		// Get all redmine entries, related with clubhouse
		$tickets = $this->getLastRedmineTicketsRelatedWithClubhouse();

		return $tickets;
	}

	/**
	* Sends the tickets to Clubhouse using the API.
	*/
	private function createTicketsClubhouse(array $tickets) {
		// Check if there are any tickets to be created
		if (!$tickets) {
			return false;
		}

		// Clubhouse controller
		$this->clubhouse();

		if ($this->option('limit')) {
			$tickets = array_slice ($tickets, 0, $this->option('limit'));
		}

		foreach ($tickets as $ticket) {
			try {
				$clubhouse_ticket = $ticket->getClubhouseTicketArray();

				if ($this->debug) {

					$this->writeLog("-- Task {$ticket['ticket_details']['id']} NOT sent to Clubhouse due to debug mode.");

				} else {
					$clubhouse_created = $this->createStory($clubhouse_ticket);

					if (!$clubhouse_created) {
						$this->writeLog("-- Task {$ticket->getId()} NOT sent to Clubhouse. Error: ".print_r($clubhouse_ticket, true));
						continue;
					}

					$this->writeLog("-- Task {$ticket->getId()} sent to Clubhouse.");

					$ticket->linkWithClubhouseStory($clubhouse_created['id']);

					$this->writeLog("-- Task {$ticket->getId()} saved on database.");

					$ticket->addExtraDataToDescription($clubhouse_created['id']);

					$this->saveJiraIDAndDescription($ticket);
				}

			} catch (\Exeption $e) {
				$this->writeLog("-- Task {$ticket->getId()} could not be sent to Redmine, Error: {$e->getMessage()}, Trace: {$e->getTraceAsString()}");
			}
		}

		return true;
	}
}
