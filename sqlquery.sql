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


SELECT id AS main_id, title FROM `ttm7s_jticketing_events`
WHERE `id` = 4518 OR `parent_event` = 4518

UNION

SELECT id AS sub_id, title FROM `ttm7s_jticketing_events`
WHERE `parent_event` IN (
    SELECT id FROM `ttm7s_jticketing_events`
    WHERE `id` = 4518 OR `parent_event` = 4518
)
GROUP BY id, title;

=======================================
SELECT 
    u.name AS UserOfName,
    u.email AS userEmail,
    c.title AS CourseName,
    CASE 
        WHEN track.status = 'I' THEN 'Incomplete'
        WHEN track.status = 'C' THEN 'Complete'
        ELSE ''
    END AS CourseStatus,
    tjm.title AS Enterprsie,
    eu.enrolled_on_time AS EnrollmentDate,
    track.timeend AS CompletionDate
FROM 
    zdklo_users AS u
JOIN 
    zdklo_tjlms_enrolled_users AS eu ON u.id = eu.user_id
JOIN 
    zdklo_tjlms_courses AS c ON c.id = eu.course_id
JOIN 
    zdklo_tjlms_course_track AS track ON track.user_id = eu.user_id AND track.course_id = eu.course_id

INNER JOIN
    zdklo_hierarchy_users AS hu ON u.id=hu.user_id

LEFT JOIN
    zdklo_tjmultiagency_multiagency AS tjm ON tjm.manager_id=hu.reports_to
WHERE 
    u.email IN (
        'homa.ms2019@gmail.com',
        'asmatanweer@gmail.com');

===========================================

du -hs * | sort -hr

vowel-prod login ... select site ... biotech13

2. admin/compo/com akeeb/backup .. delete

3. /media/com tjlms/lesson ... delet *.zip

4, go to ~/AmazonEFSCommon/efs

5. sudo mkdir biotech13.vowel.work

6. sudo cp -R /var/www/biotech13.vowel.work/* .

... files moved to EFS

7. log into MASTER_PROD_EFS .. (open firewall)

8. std APAChe changes ...

server alias biotech13-temp.vowel.work


9. rollout .... DNS changes after many sites
(edited)


Rohan
  10:11 AM
SELECT *
FROM `ttm7s_jticketing_attendees`
WHERE `owner_email` IN (
    SELECT `owner_email`
    FROM `ttm7s_jticketing_attendees`
    GROUP BY `owner_email`
    HAVING COUNT(DISTINCT `owner_id`) > 1
)
ORDER BY `owner_email`,`owner_id`


Rohan
  2:41 PM
SELECT attendees.id, attendees.owner_id,attendees.owner_email, users.email AS user_email
FROM `ttm7s_jticketing_attendees` AS attendees
LEFT JOIN `ttm7s_users` AS users ON attendees.owner_id = users.id
WHERE attendees.owner_email IN (
    SELECT `owner_email`
    FROM `ttm7s_jticketing_attendees`
    GROUP BY `owner_email`
    HAVING COUNT(DISTINCT `owner_id`) > 1
)
ORDER BY attendees.owner_email, attendees.owner_id;














