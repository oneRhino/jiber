<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RedmineTicket extends Model
{
	private $due_date;

	private $start_date;
	private $assigned_to;
	private $created_on;
	private $author;
	private $priority;
	private $updated_on;
	private $subject;
	private $project;
	private $done_ratio;
	private $id;
	private $tracker;
	private $custom_fields;
	private $description;
	private $status;
	private $journals        = [];
	private $RedmineProject;

	/**
	* When creating a new Redmine Ticket object, pass the ticket array received
	* by Redmine API.
	*/
	public function __construct(array $ticket) {
		$this->due_date      = $ticket['due_date'] ?? null;

		$this->start_date    = $ticket['start_date'];
		$this->assigned_to   = $ticket['assigned_to'] ?? null;
		$this->created_on    = $ticket['created_on'];
		$this->author        = $ticket['author'];
		$this->priority      = $ticket['priority'];
		$this->updated_on    = $ticket['updated_on'];
		$this->subject       = $ticket['subject'];
		$this->project       = $ticket['project'];
		$this->done_ratio    = $ticket['done_ratio'];
		$this->id            = $ticket['id'];
		$this->tracker       = $ticket['tracker'];
		$this->custom_fields = $ticket['custom_fields'];
		$this->description   = $ticket['description'] ?? '';
		$this->status        = $ticket['status'];

		if (!empty($ticket['journals'])) {
			foreach ($ticket['journals'] as $_journal) {
				$this->journals[] = new RedmineJournalEntry($_journal);
			}
		}
	}

	public function getID():string {
		return $this->id;
	}

	public function getAuthorName():string {
		return $this->author['name'];
	}

	public function getAssignedToName():string {
		return $this->assigned_to['name'] ?? '';
	}

	public function getSubject():string {
		return $this->subject;
	}

	public function getDescription():string {
		return $this->description;
	}

	public function getStatus():RedmineStatus {
		return RedmineStatus::where('redmine_id', $this->status['id'])->first();
	}

	public function getTracker():RedmineTracker {
		return RedmineTracker::where('redmine_id', $this->tracker['id'])->first();
	}

	public function getDueDate():string {
		return $this->due_date ?? '';
	}

	public function getJournals():array {
		return $this->journals;
	}

	public function getRelatedClubhouseStory() {
		return ClubhouseStory::where('redmine_ticket_id', $this->getID())->first();
	}

	public function hasRelatedClubhouseTicketCreated():bool {
		$task = ClubhouseStory::where('redmine_ticket_id', $this->getID())->count();

		return $task > 0;
	}

	public function getClubhouseTicketArray():array {
		$clubhouse_ticket = [
			'project_id'        => $this->getClubhouseProjectID(),
			'name'              => $this->getSubject(),
			'story_type'        => $this->getClubhouseStoryType(),
			'description'       => $this->getDescription(),
			'requested_by_id'   => $this->getClubhouseAuthorID(),
			'workflow_state_id' => $this->getClubhouseWorkflowStateID(),
		];

		// Only if ticket has a assignee
		$assignee = $this->getClubhouseAssignedToIDs();

		if ($assignee) {
			$clubhouse_ticket['owner_ids'] = [$assignee];
		}

		// Only send deadline if set
		$deadline = $this->getClubhouseDeadline();

		if ($deadline) {
			$clubhouse_ticket['deadline'] = $deadline;
		}

		return $clubhouse_ticket;
	}

	public function setRedmineProject(RedmineProject $RedmineProject) {
		$this->RedmineProject = $RedmineProject;
	}

	public function linkWithClubhouseStory(string $clubhouse_story_id) {
		$ClubhouseStory                    = new ClubhouseStory;
		$ClubhouseStory->redmine_ticket_id = $this->getID();
		$ClubhouseStory->story_id          = $clubhouse_story_id;
		$ClubhouseStory->save();
	}

	private function getClubhouseProjectID():string {
		return $this->RedmineProject->third_party_project_id;
	}

	public function getClubhouseStoryType():string {
		return $this->getTracker()->clubhouse_name;
	}

	private function getClubhouseAuthorID():string {
		return $this->getClubhousePermissionsIDByRedmineName($this->getAuthorName());
	}

	public function getClubhouseAssignedToIDs() {
		$redmine_name = $this->getAssignedToName();

		if (!$redmine_name) {
			return null;
		}

		return $this->getClubhousePermissionsIDByRedmineName($redmine_name);
	}

	public function getClubhouseWorkflowStateID():string {
		// First, get possible clubhouse statuses ids
		$clubhouse_status_ids = $this->getStatus()->clubhouse_ids;

		if (!$clubhouse_status_ids) {
			throw new \Exception("Status {$this->getStatus()->redmine_name} not linked to a Clubhouse Status");
		}

		// Then, get clubhouse workflow id, based on project id
		$clubhouse_workflow_id = $this->getClubhouseWorkflowByProject();

		// Last, get a clubhouse status based on possible status ids and workflow is
		$clubhouse_status = ClubhouseStatus::whereIn('clubhouse_id', $clubhouse_status_ids)
		->where('workflow_id', $clubhouse_workflow_id)
		->first();

		return $clubhouse_status->clubhouse_id;
	}

	public function getClubhouseDeadline() {
		$due_date = $this->getDueDate();

		if (!$due_date) return false;

		$due_date = new \DateTime($due_date);
		$due_date->modify('+1 day');

		return date_format($due_date, 'Y-m-d');
	}

	private function getClubhouseUserIDByRedmineName($redmine_name):string {
		$redmine_clubhouse_user = RedmineClubhouseUser::where('redmine_names', 'like', "%{$redmine_name}%")->first();

		if (!$redmine_clubhouse_user) {
			throw new \Exception("Redmine/Clubhouse User {$redmine_name} not found.");
		}

		return $redmine_clubhouse_user->clubhouse_user_id;
	}

	private function getClubhousePermissionsIDByRedmineName($redmine_name):string {
		$redmine_clubhouse_user = RedmineClubhouseUser::where('redmine_names', 'like', "%{$redmine_name}%")->first();

		if (!$redmine_clubhouse_user) {
			throw new \Exception("Redmine/Clubhouse User {$redmine_name} not found.");
		}

		return $redmine_clubhouse_user->clubhouse_user_permissions_id;
	}

	private function getClubhouseWorkflowByProject() {
		$project_id = $this->getClubhouseProjectID();

		$clubhouse_workflow = ClubhouseWorkflow::where('project_ids', 'like', '%'.$project_id.'%')->first();

		if (!$clubhouse_workflow) {
			throw new \Exception("Clubhouse Project {$project_id} not found on Clubhouse Workflows. Please re-import Clubhouse Statuses.");
		}

		return $clubhouse_workflow->clubhouse_id;
	}
}
