<?php
/**
 * @package     Shika
 * @subpackage  com_tjlms
 *
 * @author      Techjoomla <extensions@techjoomla.com>
 * @copyright   Copyright (C) 2009 - 2020 Techjoomla. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\MVC\Controller\FormController;

/**
 * Course controller class.
 *
 * @since  1.0.0
 */
class TjlmsControllerCourse extends FormController
{
	/**
	 * Constructor.
	 *
	 * @see     JControllerLegacy
	 *
	 * @since   1.0.0
	 *
	 * @throws  Exception
	 */
	public function __construct()
	{
		parent::__construct();

		$this->view_list = 'courses';
		$this->text_prefix = 'COM_TJLMS_COURSE';
	}

	/**
	 * Method to save course information
	 *
	 * @param   string  $key     TO ADD
	 * @param   string  $urlVar  TO ADD
	 *
	 * @return    void
	 *
	 * @since    1.6
	 */
	public function save($key = null, $urlVar = null)
	{
		// Check for request forgeries.
		Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

		// Initialise variables.
		$app     = Factory::getApplication();
		$input   = $app->input;
		$model   = $this->getModel('Course', 'TjlmsModel');
		$table   = $model->getTable();
		$task    = $input->get('task');
		$checkin = property_exists($table, $table->getColumnAlias('checked_out'));

		// Get the user data.
		$data = $app->input->get('jform', array(), 'array');

		if ($task !== 'save2copy')
		{
			$data['image'] = $input->post->files->get('jform', array(), 'array');
		}

		// Validate the posted data.
		$form = $model->getForm();

		if (!$form)
		{
			/** @scrutinizer ignore-deprecated */ JError::raiseError(500, $model->getError());

			return false;
		}

		// Validate the posted data.
		$data = $model->validate($form, $data);

		// Validate extra data.
		$extraValidate = $model->validateExtra($data);

		// Check for errors.
		if ($data === false || $extraValidate === false)
		{
			// Get the validation messages.
			$errors = $model->getErrors();

			// Push up to three validation messages out to the user.
			for ($i = 0, $n = count($errors); $i < $n && $i < 3; $i++)
			{
				if ($errors[$i] instanceof Exception)
				{
					$app->enqueueMessage($errors[$i]->getMessage(), 'warning');
				}
				else
				{
					$app->enqueueMessage($errors[$i], 'warning');
				}
			}

			// Save the data in the session.
			// Tweak.
			$app->setUserState('com_tjlms.edit.course.data', $data);

			// Tweak *important
			$app->setUserState('com_tjlms.edit.course.id', $data['id']);

			// Redirect back to the edit screen.
			$id = (int) $app->getUserState('com_tjlms.edit.course.id');
			$this->setRedirect(Route::_('index.php?option=com_tjlms&view=course&layout=edit&id=' . $id, false));

			return false;
		}

		// Determine the name of the primary key for the data.
		if (empty($key))
		{
			$key = $table->getKeyName();
		}

		// To avoid data collisions the urlVar may be different from the primary key.
		if (empty($urlVar))
		{
			$urlVar = $key;
		}

		$recordId = $input->getInt($urlVar);

		// Populate the row id from the session.
		$data[$key] = $recordId;

		// The save2copy task needs to be handled slightly differently.
		if ($task === 'save2copy')
		{
			// Check-in the original row.
			if ($checkin && $model->checkin($data[$key]) === false)
			{
				// Check-in failed. Go back to the item and display a notice.
				$this->setError(Text::sprintf('JLIB_APPLICATION_ERROR_CHECKIN_FAILED', $model->getError()));
				$this->setMessage($this->getError(), 'error');

				$this->setRedirect(
					Route::_(
						'index.php?option=com_tjlms&view=course' . $this->getRedirectToItemAppend($recordId, $urlVar), false
					)
				);

				return false;
			}

			// Reset the ID, the multilingual associations and then treat the request as for Apply.
			$data[$key] = 0;
			$data['associations'] = array();
		}

		/* Attempt to save the data.
		$return = $model->save($data);
		Tweaked. */
		$return = $model->save($data);

		// Check for errors.
		if ($return === false)
		{
			/* Save the data in the session.
			$app->setUserState('com_tjlms.edit.event.data', $data);
			Tweak.*/
			$app->setUserState('com_tjlms.edit.course.data', $data);

			/* Tweak *important.
			$app->setUserState('com_tjlms.edit.event.id', $all_jform_data['id']);*/

			// Redirect back to the edit screen.
			$id = (int) $app->getUserState('com_tjlms.edit.course.id');
			$this->setMessage(Text::sprintf('COM_TJLMS_COURSE_ERROR_MSG_SAVE', $model->getError()), 'warning');
			$this->setRedirect(Route::_('index.php?option=com_tjlms&view=course&layout=edit&id=' . $id, false));

			return false;
		}

		$msg     = Text::_('COM_TJLMS_COURSE_CREATED_SUCCESSFULLY');
		$msgCopy = Text::_('COM_TJLMS_COURSE_COPIED_SUCCESSFULLY');

		$id = $input->get('id');

		if (empty($id))
		{
			$id = $return;
		}

		if ($task == 'apply')
		{
			// Set the record data in the session.
			$this->holdEditId('com_tjlms.edit.course', $id);
			$app->setUserState('com_tjlms.edit.course.data', null);

			$redirect = Route::_('index.php?option=com_tjlms&&view=course&layout=edit&id=' . $id, false);
			$app->redirect($redirect, $msg);
		}

		if ($task == 'save2new')
		{
			// Clear the id and data from the session.
			$this->releaseEditId('com_tjlms.edit.course', $id);
			$app->setUserState('com_tjlms.edit.course.data', null);

			$redirect = Route::_('index.php?option=com_tjlms&&view=course&layout=edit', false);
			$app->redirect($redirect, $msg);
		}

		if ($task == 'save2copy')
		{
			$redirect = Route::_('index.php?option=com_tjlms&&view=course&layout=edit&id=' . $return, false);
			$app->redirect($redirect, $msgCopy);
		}

		// Clear the profile id from the session.
		$app->setUserState('com_tjlms.edit.course.id', null);

		// Check in the profile.
		if ($return)
		{
			$model->checkin($return);
		}

		// Redirect to the list screen.
		$redirect = Route::_('index.php?option=com_tjlms&view=courses', false);
		$app->redirect($redirect, $msg);

		// Flush the data from the session.
		$app->setUserState('com_tjlms.edit.course.data', null);
	}

