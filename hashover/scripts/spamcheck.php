<?php namespace HashOver;

// Copyright (C) 2010-2017 Jacob Barkdull
// This file is part of HashOver.
//
// HashOver is free software: you can redistribute it and/or modify
// it under the terms of the GNU Affero General Public License as
// published by the Free Software Foundation, either version 3 of the
// License, or (at your option) any later version.
//
// HashOver is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Affero General Public License for more details.
//
// You should have received a copy of the GNU Affero General Public License
// along with HashOver.  If not, see <http://www.gnu.org/licenses/>.


// Display source code
if (basename ($_SERVER['PHP_SELF']) === basename (__FILE__)) {
	if (isset ($_GET['source'])) {
		header ('Content-type: text/plain; charset=UTF-8');
		exit (file_get_contents (basename (__FILE__)));
	} else {
		exit ('<b>HashOver</b>: This is a class file.');
	}
}

class SpamCheck
{
	public $blocklist;
	public $database;
	public $error;

	public function __construct (Setup $setup)
	{
		// JSON IP address blocklist file
		$this->blocklist = $setup->getAbsolutePath ('blocklist.json');

		// CSV spam database file
		$this->database = $setup->getAbsolutePath ('spam-database.csv');
	}

	// Compare array of IP addresses to user's IP
	public function checkIPs ($ips = array ())
	{
		// Do nothing if input isn't an array
		if (!is_array ($ips)) {
			return false;
		}

		// Run through each IP
		for ($ip = count ($ips) - 1; $ip >= 0; $ip--) {
			// Return true if they match
			if ($ips[$ip] === $_SERVER['REMOTE_ADDR']) {
				return true;
			}
		}

		// Otherwise, return false
		return false;
	}

	// Return false if visitor's IP address is in block list file
	public function checkList ()
	{
		// Do nothing if blocklist file doesn't exist
		if (!file_exists ($this->blocklist)) {
			return false;
		}

		// Read blocklist file
		$data = @file_get_contents ($this->blocklist);

		// Parse blocklist file
		$blocklist = @json_decode ($data, true);

		// Check user's IP address against blocklist
		if ($blocklist !== null) {
			return $this->checkIPs ($blocklist);
		}

		return false;
	}

	// Get Stop Forum Spam remote spam database JSON
	public function getStopForumSpamJSON ()
	{
		// Stop Forum Spam API URL
		$url = 'http://www.stopforumspam.com/api?ip=' . $_SERVER['REMOTE_ADDR'] . '&f=json';

		// Check if we have cURL
		if (function_exists ('curl_init')) {
			// If so, initiate cURL
			$ch = curl_init ();
			$options = array (CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true);
			curl_setopt_array ($ch, $options);

			// Fetch response from Stop Forum Spam database check
			$output = curl_exec ($ch);

			// Close cURL
			curl_close ($ch);
		} else {
			// If not, open file via URL if allowed
			if (ini_get ('allow_url_fopen')) {
				$output = @file_get_contents ($url);
			}
		}

		// Parse response as JSON
		if (!empty ($output)) {
			$json = @json_decode ($output, true);

			if ($json !== null) {
				return $json;
			}
		}

		return array ();
	}

	// Stop Forum Spam remote spam database check
	public function remote ()
	{
		// Get Stop Forum Spam JSON
		$spam_database = $this->getStopForumSpamJSON ();

		// Set error message and return true if spam check failed
		if (!isset ($spam_database['success'])) {
			$this->error = 'Spam check failed!';
			return true;
		}

		// Set error message and return true if response was invalid
		if (!isset ($spam_database['ip']['appears'])) {
			$this->error = 'Spam check received invalid JSON!';
			return true;
		}

		// If spam check was successful
		if ($spam_database['success'] === 1) {
			// Return true if user's IP appears in the database
			if ($spam_database['ip']['appears'] === 1) {
				return true;
			}
		}

		return false;
	}

	// Local CSV spam database check
	public function local ()
	{
		// Do nothing if CSV spam database file doesn't exist
		if (!file_exists ($this->database)) {
			return false;
		}

		// Read CSV spam database file
		$data = @file_get_contents ($this->database);

		// Check if file read successfully
		if ($data !== false) {
			// If so, convert CSV database into array
			$ips = explode (',', $data);

			// And check user's IP address against CSV database
			return $this->checkIPs ($ips);
		} else {
			// If not, set error message
			$this->error = 'No local database found!';
		}

		return false;
	}
}
