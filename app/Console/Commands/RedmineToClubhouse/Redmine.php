<?php

namespace App\Console\Commands\RedmineToClubhouse;

use App\{RedmineProject, RedmineTicket, User};
use App\Http\Controllers\RedmineController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

trait Redmine {
	private $redmine;

	private function redmine() {
		$user = User::find(7);

		// Set user as logged-in user
		$request = new Request();
		$request->merge(['user' => $user]);
		$request->setUserResolver(function () use ($user) {
			return $user;
		});

		Auth::setUser($user);

		$RedmineController = new RedmineController;
		$this->redmine = $RedmineController->connect();
	}

	private function getLastRedmineTicketsRelatedWithClubhouse():array {
		$redmine_projects = RedmineProject::clubhouse()->get();

		$all_tickets = [];

		foreach ($redmine_projects as $_project) {
			$this->writeLog("- PROJECT [{$_project->project_name}]");

			$redmine_entries = $this->getLastRedmineEntries('created', $_project);

			foreach ($redmine_entries['issues'] as $_ticket) {
				$RedmineTicket = new RedmineTicket($_ticket);

				// Check if task has already been created on Clubhouse (ClubhouseStory)
				if ($RedmineTicket->hasRelatedClubhouseTicketCreated()) {
					$this->writeLog('-- Story '.$RedmineTicket->getId().' already been created on Clubhouse, CONTINUE');
					continue;
				}

				$this->writeLog("Ticket {$RedmineTicket->getId()} will be created on Clubhouse");

				$RedmineTicket->setRedmineProject($_project);

				$all_tickets[] = $RedmineTicket;
			}
		}

		return $all_tickets;
	}

	private function getLastRedmineEntries($action, $project) {
		// Current date
		$date = date('Y-m-d', strtotime("-20 minutes"));

		$args = array(
			"{$action}_on" => '>=' . $date,
			'limit'        => 100,
			'sort'         => "{$action}_on:desc",
			'include'      => 'attachments',
			'project_id'   => $project->project_id,
		);

		return $this->redmine->issue->all($args);
	}

	private function getJournalsFromRedmine($ticket_id) {
		$args           = ['include' => 'journals'];
		$ticket_details = $this->redmine->issue->show($ticket_id, $args);
		return $ticket_details['issue']['journals'];
	}
}