	/**
	 * Method override to check if you can add a new record.
	 *
	 * @param   array  $data  An array of input data.
	 *
	 * @return  boolean
	 *
	 * @since   1.6
	 */
	protected function allowAdd($data = array())
	{
		$user       = Factory::getUser();
		$categoryId = ArrayHelper::getValue($data, 'catid', $this->input->getInt('catid'), 'int');
		$allow      = null;

		if ($categoryId)
		{
			// If the category has been passed in the data or URL check it.
			$allow = $user->authorise('core.create', 'com_tjlms.category.' . $categoryId);
		}

		if ($allow === null)
		{
			// In the absense of better information, revert to the component permissions.
			return parent::allowAdd();
		}
		else
		{
			return $allow;
		}
	}

	/**
	 * Method override to check if you can edit an existing record.
	 *
	 * @param   array   $data  An array of input data.
	 * @param   string  $key   The name of the key for the primary key; default is id.
	 *
	 * @return  boolean
	 *
	 * @since   1.6
	 */
	protected function allowEdit($data = array(), $key = 'id')
	{
		$recordId = (int) isset($data[$key]) ? $data[$key] : 0;
		$user = Factory::getUser();

		// Zero record (id:0), return component edit permission by calling parent controller method
		if (!$recordId)
		{
			return parent::allowEdit($data, $key);
		}

		// Check edit on the record asset (explicit or inherited)
		if ($user->authorise('core.edit', 'com_tjlms.course.' . $recordId))
		{
			return true;
		}

		// Check edit own on the record asset (explicit or inherited)
		if ($user->authorise('core.create', 'com_tjlms.course.' . $recordId))
		{
			// Existing record already has an owner, get it
			$record = $this->getModel()->getItem($recordId);

			if (empty($record))
			{
				return false;
			}

			// Grant if current user is owner of the record
			return $user->get('id') == $record->created_by;
		}

		return false;
	}

