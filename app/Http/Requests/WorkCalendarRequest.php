<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class WorkCalendarRequest extends FormRequest
{
    protected function failedValidation(Validator $validator): void
    {
        if ($this->routeIs('wc.index') && !$this->expectsJson()) {
            parent::failedValidation($validator);
        }

        throw new HttpResponseException(response()->json(['code' => 'validation_failed', 'message' => 'Periksa isian kalender.', 'errors' => (object) $validator->errors()->messages()], 422));
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $date = ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:2199-12-31'];
        $revision = ['required', 'integer', 'min:1'];
        $name = $this->route()->getName();
        if ($name === 'wc.years.store') {
            return ['year' => 'required|integer|between:1900,2199', 'copy_from_year' => 'nullable|integer|between:1900,2199'];
        }
        if ($name === 'wc.years.update') {
            return ['revision' => $revision, 'working_weekdays' => 'required|array|list|min:1|max:7', 'working_weekdays.*' => 'required|integer|between:1,7|distinct'];
        }
        if ($name === 'wc.years.confirm') {
            return ['revision' => $revision];
        }
        if ($name === 'wc.events.destroy') {
            return ['revision' => $revision, 'calendar_revisions' => 'required|array', 'calendar_revisions.*' => 'required|integer|min:1'];
        }
        if (in_array($name, ['wc.events.store', 'wc.events.update'], true)) {
            return ['revision' => $name === 'wc.events.update' ? $revision : ['prohibited'], 'calendar_revisions' => 'required|array', 'calendar_revisions.*' => 'required|integer|min:1', 'title' => 'required|string|max:150', 'kind' => 'required|in:activity,holiday,working_day', 'start_date' => $date, 'end_date' => [...$date, 'after_or_equal:start_date'], 'all_day' => 'required|boolean', 'start_time' => 'nullable|date_format:H:i', 'end_time' => 'nullable|date_format:H:i', 'location' => 'nullable|string|max:255', 'description' => 'nullable|string|max:5000'];
        }
        if ($name === 'wc.count') {
            return ['start_date' => $date, 'end_date' => [...$date, 'after_or_equal:start_date'], 'basis' => 'required|in:calendar_days,working_days'];
        }
        if ($name === 'wc.holidays.preview') {
            return ['revision' => $revision, 'include_collective_leave' => 'required|boolean'];
        }
        if ($name === 'wc.holidays.apply') {
            return ['preview_id' => 'required|uuid', 'preview_token' => 'required|string', 'selected_ids' => 'required|array|list|min:1', 'selected_ids.*' => 'required|uuid|distinct'];
        }

        return ['year' => 'sometimes|integer|between:1900,2199', 'date' => ['sometimes', ...$date], 'view' => 'sometimes|in:month,week,day,agenda', 'search' => 'nullable|string|max:100', 'kind' => 'nullable|in:activity,holiday,working_day'];
    }
}
