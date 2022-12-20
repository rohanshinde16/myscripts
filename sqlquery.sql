//get course track

SELECT lt.id,lt.user_id,lt.score,lt.lesson_status,lt.attempt,l.id as lesson_id,ct.status,ct.no_of_lessons,ct.completed_lessons
FROM ttm7s_tjlms_lesson_track AS lt
INNER JOIN ttm7s_tjlms_lessons AS l ON l.id=lt.lesson_id
INNER JOIN ttm7s_tjlms_course_track AS ct ON ct.user_id=lt.user_id
WHERE lt.lesson_id = 87 AND lt.lesson_status = 'passed' AND ct.course_id = '25'

//update course track
UPDATE `yjaok_tjlms_course_track` AS ct
INNER JOIN `yjaok_tjlms_activities` AS act ON ct.course_id = act.element_id AND ct.user_id = act.actor_id
SET `ct`.`first_completion_date` = `act`.`added_time`
WHERE `act`.`action` = 'COURSE_COMPLETED'