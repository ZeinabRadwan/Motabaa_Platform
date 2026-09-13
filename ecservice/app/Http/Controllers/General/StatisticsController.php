<?php

namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Assessment;
use App\Models\SCase;
use App\Models\User;
use App\Models\Disability;
use App\Models\Service;
use App\Models\EvaluationMethod;
use App\Models\GoalEvaluation;
use App\Models\Goal;
use App\Models\AssessmentEvaluation;
use App\Models\Attendance;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeLeave;
use App\Models\Log;
use App\Models\LogType;
use App\Models\Message;
use App\Models\Term;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;
use App\Support\ReferenceCache;
use DB;

class StatisticsController extends Controller
{
    public function admin_dashboard(Request $request) {

        $user = auth()->user();
        $center = null;
        if($request->center_id && $user->isInCenter($request->center_id)) {
            $center = $request->center_id;
        }
        else if($user->centers){ 
            $center = $user->centers[0]->id;
        }

        $logType = $request->log_type ?: 'all';
        $result = ReferenceCache::remember('statistics', 'admin:'.$center.':'.$logType, function () use ($center, $logType) {
        $result = (object)[];
        
        $result->cases_count = SCase::where('center_id', $center)->count();
        
        $result->teachers_count = User::whereHas('roles', function ($query) {
            $query->where('roles.default_name', 'like','%teacher%');
        })
        ->whereHas('centers', function ($query) use($center) {
            $query->where('centers.id', $center);
        })
        ->count();
        
        $result->specialists_count = User::whereHas('roles', function ($query) {
            $query->where('roles.default_name', 'like','%specialist%');
        })
        ->whereHas('centers', function ($query) use($center) {
            $query->where('centers.id', $center);
        })
        ->count();
        
        $result->recent_assessments = AssessmentEvaluation::query()
        ->whereHas('assesment', function ($subQuery) use($center) {
            $subQuery->where(function ($q) use ($center) {
                $q->whereNull('assessments.center_id')
                    ->orWhere('assessments.center_id', $center);
            });
        })
        ->whereHas('case', function ($query) use ($center) {
            $query->where('scases.center_id', $center);
        })
        ->whereRaw('updated_at > DATE_ADD(NOW(), INTERVAL -1 MONTH)')
        ->count();
        
        $result->recent_goals_count = Goal::whereHas('case', function ($query) use ($center) {
            $query->where('scases.center_id', $center);
        })
        ->whereHas('goal_evaluations', function ($query) {
            $query->whereRaw('goal_evaluations.updated_at > DATE_ADD(NOW(), INTERVAL -1 MONTH)');
        })
        ->count();

        $result->attendance_daily = Attendance::selectRaw('YEAR(attendance_at) as year, MONTH(attendance_at) as month, DAY(attendance_at) as day, count(*) as count')
        ->whereHas('case', function ($query) use ($center) {
            $query->where('scases.center_id', $center);
        })
        ->whereRaw('attendance_at > DATE_ADD(NOW(), INTERVAL -1 MONTH)')
        ->groupByRaw('YEAR(attendance_at), MONTH(attendance_at), DAY(attendance_at)')
        ->where('status', 1)
        ->get()
        ->toArray();

        $result->attendance_monthly = Attendance::selectRaw('YEAR(attendance_at) as year, MONTH(attendance_at) as month, count(*) as count')
        ->whereHas('case', function ($query) use ($center) {
            $query->where('scases.center_id', $center);
        })
        ->whereRaw('attendance_at > DATE_ADD(NOW(), INTERVAL -12 MONTH)')
        ->groupByRaw('YEAR(attendance_at), MONTH(attendance_at)')
        ->where('status', 1)
        ->get()
        ->toArray();

        $result->recent_goals = Goal::whereHas('case', function ($query) use ($center) {
            $query->where('scases.center_id', $center);
        })
        ->whereHas('goal_evaluations', function ($query) {
            $query->whereRaw('goal_evaluations.updated_at > DATE_ADD(NOW(), INTERVAL -1 MONTH)');
        })
        ->selectRaw('category, count(*) as count')
        ->whereNotNull('category')
        ->groupBy('category')
        ->get()
        ->toArray();

        $result->disabilities = DB::table('scase_disability')
        ->selectRaw('scase_disability.disability_id as id, disabilities.name_ar as name, count(*) as count')
        ->join('scases', 'scases.id', 'scase_disability.scase_id')
        ->join('disabilities', 'disabilities.id', 'scase_disability.disability_id')
        ->where('scases.center_id', $center)
        ->where(function ($q) use ($center) {
            $q->whereNull('disabilities.center_id')
                ->orWhere('disabilities.center_id', $center);
        })
        ->groupBy('scase_disability.disability_id')->groupBy('disabilities.name_ar')
        ->get()
        ->toArray();

        $result->recent_logs = Log::selectRaw('logs.created_by as id, users.name as name, count(*) as count')
        ->join('users', 'users.id', 'logs.created_by')
        ->where('logs.center_id', $center)
        ->when(
            $logType && $logType != 'all',
            fn ($q) => $q->join('logs_types', function($join) use($logType) {
                $join->on('logs_types.type', 'logs.type');
                $join->Where('category', $logType);
            })
        )
        ->whereRaw('logs.created_at > DATE_ADD(NOW(), INTERVAL -1 MONTH)')
        ->groupBy('logs.created_by')->groupBy('users.name')
        ->orderBy('count', 'DESC')
        ->limit(25)
        ->get()
        ->toArray();

        $result->goals_daily = Message::whereHas('goal.case', function ($query) use ($center) {
            $query->where('scases.center_id', $center);
        })
        ->whereRaw('created_at > DATE_ADD(NOW(), INTERVAL -14 DAY)')
        ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, DAY(created_at) as day, count(*) as count')
        ->whereNotNull('goal_id')
        ->groupByRaw('YEAR(created_at), MONTH(created_at), DAY(created_at)')
        ->get()
        ->toArray();

        $result->goals_monthly = Message::whereHas('goal.case', function ($query) use ($center) {
            $query->where('scases.center_id', $center);
        })
        ->whereRaw('created_at > DATE_ADD(NOW(), INTERVAL -12 MONTH)')
        ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, count(*) as count')
        ->whereNotNull('goal_id')
        ->groupByRaw('YEAR(created_at), MONTH(created_at)')
        ->get()
        ->toArray();

        return $result;
        }, 60);

        return apiResponse($result);
    }

