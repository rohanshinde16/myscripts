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

SELECT *  FROM `ttm7s_tjlms_lesson_track`
WHERE `lesson_id` = 919 AND `lesson_status` = 'incomplete' AND `user_id` IN (4478,7493,1173,6191,7123,4814,2469,5337,1075,5096,1074,4603,4215,4393,6851,6659,3882,6256,2463,5147,2745,1554,3509,4359,6206,4105,1081,1456,1711,1501,1874,3994,1055,2111,1562,2483,2283,3425,1527,5485,4052,2322,2712,1287,1517,5763,1321,3500,2185,1135,5967,5704,6067,4561,5741,3332,6055,4893,2828,3273,5559,2605,3872,1518,2848,1226,4983,3310,5523,5529,1573,1116,2708,4182,2546,3943,2636,2573,4956,2671,2541,2577,6137,3637,2751,4376,3192,5324,6044,2716,2631,2246,3604,2486)
ORDER BY `id`  DESC


UPDATE `ttm7s_tjlms_lesson_track` SET `lesson_staus` = 'completed'
WHERE `lesson_id` = 919 AND `lesson_status` = 'incomplete' AND `user_id` IN (4478,7493,1173,6191,7123,4814,2469,5337,1075,5096,1074,4603,4215,4393,6851,6659,3882,6256,2463,5147,2745,1554,3509,4359,6206,4105,1081,1456,1711,1501,1874,3994,1055,2111,1562,2483,2283,3425,1527,5485,4052,2322,2712,1287,1517,5763,1321,3500,2185,1135,5967,5704,6067,4561,5741,3332,6055,4893,2828,3273,5559,2605,3872,1518,2848,1226,4983,3310,5523,5529,1573,1116,2708,4182,2546,3943,2636,2573,4956,2671,2541,2577,6137,3637,2751,4376,3192,5324,6044,2716,2631,2246,3604,2486)
ORDER BY `id`  DESC
