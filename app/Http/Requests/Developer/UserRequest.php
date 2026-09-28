<?php

namespace App\Http\Requests\Developer;

use App\Enums\TeacherType;
use App\Enums\UserRole;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is already guarded by role:developer
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
            'section_id'   => ['nullable', 'integer', Rule::exists('sections', 'id')],
            'password'     => [$user ? 'nullable' : 'required', 'string', Password::min(8)],
        ];
    }

    public function attributes(): array
    {
        return [
            'teacher_type' => 'teacher type',
            'section_id'   => 'section',
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

            // Don't let a developer lock themselves out
            if ($user && $user->is($this->user()) && $this->input('role') !== UserRole::Developer->value) {
                $v->errors()->add('role', 'You cannot remove your own developer access.');
            }

            // Section capacity for students
            if ($this->input('role') === UserRole::Student->value && $this->filled('section_id')) {
                $section = Section::withCount('students')->find($this->integer('section_id'));
                $alreadyIn = $user && (int) $user->section_id === $section->id && $user->isStudent();

                if (! $alreadyIn && $section->students_count >= $section->capacity) {
                    $v->errors()->add('section_id', "{$section->name} is full ({$section->capacity} students).");
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

        if (blank($data['password'] ?? null)) {
            unset($data['password']); // keep the current password on edit
        }

        return $data;
    }
}
