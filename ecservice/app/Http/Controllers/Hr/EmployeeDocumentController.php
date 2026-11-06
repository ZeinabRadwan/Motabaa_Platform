<?php

namespace App\Http\Controllers\Hr;

use App\Http\Requests\Hr\EmployeeDocumentRequest;
use App\Http\Resources\Hr\EmployeeDocumentResource;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeDocument;
use App\Support\Hr\HrEmployeeDocuments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EmployeeDocumentController extends HrController
{
    public function index(Request $request, int $employee): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $employee) {
            $model = $this->findInCenter(Employee::class, $employee, $centerId);
            $q = trim((string) $request->get('q', ''));

            $query = EmployeeDocument::query()->forCenter($centerId)
                ->where('employee_id', $model->id)
                ->with('uploader:id,name')
                ->when($request->filled('category'), fn ($query) => $query->where('category', $request->get('category')))
                ->when($q !== '', fn ($query) => $query->where(function ($inner) use ($q) {
                    $inner->where('title', 'like', "%{$q}%")
                        ->orWhere('reference_number', 'like', "%{$q}%")
                        ->orWhere('original_name', 'like', "%{$q}%");
                }))
                ->when(empty($request->input('options.sortBy')), fn ($query) => $query->orderByDesc('id'));

            return $this->paginated($request, $query, EmployeeDocumentResource::class, [
                'title' => 'title', 'expiry_date' => 'expiry_date', 'created_at' => 'created_at',
            ]);
        });
    }

    public function store(EmployeeDocumentRequest $request, int $employee): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $employee) {
            $document = HrEmployeeDocuments::store(
                $this->findInCenter(Employee::class, $employee, $centerId),
                $request->safe()->except('file'),
                $request->file('file'),
                $request->user()
            );

            return success(new EmployeeDocumentResource($document->load('uploader:id,name')), null, __('validation.hr.saved'));
        });
    }

    public function update(EmployeeDocumentRequest $request, int $employee, int $document): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $employee, $document) {
            $model = $this->findDocument($centerId, $employee, $document);

            return success(new EmployeeDocumentResource(HrEmployeeDocuments::update($model, $request->validated())->load('uploader:id,name')), null, __('validation.hr.saved'));
        });
    }

    public function download(Request $request, int $employee, int $document): Response
    {
        return $this->inCenter($request, function (int $centerId) use ($employee, $document) {
            return HrEmployeeDocuments::download($this->findDocument($centerId, $employee, $document));
        });
    }

    public function destroy(Request $request, int $employee, int $document): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($employee, $document) {
            $this->findDocument($centerId, $employee, $document)->delete();

            return success(null, null, __('validation.hr.deleted'));
        });
    }

    private function findDocument(int $centerId, int $employee, int $document): EmployeeDocument
    {
        $model = $this->findInCenter(Employee::class, $employee, $centerId);

        return $model->documents()->whereKey($document)->firstOrFail();
    }
}
