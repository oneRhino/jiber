<?php

/**
 * Connect into Jira, get and send information using its API
 *
 * @author Thaissa Mendes <thaissa.mendes@gmail.com>
 * @since 8 August, 2016
 * @version 0.1
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

use App\Http\Requests;
use App\JiraSent;
use App\Setting;
use App\TogglReport;

use chobie\Jira\Api;
use chobie\Jira\Api\Authentication\Basic;
use chobie\Jira\Issues\Walker;

class JiraController extends Controller
{
  /**
	 * Connect into Jira API
	 */
	public function connect(Request $request)
	{
		$redirect = app('Illuminate\Routing\Redirector');
    $url      = Config::get('jira.url');
    $username = Setting::find($request->user()->id)->jira;
		$password = $request->session()->get('jira.password');

		if (!$password)
		{
			$request->session()->flash('alert-warning', 'Please set your Jira Password (it will be stored only for this session).');
			$request->session()->put('back', $request->path());
			$redirect->to('/jira/set-password')->send();
		}

		$api = new Api(
			$url,
			new Basic($username, $password)
		);

		return $api;
	}

	/**
	 * Show set Jira password form and save it into session
	 */
	public function set_password(Request $request)
	{
		$redirect = app('Illuminate\Routing\Redirector');

		if (!Setting::find($request->user()->id)->jira)
		{
			$request->session()->flash('alert-warning', 'Please set your Jira Username.');
			$request->session()->put('back', $request->path());
			$redirect->to('/settings')->send();
		}

		if ($request->isMethod('post'))
		{
			$request->session()->put('jira.password', $request->jira_password);

			if ($request->session()->has('back'))
				return redirect($request->session()->get('back'));
			else
				return back()->withInput();
		}
		else
		{
			return view('jira.set_password');
		}
	}

	/**
	 * Test Jira connection
	 */
	public function test(Request $request)
	{
		$connection = $this->connect($request);

		try
		{
			$details = $connection->api('GET', '/rest/api/2/mypermissions');

			if ($details) return true;
		}
		catch (\Exception $e)
		{
			return false;
		}

		return false;
	}

	/**
	 * Show Jira's time entries grouped by date and Redmine's task ID
	 */
	public function show(TogglReport $report, Request $request)
	{
		if ($report->user_id != $request->user()->id)
			abort(403, 'Unauthorized action.');

		#$request->session()->forget('jira.report');

		// Only enter if no session exists for jira.report
		// It'll be reset elsewhere
		if (!$request->session()->has('jira.report.'.$report->id))
		{
			set_time_limit(0);

			// Connect into Jira
			$jira = $this->connect($request);

			// Get Jira current username
			$setting = Setting::find($request->user()->id);
			$jira_username = $setting->jira;

			// Entries array - that will contain all Toggl's and Jira's entries to display
			$entries = array();

			// Get all report's entries that have a Jira Task ID set
			$jira_entries = $report->entries()->whereNotNull('jira')->orderBy('redmine')->orderBy('duration')->get();

			if (!$jira_entries->count())
			{
	      $request->session()->flash('alert-warning', 'No Jira tasks have been found in the period.');
    		return back()->withInput();
			}

			// First create arrays and fill with Toggl information
			foreach ($jira_entries as $_entry)
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
				$entries[$_entry->date][$_entry->redmine]['toggl_total']    += $_entry->round_duration;
				$entries[$_entry->date]['toggl_total']                      += $_entry->round_duration;
			}

			// Then, through all Toggl's entries, get Jira's worklog
			foreach ($entries as $_date => $_entries)
			{
				foreach ($_entries as $_redmine => $__entries)
				{
					// Skip *_total keys
					if (in_array($_redmine, array('toggl_total','third_total'))) continue;

					// Get Jira worklog for this task
					$worklog = $jira->getWorklogs($__entries['toggl_entries'][0]->jira, array());
					$results = $worklog->getResult();

					foreach ($results['worklogs'] as $_time)
					{
						// Worklog author isn't current jira user? Continue!
						if ($_time['author']['name'] != $jira_username) continue;

						// Only add worklog for this specific date
						if (strtotime($_date) != strtotime(date('Y-m-d', strtotime($_time['started'])))) continue;

						$_time['description'] = ($_time['comment'] ? $_time['comment'] : '<No comment>');
						$_time['time']        = $_time['timeSpentSeconds'] / 3600;

						$entries[$_date][$_redmine]['third_entries'][] = $_time;
						$entries[$_date][$_redmine]['third_total']    += ($_time['timeSpentSeconds'] / 3600);
						$entries[$_date]['third_total']               += ($_time['timeSpentSeconds'] / 3600);
					}
				}
			}

			// Sort entries based on first key (date), ascending
			ksort($entries);

			$request->session()->put('jira.report.'.$report->id, $entries);
		}
		else
			$entries = $request->session()->get('jira.report.'.$report->id);

		return view('jira.show', [
			'entries'   => $entries,
			'report_id' => $report->id
		]);
	}

	/**
	 * Send time to Jira
	 */
	public function send(Request $request)
	{
		if ($request->isMethod('post'))
		{
			// Connect into Jira
			$jira = $this->connect($request);

			foreach ($request->task as $_date => $_entries)
			{
				// Transforming date into Jira's format
				// removing ':' from timezone and adding '.000' after seconds
				$_date = preg_replace('/([-+][0-9]{2}):([0-9]{2})$/', '.000${1}${2}', $_date);

				foreach ($_entries as $_jira => $_hours)
				{
					foreach ($_hours as $_time => $_comment)
					{
						$data = array(
								'timeSpentSeconds' => $_time * 3600,
								'started'          => $_date,
								'comment'          => $_comment,
								'issueId'          => $_jira,
						);
						$response = $jira->addWorklog($_jira, $data);

						if ($response)
						{
							// Create a JiraSent (log)
							$sent = new JiraSent;
							$sent->report_id = $request->report_id;
							$sent->task      = $_jira;
							$sent->date      = $_date;
							$sent->duration  = $_time;
							$sent->user_id   = $request->user()->id;
							$sent->save();
						}
					}
				}
			}

			// Remove report from session, so when we show previous page again, it's updated
			$request->session()->forget('jira.report.'.$request->report_id);
      $request->session()->flash('alert-success', 'All tasks have been sent successfully to Jira!');
		}

    return back()->withInput();
	}
}
