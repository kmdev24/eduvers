<?php

namespace App\Http\Requests\Admin;

use App\Enums\TeacherType;
use App\Enums\UserRole;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\TrackStrand;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is already guarded by role:admin
    }

    public function rules(): array
    {
        /** @var User|null $user */
        $user = $this->route('user');

        return [
            'name'         => ['required', 'string', 'max:255'],
            'email'        => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'role'         => ['required', Rule::enum(UserRole::class)],
            'teacher_type' => ['nullable', Rule::requiredIf(fn () => $this->input('role') === UserRole::Teacher->value), Rule::enum(TeacherType::class)],
            'section_id'      => ['nullable', 'integer', Rule::exists('sections', 'id')],
            'grade_level_id'  => ['nullable', 'integer', Rule::exists('grade_levels', 'id')],
            'track_strand_id' => ['nullable', 'integer', Rule::exists('track_strands', 'id')],
            'password'     => [$user ? 'nullable' : 'required', 'string', Password::min(8)],
        ];
    }

    public function attributes(): array
    {
        return [
            'teacher_type'    => 'teacher type',
            'section_id'      => 'section',
            'grade_level_id'  => 'year level',
            'track_strand_id' => 'strand / course',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            /** @var User|null $user */
            $user = $this->route('user');

            // Don't let an admin or developer lock themselves out
            if ($user && $user->is($this->user()) && ! $user->role->isAdminLevel() && $this->input('role') !== UserRole::Admin->value && $this->input('role') !== UserRole::Developer->value) {
                $v->errors()->add('role', 'You cannot remove your own admin/developer access.');
            }

            if ($this->input('role') !== UserRole::Student->value) {
                return;
            }

            // Section capacity
            if ($this->filled('section_id')) {
                $section = Section::withCount('students')->find($this->integer('section_id'));
                $alreadyIn = $user && (int) $user->section_id === $section->id && $user->isStudent();

                if (! $alreadyIn && $section->students_count >= $section->capacity) {
                    $v->errors()->add('section_id', "{$section->name} is full ({$section->capacity} students).");
                }
            }

            // A College course can't be taken at a Senior High year level, and the other way round
            if ($this->filled('grade_level_id') && $this->filled('track_strand_id')) {
                $level  = GradeLevel::find($this->integer('grade_level_id'));
                $strand = TrackStrand::find($this->integer('track_strand_id'));

                if ($level && $strand && ! $strand->isCompatibleWith($level)) {
                    $v->errors()->add('track_strand_id', "{$strand->name} can't be taken in {$level->name}.");
                }
            }
        });
    }

    /** Validated data cleaned up for the chosen role. */
    public function payload(): array
    {
        $data = $this->validated();
        $role = UserRole::from($data['role']);

        $data['teacher_type'] = $role === UserRole::Teacher ? ($data['teacher_type'] ?? null) : null;
        $data['section_id']   = $role === UserRole::Student ? ($data['section_id'] ?? null) : null;

        if ($role !== UserRole::Student) {
            $data['grade_level_id'] = $data['track_strand_id'] = null;
        } elseif ($section = $data['section_id'] ? Section::find($data['section_id']) : null) {
            // A section is the stronger fact: the year level and strand follow it
            $data['grade_level_id']  = $section->grade_level_id;
            $data['track_strand_id'] = $section->track_strand_id;
        } else {
            $data['grade_level_id']  ??= null;
            $data['track_strand_id'] ??= null;
        }

        if (blank($data['password'] ?? null)) {
            unset($data['password']); // keep the current password on edit
        }

        return $data;
    }
}
