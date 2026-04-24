<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ImportArticlesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->is_active
            && ($user->can('company.articles.create') || $user->can('company.articles.update'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'csv_file' => [
                'required',
                'file',
                'max:4096',
                'mimes:csv,txt',
                'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel',
            ],
        ];
    }
}
