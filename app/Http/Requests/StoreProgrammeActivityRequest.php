<?php

namespace App\Http\Requests;

use App\Models\ActivityItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProgrammeActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'activities' => ['nullable', 'array'],
            'activities.*.activity_item_id' => [
                'required',
                'integer',
                Rule::exists('taxonomy_items', 'id')->where('is_active', true),
            ],
            'activities.*.is_primary' => ['sometimes', 'boolean'],
            'activities.*.inclusion_group' => ['nullable', 'string', 'max:255'],
            'activities.*.inclusion_type' => ['nullable', 'string', 'max:255'],
            'activities.*.other_text' => ['nullable', 'string', 'max:1000'],
            'activities.*.education_level_ids' => ['sometimes', 'array'],
            'activities.*.education_level_ids.*' => [
                'integer',
                Rule::exists('education_levels', 'id'),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $activities = $this->input('activities', []);
            $itemIds = collect($activities)->pluck('activity_item_id')->filter()->unique();
            $activityItems = ActivityItem::whereIn('id', $itemIds)->get()->keyBy('id');

            foreach ($activities as $index => $activity) {
                $activityItem = $activityItems->get($activity['activity_item_id'] ?? null);

                if (! $activityItem?->is_other) {
                    continue;
                }

                if (trim((string) ($activity['other_text'] ?? '')) === '') {
                    $validator->errors()->add(
                        "activities.{$index}.other_text",
                        'The other text field is required when selecting Other.'
                    );
                }
            }
        });
    }
}