    public function hr_dashboard(Request $request)
    {
        $user = auth()->user();
        $center = null;
        if ($request->center_id && $user->isInCenter($request->center_id)) {
            $center = $request->center_id;
        } else if ($user->centers) {
            $center = $user->centers[0]->id;
        }

        $today = Carbon::now()->toDateString();
        [$alertFrom, $alertTo] = User::hrAlertDateRange();

        $staff = User::staffInCenter($center);
        $activeStaff = (clone $staff);
        $allStaff = (clone $staff)->withTrashed();

        $result = (object)[];
        $result->total_employees = (clone $allStaff)->count();
        $result->active_employees = (clone $activeStaff)->count();
        $result->expiring_contracts = (clone $activeStaff)
            ->whereNotNull('contract_end_date')
            ->whereBetween('contract_end_date', [$alertFrom, $alertTo])
            ->count();
        $result->expiring_ids = (clone $activeStaff)
            ->whereNotNull('id_expiry_date')
            ->whereBetween('id_expiry_date', [$alertFrom, $alertTo])
            ->count();
        $result->expiring_documents = (clone $activeStaff)
            ->whereHas('fileMeta', function ($meta) use ($alertFrom, $alertTo) {
                $meta->whereNotNull('expiry_date')
                    ->whereBetween('expiry_date', [$alertFrom, $alertTo]);
            })
            ->count();
        $result->expiring_ids_or_docs = (clone $activeStaff)
            ->where(function ($query) use ($alertFrom, $alertTo) {
                $query->where(function ($idQ) use ($alertFrom, $alertTo) {
                    $idQ->whereNotNull('id_expiry_date')
                        ->whereBetween('id_expiry_date', [$alertFrom, $alertTo]);
                })->orWhereHas('fileMeta', function ($meta) use ($alertFrom, $alertTo) {
                    $meta->whereNotNull('expiry_date')
                        ->whereBetween('expiry_date', [$alertFrom, $alertTo]);
                });
            })
            ->count();

        $leaveBase = EmployeeLeave::whereHas('user.centers', function ($query) use ($center) {
            $query->where('centers.id', $center);
        });
        $result->pending_leaves = (clone $leaveBase)->where('status', 'pending')->count();
        $result->on_leave = (clone $leaveBase)
            ->where('status', 'approved')
            ->whereDate('date_from', '<=', $today)
            ->whereDate('date_to', '>=', $today)
            ->pluck('user_id')
            ->unique()
            ->count();

        $staffIds = (clone $activeStaff)->pluck('id');
        $result->attendance_today = [
            'date' => $today,
            'total' => $staffIds->count(),
            'present' => EmployeeAttendance::whereIn('user_id', $staffIds)
                ->whereDate('attendance_at', $today)
                ->where('status', 1)
                ->count(),
            'absent' => EmployeeAttendance::whereIn('user_id', $staffIds)
                ->whereDate('attendance_at', $today)
                ->where('status', 0)
                ->count(),
        ];
        $result->attendance_today['unmarked'] = max(
            0,
            $result->attendance_today['total']
            - $result->attendance_today['present']
            - $result->attendance_today['absent']
        );

        return apiResponse($result);
    }

