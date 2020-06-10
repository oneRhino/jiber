<?php

namespace App\Console\Commands\RedmineToClubhouse;

use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

trait Log {
	private $log_filename;

	private function setLogFilename($filename) {
		$this->log_filename = $filename . '.log';
	}

	private function writeLog($message) {
		if (!$this->log_filename) {
			throw Exception('Log filename must be set.');
		}

		if (is_array($message) || is_object($message)) {
			$message = print_r($message, true);
		}

		file_put_contents($this->log_filename, date('Y-m-d H:i:s').' - '.$message."\n", FILE_APPEND);
	}
}
