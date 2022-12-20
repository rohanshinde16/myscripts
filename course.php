<?php
	public function getAllUsers()
	{
		$group = '10';
        $db    = Factory::getDBO();
		$query = $db->getQuery(true);
        $query->select('u.id');
        $query->from('`#__users` AS u');
        $db->setQuery($query);
        $allUser = $db->loadObjectList();

		$query = $db->getQuery(true);
        $query->select('distinct u.id');
        $query->from('`#__users` AS u');
        $query->join('INNER', '`#__user_usergroup_map` as uum ON u.id = uum.user_id');
        $query->where($db->qn('uum.group_id') . '=' . $db->q($group));
        $db->setQuery($query);
        $groupUser = $db->loadObjectList();

		$allUsers = array_column(json_decode(json_encode($allUser), true), 'id');
		$groupUsers = array_column(json_decode(json_encode($groupUser), true), 'id');

		$result = array_diff($allUsers, $groupUsers);
	}


	public function completeStatus()
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select(array('lt.user_id,ct.status,ct.no_of_lessons,ct.completed_lessons,ct.course_id'));
		$query->from('#__tjlms_lesson_track AS lt');
		$query->join('inner', '#__tjlms_lessons AS l ON l.id=lt.lesson_id');
		$query->join('inner', '#__tjlms_course_track AS ct ON ct.user_id=lt.user_id');
		$query->where('lt.lesson_id = ' . (int) 87);
		$query->where('lt.lesson_status = ' . $db->q('passed'));
		$query->where('ct.course_id = ' . $db->q('25'));
		$query->where('ct.status = ' . $db->q(''));
		$db->setQuery($query);
		$userData = $db->loadObjectList();

		foreach ($userData as $key => $data)
		{
			$query = $db->getQuery(true);
			$query->update($db->qn('#__tjlms_course_track','ct'));
			$query->set($db->qn('ct.completed_lessons') . '=' . $db->q((int) $data->no_of_lessons));
			$query->set($db->qn('ct.status') . '=' . $db->q('C'));
			$query->where($db->qn('ct.user_id') . '=' . $db->q((int) $data->user_id));
			$query->where($db->qn('ct.course_id') . '=' . $db->q((int) $data->course_id));
			$db->setQuery($query);
			$db->execute();
		}

	}
}
