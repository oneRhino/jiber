<?php

namespace App;

use DB;
use Illuminate\Database\Eloquent\Model;

/**
 * This model is extended by all Toggl's models
 */

class Toggl extends Model
{
	/**
	 * Get record based on Toggl ID and User ID
	 */
	public static function getByTogglID($toggl_id, $user_id)
	{
		return self::where(array(
			'toggl_id' => $toggl_id,
			'user_id'  => $user_id
		))->get()->first();
	}

	/**
	 * Get record based on name and User ID
	 */
	public static function getByName($name, $user_id)
	{
		return self::where(array(
			'name'    => $name,
			'user_id' => $user_id
		))->get()->first();
	}

	/**
	 * Get records based on User ID
	 */
	public static function getAllByUserID($user_id, $orderBy = 'name', $sort = 'ASC')
	{
		return self::where(array(
			'user_id'  => $user_id
		))->orderBy($orderBy, $sort)->get();
	}
}
