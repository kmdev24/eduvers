<?php

/*
| The student assistant's knowledge.
|
| Each topic is matched against what the student types: the more keywords that
| appear, the better the match. The best match wins; if nothing matches, the
| assistant says what it can help with instead of guessing.
|
| To teach it something new, add a topic. No code changes needed.
|   keywords - lowercase words to look for (stems are fine: "grade" matches "grades")
|   answer   - what it replies, in plain language
|   route    - optional named route for a button under the answer
|   label    - the button's text
*/

return [

    // Answers come only from this list, so the assistant has no way to help with
    // quiz questions themselves - it simply does not know any subject material.
    'topics' => [
        [
            'keywords' => ['grade', 'score', 'result', 'mark', 'rating'],
            'answer'   => 'Your grades are under My Grades. It lists every quiz you have taken, with your score and whether you passed. Older terms stay there too, so you can look back at past semesters.',
            'route'    => 'student.grades',
            'label'    => 'Open My Grades',
        ],
        [
            'keywords' => ['subject', 'class', 'course', 'enrolled', 'enrol'],
            'answer'   => 'My Subjects shows every subject you take this term, with the teacher for each. Open a subject to see its lessons and quizzes. Subjects appear automatically once you are enrolled in a section - you do not add them yourself.',
            'route'    => 'student.subjects.index',
            'label'    => 'Open My Subjects',
        ],
        [
            // No "answer" here on purpose: it is the word in "what is the answer to
            // question 3", which must never be treated as a request to navigate.
            'keywords' => ['quiz', 'exam', 'test'],
            'answer'   => 'Quizzes lists the quizzes your teacher has published, and which ones you have already taken. You can take each quiz once, so answer every question before you submit.',
            'route'    => 'student.quizzes.index',
            'label'    => 'Open Quizzes',
        ],
        [
            'keywords' => ['lesson', 'module', 'material', 'reading', 'video', 'file', 'download'],
            'answer'   => 'Lessons live inside each subject. Open My Subjects, choose the subject, and you will see its lessons with any files or videos your teacher attached.',
            'route'    => 'student.subjects.index',
            'label'    => 'Open My Subjects',
        ],
        [
            'keywords' => ['announcement', 'news', 'notice', 'post'],
            'answer'   => 'Announcements from your teachers and the school appear on your Dashboard, and the bell at the top shows new ones as they arrive.',
            'route'    => 'student.dashboard',
            'label'    => 'Open Dashboard',
        ],
        [
            'keywords' => ['notification', 'bell', 'alert'],
            'answer'   => 'The bell at the top right shows new lessons, quizzes and announcements. A number on it means something is unread. Click it to see them, or open Notifications for the full list.',
            'route'    => 'notifications.index',
            'label'    => 'Open Notifications',
        ],
        [
            'keywords' => ['password', 'forgot', 'reset', 'change', 'login', 'signin', 'sign'],
            'answer'   => 'To change your password, open My Profile. If you cannot sign in at all, use "Forgot password?" on the sign-in page and EduVers will email you a link that works for 60 minutes.',
            'route'    => 'profile.edit',
            'label'    => 'Open My Profile',
        ],
        [
            'keywords' => ['apply', 'join', 'another', 'section', 'add', 'request'],
            'answer'   => 'Use "Apply for a subject" to ask to join a class taught in another section. An admin has to approve it, and you will get a notification with the decision. Most of the time you do not need this: every subject of your own strand is already in your list.',
            'route'    => 'student.enrollments.index',
            'label'    => 'Open Apply for a subject',
        ],
        [
            'keywords' => ['profile', 'email', 'name', 'account', 'photo'],
            'answer'   => 'My Profile holds your name, email and password. If your name or email is wrong, ask the Registrar\'s Office - only an admin can change those.',
            'route'    => 'profile.edit',
            'label'    => 'Open My Profile',
        ],
        [
            'keywords' => ['signed', 'logout', 'logged', 'out', 'idle', 'timeout', 'kicked'],
            'answer'   => 'EduVers signs you out after 30 minutes without activity, so an unattended computer does not stay open. Just sign in again - nothing you submitted is lost.',
        ],
        [
            'keywords' => ['teacher', 'who', 'contact', 'ask'],
            'answer'   => 'Each subject shows its teacher on My Subjects and at the top of the subject page. For anything about the lessons themselves, your teacher is the right person to ask.',
            'route'    => 'student.subjects.index',
            'label'    => 'Open My Subjects',
        ],
        [
            'keywords' => ['term', 'semester', 'period'],
            'answer'   => 'EduVers follows the school calendar, so My Subjects and Quizzes show the term that is running now. My Grades is the exception - it keeps every term, so your past results never disappear.',
            'route'    => 'student.grades',
            'label'    => 'Open My Grades',
        ],
    ],

    /*
    | Optional: a free AI service answers anything the list above does not cover.
    | Leave the key empty and the assistant simply says what it can help with -
    | everything above still works with no internet and no account.
    |
    | Google AI Studio gives a free key: https://aistudio.google.com/apikey
    */
    'ai' => [
        'key'      => env('ASSISTANT_AI_KEY', ''),
        'model'    => env('ASSISTANT_AI_MODEL', 'gemini-2.0-flash'),
        'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models',
        'timeout'  => 12,
    ],

];
