<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:100'],
            'age'             => ['required', 'integer', 'min:13', 'max:120'],
            'gender'          => ['required', 'string', 'in:male,female,non-binary,other,prefer_not'],
            'country'         => ['required', 'string', 'size:2'],
            'email'           => ['required', 'email', 'max:150', 'unique:users,email'],
            'username'        => ['required', 'string', 'max:50', 'unique:users,username',
                                  'regex:/^[a-zA-Z0-9_.]+$/'],
            'password'        => ['required', 'string', 'min:8'],
            'profile_picture' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex'           => 'Username may only contain letters, numbers, underscores, and dots.',
            'profile_picture.required' => 'A profile picture is required.',
            'profile_picture.image'    => 'The profile picture must be an image.',
            'profile_picture.max'      => 'Profile picture must be under 5 MB.',
            'email.unique'             => 'This email is already registered.',
            'username.unique'          => 'This username is already taken.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}
