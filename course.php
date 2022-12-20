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

	public function getAssociatedFiles()
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('m.id as mediaId, m.source, m.org_filename, af.lesson_id');
		$query->from($db->qn('#__tjlms_media', 'm'));
		$query->join('INNER', $db->qn('#__tjlms_associated_files', 'af') . ' ON (' . $db->qn('af.media_id') . ' = ' . $db->qn('m.id') . ')');
		$query->join('INNER', $db->qn('#__tjlms_lessons', 'l') . ' ON (' . $db->qn('l.id') . ' = ' . $db->qn('af.lesson_id') . ')');
		$query->where($db->qn('m.format') . '=' . $db->q('associate'));
		$db->setQuery($query);

		$lessonMedia = $db->loadObjectList();

		$fp = fopen(JPATH_ROOT . '/media/com_tjlms' . "/associateFiles.txt","wb");

		foreach ($lessonMedia as $media)
		{
			if (file_exists(JPATH_ROOT . '/media/com_tjlms/lessons/'. $media->source))
			{
				$content = $media->source . " is present " . $media->org_filename . " Lesson Id " . $media->lesson_id . "\n";
				fwrite($fp,$content);
			}
			elseif (file_exists(JPATH_ROOT . '/media/com_tjlms/bkp_lesson/lessons/'. $media->source))
			{
				$content = $media->source . " is present in backup" . "\n";
				fwrite($fp,$content);
			}
			else
			{
				$content = $media->source . " is absent => original file name is ". $media->org_filename . "Lesson Id " . $media->lesson_id . "\n";
				fwrite($fp,$content);
			}
		}

		fclose($fp);
	}
}
