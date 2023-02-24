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


}