	/**
	 * Redirect to manage training material page
	 *
	 * @return    void
	 *
	 * @since    1.1
	 */
	public function managematerial()
	{
		parent::cancel();
		$input = Factory::getApplication()->input;
		$courseid  = $input->get->get('id');
		$url = Route::_('index.php?option=com_tjlms&view=modules&course_id=' . $courseid, false);
		$this->setRedirect($url);
	}

	/**
	 * Method to cancel an edit.
	 *
	 * @param   string  $key  The name of the primary key of the URL variable.
	 *
	 * @return  boolean  True if access level checks pass, false otherwise.
	 *
	 * @since   12.2
	 */
	public function cancel($key = null)
	{
		Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

		parent::cancel();

		$this->setRedirect(
			Route::_(
				'index.php?option=' . $this->option . '&view=' . $this->view_list
				. $this->getRedirectToListAppend(), false
			)
		);

		return true;
	}

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


	public function generateCertificateWithLessontrack()
	{
		// Need to change in below fine for certificate issued date
		// SITE_NAME/components/com_tjlms/models/course.php

		// Lesson is passed but course is not completed and not get certificates.

		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select(array('lt.user_id,ct.status,ct.no_of_lessons,ct.completed_lessons,ct.course_id,lt.timeend'));
		$query->from('#__tjlms_lesson_track AS lt');
		$query->join('inner', '#__tjlms_lessons AS l ON l.id=lt.lesson_id');
		$query->join('inner', '#__tjlms_course_track AS ct ON ct.user_id=lt.user_id');
		$query->where('lt.lesson_id = ' . (int) 87);
		$query->where('lt.lesson_status = ' . $db->q("passed"));
		$query->where('ct.course_id = ' . $db->q("25"));
		$query->where('ct.status = ' . $db->q(""));
		$query->where('ct.user_id = ' . $db->q("2915"));
		$query->group($db->qn('ct.user_id'));
		$db->setQuery($query);
		$userData = $db->loadObjectList();

		echo "<pre>";print_r($userData);die("test");

		foreach ($userData as $key => $data)
		{
			$query = $db->getQuery(true);
			$query->update($db->qn('#__tjlms_course_track','ct'));
			$query->set($db->qn('ct.completed_lessons') . '=' . $db->q((int) $data->no_of_lessons));
			$query->set($db->qn('ct.status') . '=' . $db->q('C'));
			$query->set($db->qn('ct.timeend') . '=' . $db->q($data->timeend));
			$query->where($db->qn('ct.user_id') . '=' . $db->q((int) $data->user_id));
			$query->where($db->qn('ct.course_id') . '=' . $db->q((int) $data->course_id));
			$db->setQuery($query);
			$db->execute();

			JLoader::import('components.com_tjlms.models.course', JPATH_SITE);
			$tjlmsModelcourse = BaseDatabaseModel::getInstance('Course', 'TjlmsModel', array('ignore_request' => true));

			// Need to change in below fine for certificate issued date
			// SITE_NAME/components/com_tjlms/models/course.php
			$result = $tjlmsModelcourse->addCertEntry($data->course_id, $data->user_id, $data->timeend);
		}

	}

