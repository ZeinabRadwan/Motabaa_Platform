<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\Branch;
use App\Models\Hr\Employee;
use App\Models\Hr\Shift;
use App\Models\Hr\WorkLocation;
use App\Support\Hr\HrBranchScope;
use App\Support\Hr\HrContext;
use App\Support\Hr\HrException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base for HR endpoints: resolves the center, maps HrException to the platform's 422 error shape and
 * guarantees records are only ever loaded inside the caller's center.
 */
abstract class HrController extends Controller
{
    /**
     * @param  callable(int $centerId): Response  $action
     */
    protected function inCenter(Request $request, callable $action): Response
    {
        try {
            return $action(HrContext::requireCenterId($request));
        } catch (HrException $e) {
            return $e->toResponse();
        } catch (ModelNotFoundException) {
            return response()->json(['errors' => ['error' => [__('validation.hr.not_found')]]], 404);
        }
    }

    /**
     * @template T of Model
     *
     * @param  class-string<T>|Builder  $model
     * @return T
     */
    protected function findInCenter(string|Builder $model, int $id, int $centerId, bool $withTrashed = false): Model
    {
        $query = $model instanceof Builder ? $model : $model::query();
        if ($withTrashed) {
            $query->withTrashed();
        }
        $query->forCenter($centerId)->whereKey($id);

        // Records outside the user's branches are reported as not found.
        $instance = $query->getModel();
        $column = match ($instance::class) {
            Branch::class => 'id',
            WorkLocation::class, Employee::class, Shift::class => 'branch_id',
            default => null,
        };
        if ($column !== null) {
            HrBranchScope::restrict($query, $this->branchScope(request(), $centerId), $instance->qualifyColumn($column), $instance instanceof Shift);
        }

        return $query->firstOrFail();
    }

    /**
     * Branches the signed-in user may see (null = all), resolved once per request.
     *
     * @return int[]|null
     */
    protected function branchScope(Request $request, int $centerId): ?array
    {
        $key = "hr.branch_scope.{$centerId}";
        if (! $request->attributes->has($key)) {
            $request->attributes->set($key, HrBranchScope::branchIds($request->user(), $centerId));
        }

        return $request->attributes->get($key);
    }

    /**
     * Paginates with the VDataTableServer `options` the frontend sends (page, itemsPerPage, sortBy),
     * sorting only by whitelisted columns.
     *
     * @param  array<string, string>  $sortable  request key => column
     */
    protected function paginated(Request $request, Builder $query, string $resourceClass, array $sortable = []): JsonResponse
    {
        $options = $request->get('options');
        $options = is_array($options) ? $options : [];

        foreach ((array) ($options['sortBy'] ?? []) as $sort) {
            $key = is_array($sort) ? ($sort['key'] ?? null) : null;
            if ($key !== null && isset($sortable[$key])) {
                $query->orderBy($sortable[$key], ($sort['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc');
            }
        }

        $page = max(1, (int) ($options['page'] ?? $request->get('page', 1)));
        $paginator = $query->paginate(resolvePerPage($request), ['*'], 'page', $page);

        return apiPaginateResponse($paginator, $resourceClass::collection($paginator));
    }

    /**
     * Employee options for pickers, within the user's branches: `q` (name or number), `ids` (selected values,
     * terminated employees included), `branch_id`, `limit`.
     */
    protected function employeeOptions(Request $request, int $centerId): JsonResponse
    {
        $query = Employee::query()->forCenter($centerId)->with(['user:id,name', 'branch:id,name_ar,name_en,deleted_at']);
        HrBranchScope::restrict($query, $this->branchScope($request, $centerId), 'hr_employees.branch_id');
        $ids = array_filter(array_map('intval', (array) $request->get('ids', [])));
        if ($ids !== []) {
            $query->whereIn('hr_employees.id', $ids);
        } else {
            $query->employed();
        }
        if ((int) $request->get('branch_id') > 0) {
            $query->where('hr_employees.branch_id', (int) $request->get('branch_id'));
        }
        $term = trim((string) $request->get('q'));
        if ($term !== '') {
            $query->where(fn (Builder $inner) => $inner
                ->where('hr_employees.employee_number', 'like', "%{$term}%")
                ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'like', "%{$term}%")));
        }

        $limit = $ids !== [] ? count($ids) : min(100, max(1, (int) $request->get('limit', 50)));

        return success($query->orderBy('employee_number')->limit($limit)->get()->map(fn (Employee $employee) => [
            'id' => $employee->id,
            'value' => $employee->id,
            'title' => trim(($employee->user?->name ?? '').' ('.$employee->employee_number.')'),
            'name' => $employee->user?->name,
            'employee_number' => $employee->employee_number,
            'branch_id' => $employee->branch_id,
            'branch' => $employee->branch?->name,
        ])->values());
    }

    protected function denied(): JsonResponse
    {
        return response()->json(['errors' => ['error' => [__("validation.You don't have permission to access this page.")]]], 403);
    }
}
