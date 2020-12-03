<?php

namespace App\Console\Commands\RedmineToClubhouse;

use Mail;
use App\{RedmineProject, RedmineTicket};
use Illuminate\Console\Command;

class UpdatedTasks extends Command
{
	use Redmine, Clubhouse, Log;

	/**
	* The name and signature of the console command.
	*
	* @var string
	*/
	protected $signature = 'redmine-to-clubhouse:sync-updated-tasks {--debug}';

	/**
	* The console command description.
	*
	* @var string
	*/
	protected $description = 'Syncs updated tickets from Redmine to Clubhouse.';

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
		$this->setLogFilename('redmine-clubhouse-update');

		$this->writeLog('***** INIT *****');

		if ($this->option('debug')) {
			$this->debug = true;
		}

		$this->sendRedmineChangesToClubhouse();

		$this->writeLog('***** END *****');
	}

	/**
	* Get recent Redmine ticket changed (only realted to Clubhouse Projects).
	*
	* @return array
	*/
	private function sendRedmineChangesToClubhouse() {
		// Redmine controller
		$this->redmine();

		$redmine_projects = RedmineProject::clubhouse()->get();

		foreach ($redmine_projects as $_project) {
			$this->writeLog("- PROJECT [{$_project->project_name}]", true);

			$redmine_entries = $this->getLastRedmineEntries('updated', $_project);

			foreach ($redmine_entries['issues'] as $_ticket) {
				$this->writeLog("- Ticket [{$_ticket['id']}]");

				// Get ticke journals from redmine
				$journals = $this->getJournalsFromRedmine($_ticket['id']);

				// Check if ticket has any journals
				if (! $journals) {
					// $this->writeLog("-- Ticket has no journals");
					continue;
				}

				// Assign journals to ticket journals var
				$_ticket['journals'] = $journals;

				// Create RedmineTicket object
				$RedmineTicket = new RedmineTicket($_ticket);
				$RedmineTicket->setRedmineProject($_project);

				foreach ($RedmineTicket->getJournals() as $_Journal) {
					// $this->writeLog("Journal {$_Journal->getID()}");
					// Check if journal is recent (last 10 minutes)
					if (!$_Journal->isRecent(15)) {
						// $this->writeLog($_Journal->getCreatedOn());
						// $this->writeLog("- Not recent");
						continue;
					}

					// $this->writeLog($_Journal);

					// Check if change was already sent to Clubhouse.
					if ($_Journal->isSentToClubhouse()) {
						// $this->writeLog('-- Change already sent to Clubhouse: ' . $_Journal->getID() . ', CONTINUE');
						continue;
					}

					// Send ticket comments to Clubhouse
					if ($_Journal->getNotes()) {
						// $this->writeLog("- Has notes");
						$this->sendClubhouseStoryComment($RedmineTicket, $_Journal);
					}

					// $this->writeLog("- Not sent yet");

					if (!$_Journal->getDetails()) {
						// $this->writeLog("- Does not have details");
						continue;
					}

					foreach ($_Journal->getDetails() as $_detail) {
						// $this->writeLog($_detail);

						switch ($_detail['name']) {
							case 'subject':
								$changeArray = ['name' => $RedmineTicket->getSubject()];
								$this->updateClubhouseStory($RedmineTicket, $_Journal, $changeArray);
								break;

							case 'description':
								$changeArray = ['description' => $RedmineTicket->getDescription()];
								$this->updateClubhouseStory($RedmineTicket, $_Journal, $changeArray);
								break;

							case 'status_id':
								$changeArray = ['workflow_state_id' => $RedmineTicket->getClubhouseWorkflowStateID()];
								// $this->writeLog($changeArray);
								$this->updateClubhouseStory($RedmineTicket, $_Journal, $changeArray);
								break;

							case 'tracker_id':
								$changeArray = ['story_type' => $RedmineTicket->getClubhouseStoryType()];
								$this->updateClubhouseStory($RedmineTicket, $_Journal, $changeArray);

							case 'due_date':
								$changeArray = ['deadline' => $RedmineTicket->getClubhouseDeadline()];
								$this->updateClubhouseStory($RedmineTicket, $_Journal, $changeArray);
								break;

							case 'assigned_to_id':
								$changeArray = ['owner_ids' => [$RedmineTicket->getClubhouseAssignedToIDs()]];
								$this->updateClubhouseStory($RedmineTicket, $_Journal, $changeArray);

							case 'priority_id':
							case 'start_date':
							case 'estimated_hours':
								// These fields does not exist on Clubhouse.
								break;
						}
					}
				}
			}
		}
	}
}
