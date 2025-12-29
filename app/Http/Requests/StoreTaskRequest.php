<?php

namespace App\Http\Requests;

use App\Enums\TaskStatus;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'status' => ['nullable', Rule::in(array_map(fn($c) => $c->value, TaskStatus::cases()))],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Введите название задачи.',
            'title.string' => 'Название задачи должно быть строкой.',
            'title.max' => 'Название задачи не должно быть длиннее :max символов.',

            'description.string' => 'Описание должно быть строкой.',
            'description.max' => 'Описание не должно быть длиннее :max символов.',

            'status.in' => 'Некорректный статус задачи.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Validation error',
            'errors' => $validator->errors(),
        ], 422));
    }
}
