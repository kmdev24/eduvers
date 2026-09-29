<?php

namespace App\Http\Requests\Developer;

use App\Models\GradeLevel;
use App\Models\TrackStrand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code'            => strtoupper(trim((string) $this->input('code'))),
            'track_strand_id' => $this->input('track_strand_id') ?: null, // "" = core subject
        ]);
    }

    public function rules(): array
    {
        return [
            'code'             => ['required', 'string', 'max:32', Rule::unique('subjects', 'code')->ignore($this->route('subject'))],
            'name'             => ['required', 'string', 'max:255'],
            'grade_level_id'   => ['required', 'integer', Rule::exists('grade_levels', 'id')],
            'track_strand_id'  => ['nullable', 'integer', Rule::exists('track_strands', 'id')],
            'academic_term_id' => ['required', 'integer', Rule::exists('academic_terms', 'id')],
        ];
    }

    public function attributes(): array
    {
        return [
            'grade_level_id'   => 'grade level',
            'track_strand_id'  => 'track / strand',
            'academic_term_id' => 'term',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty() || ! $this->filled('track_strand_id')) {
                return;
            }

            $level  = GradeLevel::find($this->integer('grade_level_id'));
            $strand = TrackStrand::find($this->integer('track_strand_id'));

            if (! $strand->isCompatibleWith($level)) {
                $v->errors()->add('track_strand_id', "{$strand->name} can't be used with {$level->name}.");
            }
        });
    }
}
