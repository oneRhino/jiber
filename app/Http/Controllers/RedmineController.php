<?php

/**
 * Copyright 2016 Thaissa Mendes
 *
 * This file is part of Jiber.
 *
 * Jiber is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jiber is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jiber. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Connect into Redmine, using Redmine Client,
 * get and send information using its API
 *
 * @author Thaissa Mendes <thaissa.mendes@gmail.com>
 * @since July 28, 2016
 * @version 0.1
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

use App\Http\Requests;
use App\RedmineSent;
use App\Setting;
use App\TogglClient;
use App\TogglReport;
use App\TogglTimeEntry;

use Redmine\Client as RedmineClient;

class RedmineController extends Controller
{
  /**
	 * Connect into Redmine API
	 */
  function connect()
  {
		$request  = app('Illuminate\Http\Request');
		$redirect = app('Illuminate\Routing\Redirector');
		$setting  = Setting::find($request->user()->id);

		if (!$setting || !$setting->redmine)
		{
			$request->session()->flash('alert-warning', 'Please set your Redmine API Token before importing data.');
			$redirect->to('/settings')->send();
		}

    $url    = Config::get('redmine.url');
    $token  = $setting->redmine;
    $client = new RedmineClient($url, $token);

    return $client;
  }

	/**
	 * Test Redmine connection
	 */
	function test(Request $request)
	{
		$connection = $this->connect($request);

		$user = $connection->api('user')->getCurrentUser();

		if ($user && is_array($user)) return true;

		return false;
	}

  /**
   * Show Redmine's time entries grouped by date and Redmine's task ID
	 * *Only OneRhino tasks*
   */
  function show(TogglReport $report, Request $request)
  {
		if ($report->user_id != $request->user()->id)
			abort(403, 'Unauthorized action.');

		$request->session()->forget('redmine.report');

		// Only enter if no session exists for redmine.report
		// It'll be reset elsewhere
		if (!$request->session()->has('redmine.report.'.$report->id))
		{
			set_time_limit(0);

			// Connect into Redmine
			$redmine = $this->connect();

			// Get all Redmine's entries for this user, on these dates
			$args = array(
				'user_id'  => 'me',
				'spent_on' => "><{$report->start_date}|{$report->end_date}",
				'limit'    => 100,
				'sort'     => 'hours',
			);
			$redmine_entries = $redmine->time_entry->all($args);

			// Redmine has a 100 entries per page limit, so, we need to paginate
			$pages = ceil($redmine_entries['total_count'] / $redmine_entries['limit']);
			if ($pages > 1)
			{
				for ($page=1; $page<$pages; $page++)
				{
					$args['offset'] = $page * $args['limit'];
					$_results = $redmine->time_entry->all($args);
					$redmine_entries['time_entries'] = array_merge($redmine_entries['time_entries'], $_results['time_entries']);
				}
			}

			// Get all time entries from Report, but only those
			// that have Redmine field filled
			$toggl_entries = $report->getAllRedmine($request->user()->id);
			$entries       = array();

			// First create arrays and fill with Toggl information
			foreach ($toggl_entries as $_entry)
			{
				// Create default arrays
				if (!isset($entries[$_entry->date]))
				{
					$entries[$_entry->date] = array(
						'toggl_total' => 0,
						'third_total' => 0
					);
				}

				if (!isset($entries[$_entry->date][$_entry->redmine]))
				{
					$entries[$_entry->date][$_entry->redmine] = array(
						'toggl_entries' => array(),
						'toggl_total'   => 0,
						'third_total'   => 0,
						'third_entries' => array(),
					);
				}

				// Fill arrays
				$entries[$_entry->date][$_entry->redmine]['toggl_entries'][] = $_entry;
				$entries[$_entry->date][$_entry->redmine]['toggl_total']    += $_entry->round_decimal_duration;
				$entries[$_entry->date]['toggl_total']                      += $_entry->round_decimal_duration;
			}

			// Then, through all Toggl's entries, add Redmine's entries
			foreach ($entries as $_date => $_entries)
			{
				foreach ($_entries as $_issue_id => $__entries)
				{
					// Skip *_total keys
					if (in_array($_issue_id, array('toggl_total','third_total'))) continue;

					// Get Redmine time entries
					foreach ($redmine_entries['time_entries'] as $_redmine)
					{
						// Ignore issues that are different than current,
						// and with date different than current
						if ($_redmine['spent_on']    != $_date)     continue;
						if ($_redmine['issue']['id'] != $_issue_id) continue;

						$_redmine['description'] = (isset($_redmine['comments']) ? $_redmine['comments'] : $_redmine['activity']['name']);
						$_redmine['time']        = $_redmine['hours'];

						$entries[$_date][$_issue_id]['third_entries'][] = $_redmine;
						$entries[$_date][$_issue_id]['third_total']    += $_redmine['hours'];
						$entries[$_date]['third_total']                += $_redmine['hours'];
					}
				}
			}

			// Sort entries based on first key (date), ascending
			ksort($entries);

			$request->session()->put('redmine.report.'.$report->id, $entries);
		}
		else
			$entries = $request->session()->get('redmine.report.'.$report->id);

		return view('redmine.show', [
			'entries'   => $entries,
			'report_id' => $report->id
		]);
  }

	/**
	 * Send time to Redmine
	 */
	public function send(Request $request)
	{
		if ($request->isMethod('post'))
		{
			if (!$request->task)
			{
      	$request->session()->flash('alert-success', 'No tasks sent - nothing to do.');
				return back();
			}

			// Connect into Redmine
			$redmine = $this->connect($request);

			foreach ($request->task as $_entry_id)
			{
				$_entry = TogglTimeEntry::find($_entry_id);

				if (!$_entry || $_entry->user_id != $request->user()->id) continue;

				$_data = array(
					'issue_id' => $_entry->redmine,
					'spent_on' => $_entry->date,
					'hours'    => $_entry->round_decimal_duration,
					'comments' => $_entry->description
				);

				$_create = $redmine->time_entry->create($_data);

				if ($_create)
				{
					// Create a RedmineSent (log)
					$_sent = new RedmineSent;
					$_sent->report_id = $request->report_id;
					$_sent->date      = $_entry->date;
					$_sent->task      = $_entry->redmine;
					$_sent->duration  = $_entry->round_decimal_duration;
					$_sent->user_id   = $request->user()->id;
					$_sent->save();
				}
			}

			// Remove report from session, so when we show previous page again, it's updated
			$request->session()->forget('redmine.report.'.$request->report_id);
      $request->session()->flash('alert-success', 'All tasks have been sent successfully to Redmine!');
		}

    return back()->withInput();
	}
}
