<?php

namespace App\Notifications;

use App\Enums\EnrollmentStatus;
use App\Models\ClassEnrollment;

/** Tells a student that an admin approved or rejected their request to join a class. */
class EnrollmentDecisionNotification extends StudentActivityNotification
{
    public static function for(ClassEnrollment $enrollment): self
    {
        $enrollment->loadMissing(['offering.subject', 'offering.section', 'offering.teacher']);
        $class    = $enrollment->offering;
        $approved = $enrollment->status === EnrollmentStatus::Approved;

        return new self([
            'kind'     => 'enrollment',
            'icon'     => $approved ? 'check-circle' : 'x',
            'headline' => $approved ? 'Enrollment approved' : 'Enrollment not approved',
            'title'    => $class->subject->code.' · '.$class->subject->name,
            'excerpt'  => $approved
                ? 'You can now open its lessons and quizzes.'
                : 'You were not added to this class. Ask your teacher or the Registrar\'s Office if you think this is a mistake.',
            'context'  => $class->subject->code.' · '.$class->subject->name,
            'author'   => null,
            'route'    => $approved ? 'student.subjects.show' : 'student.enrollments.index',
            'params'   => $approved ? ['subject' => $class->subject_id] : [],
            'action'   => $approved ? 'Open subject' : 'See my applications',
            'details'  => array_filter([
                'Subject' => $class->subject->code.' – '.$class->subject->name,
                'Section' => $class->section?->name,
                'Teacher' => $class->teacher?->name,
            ]),
        ]);
    }

    protected function mailSubject(): string
    {
        return '[EduVers] '.$this->data['headline'].': '.$this->data['title'];
    }

    protected function mailIntro(): string
    {
        return self::md($this->data['route'] === 'student.subjects.show'
            ? 'An admin approved your enrollment in '.$this->data['context'].'.'
            : 'An admin did not approve your enrollment in '.$this->data['context'].'.');
    }
}