	public function generateCertificateWithOutLessontrack()
	{
		//Who are course completed but not in lesson track.
		// Need to change in below fine for certificate issued date
		// SITE_NAME/components/com_tjlms/models/course.php

		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select(array('ct.user_id,ct.status,ct.no_of_lessons,ct.completed_lessons,ct.course_id,en.enrolled_on_time'));
		$query->from('#__tjlms_course_track AS ct');
		$query->join('LEFT', '#__tjlms_enrolled_users AS en ON en.user_id=ct.user_id');
		$query->where($db->qn('ct.course_id') . '=' . $db->q("25"));
		$query->where($db->qn('ct.status') . '=' . $db->q("C"));
		$query->group($db->qn('ct.user_id'));
		$db->setQuery($query);
		$userData = $db->loadObjectList();

		foreach ($userData as $key => $data)
		{
			$db = JFactory::getDbo();
			$query = $db->getQuery(true);
			$query->select(array('lt.id'));
			$query->from('#__tjlms_lesson_track AS lt');
			$query->where('lt.lesson_id = ' . $db->q("87"));
			$query->where('lt.user_id = ' . $db->q($data->user_id));
			$db->setQuery($query);
			$userLtData = $db->loadObject();

			if (empty($userLtData->id))
			{
				$query = $db->getQuery(true);
				$query->update($db->qn('#__tjlms_course_track','ct'));
				$query->set($db->qn('ct.completed_lessons') . '=' . $db->q((int) $data->no_of_lessons));
				$query->set($db->qn('ct.status') . '=' . $db->q('C'));
				$query->set($db->qn('ct.timeend') . '=' . $db->q($data->enrolled_on_time));
				$query->where($db->qn('ct.user_id') . '=' . $db->q((int) $data->user_id));
				$query->where($db->qn('ct.course_id') . '=' . $db->q((int) $data->course_id));
				$db->setQuery($query);
				$db->execute();

				JLoader::import('components.com_tjlms.models.course', JPATH_SITE);
				$tjlmsModelcourse = BaseDatabaseModel::getInstance('Course', 'TjlmsModel', array('ignore_request' => true));

				// Need to change in below fine for certificate issued date
				// SITE_NAME/components/com_tjlms/models/course.php

				$result = $tjlmsModelcourse->addCertEntry($data->course_id, $data->user_id, $data->enrolled_on_time);

				print_r($result);
				die("here");
			}
		}
	}

	public function generateCertificateIfCourseCompleted()
	{
		//Who are course completed but not in lesson track.
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select(array('ct.user_id,ct.status,ct.no_of_lessons,ct.completed_lessons,ct.course_id,en.enrolled_on_time'));
		$query->from('#__tjlms_course_track AS ct');
		$query->join('LEFT', '#__tjlms_enrolled_users AS en ON en.user_id=ct.user_id');
		$query->where($db->qn('ct.course_id') . '=' . $db->q("22"));
		$query->where($db->qn('ct.status') . '=' . $db->q("C"));
		$query->group($db->qn('ct.user_id'));
		$db->setQuery($query);
		$userData = $db->loadObjectList();

		echo "<pre>";print_r($userData);die("here");

		JLoader::import('components.com_tjlms.models.course', JPATH_SITE);
		$tjlmsModelcourse = BaseDatabaseModel::getInstance('Course', 'TjlmsModel', array('ignore_request' => true));

		foreach ($userData as $key => $data)
		{
			$certId = $tjlmsModelcourse->checkCertificateIssued($data->course_id, $data->user_id);

			if(empty($certId[0]->id))
			{
				$result = $tjlmsModelcourse->addCertEntry($data->course_id, $data->user_id, $data->enrolled_on_time);
			}
		}
	}

	public function completeCourseStatuswithoutlessontrack()
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select(array('ct.user_id,ct.status,ct.no_of_lessons,ct.completed_lessons,ct.course_id,en.enrolled_on_time'));
		$query->from('#__tjlms_course_track AS ct');
		$query->join('LEFT', '#__tjlms_enrolled_users AS en ON en.user_id=ct.user_id');
		$query->where($db->qn('ct.course_id') . '=' . $db->q("77"));
		$query->where($db->qn('ct.user_id') . ' IN (' . '522,539,103,1089,1449,2197' . ')');
		$query->group($db->qn('ct.user_id'));

		$db->setQuery($query);
		$userData = $db->loadObjectList();

		echo "<pre>";print_r($userData);die("here");