    public function case(Request $request, SCase $case) {

        app()->setLocale('ar');
        $result = (object)[];
        $user = auth()->user();
        $visibleTerm = Term::visibleTermId($user, $case->center_id, $request->term_id ?? null);
        if (!$visibleTerm['ok']) {
            return Term::forbiddenResponse();
        }

        $goals = Goal::where('goals.case_id', $case->id)
        ->selectRaw('goals.category, count(*) as count')
        ->whereNotNull('goals.category');
        if ($visibleTerm['term_id'] === 0) {
            $goals->whereRaw('0 = 1');
        } elseif ($visibleTerm['term_id']) {
            $goals->where('goals.term_id', $visibleTerm['term_id']);
        }
        $goals = $goals
        ->groupBy('goals.category')
        ->get()
        ->toArray();

        $result->goals = [];

        $abilitiesByCategory = [];
        $categories = array_values(array_unique(array_filter(array_column($goals, 'category'))));
        if ($categories) {
            $abilityRows = Goal::where('goals.case_id', $case->id)
            ->selectRaw('goals.category, evaluation_methods_values.ability, count(*) as count')
            ->join('evaluation_methods_values', 'evaluation_methods_values.id', 'goals.value_id')
            ->whereIn('goals.category', $categories)
            ->groupBy('goals.category', 'evaluation_methods_values.ability')
            ->orderBy('evaluation_methods_values.ability', 'Desc')
            ->get();

            foreach ($abilityRows as $row) {
                $abilitiesByCategory[$row->category][$row->ability] = $row->count;
            }
            foreach ($abilitiesByCategory as &$abilities) {
                krsort($abilities);
            }
            unset($abilities);
        }

        foreach ($goals as $goal) {
            
            $goal = (object)$goal;

            $goal->ability = $abilitiesByCategory[$goal->category] ?? [];

            $withoutAbility = $goal->count;
            $goal->values = [];
            if(isset($goal->ability['power'])) {
                $goal->values[] = (int)$goal->ability['power'];
                $withoutAbility -= (int)$goal->ability['power'];
            }
            else {
                $goal->values[] = 0;
            }

            if(isset($goal->ability['weak'])) {
                $goal->values[] = (int)$goal->ability['weak'];
                $withoutAbility -= (int)$goal->ability['weak'];
            }
            else {
                $goal->values[] = 0;
            }
            
            $goal->values[] = $withoutAbility;

            $result->goals[] = $goal;
        }

        $result->recent_goals = Goal::whereHas('messages', function ($query) {
            $query->whereRaw('messages.created_at > DATE_ADD(NOW(), INTERVAL -1 MONTH)');
        })
        ->where('case_id', $case->id)
        ->selectRaw('category, count(*) as count')
        ->whereNotNull('category')
        ->groupBy('category')
        ->get()
        ->toArray();

        $result->goals_daily = Message::whereHas('goal', function ($query) use($case) {
            $query->where('goals.case_id', $case->id);
        })
        ->whereRaw('created_at > DATE_ADD(NOW(), INTERVAL -14 DAY)')
        ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, DAY(created_at) as day, count(*) as count')
        ->whereNotNull('goal_id')
        ->groupByRaw('YEAR(created_at), MONTH(created_at), DAY(created_at)')
        ->get()
        ->toArray();

        $result->goals_monthly = Message::whereHas('goal', function ($query) use($case) {
            $query->where('goals.case_id', $case->id);
        })
        ->whereRaw('created_at > DATE_ADD(NOW(), INTERVAL -12 MONTH)')
        ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, count(*) as count')
        ->whereNotNull('goal_id')
        ->groupByRaw('YEAR(created_at), MONTH(created_at)')
        ->get()
        ->toArray();

        $termsGoals = Goal::where('goals.case_id', $case->id)
        ->selectRaw('terms.id, terms.title, count(*) as count')
        ->join('terms', 'terms.id', 'goals.term_id')
        ->whereNull('terms.deleted_at');
        if ($visibleTerm['term_id'] === 0) {
            $termsGoals->whereRaw('0 = 1');
        } elseif ($visibleTerm['term_id']) {
            $termsGoals->where('terms.id', $visibleTerm['term_id']);
        }
        $termsGoals = $termsGoals
        ->groupBy('terms.id', 'terms.title')
        ->get()
        ->toArray();
        
        $termGoalsCount = 0;
        $result->terms_goals = [];
        foreach ($termsGoals as $termGoals) {
            
            $termGoals['count'] += $termGoalsCount;
            $result->terms_goals[] = $termGoals;
            $termGoalsCount = $termGoals['count'];
        }

        $termSql = '';
        if ($visibleTerm['term_id'] === 0) {
            $termSql = ' AND 0 = 1';
        } elseif ($visibleTerm['term_id']) {
            $termSql = ' AND goals.term_id = '.(int) $visibleTerm['term_id'];
        }

        $result->goals_evaluations = [];
        $goalsEvaluations = GoalEvaluation::selectRaw('temp.starts_at, temp.period, count(*) as count')
        ->fromRaw("(
            SELECT goal_evaluations.goal_id, min(terms.starts_at) AS starts_at, min(goal_evaluations.period) AS PERIOD 
            FROM goal_evaluations 
            INNER JOIN goals ON goals.id = goal_evaluations.goal_id 
            INNER JOIN terms ON terms.id = goals.term_id AND terms.deleted_at is null 
            INNER JOIN evaluation_methods_values ON evaluation_methods_values.id = goal_evaluations.value_id 
            WHERE goals.case_id = {$case->id} 
            AND evaluation_methods_values.ability = 'power' 
            {$termSql}
            GROUP BY goal_evaluations.goal_id 
        ) AS temp")
        ->orderBy('temp.starts_at', 'ASC')
        ->groupByRaw('temp.starts_at, temp.period')
        ->get()
        ->toArray();

        $termID = 0;
        $periodsCount = 0;
        $termsByStart = [];
        $startsAt = array_values(array_unique(array_filter(array_column($goalsEvaluations, 'starts_at'))));
        if ($startsAt) {
            foreach (Term::withPeriods()->whereIn('starts_at', $startsAt)->orderBy('id')->get() as $term) {
                $startKey = (string) $term->starts_at;
                if (!isset($termsByStart[$startKey])) {
                    $termsByStart[$startKey] = $term;
                }
            }
        }
        foreach ($goalsEvaluations as $evaluations) {
            
            $data = [];
            $term = $termsByStart[(string) $evaluations['starts_at']] ?? null;
            if(!$term)
                continue;

            $periodDate = $term->periodDate((int) $evaluations['period']);
            $periodName = $term->periodTitle($evaluations['period']);

            $dateNow = Carbon::now();
            $date = Carbon::parse($periodDate);
            if($date->gt($dateNow))
                continue;
            
            $periodsCount += $evaluations['count'];

            $data['title'] = $periodDate.' '.$periodName;
            $data['starts_at'] = $term->starts_at;
            $data['term'] = $term->title;
            $data['term_id'] = $term->id;
            $data['period'] = $periodName;
            $data['count'] = $periodsCount;

            $result->goals_evaluations[] = $data;
        }

        return apiResponse($result);
    }
}
