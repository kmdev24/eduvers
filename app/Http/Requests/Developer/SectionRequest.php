<?php

namespace App\Http\Requests\Developer;

use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\TrackStrand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Section|null $section */
        $section = $this->route('section');

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('sections', 'name')
                    ->where('grade_level_id', $this->input('grade_level_id'))
                    ->where('track_strand_id', $this->input('track_strand_id'))
                    ->ignore($section),
            ],
            'grade_level_id'  => ['required', 'integer', Rule::exists('grade_levels', 'id')],
            'track_strand_id' => ['required', 'integer', Rule::exists('track_strands', 'id')],
            'capacity'        => ['required', 'integer', 'min:1', 'max:200'],
        ];
    }

    public function attributes(): array
    {
        return [
            'grade_level_id'  => 'grade level',
            'track_strand_id' => 'track / strand',
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A section with this name already exists for that grade level and strand.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $level  = GradeLevel::find($this->integer('grade_level_id'));
            $strand = TrackStrand::find($this->integer('track_strand_id'));

            if (! $strand->isCompatibleWith($level)) {
                $v->errors()->add('track_strand_id', $level->level_type->value === 'college'
                    ? 'College sections need a College program such as HRS.'
                    : 'SHS sections need an Academic or TechPro strand.');
            }

            /** @var Section|null $section */
            $section = $this->route('section');
            if ($section) {
                $enrolled = $section->students()->count();
                if ($this->integer('capacity') < $enrolled) {
                    $v->errors()->add('capacity', "Capacity can't be lower than the {$enrolled} students already enrolled.");
                }
            }
        });
    }
}
