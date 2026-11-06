<?php

namespace App\Http\Requests\Hr;

use App\Models\Hr\EmployeeDocument;
use Illuminate\Validation\Rule;

class EmployeeDocumentRequest extends HrFormRequest
{
    public function rules(): array
    {
        $creating = $this->isCreate('document');
        $required = $creating ? 'required' : 'sometimes';

        return [
            'file' => $creating
                ? ['required', 'file', 'max:'.EmployeeDocument::MAX_UPLOAD_KB, 'mimes:'.implode(',', EmployeeDocument::ALLOWED_EXTENSIONS)]
                : ['prohibited'],
            'category' => [$required, Rule::in(EmployeeDocument::categories())],
            'document_type' => [$required, Rule::in(EmployeeDocument::allTypes())],
            'title' => [$required, 'string', 'max:191'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => array_merge(['nullable', 'date'], $this->filled('issue_date') ? ['after:issue_date'] : []),
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
