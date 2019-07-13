<?php
/*********************************************************\
| Show the roadmap                                        |
| ~~~~~~~~~~~~~~~~~~~                                     |
\*********************************************************/

if (!defined('IN_FS')) {
    die('Do not access this file directly.');
}

if (!$proj->id) {
    Flyspray::show_error(25);
}

if ((!$user->isAnon() && !$user->perms('view_roadmap')) || ($user->isAnon() && $proj->prefs['others_viewroadmap'] !=1)) {
	# better set redirect to false to avoid endless loops
	Flyspray::show_error(28, false);
} else{

	if($proj->prefs['use_effort_tracking']){
	    require_once BASEDIR . '/includes/class.effort.php';
	}


	$page->setTitle($fs->prefs['page_title'] . L('roadmap'));

	// Get milestones
	$milestones = $db->query('SELECT   version_id, version_name
                          FROM     {list_version}
                          WHERE    (project_id = ? OR project_id=0) AND version_tense = 3
                          ORDER BY list_position ASC',
		array($proj->id));

	$data = array();

while ($row = $db->fetchRow($milestones)) {
    // Get all tasks related to a milestone
    $all_tasks = $db->query('SELECT  percent_complete, is_closed
                             FROM    {tasks}
                             WHERE   closedby_version = ? AND project_id = ?',
        array($row['version_id'], $proj->id));
    $all_tasks = $db->fetchAllArray($all_tasks);

    $percent_complete = 0;
    foreach($all_tasks as $task) {
        if($task['is_closed']) {
            $percent_complete += 100;
        } else {
            $percent_complete += $task['percent_complete'];
        }
    }
    $percent_complete = round($percent_complete/max(count($all_tasks), 1));

    $tasks = $db->query('SELECT task_id, item_summary, detailed_desc, item_status, task_severity, task_priority, task_type, mark_private, opened_by, content, task_token, t.project_id,estimated_effort
                           FROM {tasks} t
                      LEFT JOIN {cache} ca ON (t.task_id = ca.topic AND ca.type = \'rota\' AND t.last_edited_time <= ca.last_updated)
                          WHERE closedby_version = ? AND t.project_id = ? AND is_closed = 0',
        array($row['version_id'], $proj->id));
    $tasks = $db->fetchAllArray($tasks);

    $count = count($tasks);
    for ($i = 0; $i < $count; $i++) {
        if (!$user->can_view_task($tasks[$i])) {
            unset($tasks[$i]);
        }
    }

    $data[] = array('id' => $row['version_id'], 'open_tasks' => $tasks, 'percent_complete' => $percent_complete,
        'all_tasks' => $all_tasks, 'name' => $row['version_name']);
} # end while

	if (Get::val('txt')) {
	    $page = new FSTpl;
	    header('Content-Type: text/plain; charset=UTF-8');
	    $page->uses('data', 'page');
	    $page->display('roadmap.text.tpl');
	    exit();
	} else {
	    $page->uses('data', 'page');
	    $page->pushTpl('roadmap.tpl');
	}

} # end if allowed roadmap view
?>