		foreach ($userData as $data)
		{
			if($data->status != "C")
			{
				$query = $db->getQuery(true);
				$query->update($db->qn('#__tjlms_course_track','ct'));
				$query->set($db->qn('ct.completed_lessons') . '=' . $db->q((int) $data->no_of_lessons));
				$query->set($db->qn('ct.status') . '=' . $db->q('C'));
				$query->set($db->qn('ct.timeend') . '=' . $db->q($data->enrolled_on_time));
				$query->where($db->qn('ct.user_id') . '=' . $db->q((int) $data->user_id));
				$query->where($db->qn('ct.course_id') . '=' . $db->q((int) $data->course_id));
				$db->setQuery($query);

				//echo $query->dump();die("here");
				$db->execute();
			}
		}

	}
	
	public function userSendEmail()
    {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__users'))
            ->set($db->quoteName('sendEmail') . ' = 0');
        $db->setQuery($query);
        $db->execute();
    }

	public function updateDuplicateAttendeesEmail()
	{
		$db = Factory::getDbo();

		$query = $db->getQuery(true);

		$query->select($db->quoteName(array(
			'attendees.id',
			'attendees.owner_id',
			'attendees.owner_email',
			'users.email'
		)));
		$query->from($db->quoteName('#__jticketing_attendees', 'attendees'));
		$query->join('INNER', $db->quoteName('#__users', 'users') . ' ON ' . $db->quoteName('attendees.owner_id') . ' = ' . $db->quoteName('users.id'));
		$query->where($db->quoteName('attendees.owner_email') . ' IN (SELECT ' . $db->quoteName('owner_email') . ' FROM ' . $db->quoteName('#__jticketing_attendees') . ' GROUP BY ' . $db->quoteName('owner_email') . ' HAVING COUNT(DISTINCT ' . $db->quoteName('owner_id') . ') > 1)');
		$query->where($db->quoteName('attendees.owner_email') . ' <> ' . $db->quoteName('users.email'));
		$query->order('attendees.owner_email, attendees.owner_id');

		echo $query->dump();die;

		$db->setQuery($query);

		$results = $db->loadAssocList();

		foreach ($results as $row) {

			if(!empty($row['email']))
			{
				$query = $db->getQuery(true)
				->update($db->quoteName('#__jticketing_attendees'))
				->set($db->quoteName('owner_email') . ' = ' . $db->quote($row['email']))
				->where($db->quoteName('id') . ' = ' . (int)$row['id']);		
				$db->setQuery($query);
				$db->execute();
			}
		}
	}

	public function mergeUsers()
	{
		// Get Joomla DB and input
		$app = \Joomla\CMS\Factory::getApplication();
		$db    = \Joomla\CMS\Factory::getDbo();
		$input = \Joomla\CMS\Factory::getApplication()->input;

		// Get user IDs from URL
		$userFrom = (int) $input->getInt('user_from');
		$userTo   = (int) $input->getInt('user_to');

		$csvPath = JPATH_SITE . '/components/com_tjlms/controllers/BDO_users.csv';

		if (!file_exists($csvPath)) {
            $app->enqueueMessage("CSV file not found: $csvPath", 'error');
            return;
        }

		$duplicateUserIds = [];
		$originalUserIds = [];

		if (($handle = fopen($csvPath, "r")) !== false) {
            $header = fgetcsv($handle); // read header
            while (($data = fgetcsv($handle)) !== false) {
                $row = array_combine($header, $data);

                $duplicateEmail = trim($row['duplicateEmail']);
                $originalEmail = trim($row['originalEmail']);

				if (!$duplicateEmail || !$originalEmail) {
                    continue;
                }

				$duplicateEmailUserData = $this->getUser($duplicateEmail);
				$originalEmailUserData = $this->getUser($originalEmail);

				$rows = $this->mergeTjlmsUserLMSData($duplicateEmailUserData->id, $originalEmailUserData->id);
				$rows = $this->mergeJTUserLMSData($duplicateEmailUserData->id, $originalEmailUserData->id, $originalEmailUserData->email);
				$rows = $this->mergeJlikeUserLMSData($duplicateEmailUserData->id, $originalEmailUserData->id);
				// print_r($dupicateEmailUserData->email);echo " : "; print_r($originalEmailUserData->email);echo "\n";

				$duplicateUserIds[] = $duplicateEmailUserData->id;
				$originalUserIds[] = $originalEmailUserData->id;
            }
            fclose($handle);
			print_r("User data merged successfully.\n");
			echo "<pre>";print_r($duplicateUserIds);echo "\n";
			echo "<pre>";print_r($originalUserIds);;
			$app->enqueueMessage("User data merged successfully.", 'message');
        } else {
            $app->enqueueMessage("Unable to open CSV file", 'error');
        }
	}

	/**
	 * Returns userid if a user exists
	 *
	 * @param   string  $email  The email to search on.
	 *
	 * @return  Object
	 *
	 * @since   11.1
	 */
	public function getUser($email)
	{
		$this->_db = Factory::getDbo();
		$query = $this->_db->getQuery(true);
		$query->select('u.*');
		$query->from($this->_db->qn('#__users', 'u'));
		$query->where($this->_db->qn('u.email') . '=' . $this->_db->q($email));
		$this->_db->setQuery($query);

		return $this->_db->loadobject();
	}

	/**
	 * Returns userid if a user exists
	 *
	 * @param   string  $email  The email to search on.
	 *
	 * @return  Object
	 *
	 * @since   11.1
	 */
	public function mergeTjlmsUserLMSData($user1Id, $user2Id)
	{
		$db = \Joomla\CMS\Factory::getDbo();

		// Step 1: Deactivate enrollments where both users are enrolled in same course
		$query = $db->getQuery(true)
			->select('a.course_id')
			->from($db->quoteName('#__tjlms_enrolled_users', 'a'))
			->join('INNER', $db->quoteName('#__tjlms_enrolled_users', 'b') . ' ON a.course_id = b.course_id')
			->where('a.user_id = ' . (int)$user1Id)
			->where('b.user_id = ' . (int)$user2Id);
		$db->setQuery($query);
		$commonCourses = $db->loadColumn();

		if (!empty($commonCourses)) {
			$query = $db->getQuery(true)
				->update($db->quoteName('#__tjlms_enrolled_users'))
				->set('state = 0')
				->where('user_id = ' . (int)$user1Id)
				->where('course_id IN (' . implode(',', array_map('intval', $commonCourses)) . ')');
			$db->setQuery($query)->execute();
		}

		// Step 2: Get all course_ids user2 already has in enrolled_users and course_track
		$existingCourses = [];

		$migrationTables = [
			['table' => '#__tjlms_enrolled_users', 'user_field' => 'user_id', 'course_field' => 'course_id'],
			['table' => '#__tjlms_course_track', 'user_field' => 'user_id', 'course_field' => 'course_id'],
		];

		foreach ($migrationTables as $tbl) {
			$query = $db->getQuery(true)
				->select($db->quoteName($tbl['course_field']))
				->from($db->quoteName($tbl['table']))
				->where($db->quoteName($tbl['user_field']) . ' = ' . (int)$user2Id);
			$db->setQuery($query);
			$courses = $db->loadColumn();
			$existingCourses[$tbl['table']] = !empty($courses) ? array_map('intval', $courses) : [];
		}

		// Step 3: Safely update only non-conflicting course entries
		foreach ($migrationTables as $tbl) {
			$skipCourses = $existingCourses[$tbl['table']];
			$skipClause = !empty($skipCourses)
				? ' AND ' . $tbl['course_field'] . ' NOT IN (' . implode(',', $skipCourses) . ')'
				: '';

			$query = $db->getQuery(true)
				->update($db->quoteName($tbl['table']))
				->set($db->quoteName($tbl['user_field']) . ' = ' . (int)$user2Id)
				->where($db->quoteName($tbl['user_field']) . ' = ' . (int)$user1Id . $skipClause);
			$db->setQuery($query)->execute();
		}

		// Step 4: Migrate lesson_track (no course_id, so transfer all)
			$query = $db->getQuery(true)
				->select(['id', 'lesson_id', 'attempt']) // Add any other fields used in UNIQUE KEY
				->from($db->quoteName('#__tjlms_lesson_track'))
				->where('user_id = ' . (int)$user1Id);
			$db->setQuery($query);
			$lessonTracks = $db->loadObjectList();

			foreach ($lessonTracks as $track) {
				$checkQuery = $db->getQuery(true)
					->select('id')
					->from($db->quoteName('#__tjlms_lesson_track'))
					->where('user_id = ' . (int)$user2Id)
					->where('lesson_id = ' . (int)$track->lesson_id)
					->where('attempt = ' . (int)$track->attempt);
				$db->setQuery($checkQuery);
				$exists = $db->loadResult();

				if (!$exists) {
					$updateQuery = $db->getQuery(true)
						->update($db->quoteName('#__tjlms_lesson_track'))
						->set('user_id = ' . (int)$user2Id)
						->where('id = ' . (int)$track->id);
					$db->setQuery($updateQuery)->execute();
				}
			}

		// Step 5: Migrate selected activities only if not duplicate COURSE_COMPLETED
		$allowedActions = ['COURSE_COMPLETED', 'ENROLL', 'ATTEMPT', 'ATTEMPT_END'];

		// Get existing COURSE_COMPLETED entries for user2
		$query = $db->getQuery(true)
			->select('element_id')
			->from($db->quoteName('#__tjlms_activities'))
			->where('actor_id = ' . (int)$user2Id)
			->where("action = 'COURSE_COMPLETED'");
		$db->setQuery($query);
		$user2Completed = array_map('intval', $db->loadColumn());

		// Build condition
		$actionList = implode("','", array_map([$db, 'escape'], $allowedActions));

		$activityConditions = [];
		$activityConditions[] = "action IN ('$actionList')";
		$activityConditions[] = 'actor_id = ' . (int)$user1Id;

		if (!empty($user2Completed)) {
			$activityConditions[] = '(action != "COURSE_COMPLETED" OR element_id NOT IN (' . implode(',', $user2Completed) . '))';
		}

		$query = $db->getQuery(true)
			->update($db->quoteName('#__tjlms_activities'))
			->set('actor_id = ' . (int)$user2Id)
			->where(implode(' AND ', $activityConditions));
		$db->setQuery($query)->execute();

		// Step 6: Migrate certificates (skip if already issued to user2 for same course)
		$query = $db->getQuery(true)
			->select('client_id')
			->from($db->quoteName('#__tj_certificate_issue'))
			->where('user_id = ' . (int)$user2Id);
		$db->setQuery($query);
		$user2CertCourses = array_map('intval', $db->loadColumn());

		$certExclude = !empty($user2CertCourses)
			? ' AND client_id NOT IN (' . implode(',', $user2CertCourses) . ')'
			: '';

		$query = $db->getQuery(true)
			->update($db->quoteName('#__tj_certificate_issue'))
			->set('user_id = ' . (int)$user2Id)
			->where('user_id = ' . (int)$user1Id . $certExclude);
		$db->setQuery($query)->execute();

	}

	/**
	 * Returns userid if a user exists
	 *
	 * @param   string  $email  The email to search on.
	 *
	 * @return  Object
	 *
	 * @since   11.1
	 */
	public function mergeJTUserLMSData($user1Id, $user2Id, $user2Email)
	{
		$db = \Joomla\CMS\Factory::getDbo();

		// Get event_ids for user2
		$query = $db->getQuery(true)
		->select('event_id')
		->from($db->quoteName('#__jticketing_attendees'))
		->where('owner_id = ' . (int)$user2Id);
		$db->setQuery($query);
		$user2Events = array_map('intval', $db->loadColumn());


		$eventExclude = !empty($user2Events)
		? ' AND event_id NOT IN (' . implode(',', $user2Events) . ')'
		: '';

		$query = $db->getQuery(true)
		->update($db->quoteName('#__jticketing_attendees'))
		->set('owner_id = ' . (int)$user2Id)
		->where('owner_id = ' . (int)$user1Id . $eventExclude);
		// echo $query->dump();die;
		$db->setQuery($query)->execute();

		// Step 4: Get all attendee records for user1
			$query = $db->getQuery(true)
			->select(['id', 'event_id'])
			->from($db->quoteName('#__jticketing_attendees'))
			->where('owner_id = ' . (int) $user1Id);
		$db->setQuery($query);
		$user1Attendees = $db->loadObjectList();

		foreach ($user1Attendees as $attendee)
		{
			$oldAttendeeId = (int) $attendee->id;
			$eventId = (int) $attendee->event_id;
	
			// Get corresponding attendee ID for user2 for same event
			$query = $db->getQuery(true)
				->select('id')
				->from($db->quoteName('#__jticketing_attendees'))
				->where('owner_id = ' . (int) $user2Id)
				->where('event_id = ' . $eventId);
			$db->setQuery($query);
			$newAttendeeId = (int) $db->loadResult();
	
			if ($newAttendeeId) {
				// Check if user2 already has a check-in for this event
				$query = $db->getQuery(true)
					->select('COUNT(*)')
					->from($db->quoteName('#__jticketing_checkindetails'))
					->where('attendee_id = ' . $newAttendeeId)
					->where('eventid = ' . $eventId);
				$db->setQuery($query);
				$exists = (int) $db->loadResult();
	
				if ($exists === 0) {
					// Update check-in records that point to old attendee_id
					$query = $db->getQuery(true)
						->update($db->quoteName('#__jticketing_checkindetails'))
						->set([
							'attendee_id = ' . $newAttendeeId,
							'attendee_email = ' . $db->quote($user2Email)
						])
						->where('attendee_id = ' . $oldAttendeeId)
						->where('eventid = ' . $eventId);
					$db->setQuery($query)->execute();
				}
			}
		}

		// Step 5: Update jticketing_attendancedetails
		foreach ($user1Attendees as $attendee) {
			$oldAttendeeId = (int) $attendee->id;
			$eventId = (int) $attendee->event_id;

			// Get new attendee id for user2 for same event
			$query = $db->getQuery(true)
				->select('id')
				->from($db->quoteName('#__jticketing_attendees'))
				->where('owner_id = ' . (int) $user2Id)
				->where('event_id = ' . $eventId);
			$db->setQuery($query);
			$newAttendeeId = (int) $db->loadResult();

			if ($newAttendeeId) {
				// Check if user2 already has attendance entry for this event
				$query = $db->getQuery(true)
					->select('COUNT(*)')
					->from($db->quoteName('#__jticketing_attendancedetails'))
					->where('attendee_id = ' . $newAttendeeId)
					->where('userid = ' . $user2Id);
				$db->setQuery($query);
				$exists = (int) $db->loadResult();

				if ($exists === 0) {
					// Update records from old attendee_id → new one
					$query = $db->getQuery(true)
						->update($db->quoteName('#__jticketing_attendancedetails'))
						->set([
							'attendee_id = ' . $newAttendeeId,
							'userid = ' . (int) $user2Id
						])
						->where('attendee_id = ' . $oldAttendeeId)
						->where('userid = ' . $user1Id);
					$db->setQuery($query)->execute();
				}
			}
		}
	}

	/**
	 * Returns userid if a user exists
	 *
	 * @param   string  $email  The email to search on.
	 *
	 * @return  Object
	 *
	 * @since   11.1
	 */
	public function mergeJlikeUserLMSData($user1Id, $user2Id)
	{
		$db = \Joomla\CMS\Factory::getDbo();
		// Step 6: Update ttm7s_jlike_todos
		$query = $db->getQuery(true)
		->select(['id', 'content_id'])
		->from($db->quoteName('#__jlike_todos'))
		->where('assigned_to = ' . (int) $user1Id);
		$db->setQuery($query);
		$todos = $db->loadObjectList();

		foreach ($todos as $todo) {
			$todoId = (int) $todo->id;
			$contentId = (int) $todo->content_id;

			// Check if user2 already has this content_id assigned
			$query = $db->getQuery(true)
				->select('COUNT(*)')
				->from($db->quoteName('#__jlike_todos'))
				->where('assigned_to = ' . (int) $user2Id)
				->where('content_id = ' . $contentId);
			$db->setQuery($query);
			$exists = (int) $db->loadResult();

			if ($exists === 0) {
				// Safe to update
				$query = $db->getQuery(true)
					->update($db->quoteName('#__jlike_todos'))
					->set('assigned_to = ' . (int) $user2Id)
					->where('id = ' . $todoId);
				$db->setQuery($query)->execute();
			}
		}

		// Final Step: Block user1 account in users table
		$query = $db->getQuery(true)
			->update($db->quoteName('#__users'))
			->set($db->quoteName('block') . ' = 1')
			->where($db->quoteName('id') . ' = ' . (int)$user1Id);
		$db->setQuery($query)->execute();
	}
}
