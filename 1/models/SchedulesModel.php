<?php 
namespace Plugins\RepostPro;

// Disable direct access
if (!defined('APP_VERSION')) 
    die("Yo, what's up?");

/**
 * Schedules model
 *
 * @version 1.0
 * @author Onelab <hello@onelab.co> 
 * 
 */
class SchedulesModel extends \DataList
{	
	/**
	 * Initialize
	 */
	public function __construct()
	{
		$this->setQuery(\DB::table(TABLE_PREFIX."repost_pro_schedule"));
	}
}
